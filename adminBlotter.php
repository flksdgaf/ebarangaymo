<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];
$newTid    = $_GET['transaction_id'] ?? '';

// ── 0) FILTER + SEARCH SETUP ───────────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$date_from   = $_GET['date_from'] ?? '';
$date_to     = $_GET['date_to'] ?? '';

$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

// Global search on transaction_id and complainants
if ($search !== '') {
  $whereClauses[] = "(transaction_id LIKE ? OR complainants LIKE ? OR complaint_nature LIKE ?)";
  $bindTypes   .= 'sss';
  $term         = "%{$search}%";
  $bindParams[] = $term;
  $bindParams[] = $term;
  $bindParams[] = $term;
}

// Date occurrence filter
if ($date_from && $date_to) {
  $whereClauses[] = 'DATE(date_occurrence) BETWEEN ? AND ?';
  $bindTypes    .= 'ss';
  $bindParams[]  = $date_from;
  $bindParams[]  = $date_to;
} elseif ($date_from) {
  $whereClauses[] = 'DATE(date_occurrence) >= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $date_from;
} elseif ($date_to) {
  $whereClauses[] = 'DATE(date_occurrence) <= ?';
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
$countSQL  = "SELECT COUNT(*) AS total FROM blotter_records {$whereSQL}";
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

// ── FETCH PAGE OF RECORDS ────────────────────────────────────────────────────
$sql = "
  SELECT
    transaction_id,
    complainants,
    DATE_FORMAT(date_occurrence, '%M %d, %Y') AS formatted_occurrence,
    complaint_nature
  FROM blotter_records
  {$whereSQL}
  ORDER BY date_filed ASC
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

<title>eBarangay Mo | Blotter</title>

<div class="container py-3">
   <?php if ($newTid): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      New blotter record <strong><?= htmlspecialchars($newTid) ?></strong> added successfully!
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
            <!-- preserve search -->
            <input type="hidden" name="page" value="adminBlotter">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

            <!-- Date Occurrence -->
            <div class="mb-2">
              <label class="form-label mb-1">Date Occurrence</label>
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
              <a href="?page=adminBlotter" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
              <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Add New Blotter button -->
      <button type="button" class="btn btn-sm btn-success ms-3" id="addBlotterBtn">
        <i class="bi bi-plus-lg me-1"></i> Add New Blotter
      </button>


      <form method="get" id="searchForm" class="d-flex ms-auto me-2">
        <input type="hidden" name="page" value="adminBlotter">
        <input type="hidden" name="page_num" value="1">
        <!-- preserve filters -->
        <input type="hidden" name="date_from" value="<?=htmlspecialchars($date_from)?>">
        <input type="hidden" name="date_to"   value="<?=htmlspecialchars($date_to)?>">

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
            <th class="text-nowrap">Date Occurrence</th>
            <th class="text-nowrap">Complaint Nature</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                <td><?= htmlspecialchars($row['complainants']) ?></td>
                <td><?= $row['formatted_occurrence'] ?></td>
                <td><?= htmlspecialchars($row['complaint_nature']) ?></td>
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
            if (
              $i == 1 ||
              $i == $totalPages ||
              ($i >= $page - $range && $i <= $page + $range)
            ) {
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

<!-- Add New Blotter Modal -->
<div class="modal fade" id="addBlotterModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow-sm">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">
          <i class="bi bi-plus-lg me-2"></i> New Blotter Record
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="addBlotterForm" action="functions/superAdminBlotter_submit.php" method="POST">
        <div class="modal-body">
          <div class="row">

            <!-- Complainants column -->
            <div class="col-md-6 mb-3">
              <label class="form-label">Complainants</label>
              <div id="complainantsWrapper">
                <div class="input-group mb-2 complaint-entry">
                  <input name="complainants[]" type="text" class="form-control" required>
                  <button type="button" class="btn btn-danger remove-entry">×</button>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-success" id="addComplainantBtn" style="width: 100%;">
                <i class="bi bi-plus-lg"></i> Add Complainant
              </button>
            </div>

            <!-- Respondents column -->
            <div class="col-md-6 mb-3">
              <label class="form-label">Respondents</label>
              <div id="respondentsWrapper">
                <div class="input-group mb-2 respondent-entry">
                  <input name="respondents[]" type="text" class="form-control" required>
                  <button type="button" class="btn btn-danger remove-entry">×</button>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-success" id="addRespondentBtn" style="width: 100%;">
                <i class="bi bi-plus-lg"></i> Add Respondent
              </button>
            </div>
          </div>

          <!-- Date Filed & Date Occurrence -->
          <div class="row mb-3">
            <div class="col">
              <label class="form-label">Date Filed</label>
              <input name="date_filed" type="date" class="form-control" required>
            </div>
            <div class="col">
              <label class="form-label">Date Occurrence</label>
              <input name="date_occurrence" type="date" class="form-control" required>
            </div>
          </div>
          <!-- Incidence Place -->
          <div class="mb-3">
            <label class="form-label">Incidence Place</label>
            <input name="incidence_place" type="text" class="form-control" required>
          </div>
          <!-- Complaint Nature -->
          <div class="mb-3">
            <label class="form-label">Complaint Nature</label>
            <input name="complaint_nature" type="text" class="form-control" required>
          </div>
          <!-- Complaint Description -->
          <div class="mb-3">
            <label class="form-label">Complaint Description</label>
            <textarea name="complaint_description" class="form-control" rows="3" required></textarea>
          </div>
          <!-- Remarks -->
          <div class="mb-3">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2"></textarea>
          </div>
          <!-- Payment Method -->
          <div class="mb-3">
            <label class="form-label">Payment Method</label>
            <select name="payment_method" class="form-select" required>
              <option value="GCash">GCash</option>
              <option value="Brgy Payment Device">Brgy Payment Device</option>
              <option value="Over-the-Counter">Over-the-Counter</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // ——— SEARCH FORM TOGGLE —————————————————————————————
  const form      = document.getElementById('searchForm');
  const input     = document.getElementById('searchInput');
  const btn       = document.getElementById('searchBtn');
  const hasSearch = <?= json_encode(!empty($search)) ?>;

  btn.addEventListener('click', () => {
    if (hasSearch) input.value = '';
    form.submit();
  });

  // ——— “Add New Blotter” modal toggle ——————————————————
  const addBlotterBtn      = document.getElementById('addBlotterBtn');
  const addBlotterModalEl  = document.getElementById('addBlotterModal');
  const addBlotterBs       = new bootstrap.Modal(addBlotterModalEl);
  const complainantsWrapper = document.getElementById('complainantsWrapper');
  const respondentsWrapper  = document.getElementById('respondentsWrapper');

  // Prepare HTML templates
  const complainantTemplate = `
    <div class="input-group mb-2 complaint-entry">
      <input name="complainants[]" type="text" class="form-control" required>
      <button type="button" class="btn btn-danger remove-entry">×</button>
    </div>
  `;
  const respondentTemplate = `
    <div class="input-group mb-2 respondent-entry">
      <input name="respondents[]" type="text" class="form-control" required>
      <button type="button" class="btn btn-danger remove-entry">×</button>
    </div>
  `;

  // Helper to disable/enable remove buttons depending on count
  function updateRemoveButtons(wrapperEl, entrySelector) {
    const entries = wrapperEl.querySelectorAll(entrySelector);
    const disable = entries.length <= 1;
    entries.forEach(entry => {
      const btn = entry.querySelector('.remove-entry');
      btn.disabled = disable;
      // Optionally, change opacity so it looks disabled:
      btn.style.opacity = disable ? '0.5' : '1';
      btn.style.pointerEvents = disable ? 'none' : 'auto';
    });
  }

  // Utility to add / remove entries
  function setupDynamicList(addBtnId, wrapperEl, entryHtml, entrySelector) {
    // Delegate remove clicks
    wrapperEl.addEventListener('click', e => {
      if (e.target.matches('.remove-entry')) {
        // only remove if more than 1
        if (wrapperEl.querySelectorAll(entrySelector).length > 1) {
          e.target.closest('.input-group').remove();
          updateRemoveButtons(wrapperEl, entrySelector);
        }
      }
    });

    // Add button
    document.getElementById(addBtnId).addEventListener('click', () => {
      const temp = document.createElement('div');
      temp.innerHTML = entryHtml.trim();
      wrapperEl.appendChild(temp.firstChild);
      updateRemoveButtons(wrapperEl, entrySelector);
    });
  }

  // Initialize dynamic lists
  setupDynamicList('addComplainantBtn', complainantsWrapper, complainantTemplate, '.complaint-entry');
  setupDynamicList('addRespondentBtn',  respondentsWrapper,  respondentTemplate, '.respondent-entry');

  // Reset form & wrappers each time modal opens
  addBlotterBtn.addEventListener('click', () => {
    const form = document.getElementById('addBlotterForm');
    form.reset();

    complainantsWrapper.innerHTML = complainantTemplate.trim();
    respondentsWrapper.innerHTML  = respondentTemplate.trim();

    // hide/remove any old redirect flag and inject a fresh one
    const old = form.querySelector('input[name="adminRedirect"]');
    if (old) old.remove();
    const flag = document.createElement('input');
    flag.type  = 'hidden';
    flag.name  = 'adminRedirect';
    flag.value = '1';
    form.prepend(flag);

    // ensure exactly one entry and disable its remove button
    updateRemoveButtons(complainantsWrapper, '.complaint-entry');
    updateRemoveButtons(respondentsWrapper,  '.respondent-entry');

    addBlotterBs.show();
  });
});
</script>


<?php
$st->close();
$conn->close();
?>
