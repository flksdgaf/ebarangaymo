<?php
require 'functions/dbconn.php';
$userId  = (int) $_SESSION['loggedInUserID'];
// $newCase = $_GET['transaction_id'] ?? '';

// ——— Filters & pagination setup ——————————————————————————————————————
$status  = $_GET['status'] ?? 'all';
$search  = trim($_GET['search'] ?? '');
$page    = max((int)($_GET['page_num'] ?? 1), 1);
$limit   = 10;
$offset  = ($page - 1) * $limit;

// ——— Build WHERE clauses —————————————————————————————————————————————
$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

// status filter
if ($status !== 'all') {
    $whereClauses[]  = 'kp.status = ?';
    $bindTypes      .= 's';
    $bindParams[]    = $status;
}

// global search
if ($search !== '') {
    $whereClauses[]  = '(kp.case_no LIKE ? OR s.transaction_id LIKE ? OR b.transaction_id LIKE ? OR kp.subject LIKE ? OR kp.status LIKE ?)';
    $bindTypes      .= 'sssss';
    $term            = "%{$search}%";
    $bindParams     = array_merge($bindParams, array_fill(0, 5, $term));
}

$whereSQL = $whereClauses
          ? 'WHERE ' . implode(' AND ', $whereClauses)
          : '';

// ——— Count total rows ————————————————————————————————————————————————
$countSQL = "
    SELECT COUNT(*) AS total
      FROM katarungang_pambarangay AS kp
      JOIN summon_records        AS s ON kp.smn_id = s.id
      JOIN blotter_records       AS b ON kp.blt_id = b.id
    {$whereSQL}
";
$countStmt = $conn->prepare($countSQL);
if ($whereClauses) {
    $refs = [];
    foreach ($bindParams as $i => $v) {
        $refs[$i] = & $bindParams[$i];
    }
    array_unshift($refs, $bindTypes);
    call_user_func_array([$countStmt, 'bind_param'], $refs);
}
$countStmt->execute();
$totalRows   = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages  = (int) ceil($totalRows / $limit);
$countStmt->close();

// ——— Fetch page of cases —————————————————————————————————————————————
$dataSQL = "
    SELECT
      kp.case_no,
      s.transaction_id AS smn_id,
      b.transaction_id AS blt_id,
      kp.subject,
      kp.status
    FROM katarungang_pambarangay AS kp
    JOIN summon_records        AS s ON kp.smn_id = s.id
    JOIN blotter_records       AS b ON kp.blt_id = b.id
    {$whereSQL}
    ORDER BY kp.id ASC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($dataSQL);

// bind filters + pagination
$types       = $bindTypes . 'ii';
$bindParams[] = $limit;
$bindParams[] = $offset;
$refs = [];
foreach ($bindParams as $i => $v) {
    $refs[$i] = & $bindParams[$i];
}
array_unshift($refs, $types);
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$cases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container py-3">
  <!-- <?php if ($newCase): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      New case <strong><?= htmlspecialchars($newCase) ?></strong> added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?> -->

  <div class="card shadow-sm p-3">
    <!-- Header: New Case button + Filters + Search -->
    <div class="d-flex align-items-center mb-3">
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          Filter
        </button>
        <div class="dropdown-menu p-3" aria-labelledby="filterDropdown" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
          <form method="get" class="mb-0" id="filterForm">
            <!-- reset to first page on filter change -->
            <input type="hidden" name="page"     value="superAdminKatarungangPambarangay">
            <input type="hidden" name="page_num" value="1">
            <!-- preserve existing search term -->
            <?php if ($search !== ''): ?>
              <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>

            <!-- Status Filter -->
            <div class="mb-2">
              <label class="form-label mb-1">Status</label>
              <select name="status" class="form-select form-select-sm" style="font-size:.75rem;">
                <option value="all" <?= $status==='all' ? 'selected':'' ?>>All</option>
                <option value="Punong Barangay"       <?= $status==='Punong Barangay'       ? 'selected':'' ?>>Punong Barangay</option>
                <option value="Unang Patawag"         <?= $status==='Unang Patawag'         ? 'selected':'' ?>>Unang Patawag</option>
                <option value="Pangalawang Patawag"   <?= $status==='Pangalawang Patawag'   ? 'selected':'' ?>>Pangalawang Patawag</option>
                <option value="Pangatlong Patawag"    <?= $status==='Pangatlong Patawag'    ? 'selected':'' ?>>Pangatlong Patawag</option>
                <option value="Cleared"               <?= $status==='Cleared'               ? 'selected':'' ?>>Cleared</option>
              </select>
            </div>

            <div class="d-flex">
              <a href="?page=superAdminKatarungangPambarangay&page_num=1" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
              <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
            </div>
          </form>
        </div>
      </div>

      <!-- View Case button -->
      <button type="button" class="btn btn-sm btn-success ms-3" id="viewCaseBtn">
        <i class="bi bi-plus-lg me-1"></i> View Cases
      </button>

      <form method="get" id="searchForm" class="d-flex ms-auto me-2">
        <input type="hidden" name="page"     value="superAdminKatarungangPambarangay">
        <input type="hidden" name="page_num" value="1">
        <?php if ($status !== 'all'): ?>
          <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <?php endif; ?>
        <div class="input-group input-group-sm">
          <input id="searchInput" name="search" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
          <button id="searchBtn" class="btn btn-outline-secondary" type="button">
            <span class="material-symbols-outlined" id="searchIcon">
              <?= $search ? 'close' : 'search' ?>
            </span>
          </button>
        </div>
      </form>
    </div>

    <!-- Table of Cases -->
    <div class="table-responsive admin-table" style="height:500px;overflow-y:auto;">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Case No.</th>
            <th>Summon ID</th>
            <th>Blotter ID</th>
            <th>Subject</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($cases): ?>
            <?php foreach ($cases as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['case_no']) ?></td>
              <td><?= htmlspecialchars($c['smn_id']) ?></td>
              <td><?= htmlspecialchars($c['blt_id']) ?></td>
              <td><?= htmlspecialchars($c['subject']) ?></td>
              <td><?= htmlspecialchars($c['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" class="text-center">No records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination justify-content-center pagination-sm">
        <li class="page-item<?= $page<=1?' disabled':'' ?>">
          <a class="page-link"
             href="?<?= http_build_query([
               'page'=>'superAdminKatarungangPambarangay',
               'status'=>$status,
               'search'=>$search,
               'page_num'=>$page-1
             ]) ?>">Previous</a>
        </li>
        <?php
        $range = 2; $dots = false;
        for ($i = 1; $i <= $totalPages; $i++) {
          if ($i===1 || $i===$totalPages || ($i>=$page-$range && $i<=$page+$range)) {
            $active = $i===$page ? ' active' : '';
            echo "<li class='page-item{$active}'>
                    <a class='page-link' href='?".
                    http_build_query([
                      'page'=>'superAdminKatarungangPambarangay',
                      'status'=>$status,
                      'search'=>$search,
                      'page_num'=>$i
                    ]).
                    "'>{$i}</a>
                  </li>";
            $dots = true;
          } elseif ($dots) {
            echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
            $dots = false;
          }
        }
        ?>
        <li class="page-item<?= $page>=$totalPages?' disabled':'' ?>">
          <a class="page-link"
             href="?<?= http_build_query([
               'page'=>'superAdminKatarungangPambarangay',
               'status'=>$status,
               'search'=>$search,
               'page_num'=>$page+1
             ]) ?>">Next</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>

<!-- View Case Modal -->
<div class="modal fade" id="viewCaseModal" tabindex="-1" aria-labelledby="viewCaseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewCaseModalLabel">Katarungang Pambarangay</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Status Pills -->
        <div class="d-flex mb-3">
          <span class="me-2 align-self-center fw-bold">Status:</span>
          <?php foreach (['Punong Barangay','Unang Patawag','Pangalawang Patawag','Pangatlong Patawag','Cleared'] as $s): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary me-1"><?= $s ?></button>
          <?php endforeach; ?>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="caseTab" role="tablist">
          <?php 
            $tabs = ['Summon Info','Date/Time','Subject','Blotter Info'];
            foreach ($tabs as $i => $t): 
              $active = $i===0 ? 'active' : '';
          ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link <?= $active ?>"
                      id="tab-<?= $i ?>"
                      data-bs-toggle="tab"
                      data-bs-target="#panel-<?= $i ?>"
                      type="button"
                      role="tab"
                      aria-selected="<?= $i===0 ? 'true':'false' ?>">
                <?= $t ?>
              </button>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="tab-content">
          <!-- Summon Info -->
          <div class="tab-pane fade show active" id="panel-0" role="tabpanel">
            <div class="row mb-3">
              <label class="col-sm-3 col-form-label fw-bold">Summon ID:</label>
              <div class="col-sm-9">
                <select id="modalSummonId" class="form-select">
                <?php foreach ($allSummons as $s): ?>
                  <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['transaction_id']) ?></option>
                <?php endforeach; ?>
              </select>
              </div>
            </div>
          </div>

          <!-- Date/Time panel -->
          <div class="tab-pane fade" id="panel-1" role="tabpanel">
            <div class="row mb-3">
              <label class="col-sm-3 col-form-label fw-bold">Date:</label>
              <div class="col-sm-3">
                <input type="date" id="modalDate" class="form-control">
              </div>
              <label class="col-sm-2 col-form-label fw-bold">Time:</label>
              <div class="col-sm-4">
                <input type="time" id="modalTime" class="form-control">
              </div>
            </div>
          </div>

          <!-- Subject panel -->
          <div class="tab-pane fade" id="panel-2" role="tabpanel">
            <div class="mb-3">
              <label class="form-label fw-bold">Subject</label>
              <textarea id="modalSubject" class="form-control" rows="3" readonly></textarea>
            </div>
          </div>

          <!-- Complainants table body -->
          <tbody id="modalComplaintsBody">
            <!-- rows injected by JS -->
          </tbody>

          <!-- Respondents table body -->
          <tbody id="modalRespondentsBody">
            <!-- rows injected by JS -->
          </tbody>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">
          <i class="bi bi-printer me-1"></i> Print
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$conn->close();
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form      = document.getElementById('searchForm');
  const input     = document.getElementById('searchInput');
  const btn       = document.getElementById('searchBtn');
  const icon      = document.getElementById('searchIcon');
  let hasSearch   = <?= $search !== '' ? 'true' : 'false' ?>;

  btn.addEventListener('click', () => {
    // if there’s already a search term, clear it and flip icon
    if (hasSearch) {
      input.value = '';
      icon.textContent = 'search';
      hasSearch = false;
    }
    // submit the form (GET)
    form.submit();
  });

  // Open the modal when the View Cases button is clicked
  document.getElementById('viewCaseBtn').addEventListener('click', () => {
    new bootstrap.Modal(document.getElementById('viewCaseModal')).show();
  });

  // when Summon ID changes, fetch its details
  document.getElementById('modalSummonId').addEventListener('change', async function() {
    const summonId = this.value;
    const form    = new URLSearchParams({ summon_id: summonId });

    try {
      const resp = await fetch('functions/get_case_details.php', {
        method: 'POST',
        headers: { 'Content-Type':'application/x-www-form-urlencoded' },
        body: form
      });
      const data = await resp.json();
      if (data.error) throw new Error(data.error);

      // populate date/time
      document.getElementById('modalDate').value    = data.date_summon || '';
      document.getElementById('modalTime').value    = data.time_summon || '';
      // subject
      document.getElementById('modalSubject').textContent = data.subject || '';

      // rebuild complainants table
      const cBody = document.getElementById('modalComplaintsBody');
      cBody.innerHTML = '';
      (JSON.parse(data.complainants) || []).forEach(c => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${c.name}</td><td>${c.address}</td><td>${c.age}</td>`;
        cBody.appendChild(tr);
      });

      // rebuild respondents table
      const rBody = document.getElementById('modalRespondentsBody');
      rBody.innerHTML = '';
      (JSON.parse(data.respondents) || []).forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${r.name}</td><td>${r.address}</td><td>${r.age}</td>`;
        rBody.appendChild(tr);
      });
    } catch (err) {
      alert('Failed to load case details: ' + err.message);
    }
  });

  // Trigger initial load if you want the first option’s data to appear immediately:
  const selectEl = document.getElementById('modalSummonId');
  if (selectEl.value) {
    selectEl.dispatchEvent(new Event('change'));
  }

});
</script>
