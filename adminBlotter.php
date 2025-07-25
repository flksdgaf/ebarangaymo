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
  'page' => 'adminComplaints',
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
  $bindParams[] = $date_from;
  $bindParams[] = $date_to;
} elseif ($date_from) {
  $whereClauses[] = 'DATE(incident_date) >= ?';
  $bindTypes .= 's';
  $bindParams[] = $date_from;
} elseif ($date_to) {
  $whereClauses[] = 'DATE(incident_date) <= ?';
  $bindTypes .= 's';
  $bindParams[] = $date_to;
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
$sql = "SELECT transaction_id, client_name, client_address, respondent_name, respondent_address, incident_type, incident_date, incident_time, incident_place, incident_description, DATE_FORMAT(incident_date,'%b %e, %Y') AS formatted_date, DATE_FORMAT(incident_time,'%l:%i %p') AS formatted_time FROM blotter_records {$whereSQL} ORDER BY transaction_id ASC LIMIT ? OFFSET ?";
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
  <?php if (isset($_GET['new_blotter_id'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      New blotter record <strong><?= htmlspecialchars($_GET['new_blotter_id']) ?></strong> added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['blotter_updated'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    Blotter record <strong><?= htmlspecialchars($_GET['blotter_updated']) ?></strong> updated successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>

  <?php elseif (isset($_GET['blotter_nochange'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      No changes detected, nothing to update.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['blotter_deleted'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      Blotter record <strong><?= htmlspecialchars($_GET['blotter_deleted']) ?></strong> was permanently deleted.
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
          <form method="get" action="?page=adminComplaints" id="blotterfilterForm" class="mb-0">
            <!-- preserve search -->
            <input type="hidden" name="page" value="adminComplaints">
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
              <a href="?page=adminComplaints&blotter_page=1" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
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

      <form method="get" action="?page=adminComplaints" id="searchFormBlotter" class="d-flex ms-auto me-2">
        <input type="hidden" name="page" value="adminComplaints">
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
                    <h6 class="fw-bold fs-5" style="color: #13411F;">Client Details</h6>
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
                       <input class="form-check-input" type="checkbox" id="hasRespondentCheck" name="has_respondent" checked>
                       <label class="form-check-label fs-6" for="hasRespondentCheck"></label>
                     </div>
                     <h6 class="fw-bold mb-0 fs-5" style="color: #13411F;">Respondent Details</h6>
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
                    <h6 class="fw-bold fs-5" style="color: #13411F;">Complaint Details</h6>
                    <hr class="my-2">
                  </div>
                  <div class="col-12 col-md-6 me-1">
                    <label class="form-label fw-bold">Complaint / Incident Type</label>
                    <input name="incident_type" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Incident Place</label>
                    <input name="incident_place" type="text" class="form-control form-control-sm" required>
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

      <!-- Edit Blotter Modal -->
      <div class="modal fade" id="editBlotterModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editBlotterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:90vw;">
          <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #13411F;">
              <h5 class="modal-title" id="editBlotterModalLabel">Edit Blotter Record</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editBlotterForm" method="POST" action="functions/process_edit_blotter.php">
              <div class="modal-body">
                <input type="hidden" name="transaction_id" id="edit_transaction_id" value="">
                <input type="hidden" name="account_id" value="<?= $userId ?>">

                <div class="row gy-2">
                  <!-- Client Details -->
                  <div class="col-12">
                    <h6 class="fw-bold fs-5" style="color: #13411F;">Client Details</h6>
                    <hr class="my-2">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">First Name</label>
                    <input name="client_first_name" id="edit_client_first_name" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                    <input name="client_middle_name" id="edit_client_middle_name" type="text" class="form-control form-control-sm">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Last Name</label>
                    <input name="client_last_name" id="edit_client_last_name" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                    <input name="client_suffix" id="edit_client_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Client Address</label>
                    <input name="client_address" id="edit_client_address" type="text" class="form-control form-control-sm" required>
                  </div>

                  <!-- Respondent Details Header + Toggle -->
                  <div class="col-12 mt-3 d-flex align-items-center">
                    <div class="form-check d-inline-block">
                      <input class="form-check-input" type="checkbox" id="edit_hasRespondentCheck" name="has_respondent" checked>
                      <label class="form-check-label" for="edit_hasRespondentCheck"></label>
                    </div>
                    <h6 class="fw-bold mb-0 fs-5" style="color: #13411F;">Respondent Details</h6>
                  </div>
                  <div class="col-12"><hr class="my-2"></div>

                  <!-- Respondent Fields -->
                  <div id="edit_respondentSection" class="row gy-2 col-12">
                    <div class="col-12 col-md-3">
                      <label class="form-label fw-bold">First Name</label>
                      <input name="respondent_first_name" id="edit_respondent_first_name" type="text" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12 col-md-3">
                      <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                      <input name="respondent_middle_name" id="edit_respondent_middle_name" type="text" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-3">
                      <label class="form-label fw-bold">Last Name</label>
                      <input name="respondent_last_name" id="edit_respondent_last_name" type="text" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12 col-md-3">
                      <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                      <input name="respondent_suffix" id="edit_respondent_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                      
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label fw-bold">Respondent Address</label>
                      <input name="respondent_address" id="edit_respondent_address" type="text" class="form-control form-control-sm" required>
                    </div>
                  </div>

                  <!-- Complaint Details -->
                  <div class="col-12 mt-3">
                    <h6 class="fw-bold fs-5" style="color: #13411F;">Complaint Details</h6>
                    <hr class="my-2">
                  </div>
                  <div class="col-12 col-md-6 me-1">
                    <label class="form-label fw-bold">Complaint / Incident Type</label>
                    <input name="incident_type" id="edit_incident_type" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Incident Place</label>
                    <input name="incident_place" id="edit_incident_place" type="text" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Date Occurred</label>
                    <input name="incident_date" id="edit_incident_date" type="date" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Time Occurred</label>
                    <input name="incident_time" id="edit_incident_time" type="time" class="form-control form-control-sm" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label fw-bold">Incident Description</label>
                    <textarea name="incident_description" id="edit_incident_description" class="form-control form-control-sm" rows="3" required></textarea>
                  </div>
                </div>
              </div>

              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- View Blotter Modal -->
      <div class="modal fade" id="viewBlotterModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="viewBlotterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 820px; width: 100%;">
          <div class="modal-content" style="height: auto; display: flex; flex-direction: column;">
            <!-- Modal Header -->
            <div class="modal-header text-white" style="background-color: #13411F;">
              <h5 class="modal-title" id="viewBlotterModalLabel">Blotter Record Preview</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Modal Body with Preview -->
            <div style="background-color: #ccc; padding: 20px;">
              <iframe id="blotterPreviewFrame" style="border: none; width: 100%; height: 1123px; background: #ccc;" src="" allowfullscreen></iframe>
            </div>

            <!-- Action Buttons -->
            <div class="modal-footer justify-content-between px-4 py-2" style="background-color: #f8f9fa;">
              <span class="text-muted">Preview only — use the buttons below to save or print</span>
              <div>
                <button class="btn btn-outline-success me-2" id="printBlotterBtn">
                  <i class="bi bi-printer"></i> Print
                </button>
                <a id="downloadPDFLink" class="btn btn-success" href="#" target="_blank">
                  <i class="bi bi-download"></i> Save as PDF
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div class="modal fade" id="deleteBlotterModal" tabindex="-1" aria-labelledby="deleteBlotterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <form id="deleteBlotterForm">
              <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteBlotterModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                Are you sure you want to permanently delete blotter record <strong id="deleteTransactionIdLabel"></strong>?
                <input type="hidden" name="transaction_id" id="deleteTransactionId">
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
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
              <tr 
                data-id="<?= $tid ?>"
                data-client-address="<?= htmlspecialchars($row['client_address'], ENT_QUOTES) ?>"
                data-respondent-address="<?= htmlspecialchars($row['respondent_name'] ? $row['respondent_address'] : '', ENT_QUOTES) ?>"
                data-incident-date="<?= $row['incident_date'] ?>"
                data-incident-time="<?= $row['incident_time'] ?>"
                data-incident-place="<?= htmlspecialchars($row['incident_place'], ENT_QUOTES) ?>"
                data-incident-desc="<?= htmlspecialchars($row['incident_description'], ENT_QUOTES) ?>"
              >
                <td><?= $tid ?></td>
                <td><?= htmlspecialchars($row['client_name']) ?></td>
                <td><?= htmlspecialchars($row['respondent_name'] ?: '—') ?></td>
                <td><?= htmlspecialchars($row['incident_type']) ?></td>
                <td>
                  <?= htmlspecialchars($row['formatted_date']) ?>
                  <?= htmlspecialchars($row['formatted_time']) ?>
                </td>
                <td class="text-nowrap text-center">
                  <!-- Print -->
                  <!-- <button class="btn btn-sm btn-primary blotter-print-btn" data-id="<?= $tid ?>">
                    <span class="material-symbols-outlined" style="font-size: 12px;">
                      print
                    </span>
                  </button> -->

                  <button class="btn btn-sm btn-warning blotter-view-btn" data-id="<?= $tid ?>">
                    <span class="material-symbols-outlined" style="font-size: 12px;">
                      visibility
                    </span>
                  </button>
                  
                  <!-- Edit -->
                  <button class="btn btn-sm btn-primary blotter-edit-btn">
                    <span class="material-symbols-outlined" style="font-size: 12px;">
                      stylus
                    </span>
                  </button>

                  <!-- Delete -->
                  <button class="btn btn-sm btn-danger blotter-delete-btn" data-id="<?= $tid ?>" data-bs-toggle="modal" data-bs-target="#deleteBlotterModal">
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
    blotter_toggleRespondent();
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

  // document.querySelectorAll('.blotter-print-btn').forEach(btn => {
  //   btn.addEventListener('click', () => {
  //     const tid = btn.getAttribute('data-id');
  //     window.open('functions/print_blotter.php?transaction_id=' + encodeURIComponent(tid),'_blank');
  //   });
  // });

  // 1) Grab Edit modal + form + fields
  const editModalEl = document.getElementById('editBlotterModal');
  const editModal = new bootstrap.Modal(editModalEl);
  const editRespCheck = document.getElementById('edit_hasRespondentCheck');
  const editRespSection = document.getElementById('edit_respondentSection');

  // 2) Respondent toggle logic (same as add)
  function edit_toggleRespondent() {
    const show = editRespCheck.checked;
    editRespSection.style.display = show ? '' : 'none';
    editRespSection.querySelectorAll('input, textarea').forEach(el => el.disabled = !show);
  }
  editRespCheck.addEventListener('change', edit_toggleRespondent);
  edit_toggleRespondent();

  // 3) Wire up all .edit-btn clicks
  document.querySelectorAll('.blotter-edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      const tid = tr.getAttribute('data-id');
      // const status = tr.dataset.status;

      // inject transaction_id
      document.getElementById('edit_transaction_id').value = tid;
      // document.getElementById('edit_blotter_status').value = status;

      // 2) Read the two name‑cells:
      const clientFull = tr.children[1].textContent.trim(); // e.g. "Doe Jr., John A."
      const respondentFull = tr.children[2].textContent.trim(); // e.g. "—" or "Smith, Jane"

      // Helper to parse "Last[ Suffix], First[ Middle]" into parts
      function parseName(full) {
        // split into [ leftOfComma, rightOfComma ]
        const [ left = '', right = '' ] = full.split(/\s*,\s*/);

        // --- LAST & SUFFIX ---
        // left could be “Britos” or “Britos Jr.” etc
        const leftWords = left.trim().split(/\s+/);
        const last   = leftWords[0] || '';
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

      // 3) Populate client inputs
      const c = parseName(clientFull);
      document.getElementById('edit_client_first_name').value = c.first;
      document.getElementById('edit_client_middle_name').value = c.middle;
      document.getElementById('edit_client_last_name').value = c.last;
      document.getElementById('edit_client_suffix').value = c.suffix;

      // 4) Handle respondent checkbox + fields
      if (respondentFull === '—' || respondentFull === '') {
        // no respondent
        editRespCheck.checked = false;
        edit_toggleRespondent();

        // clear any existing values
        [
          'first_name',
          'middle_name',
          'last_name',
          'suffix',
          'address'
        ].forEach(key => {
          document.getElementById(`edit_respondent_${key}`).value = '';
        });

      } else {
        editRespCheck.checked = true;
        edit_toggleRespondent();
        const r = parseName(respondentFull);
        document.getElementById('edit_respondent_first_name').value = r.first;
        document.getElementById('edit_respondent_middle_name').value = r.middle;
        document.getElementById('edit_respondent_last_name').value = r.last;
        document.getElementById('edit_respondent_suffix').value = r.suffix;
      }

      // 5) For the other complaint fields—if you have them in hidden <td>s or data-attributes—
      // you could do exactly the same: grab their textContent and assign to
      // document.getElementById('edit_incident_type').value, etc. 
      document.getElementById('edit_incident_type').value = tr.children[3].textContent.trim();
      document.getElementById('edit_incident_date').value = tr.getAttribute('data-incident-date');
      document.getElementById('edit_incident_time').value = tr.getAttribute('data-incident-time');
      document.getElementById('edit_incident_place').value = tr.getAttribute('data-incident-place');
      document.getElementById('edit_incident_description').value = tr.getAttribute('data-incident-desc');

      // 5b) Fill in addresses & complaint details:
      document.getElementById('edit_client_address').value = tr.dataset.clientAddress;
      if (editRespCheck.checked) {
        document.getElementById('edit_respondent_address').value = tr.dataset.respondentAddress;
      }

      document.getElementById('edit_incident_date').value = tr.dataset.incidentDate;
      document.getElementById('edit_incident_time').value = tr.dataset.incidentTime;
      document.getElementById('edit_incident_place').value = tr.dataset.incidentPlace;
      document.getElementById('edit_incident_description').value = tr.dataset.incidentDesc;

      // 6) Finally show modal
      editModal.show();
    });
  });

  const deleteModal = new bootstrap.Modal(document.getElementById('deleteBlotterModal'));
  const deleteForm = document.getElementById('deleteBlotterForm');
  const deleteIdInput = document.getElementById('deleteTransactionId');
  const deleteLabel = document.getElementById('deleteTransactionIdLabel');

  document.querySelectorAll('.blotter-delete-btn').forEach(button => {
    button.addEventListener('click', () => {
      const tid = button.getAttribute('data-id');
      deleteIdInput.value = tid;
      deleteLabel.textContent = tid;
    });
  });

  deleteForm.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(deleteForm);

    fetch('functions/delete_blotter.php', {
      method: 'POST',
      body: formData
    })
    .then(resp => resp.json())
    .then(data => {
      if (data.success) {
      deleteModal.hide();
      window.location.href = window.location.pathname + '?page=adminComplaints&blotter_deleted=' + encodeURIComponent(formData.get('transaction_id'));
    } else {
        alert('Error: ' + (data.error || 'Failed to delete.'));
      }
    });
  });

  const viewModalEl = document.getElementById('viewBlotterModal');
  const viewModal = new bootstrap.Modal(viewModalEl);
  const previewFrame = document.getElementById('blotterPreviewFrame');

  document.querySelectorAll('.blotter-view-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tid = btn.getAttribute('data-id');
      const baseUrl = 'functions/print_blotter.php?transaction_id=' + encodeURIComponent(tid);

      // Set iframe preview (preview mode)
      previewFrame.src = baseUrl;

      // Update the Save as PDF button
      document.getElementById('downloadPDFLink').href = baseUrl + '&download=1';

      // Update the Print button to open a clean print tab
      document.getElementById('printBlotterBtn').onclick = () => {
        window.open(baseUrl + '&print=1', '_blank');
      };

      viewModal.show();
    });
  });


  // Clear iframe when closing, to stop PDF load in background
  viewModalEl.addEventListener('hidden.bs.modal', () => {
    previewFrame.src = '';
  });
});
</script>
