<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];
$newTid = $_GET['transaction_id'] ?? '';

// FILTER + SEARCH SETUP
$search = trim($_GET['blotter_search'] ?? '');
$date_from = $_GET['blotter_date_from'] ?? '';
$date_to = $_GET['blotter_date_to'] ?? '';

// build a Blotter‑only query array
$bp = [
  'page' => 'superAdminComplaints',
  'blotter_search' => $search,
  'blotter_date_from' => $date_from,
  'blotter_date_to' => $date_to,
];

$whereClauses = [];
$bindTypes = '';
$bindParams = [];

// global search across a few columns
if ($search !== '') {
  $whereClauses[] = "(transaction_id LIKE ? OR client_name LIKE ? OR respondent_name LIKE ? OR incident_type LIKE ?)";
  $bindTypes .= str_repeat('s', 4);
  $term = "%{$search}%";
  $bindParams = array_merge($bindParams, array_fill(0, 4, $term));
}

// date‐range filtering
if ($date_from && $date_to) {
  $whereClauses[] = 'DATE(incident_date) BETWEEN ? AND ?';
  $bindTypes .= 'ss';
  $bindParams[]  = $date_from;
  $bindParams[]  = $date_to;
} elseif ($date_from) {
  $whereClauses[] = 'DATE(incident_date) >= ?';
  $bindTypes .= 's';
  $bindParams[]  = $date_from;
} elseif ($date_to) {
  $whereClauses[] = 'DATE(incident_date) <= ?';
  $bindTypes .= 's';
  $bindParams[]  = $date_to;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// pagination
$limit = 10;
$page = max((int)($_GET['blotter_page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

// 1) get total count
$countSQL = "SELECT COUNT(*) AS total FROM blotter_records {$whereSQL}";
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
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

$qs = $_GET;
unset($qs['blotter_page']);
$baseQS = http_build_query($qs);
if ($baseQS) {
  $baseQS .= '&';
}

// 2) fetch the actual rows
$sql = "SELECT transaction_id, client_name, respondent_name, incident_type, DATE_FORMAT(incident_date,'%b %e, %Y') AS formatted_date, DATE_FORMAT(incident_time,'%l:%i %p') AS formatted_time FROM blotter_records {$whereSQL} ORDER BY transaction_id ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

// bind the filters + pagination
$types = $bindTypes . 'ii';
$params = array_merge($bindParams, [$limit, $offset]);
$refs = [];
foreach ($params as $i => $v) {
  $refs[$i] = & $params[$i];
}
array_unshift($refs, $types);
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<div>
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
          <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">filter_list</span>
          Filter
        </button>
        <div class="dropdown-menu p-3" aria-labelledby="filterDropdown" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
          <form method="get" action="?page=superAdminComplaints" id="blotterfilterForm" class="mb-0">
            <!-- preserve search -->
            <input type="hidden" name="page" value="superAdminComplaints">
            <input type="hidden" name="blotter_search" value="<?=htmlspecialchars($search)?>">
            <input type="hidden" name="blotter_date_from" value="<?=htmlspecialchars($date_from)?>">
            <input type="hidden" name="blotter_date_to" value="<?=htmlspecialchars($date_to)?>">
            <input type="hidden" name="blotter_page" value="1">

            <!-- Date Occurrence -->
            <div class="mb-2">
              <label class="form-label mb-1">Date Occurred</label>
              <div class="d-flex gap-1">
                <div class="flex-grow-1">
                  <small class="text-muted">From</small>
                  <input type="date" name="blotter_date_from" class="form-control form-control-sm" style="font-size:.75rem;" value="<?=htmlspecialchars($date_from)?>">
                </div>
                <div class="flex-grow-1">
                  <small class="text-muted">To</small>
                  <input type="date" name="blotter_date_to" class="form-control form-control-sm" style="font-size:.75rem;" value="<?=htmlspecialchars($date_to)?>">
                </div>
              </div>
            </div>

            <div class="d-flex">
              <a href="?page=superAdminComplaints&blotter_page=1" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
              <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Add New Blotter button -->
      <div class="dropdown ms-3">
        <button class="btn btn-sm btn-success" type="button" id="addBlotterBtn" data-bs-toggle="modal" data-bs-target="#addBlotterModal">
          <span class="material-symbols-outlined" style="font-size:1rem; vertical-align:middle;">add</span>
          Add New Blotter
        </button>
      </div>

      <form method="get" action="?page=superAdminComplaints" id="searchFormBlotter" class="d-flex ms-auto me-2">
        <input type="hidden" name="page" value="superAdminComplaints">
        <input type="hidden" name="blotter_date_from"value="<?=htmlspecialchars($date_from)?>">
        <input type="hidden" name="blotter_date_to"value="<?=htmlspecialchars($date_to)?>">
        <input type="hidden" name="blotter_page" value="1">

        <div class="input-group input-group-sm">
          <input name="blotter_search" id="searchInputBlotter" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
          <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" id="searchBtnBlotter">
            <span class="material-symbols-outlined" id="searchIcon">
              <?= !empty($search) ? 'close' : 'search' ?>
            </span>
          </button>
        </div>
      </form>

      <!-- Add New Blotter Modal -->
      <div class="modal fade" id="addBlotterModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addBlotterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:90vw;">
          <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #13411F;">
              <h5 class="modal-title" id="addBlotterModalLabel">New Blotter Record</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addBlotterForm" method="POST" action="functions/process_new_blotter.php">
              <div class="modal-body">
                <input type="hidden" name="account_id" value="<?= $userId ?>">
                
                <div class="row gy-2">
                  <!-- Client Details -->
                  <div class="col-12">
                    <h6 class="fw-bold">Client Details</h6>
                    <hr class="my-2">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">First Name</label>
                    <input name="client_first_name" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                    <input name="client_middle_name" type="text" class="form-control form-control-sm">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Last Name</label>
                    <input name="client_last_name" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                    <input name="client_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Client Address</label>
                    <input name="client_address" type="text" class="form-control form-control-sm" required>
                  </div>

                  <!-- Respondent Details Header + Toggle -->
                  <div class="col-12 mt-3 d-flex align-items-center">
                     <div class="form-check d-inline-block">
                       <input class="form-check-input" type="checkbox" id="hasRespondentCheck" checked>
                       <label class="form-check-label fs-6" for="hasRespondentCheck"></label>
                     </div>
                     <h6 class="fw-bold mb-0 me-3">Respondent Details</h6>
                   </div>

                  <!-- horizontal rule covers the entire 12 columns -->
                  <div class="col-12"><hr class="my-2"></div>

                  <!-- Wrap all respondent fields here — note the `row` class -->
                  <div id="respondentSection" class="row gy-2 col-12">
                    <div class="col-12 col-md-3">
                      <label class="form-label fw-bold">First Name</label>
                      <input name="respondent_first_name" type="text" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12 col-md-3">
                      <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                      <input name="respondent_middle_name" type="text" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-3">
                      <label class="form-label fw-bold">Last Name</label>
                      <input name="respondent_last_name" type="text" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12 col-md-3">
                      <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                      <input name="respondent_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label fw-bold">Respondent Address</label>
                      <input name="respondent_address" type="text" class="form-control form-control-sm" required>
                    </div>
                  </div>

                  <!-- Complaint Details -->
                  <div class="col-12 mt-3">
                    <h6 class="fw-bold">Complaint Details</h6>
                    <hr class="my-2">
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Complaint / Incident Type</label>
                    <input name="incident_type" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Date Occurred</label>
                    <input name="incident_date" type="date" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Time Occurred</label>
                    <input name="incident_time" type="time" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-bold">Incident Place</label>
                    <input name="incident_place" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-bold">Incident Description</label>
                    <textarea name="incident_description" class="form-control form-control-sm" rows="3" required></textarea>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Create Record</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- TABLE -->
    <div class="table-responsive admin-table">
      <table class="table table-hover align-middle text-start">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">Transaction No.</th>
            <th class="text-nowrap">Client</th>
            <th class="text-nowrap">Respondent</th>
            <th class="text-nowrap">Complaint Nature</th>
            <th class="text-nowrap">Date Occurred</th>
            <th class="text-nowrap text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()):
              $tid = htmlspecialchars($row['transaction_id']);
            ?>
              <tr data-id="<?= $tid ?>">
                <td><?= $tid ?></td>
                <td><?= htmlspecialchars($row['client_name']) ?></td>
                <td><?= htmlspecialchars($row['respondent_name'] ?: '—') ?></td>
                <td><?= htmlspecialchars($row['incident_type']) ?></td>
                <td>
                  <?= htmlspecialchars($row['formatted_date']) ?>
                  <?= htmlspecialchars($row['formatted_time']) ?>
                </td>
                <td class="text-center">
                  <!-- Edit -->
                  <button class="btn btn-sm btn-success">
                    <span class="material-symbols-outlined" style="font-size: 12px;">
                      stylus
                    </span>
                  </button>

                  <!-- Delete -->
                  <button class="btn btn-sm btn-danger">
                    <span class="material-symbols-outlined" style="font-size: 12px;">
                      delete
                    </span>
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="text-center">No records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center pagination-sm">
          <!-- Prev Button -->
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($bp, ['blotter_page'=>$page-1])) ?>">Previous</a>
          </li>

          <?php
          $range = 2;
          $dots = false;

          for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
              $active = $i == $page ? 'active' : '';
              echo "<li class='page-item {$active}'>
                      <a class='page-link' href='?" . http_build_query(array_merge($bp, ['blotter_page' => $i])) . "'>$i</a>
                    </li>";
              $dots = true;
            } elseif ($dots) {
              echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
              $dots = false;
            }
          }
          ?>

          <!-- Next Button -->
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($bp, ['blotter_page' => $page + 1])) ?>">Next</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // SEARCH FORM TOGGLE 
  const form = document.getElementById('searchFormBlotter');
  const input = document.getElementById('searchInputBlotter');
  const btn = document.getElementById('searchBtnBlotter');
  const hasSearch = <?= json_encode(!empty($search)) ?>;

  btn.addEventListener('click', () => {
    if (hasSearch) input.value = '';
    form.submit();
  });

  const blotterModalEl = document.getElementById('addBlotterModal');
  const blotterForm = document.getElementById('addBlotterForm');

  // Reset all fields when the modal fully hides
  blotterModalEl.addEventListener('hidden.bs.modal', () => {
    blotterForm.reset();
  });

  // Your existing wiring
  const blotterModal = new bootstrap.Modal(blotterModalEl);
  document.getElementById('addBlotterBtn').addEventListener('click', () => blotterModal.show());

  const respCheck = document.getElementById('hasRespondentCheck');
  const respSection = document.getElementById('respondentSection');

  function blotter_toggleRespondent() {
    const show = respCheck.checked;
    respSection.style.display = show ? '' : 'none';
    respSection.querySelectorAll('input, select, textarea').forEach(el => el.disabled = !show);
  }

  // wire up change + initialize
  respCheck.addEventListener('change', blotter_toggleRespondent);
  blotter_toggleRespondent();
});
</script>
