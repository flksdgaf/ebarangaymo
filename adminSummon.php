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
$sql = "SELECT transaction_id, complainant_name, complainant_address, respondent_name, respondent_address, complaint_type, complaint_affidavit, pleading_statement, DATE_FORMAT(created_at, '%b %e, %Y %l:%i %p') AS formatted_created FROM complaint_records {$whereSQL} ORDER BY transaction_id ASC LIMIT ? OFFSET ?";
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
  <!-- <?php if (isset($_GET['new_complaint_id'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      New complaint record <strong><?= htmlspecialchars($_GET['new_complaint_id']) ?></strong> added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?> -->

  <!-- <?php if ($id = ($_GET['new_complaint_id'] ?? false)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      New complaint record <strong><?= htmlspecialchars($id) ?></strong> added!
      <a href="functions/print_complaint.php?transaction_id=<?= urlencode($id) ?>" class="btn btn-sm btn-outline-success ms-2" target="_blank">
        Print Complaint
      </a>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?> -->

    <?php if ($id = ($_GET['new_complaint_id'] ?? false)): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
      <div>New complaint record <strong><?= htmlspecialchars($id) ?></strong> added!</div>
      <div class="ms-auto d-flex align-items-center">
        <a href="#" data-tid="<?= htmlspecialchars($id) ?>" class="btn btn-sm btn-outline-success me-2 print-alert-btn">
          Print Complaint
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['scheduled_complaint_id'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Summon for transaction <strong><?= htmlspecialchars($_GET['scheduled_complaint_id']) ?></strong> was scheduled successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['updated_complaint_id'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Complaint record <strong><?= htmlspecialchars($_GET['updated_complaint_id']) ?></strong> updated successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

  <?php elseif (isset($_GET['complaint_nochange'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      No changes detected, nothing to update.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['deleted_complaint_id'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      Summon record <strong><?= htmlspecialchars($_GET['deleted_complaint_id']) ?></strong> was permanently deleted.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] === 'summon_exists'): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      A summon has already been scheduled for transaction ID <strong><?= htmlspecialchars($_GET['transaction_id']) ?></strong>.
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
                  <div class="col-12">
                    <h6 class="fw-bold fs-5" style="color: #13411F;">Complainant Details</h6>
                    <hr class="my-2">
                  </div>
                  <!-- <div class="col-12 col-md-5">
                    <label class="form-label fw-bold">Full Name</label>
                    <input name="complainant_name" type="text" class="form-control form-control-sm" required>
                  </div> -->
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
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Complainant Address</label>
                    <input name="complainant_address" type="text" class="form-control form-control-sm" required>
                  </div>

                  <!-- Respondent Details -->
                  <div class="col-12 mt-3">
                    <h6 class="fw-bold fs-5" style="color: #13411F;">Respondent Details</h6>
                    <hr class="my-2">
                  </div>
                  <!-- <div class="col-12 col-md-5">
                    <label class="form-label fw-bold">Full Name</label>
                    <input name="respondent_name" type="text" class="form-control form-control-sm" required>
                  </div> -->
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

                  <!-- Complaint Details -->
                  <div class="col-12 mt-3">
                    <h6 class="fw-bold fs-5" style="color: #13411F;">Complaint Details</h6>
                    <hr class="my-2">
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Complaint / Incident Type</label>
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

      <!-- Schedule Summon Modal -->
      <div class="modal fade" id="scheduleSummonModal" tabindex="-1" aria-labelledby="scheduleSummonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form id="scheduleSummonForm" method="POST" action="functions/process_schedule_summon.php" class="modal-content">
            <div class="modal-header bg-warning text-dark">
              <h5 class="modal-title" id="scheduleSummonModalLabel">Schedule Summon</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="transaction_id" id="scheduleSummonTransactionId">
              <div class="mb-3">
                <label for="scheduled_at" class="form-label">Select Date & Time</label>
                <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="form-control" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-warning">Schedule & Print </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Edit Complaint Modal -->
      <div class="modal fade" id="editComplaintModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editComplaintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:90vw;">
          <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #13411F;">
              <h5 class="modal-title" id="editComplaintModalLabel">Edit Complaint Record</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editComplaintForm" method="POST" action="functions/process_edit_complaint.php">
              <div class="modal-body">
                <input type="hidden" name="transaction_id" id="edit_complaint_transaction_id">
                <input type="hidden" name="account_id" value="<?= $userId ?>">
                <input type="hidden" name="summon_page" value="<?= htmlspecialchars($page) ?>">

                <div class="row gy-2">
                  <!-- Complainant Details -->
                  <div class="col-12">
                    <h6 class="fw-bold fs-5">Complainant Details</h6>
                    <hr class="my-2">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">First Name</label>
                    <input name="complainant_first_name" id="edit_complainant_first_name" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                    <input name="complainant_middle_name" id="edit_complainant_middle_name" type="text" class="form-control form-control-sm">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Last Name</label>
                    <input name="complainant_last_name" id="edit_complainant_last_name" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                    <input name="complainant_suffix" id="edit_complainant_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Complainant Address</label>
                    <input type="text" id="edit_complainant_address" name="complainant_address" class="form-control form-control-sm" required>
                  </div>

                  <!-- Respondent Details -->
                  <div class="col-12 mt-3">
                    <h6 class="fw-bold fs-5">Respondent Details</h6>
                    <hr class="my-2">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">First Name</label>
                    <input name="respondent_first_name" id="edit_complaint_respondent_first_name" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                    <input name="respondent_middle_name" id="edit_complaint_respondent_middle_name" type="text" class="form-control form-control-sm">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Last Name</label>
                    <input name="respondent_last_name" id="edit_complaint_respondent_last_name" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                    <input name="respondent_suffix" id="edit_complaint_respondent_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Full Address</label>
                    <input type="text" id="edit_complaint_respondent_address" name="respondent_address" class="form-control form-control-sm" required>
                  </div>

                  <!-- Complaint Details -->
                  <div class="col-12 mt-3">
                    <h6 class="fw-bold fs-5">Complaint Details</h6>
                    <hr class="my-2">
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Complaint Type</label>
                    <input type="text" id="edit_complaint_type" name="complaint_type" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-bold">Complaint Affidavit</label>
                    <textarea id="edit_complaint_affidavit" name="complaint_affidavit" class="form-control form-control-sm" rows="3" required></textarea>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-bold">Pleading Statement</label>
                    <textarea id="edit_pleading_statement" name="pleading_statement" class="form-control form-control-sm" rows="3" required></textarea>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="printComplaintBtn">Print Complaint</button>
                <button type="submit" class="btn btn-success">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Delete Summon Modal -->
      <div class="modal fade" id="deleteSummonModal" tabindex="-1" aria-labelledby="deleteSummonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form id="deleteSummonForm">
            <div class="modal-content">
              <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteSummonModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                Are you sure you want to permanently delete summon record <strong id="deleteSummonTransactionIdLabel"></strong>?
                <input type="hidden" name="transaction_id" id="deleteSummonTransactionId">
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
              </div>
            </div>
          </form>
        </div>
      </div>

    </div>

    <!-- TABLE -->
    <div class="table-responsive admin-table">
      <table class="table table-hover align-middle text-start">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">Case No.</th>
            <th class="text-nowrap">Complainant</th>
            <th class="text-nowrap">Respondent</th>
            <th class="text-nowrap">Complaint Type</th>
            <th class="text-nowrap">Date Filed</th>
            <th class="text-nowrap text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): 
              $tid = htmlspecialchars($row['transaction_id']);
            ?>
              <tr 
                data-id="<?= $tid ?>"
                data-complainant-name="<?= htmlspecialchars($row['complainant_name'], ENT_QUOTES) ?>"
                data-complainant-address="<?= htmlspecialchars($row['complainant_address'] ?? '', ENT_QUOTES) ?>"
                data-respondent-name="<?= htmlspecialchars($row['respondent_name'], ENT_QUOTES) ?>"
                data-respondent-address="<?= htmlspecialchars($row['respondent_address'] ?? '', ENT_QUOTES) ?>"
                data-complaint-type="<?= htmlspecialchars($row['complaint_type'], ENT_QUOTES) ?>"
                data-complaint-affidavit="<?= htmlspecialchars($row['complaint_affidavit'] ?? '', ENT_QUOTES) ?>"
                data-pleading-statement="<?= htmlspecialchars($row['pleading_statement'] ?? '', ENT_QUOTES) ?>"   
              >
                <td><?= $tid ?></td>
                <td><?= htmlspecialchars($row['complainant_name']) ?></td>
                <td><?= htmlspecialchars($row['respondent_name']) ?></td>
                <td><?= htmlspecialchars($row['complaint_type']) ?></td>
                <td><?= htmlspecialchars($row['formatted_created']) ?></td>
                <td class="text-center">
                  <!-- Schedule -->
                  <button class="btn btn-sm btn-warning schedule-btn-complaint">
                    <span class="material-symbols-outlined" style="font-size: 12px;">
                      event
                    </span>
                  </button>

                  <!-- Edit -->
                  <button class="btn btn-sm btn-success edit-btn-complaint">
                    <span class="material-symbols-outlined" style="font-size: 12px;">
                      stylus
                    </span>
                  </button>

                  <!-- Delete -->
                  <button class="btn btn-sm btn-danger delete-btn-complaint" data-id="<?= $tid ?>" data-bs-toggle="modal" data-bs-target="#deleteSummonModal">
                    <span class="material-symbols-outlined" style="font-size: 12px;">delete</span>
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

function printComplaint(transactionId) {
  if (!transactionId) return alert('No transaction ID provided.');
  window.open(
    'functions/print_complaint.php?transaction_id=' + encodeURIComponent(transactionId),
    '_blank',
    'width=900,height=600'
  );
}

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

  // Schedule‑button wiring
  const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleSummonModal'));
  document.querySelectorAll('.schedule-btn-complaint').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr  = btn.closest('tr');
      const tid = tr.dataset.id;
      document.getElementById('scheduleSummonTransactionId').value = tid;
      scheduleModal.show();
    });
  });

  const editModalEl = document.getElementById('editComplaintModal');
  const editModal = new bootstrap.Modal(editModalEl);

  document.querySelectorAll('.edit-btn-complaint').forEach(btn => {
    btn.addEventListener('click', async () => {
      const tr = btn.closest('tr');
      const tid = tr.dataset.id;

       // 1) inject transaction_id
      document.getElementById('edit_complaint_transaction_id').value = tid;

      const compFull = tr.children[1].textContent.trim();
      const respFull = tr.children[2].textContent.trim();

      // Helper to parse "Last[ Suffix], First[ Middle]" into parts
      function parseName(full) {
        // split into [ leftOfComma, rightOfComma ]
        const [left='', right=''] = full.split(/\s*,\s*/);

        // --- LAST & SUFFIX ---
        // left could be “Britos” or “Britos Jr.” etc
        const leftWords = left.trim().split(/\s+/);
        const last = leftWords[0] || '';
        const suffix = leftWords.slice(1).join(' ') || '';
        
        // --- FIRST & MIDDLE ---
        // right could be “Kent Gabriel Villariasa”
        const rightWords = right.trim().split(/\s+/);
        let first = '';
        let middle = '';

        if (rightWords.length === 0) {
          // nothing
        } else if (rightWords.length === 1) {
          first = rightWords[0];
        } else {
          // everything except last word → first
          first  = rightWords.slice(0, -1).join(' ');
          // last word → middle
          middle = rightWords.slice(-1)[0];
        }

        return { first, middle, last, suffix };
      }

      // 2) Parse & split complainant name
      const c = parseName(compFull);
      document.getElementById('edit_complainant_first_name').value = c.first;
      document.getElementById('edit_complainant_middle_name').value = c.middle;
      document.getElementById('edit_complainant_last_name').value = c.last;
      document.getElementById('edit_complainant_suffix').value = c.suffix;
      
      // 4) Complainant address
      document.getElementById('edit_complainant_address').value = tr.dataset.complainantAddress;
      
      // 5) Parse & split respondent name
      const r = parseName(respFull);
      document.getElementById('edit_complaint_respondent_first_name').value = r.first;
      document.getElementById('edit_complaint_respondent_middle_name').value = r.middle;
      document.getElementById('edit_complaint_respondent_last_name').value = r.last;
      document.getElementById('edit_complaint_respondent_suffix').value = r.suffix;

      // 6) Respondent address
      document.getElementById('edit_complaint_respondent_address').value = tr.dataset.respondentAddress;

      // 7) Other fields
      document.getElementById('edit_complaint_type').value = tr.dataset.complaintType;
      document.getElementById('edit_complaint_affidavit').value = tr.dataset.complaintAffidavit;
      document.getElementById('edit_pleading_statement').value = tr.dataset.pleadingStatement;

      // 8) Show modal
      editModal.show();
    });
  });

  const deleteSummonModal = new bootstrap.Modal(document.getElementById('deleteSummonModal'));
  const deleteSummonForm = document.getElementById('deleteSummonForm');
  const deleteSummonIdInput = document.getElementById('deleteSummonTransactionId');
  const deleteSummonLabel = document.getElementById('deleteSummonTransactionIdLabel');

  document.querySelectorAll('.delete-btn-complaint').forEach(button => {
    button.addEventListener('click', () => {
      const tid = button.getAttribute('data-id');
      deleteSummonIdInput.value = tid;
      deleteSummonLabel.textContent = tid;
    });
  });

  deleteSummonForm.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(deleteSummonForm);

    fetch('functions/delete_complaint.php', {
      method: 'POST',
      body: formData
    })
    .then(resp => resp.json())
    .then(data => {
      if (data.success) {
        deleteSummonModal.hide();
        window.location.href = window.location.pathname + '?page=adminComplaints&deleted_complaint_id=' + encodeURIComponent(formData.get('transaction_id'));
      } else {
        alert('Error: ' + (data.error || 'Failed to delete.'));
      }
    });
  });

  // 1) in the alert:
  document.querySelectorAll('.alert a.print-alert-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const tid = btn.dataset.tid;
      printComplaint(tid);
    });
  });

  // 2) in the Edit Complaint modal:
  document.getElementById('printComplaintBtn').addEventListener('click', () => {
    const tid = document.getElementById('edit_complaint_transaction_id').value;
    printComplaint(tid);
  });

});
</script>
