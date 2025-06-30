<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];
$newTid = $_GET['transaction_id'] ?? '';

// FILTER + SEARCH SETUP
$search = trim($_GET['summon_search'] ?? '');
$date_from = $_GET['summon_date_from'] ?? '';
$date_to = $_GET['summon_date_to'] ?? '';

// build a Summon-only query array
$bp = [
  'page' => 'adminComplaints',
  'summon_search' => $search,
  'summon_date_from' => $date_from,
  'summon_date_to' => $date_to,
];

$whereClauses = [];
$bindTypes = '';
$bindParams = [];

// global search across key columns
if ($search !== '') {
  $whereClauses[] = "(transaction_id LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ? OR complaint_type LIKE ?)";
  $bindTypes .= str_repeat('s', 4);
  $term = "%{$search}%";
  $bindParams = array_merge($bindParams, array_fill(0, 4, $term));
}

// date-range filtering on created_at
if ($date_from && $date_to) {
  $whereClauses[] = 'DATE(created_at) BETWEEN ? AND ?';
  $bindTypes .= 'ss';
  $bindParams[] = $date_from;
  $bindParams[] = $date_to;
} elseif ($date_from) {
  $whereClauses[] = 'DATE(created_at) >= ?';
  $bindTypes .= 's';
  $bindParams[] = $date_from;
} elseif ($date_to) {
  $whereClauses[] = 'DATE(created_at) <= ?';
  $bindTypes .= 's';
  $bindParams[] = $date_to;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// pagination
$limit = 10;
$page = max((int)($_GET['summon_page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

// 1) get total count
$countSQL = "SELECT COUNT(*) AS total FROM complaint_records {$whereSQL}";
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

// build base query string for pagination links
$qs = $_GET;
unset($qs['summon_page']);
$baseQS = http_build_query($qs);
if ($baseQS) {
  $baseQS .= '&';
}

// 2) fetch actual rows
$sql = "SELECT transaction_id, complainant_name, respondent_name, complaint_type, DATE_FORMAT(created_at, '%b %e, %Y %l:%i %p') AS formatted_created FROM complaint_records {$whereSQL} ORDER BY transaction_id ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

// bind params
$types  = $bindTypes . 'ii';
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
      New complaint record <strong><?= htmlspecialchars($newTid) ?></strong> added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm p-3">
    <!-- FILTER + SEARCH -->
    <div class="d-flex align-items-center mb-3">
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">filter_list</span>
          Filter
        </button>
        <div class="dropdown-menu p-3" aria-labelledby="filterDropdown" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
          <form method="get" action="?page=adminComplaints" id="summonfilterForm" class="mb-0">
            <!-- preserve search -->
            <input type="hidden" name="page" value="adminComplaints">
            <input type="hidden" name="summon_search" value="<?=htmlspecialchars($search)?>">
            <input type="hidden" name="summon_date_from" value="<?=htmlspecialchars($date_from)?>">
            <input type="hidden" name="summon_date_to" value="<?=htmlspecialchars($date_to)?>">
            <input type="hidden" name="summon_page" value="1">

            <!-- Date Filed -->
            <div class="mb-2">
              <label class="form-label mb-1">Date Filed</label>
              <div class="d-flex gap-1">
                <div class="flex-grow-1">
                  <small class="text-muted">From</small>
                  <input type="date" name="summon_date_from" class="form-control form-control-sm" style="font-size:.75rem;" value="<?=htmlspecialchars($date_from)?>">
                </div>
                <div class="flex-grow-1">
                  <small class="text-muted">To</small>
                  <input type="date" name="summon_date_to" class="form-control form-control-sm" style="font-size:.75rem;" value="<?=htmlspecialchars($date_to)?>">
                </div>
              </div>
            </div>

            <div class="d-flex">
              <a href="?page=adminComplaints&summon_page=1" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
              <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Add New Complaint button -->
      <div class="dropdown ms-3">
        <button class="btn btn-sm btn-success" type="button" id="addComplaintBtn" data-bs-toggle="modal" data-bs-target="#addComplaintModal">
          <span class="material-symbols-outlined" style="font-size:1rem; vertical-align:middle;">add</span> 
          Add New Complaint
        </button>
      </div>

      <form method="get" action="?page=adminComplaints" id="searchFormSummon" class="d-flex ms-auto me-2">
        <input type="hidden" name="page" value="adminComplaints">
        <input type="hidden" name="summon_date_from" value="<?=htmlspecialchars($date_from)?>">
        <input type="hidden" name="summon_date_to" value="<?=htmlspecialchars($date_to)?>">
        <input type="hidden" name="summon_page_num" value="1">

        <div class="input-group input-group-sm">
          <input name="summon_search" id="searchInputSummon" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
          <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" id="searchBtnSummon">
            <span class="material-symbols-outlined" id="searchIcon">
              <?= !empty($search) ? 'close' : 'search' ?>
            </span>
          </button>
        </div>
      </form>

      <!-- Add New Complaint Modal -->
      <div class="modal fade" id="addComplaintModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addComplaintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:90vw;">
          <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #13411F;">
              <h5 class="modal-title" id="addComplaintModalLabel">New Complaint Record</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addComplaintForm" method="POST" action="functions/process_new_complaint.php">
              <div class="modal-body">
                <input type="hidden" name="account_id" value="<?= $userId ?>">
                <div class="row gy-2">
                  <!-- Complainant Details -->
                  <div class="col-12"><h6 class="fw-bold">Complainant Details</h6><hr class="my-2"></div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">First Name</label>
                    <input name="complainant_first_name" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                    <input name="complainant_middle_name" type="text" class="form-control form-control-sm">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Last Name</label>
                    <input name="complainant_last_name" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                    <input name="complainant_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-bold">Full Address</label>
                    <input name="complainant_address" type="text" class="form-control form-control-sm" required>
                  </div>

                  <!-- Respondent Details -->
                  <div class="col-12 mt-3"><h6 class="fw-bold">Respondent Details</h6><hr class="my-2"></div>
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
                  <div class="col-12">
                    <label class="form-label fw-bold">Full Address</label>
                    <input name="respondent_address" type="text" class="form-control form-control-sm" required>
                  </div>

                  <!-- Complaint Details -->
                  <div class="col-12 mt-3"><h6 class="fw-bold">Complaint Details</h6><hr class="my-2"></div>
                  <div class="col-12 col-md-5">
                    <label class="form-label fw-bold">Complaint Type</label>
                    <input name="complaint_type" type="text" class="form-control form-control-sm" required>
                  </div>

                  <!-- Complaint Affidavit -->
                  <div class="col-12">
                    <label class="form-label fw-bold">Complaint Affidavit</label>
                    <textarea name="complaint_affidavit" class="form-control form-control-sm" rows="3" required></textarea>
                  </div>

                  <!-- Pleading Statement -->
                  <div class="col-12">
                    <label class="form-label fw-bold">Pleading Statement</label>
                    <textarea name="pleading_statement" class="form-control form-control-sm" rows="3" required></textarea>
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
            <th>Case No.</th>
            <th>Complainant</th>
            <th>Respondent</th>
            <th>Type</th>
            <th>Date Filed</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): 
              $tid = htmlspecialchars($row['transaction_id']);
            ?>
              <tr data-id="<?= $tid ?>">
                <td><?= $tid ?></td>
                <td><?= htmlspecialchars($row['complainant_name']) ?></td>
                <td><?= htmlspecialchars($row['respondent_name']) ?></td>
                <td><?= htmlspecialchars($row['complaint_type']) ?></td>
                <td><?= htmlspecialchars($row['formatted_created']) ?></td>
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
            <a class="page-link" href="?<?= http_build_query(array_merge($bp, ['summon_page' => $page - 1])) ?>">Previous</a>
          </li>

          <?php
          $range = 2;
          $dots = false;

          for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
              $active = $i == $page ? 'active' : '';
              echo "<li class='page-item {$active}'>
                      <a class='page-link' href='?" . http_build_query(array_merge($bp, ['summon_page' => $i])) . "'>$i</a>
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
            <a class="page-link" href="?<?= http_build_query(array_merge($bp, ['summon_page' => $page + 1])) ?>">Next</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('searchFormSummon');
  const input = document.getElementById('searchInputSummon');
  const btn = document.getElementById('searchBtnSummon');
  const hasSearch = <?= json_encode(!empty($search)) ?>;

  btn.addEventListener('click', () => {
    if (hasSearch) input.value = '';
    form.submit();
  });

  const complaintModalEl = document.getElementById('addComplaintModal');
  const complaintForm = document.getElementById('addComplaintForm');

  // Reset all fields when the modal fully hides
 complaintModalEl.addEventListener('hidden.bs.modal', () => {
    complaintForm.reset();
  });

  // Your existing wiring
  const complaintModal = new bootstrap.Modal(complaintModalEl);
  document.getElementById('addComplaintBtn').addEventListener('click', () => complaintModal.show());
});
</script>
