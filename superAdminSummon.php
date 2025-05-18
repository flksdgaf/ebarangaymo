<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];
$newTid    = $_GET['transaction_id'] ?? '';

// Fetch blotter options including details
$blotterOptions = $conn
  ->query("SELECT id, transaction_id, complainants, respondents, complaint_nature FROM blotter_records ORDER BY id ASC")
  ->fetch_all(MYSQLI_ASSOC);

// ── 0) FILTER + SEARCH SETUP ───────────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$date_from   = $_GET['date_from'] ?? '';
$date_to     = $_GET['date_to'] ?? '';

$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

// Global search on transaction_id, complainants, respondents
if ($search !== '') {
  $whereClauses[] = "(s.transaction_id LIKE ? OR b.complainants LIKE ? OR b.respondents LIKE ?)";
  $bindTypes   .= 'sss';
  $term         = "%{$search}%";
  $bindParams[] = $term;
  $bindParams[] = $term;
  $bindParams[] = $term;
}

// Summon date filter
if ($date_from && $date_to) {
  $whereClauses[] = 'DATE(s.summon_date) BETWEEN ? AND ?';
  $bindTypes    .= 'ss';
  $bindParams[]  = $date_from;
  $bindParams[]  = $date_to;
} elseif ($date_from) {
  $whereClauses[] = 'DATE(s.summon_date) >= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $date_from;
} elseif ($date_to) {
  $whereClauses[] = 'DATE(s.summon_date) <= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $date_to;
}

$whereSQL = $whereClauses
  ? 'WHERE ' . implode(' AND ', $whereClauses)
  : '';

$limit  = 10;
$page   = isset($_GET['page_num']) ? max((int)$_GET['page_num'], 1) : 1;
$offset = ($page - 1) * $limit;

// ── COUNT TOTAL ROWS ──────────────────────────────────────────────────────────
$countSQL  = "SELECT COUNT(*) AS total
               FROM summon_records AS s
               JOIN blotter_records AS b ON s.blotter_id = b.id
               {$whereSQL}";
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
$totalRows  = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

// ── FETCH PAGE OF SUMMON RECORDS ───────────────────────────────────────────────
$sql = "
  SELECT
    s.transaction_id,
    b.complainants,
    b.respondents,
    DATE_FORMAT(s.summon_date, '%M %d, %Y') AS formatted_summon
  FROM summon_records AS s
  JOIN blotter_records AS b ON s.blotter_id = b.id
  {$whereSQL}
  ORDER BY s.summon_date ASC
  LIMIT ? OFFSET ?
";
$st = $conn->prepare($sql);

// bind search + date + pagination params
$types = $bindTypes . 'ii';
$bindParams[] = $limit;
$bindParams[] = $offset;

$refs = [];
foreach ($bindParams as $i => $v) {
  $refs[$i] = & $bindParams[$i];
}
array_unshift($refs, $types);
call_user_func_array([$st, 'bind_param'], $refs);

$st->execute();
$result = $st->get_result();
?>

<div class="container py-3">
  <?php if ($newTid): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      New summon record <strong><?= htmlspecialchars($newTid) ?></strong> added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <div class="card shadow-sm p-3">
    <!-- SEARCH + FILTER DROPDOWN -->
    <div class="d-flex align-items-center mb-3">
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          Filter
        </button>
        <div class="dropdown-menu p-3" aria-labelledby="filterDropdown" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
          <form method="get" id="filterForm" class="mb-0">
            <input type="hidden" name="page" value="superAdminSummon">
            <?php if (!empty($search)): ?>
              <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>

            <!-- Summon Date -->
            <div class="mb-2">
              <label class="form-label mb-1">Summon Date</label>
              <div class="d-flex gap-1">
                <div class="flex-grow-1">
                  <small class="text-muted">From</small>
                  <input type="date" name="date_from" class="form-control form-control-sm" style="font-size:.75rem;" value="<?=htmlspecialchars($date_from)?>">
                </div>
                <div class="flex-grow-1">
                  <small class="text-muted">To</small>
                  <input type="date" name="date_to" class="form-control form-control-sm" style="font-size:.75rem;" value="<?=htmlspecialchars($date_to)?>">
                </div>
              </div>
            </div>

            <div class="d-flex">
              <a href="?page=superAdminSummon" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
              <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
            </div>
          </form>
        </div>
      </div>

      <!-- ← Add New Summon button -->
      <button type="button" class="btn btn-sm btn-success ms-3" id="addSummonBtn">
        <i class="bi bi-plus-lg me-1"></i> Add New Summon
      </button>

      <form method="get" id="searchForm" class="d-flex ms-auto me-2">
        <input type="hidden" name="page" value="superAdminSummon">
        <input type="hidden" name="page_num" value="1">
        <?php if (!empty($date_from)): ?> <input type="hidden" name="date_from" value="<?=htmlspecialchars($date_from)?>"><?php endif; ?>
        <?php if (!empty($date_to)): ?>   <input type="hidden" name="date_to"   value="<?=htmlspecialchars($date_to)?>"><?php endif; ?>

        <div class="input-group input-group-sm">
          <input name="search" id="searchInput" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
          <button type="button" class="btn btn-outline-secondary" id="searchBtn">
            <span class="material-symbols-outlined" id="searchIcon">
              <?= !empty($search) ? 'close' : 'search' ?>
            </span>
          </button>
        </div>
      </form>
    </div>

    <!-- TABLE -->
    <div class="table-responsive admin-table" style="height:500px; overflow-y:auto;">
      <table class="table table-hover align-middle text-start">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">Transaction No.</th>
            <th class="text-nowrap">Complainant</th>
            <th class="text-nowrap">Respondent</th>
            <th class="text-nowrap">Summon Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                <td><?= htmlspecialchars($row['complainants']) ?></td>
                <td><?= htmlspecialchars($row['respondents']) ?></td>
                <td><?= $row['formatted_summon'] ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="4" class="text-center">No records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <!-- PAGINATION -->
      <?php if ($totalPages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center pagination-sm">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_num' => $page - 1])) ?>">Previous</a>
          </li>
          <?php
          $range = 2; $dots = false;
          for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
              $active = $i == $page ? 'active' : '';
              echo "<li class='page-item {$active}'>
                      <a class='page-link' href='?" . http_build_query(array_merge($_GET, ['page_num' => $i])) . "'>$i</a>
                    </li>";
              $dots = true;
            } elseif ($dots) {
              echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
              $dots = false;
            }
          }
          ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_num' => $page + 1])) ?>">Next</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- Add New Summon Modal -->
<div class="modal fade" id="addSummonModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow-sm">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">
          <i class="bi bi-plus-lg me-2"></i> New Summon
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="addSummonForm" action="functions/superAdminSummon_submit.php" method="POST">
        <div class="modal-body">

          <!-- Blotter (foreign key) -->
          <div class="mb-3">
            <label class="form-label">Blotter Record</label>
            <select name="blotter_id" id="blotterSelect" class="form-select" required>
              <option value="">-- select blotter --</option>
            <?php foreach ($blotterOptions as $opt): ?>
              <option value="<?= $opt['id'] ?>">
                <?= htmlspecialchars($opt['transaction_id']) ?>
              </option>
            <?php endforeach; ?>
            </select>
          </div>

          <!-- Auto-filled Details -->
          <div class="row mb-3">
            <div class="col">
              <label class="form-label">Complainant</label>
              <input type="text" id="detailComplainant" class="form-control" disabled>
            </div>
            <div class="col">
              <label class="form-label">Respondent</label>
              <input type="text" id="detailRespondent" class="form-control" disabled>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Complaint Nature</label>
            <input type="text" id="detailNature" class="form-control" disabled>
          </div>

          <!-- Summon Date & Time -->
          <div class="row mb-3">
            <div class="col">
              <label class="form-label">Summon Date</label>
              <input name="summon_date" type="date"
                     class="form-control" required>
            </div>
            <div class="col">
              <label class="form-label">Summon Time</label>
              <input name="summon_time" type="time"
                     class="form-control" required>
            </div>
          </div>

          <!-- Subject -->
          <div class="mb-3">
            <label class="form-label">Subject</label>
            <input name="subject" type="text"
                   class="form-control" required>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button"
                  class="btn btn-outline-secondary"
                  data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form      = document.getElementById('searchForm');
  const input     = document.getElementById('searchInput');
  const btn       = document.getElementById('searchBtn');
  const icon      = document.getElementById('searchIcon');
  const hasSearch = <?= json_encode(!empty($search)) ?>;

  btn.addEventListener('click', () => {
    if (hasSearch) input.value = '';
    form.submit();
  });
  
  // “Add New Summon” modal toggle
  const addSummonBtn   = document.getElementById('addSummonBtn');
  const addSummonModal = new bootstrap.Modal(
    document.getElementById('addSummonModal')
  );

  addSummonBtn.addEventListener('click', () => {
    const form = document.getElementById('addSummonForm');
    form.reset();

    // remove any old flag
    const old = form.querySelector('input[name="superAdminRedirect"]');
    if (old) old.remove();
    const flag = document.createElement('input');
    flag.type  = 'hidden';
    flag.name  = 'superAdminRedirect';
    flag.value = '1';
    form.prepend(flag);

    addSummonModal.show();
  });

  // Preload blotter data into JS
  const blotterData = <?= json_encode($blotterOptions) ?>;

  // When blotter is selected, auto-fill details
  document.getElementById('blotterSelect').addEventListener('change', function() {
    const selectedId = this.value;
    const details = blotterData.find(b => b.id == selectedId);
    document.getElementById('detailComplainant').value = details ? details.complainants : '';
    document.getElementById('detailRespondent').value = details ? details.respondents : '';
    document.getElementById('detailNature').value = details ? details.complaint_nature : '';
  });
});
</script>

<?php
$st->close();
$conn->close();
?>
