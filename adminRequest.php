<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];
$newTid = $_GET['transaction_id'] ?? '';

$currentRole = $_SESSION['loggedInUserRole'] ?? '';

// New Walk-In requests are considered ones that are in "Processing"
$walkInCount = (int) $conn->query(
  "SELECT COUNT(*) FROM view_request WHERE ((request_source  = 'Walk-In' AND payment_method = 'Over-the-Counter' OR payment_method IS NULL) OR (request_source  = 'Online' AND payment_method = 'Over-the-Counter' OR payment_method IS NULL)) AND document_status = 'For Verification'"
)->fetch_row()[0];

// New Online requests are considered ones that are in "For Verification"
$onlineCount = (int) $conn->query(
  "SELECT COUNT(*) FROM view_request WHERE request_source = 'Online' AND payment_method = 'GCash' AND (document_status = 'Processing' OR document_status = 'For Verification')"
)->fetch_row()[0];

$brgyPaymentDevice = (int) $conn->query(
  "SELECT COUNT(*) FROM view_request WHERE request_source = 'Online' AND payment_method = 'Brgy Payment Device' AND (document_status = 'Processing' OR document_status = 'For Verification')"
)->fetch_row()[0];

// what each role is allowed to do on the request page
$rolePermissions = [
  'Brgy Captain' => ['add','proceed','print','edit','delete','reject'],
  'Brgy Secretary' => ['add','proceed','print','edit','delete','reject'],
  'Brgy Bookkeeper' => ['add','proceed','print','edit','delete','reject'],
  'Brgy Treasurer' => [], // no default actions
  'Brgy Kagawad' => [], // view‑only
];
$perms = $rolePermissions[$currentRole] ?? [];

// FILTER SETUP
$request_type = $_GET['request_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$document_status = $_GET['document_status'] ?? '';

// Sources
$validSources = ['Walk‑In','Online','Brgy Payment Device','Official Receipt Logs'];
$processing_type = $_GET['request_source'] ?? 'Walk-In';
if (! in_array($processing_type, $validSources, true)) {
  $processing_type = 'Walk-In';
}

$whereClauses = [];
$bindTypes = '';
$bindParams = [];

// by default hide finished requests
$whereClauses[] = "r.document_status NOT IN ('Released','Rejected')";

// GLOBAL SEARCH
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
  $whereClauses[] = "(transaction_id LIKE ? OR full_name LIKE ? OR request_type LIKE ? OR payment_method LIKE ? OR 
    payment_status LIKE ? OR document_status LIKE ?)";
  $bindTypes .= str_repeat('s', 6);
  $term = "%{$search}%";
  for ($i = 0; $i < 6; $i++) {
    $bindParams[] = $term;
  }
}
if ($request_type) {
  $whereClauses[] = 'request_type = ?';
  $bindTypes .= 's';
  $bindParams[] = $request_type;
}
if ($payment_method) {
  $whereClauses[] = 'payment_method = ?';
  $bindTypes .= 's';
  $bindParams[] = $payment_method;
}
if ($payment_status) {
  $whereClauses[] = 'payment_status = ?';
  $bindTypes .= 's';
  $bindParams[] = $payment_status;
}
if ($document_status) {
  $whereClauses[] = 'document_status = ?';
  $bindTypes .= 's';
  $bindParams[] = $document_status;
} 
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

// if ($processing_type !== '') {
//   // match the same CASE expression you use in SELECT
//   $whereClauses[] = "request_source = ?";
//   $bindTypes .= 's';
//   $bindParams[] = $processing_type;
// }

// if ($processing_type !== '' && $processing_type !== 'Official Receipt Logs') {
//   $whereClauses[] = "request_source = ?";
//   $bindTypes .= 's';
//   $bindParams[] = $processing_type;
// }

if ($processing_type !== '' && $processing_type !== 'Official Receipt Logs') {
  switch ($processing_type) {
    case 'Walk-In':
      // Over-the-counter pane: show only Walk-In source & method
      $whereClauses[] = "((r.request_source = 'Walk-In' AND r.payment_method = 'Over-the-Counter' OR r.payment_method IS NULL) OR (r.request_source = 'Online' AND r.payment_method = 'Over-the-Counter' OR r.payment_method IS NULL))";
      break;
    case 'Online':
      // GCash pane: only Online source & GCash method
      $whereClauses[] = "r.request_source = 'Online' AND r.payment_method = 'GCash'";
      break;
    case 'Brgy Payment Device':
      // Brgy device pane: still Online source, but filter payment_method
      $whereClauses[] = "r.request_source = 'Online' AND r.payment_method = 'Brgy Payment Device'";
      break;
  }
}

// if (
//   $_SESSION['loggedInUserRole'] === 'Brgy Treasurer' &&
//   $processing_type === 'Walk-In'
// ) {
//   $whereClauses[] = "transaction_id NOT IN (SELECT transaction_id FROM official_receipt_records)";
// }

// for Treasurer on BOTH Walk-In and Brgy Payment Device panes, hide ones already paid

// if (
//   $_SESSION['loggedInUserRole'] === 'Brgy Treasurer' && in_array($processing_type, ['Walk-In', 'Brgy Payment Device'], true)
// ) {
//   $whereClauses[] = "transaction_id NOT IN (SELECT transaction_id FROM official_receipt_records)";
// }

if (
  $_SESSION['loggedInUserRole'] === 'Brgy Treasurer' &&
  in_array($processing_type, ['Walk-In','Online','Brgy Payment Device'], true)
) {
  $whereClauses[] = "transaction_id NOT IN (SELECT transaction_id FROM official_receipt_records)";
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$limit = 10; // records per page
$page = max((int)($_GET['request_page'] ?? 1), 1);
$offset = ($page - 1) * $limit;
  
// $countSQL = "SELECT COUNT(*) AS total FROM view_request {$whereSQL}";
$countSQL = "SELECT COUNT(*) AS total FROM view_request r LEFT JOIN user_accounts ua ON ua.account_id = r.account_id {$whereSQL}";

$countStmt = $conn->prepare($countSQL);

if (!empty($bindTypes)) {
  $refs = [];
  foreach ($bindParams as $i => $v) {
    $refs[$i] = & $bindParams[$i];
  }
  array_unshift($refs, $bindTypes);
  call_user_func_array([$countStmt, 'bind_param'], $refs);
}

$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$totalRows = $countResult['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

// If there’s a new transaction, fetch its request_type
$newType = '';
if ($newTid) {
  $q = $conn->prepare("SELECT request_type FROM view_request WHERE transaction_id = ? LIMIT 1");
  $q->bind_param('s', $newTid);
  $q->execute();
  $r = $q->get_result()->fetch_assoc();
  if ($r) {
    $newType = $r['request_type'];
  }
  $q->close();
}

// LIST + FILTERED QUERY 
$sql = "
  SELECT r.transaction_id, r.full_name, r.request_type, r.payment_method, r.payment_status, r.document_status, r.amount,
  DATE_FORMAT(r.claim_date, '%b %e, %Y') AS formatted_claim_date, DATE_FORMAT(r.created_at, '%b %e, %Y') AS formatted_date,
  ua.role, r.request_source AS processing_type
  FROM view_request r 
  LEFT JOIN user_accounts ua ON ua.account_id = r.account_id {$whereSQL} 
  ORDER BY r.created_at ASC LIMIT ? OFFSET ?";
$st = $conn->prepare($sql);

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

<title>eBarangay Mo | Requests</title>

<div class="container-fluid p-3">
  <?php if ($newTid && $newType): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($newType) ?> request <strong><?= htmlspecialchars($newTid) ?></strong> added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- <php if (isset($_GET['updated_request_id'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Document Request record <strong><= htmlspecialchars($_GET['updated_request_id']) ?></strong> updated successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <php endif; ?> -->

  <!-- <php if (isset($_GET['nochange']) && $_GET['nochange'] == 1): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      <strong>No changes detected.</strong> No fields were updated.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <php endif; ?> -->

  <?php if (! empty($_GET['rejected_id'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert" id="rejectSuccessAlert">
      Request <strong><?= htmlspecialchars($_GET['rejected_id']) ?></strong> has been <strong>rejected</strong>.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($id = ($_GET['payment_transaction_id'] ?? false)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
       Payment for <strong><?= htmlspecialchars($id) ?></strong> recorded successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div id="pageAlerts"></div>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a href="?<?= http_build_query(array_merge($_GET, ['request_source'=>'Walk-In','request_page'=>1])) ?>"
        class="nav-link <?= $processing_type==='Walk-In' ? 'active' : '' ?>">
        Over-the-Counter <span class="badge bg-secondary"><?= $walkInCount ?></span>
      </a>
    </li>
    <li class="nav-item">
      <a href="?<?= http_build_query(array_merge($_GET, ['request_source'=>'Online','request_page'=>1])) ?>"
        class="nav-link <?= $processing_type==='Online' ? 'active' : '' ?>">
        GCash <span class="badge bg-secondary"><?= $onlineCount ?></span>
      </a>
    </li>
    <li class="nav-item">
      <a href="?<?= http_build_query(array_merge($_GET, ['request_source'=>'Brgy Payment Device','request_page'=> 1])) ?>"
        class="nav-link <?= $processing_type ==='Brgy Payment Device' ? 'active' : '' ?>">
        Brgy Payment Device
        <span class="badge bg-secondary"><?= $brgyPaymentDevice ?></span>
      </a>
    </li>

    <?php if ($_SESSION['loggedInUserRole'] === 'Brgy Treasurer'): ?>
      <li class="nav-item">
        <a href="?<?= http_build_query(array_merge($_GET, ['request_source'=>'Official Receipt Logs','request_page'=>1])) ?>"
          class="nav-link <?= $processing_type==='Official Receipt Logs' ? 'active' : '' ?>">
          Official Receipt Logs
        </a>
      </li>
    <?php endif; ?>
  </ul>

  <div class="card shadow-sm p-3">
    <!-- 2a) SEARCH FORM -->
    <div class="d-flex align-items-center mb-3">
      <?php if ($processing_type !== 'Official Receipt Logs'): ?>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">filter_list</span>
            Filter
          </button>
          <div class="dropdown-menu p-3" aria-labelledby="filterDropdown" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
            <form method="get" class="mb-0" id="filterForm">
              <!-- preserve the page -->
              <input type="hidden" name="page" value="adminRequest">
              <input type="hidden" name="request_source" value="<?= htmlspecialchars($processing_type) ?>">
              <input type="hidden" name="request_page" value="1">  

              <!-- Request Type -->
              <div class="mb-2">
                <label class="form-label mb-1">Request Type</label>
                <select name="request_type" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <?= $request_type==='Barangay ID'?'selected':''?> value="Barangay ID">Barangay ID</option>
                  <option <?= $request_type==='Business Permit'?'selected':''?> value="Business Permit">Business Permit</option>
                  <option <?= $request_type==='Good Moral'?'selected':''?> value="Good Moral">Good Moral</option>
                  <option <?= $request_type==='Guardianship'?'selected':''?> value="Guardianship">Guardianship</option>
                  <option <?= $request_type==='Indigency'?'selected':''?> value="Indigency">Indigency</option>
                  <option <?= $request_type==='Residency'?'selected':''?> value="Residency">Residency</option>
                  <option <?= $request_type==='Solo Parent'?'selected':''?> value="Solo Parent">Solo Parent</option>
                </select>
              </div>

              <!-- Payment Status -->
              <div class="mb-2">
                <label class="form-label mb-1">Payment Status</label>
                <select name="payment_status" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <?= $payment_status==='Paid'?'selected':''?> value="Paid">Paid</option>
                  <option <?= $payment_status==='Unpaid'?'selected':''?> value="Unpaid">Unpaid</option>
                </select>
              </div>

              <!-- Document Status -->
              <div class="mb-2">
                <label class="form-label mb-1">Document Status</label>
                <select name="document_status" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <?= $document_status==='For Verification'?'selected':''?> value="For Verification">For Verification</option>
                  <option <?= $document_status==='Processing'?'selected':''?> value="Processing">Processing</option>
                  <option <?= $document_status==='Ready To Release'?'selected':''?> value="Ready To Release">Ready To Release</option>
                  <option <?= $document_status==='Released'?'selected':''?> value="Released">Released</option>
                  <option <?= $document_status==='Rejected'?'selected':''?> value="Rejected">Rejected</option>
                </select>
              </div>

              <!-- Date Created -->
              <div class="mb-2">
                <label class="form-label mb-1">Date Created</label>
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
                <!-- <a href="?page=adminRequest" class="btn btn-sm btn-outline-secondary me-2">Reset</a> -->
                <a href="?page=adminRequest&<?= http_build_query(['request_source' => $processing_type,'request_page' => 1]) ?>" class="btn btn-sm btn-outline-secondary me-2">
                  Reset
                </a>
                <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <!-- Add New Request button -->
      <?php if ($processing_type === 'Walk-In'): ?>
        <?php if (in_array('add', $perms, true)): ?>
          <div class="dropdown ms-3">
            <!-- <php if (in_array($currentRole, ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper'])): ?> -->
            <button class="btn btn-sm btn-success dropdown-toggle" type="button" id="addRequestDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="material-symbols-outlined" style="font-size:1rem; vertical-align:middle;">add</span>
              Add New Request
            </button>
            <!-- <php endif; ?> -->
            
            <ul class="dropdown-menu" aria-labelledby="addRequestDropdown">
              <?php foreach (['Barangay ID','Business Permit','Good Moral','Guardianship','Indigency','Residency','Solo Parent'] as $type): ?> <!-- 'Equipment Borrowing' -->
                <li>
                  <button type="button" class="dropdown-item request-trigger" data-type="<?= $type ?>"><?= $type ?></button>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($processing_type !== ''): ?>
        <div class="<?= $processing_type === 'Official Receipt Logs' ? 'align-self-center' : 'ms-3 align-self-center' ?>">
          <small class="text-muted">
            Showing <strong><?= htmlspecialchars($processing_type) ?></strong> Requests
          </small>
        </div>
      <?php endif; ?>

      <!-- Add New Request Modal -->
      <div class="modal fade" id="addRequestModal" data-bs-backdrop="static" data-bs-keyboard="false"tabindex="-1" aria-labelledby="addRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:90vw;"> <!-- modal-dialog-scrollable -->
          <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #13411F;">
              <h5 class="modal-title" id="addRequestModalLabel">New Request</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addRequestForm" method="POST" action="functions/process_new_request.php" enctype="multipart/form-data">
              <div class="modal-body">
                <input type="hidden" name="request_type" id="modalRequestType" value="">
                <input type="hidden" name="admin_account_id" value="<?= $userId ?>">
                <!-- Dynamic fields get injected here -->
                <div class="row g-3" id="dynamicFields"></div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Create Request</button>
              </div>
            </form>
            
            <!-- BARANGAY ID TEMPLATE -->
            <template id="tpl-Barangay ID">
              <div class="row gy-2">
                <!-- Section Title: Personal Details -->
                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Personal Details</h6>
                  <hr class="my-2">
                </div>
                
                <!-- Type of Transaction -->
                <div class="col-12 d-flex align-items-center">
                  <span class="form-label fw-bold mb-2 me-4">Type of Transaction:</span>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="barangay_id_transaction_type" id="txNew" value="New Application" required>
                    <label class="form-check-label mb-0" for="barangay_id_txNew">New Application</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="barangay_id_transaction_type" id="txRenewal" value="Renewal">
                    <label class="form-check-label mb-0" for="barangay_id_txRenewal">Renewal</label>
                  </div>
                </div>

                <!-- Full Name -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="barangay_id_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="barangay_id_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="barangay_id_last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>          
                  <input name="barangay_id_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Purok, Birthday & Birth Place -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Purok</label>
                  <div class="d-flex gap-2">
                    <select name="barangay_id_purok" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option>Purok 1</option>
                      <option>Purok 2</option>
                      <option>Purok 3</option>
                      <option>Purok 4</option>
                      <option>Purok 5</option>
                      <option>Purok 6</option>
                    </select>
                  </div>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Birthday</label>
                  <input name="barangay_id_dob" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Birth Place</label>
                  <div class="row">
                    <div class="col">
                      <input type="text" name="barangay_id_birth_place" class="form-control form-control-sm" placeholder="Municipality / Province" required/>
                    </div>
                  </div>
                </div>

                <!-- Civil Status, Religion, Height & Weight-->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="barangay_id_civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Religion</label>
                  <select name="barangay_id_religion" id="religionSelect" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Roman Catholic</option>
                    <option>Islam</option>
                    <option>Iglesia ni Cristo</option>
                    <option value="Other">Others</option>
                  </select>
                  <!-- This appears only when “Others” is selected -->
                  <input name="barangay_id_religion_other" id="religionOtherInput" type="text" class="form-control form-control-sm mt-2 d-none" placeholder="Please specify religion">
                </div>
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Height (ft)</label>
                  <input name="barangay_id_height" type="decimal" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Weight (kg)</label>
                  <input name="barangay_id_weight" type="decimal" min="0" class="form-control form-control-sm" required>
                </div>

                <!-- Emergency Contact Person Name & Number -->
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Emergency Contact Person</label>
                  <input name="barangay_id_emergency_contact_person" type="text" class="form-control form-control-sm" required>
                </div>     
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Emergency Contact Address</label>
                  <input name="barangay_id_emergency_contact_address" type="text" class="form-control form-control-sm" required>
                </div>    

                <!-- Formal Picture -->
                <div class="col-12 col-md-6">
                  <div class="form-check d-inline-block">
                    <input class="form-check-input" type="checkbox" id="requirePhotoCheck">
                    <label class="form-check-label fs-6" for="requirePhotoCheck"></label>
                  </div>
                  <label class="form-label fw-bold">1×1 Formal Picture</label>
                  <input id="photoInput" name="barangay_id_photo" type="file" accept="image/*" class="form-control form-control-sm" disabled>
                  <!-- this will display the existing filename -->
                  <div id="currentPhotoName" class="form-text text-muted d-none"></div>
                </div>
              </div>
            </template>

            <!-- BUSINESS PERMIT TEMPLATE -->
            <template id="tpl-Business Permit">
              <div class="row gy-2">
                <!-- Section Title -->
                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Personal Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Type of Transaction -->
                <div class="col-12 d-flex align-items-center">
                  <span class="form-label fw-bold mb-2 me-4">Type of Transaction:</span>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="business_permit_transaction_type" id="bpNew" value="New Application" required>
                    <label class="form-check-label mb-0" for="business_permit_bpNew">New Application</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="business_permit_transaction_type" id="bpRenewal" value="Renewal">
                    <label class="form-check-label mb-0" for="business_permit_bpRenewal">Renewal</label>
                  </div>
                </div>

                <!-- Full Name -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="business_permit_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="business_permit_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="business_permit_last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="business_permit_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Barangay, Purok, Age, & Civil Status -->
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Age</label>
                  <input name="business_permit_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="business_permit_civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>
                
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Purok</label>
                  <div class="d-flex gap-2">
                    <select name="business_permit_purok" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option>Purok 1</option>
                      <option>Purok 2</option>
                      <option>Purok 3</option>
                      <option>Purok 4</option>
                      <option>Purok 5</option>
                      <option>Purok 6</option>
                    </select>
                  </div>
                </div>
                <div class="col-12 col-md-3 mb-3">
                  <label class="form-label fw-bold">Barangay</label>
                  <input name="business_permit_barangay" type="text" class="form-control form-control-sm" required>
                </div>

                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Business Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Name of Business & Type of Business -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Name of Business</label>
                  <input name="business_permit_name_of_business" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3 me-1">
                  <label class="form-label fw-bold">Type of Business</label>
                  <input name="business_permit_type_of_business" type="text" class="form-control form-control-sm" required>
                </div>

                <!-- Business Address -->
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Business Full Address</label>
                  <div class="d-flex gap-2">
                    <input name="business_permit_full_address" type="text" class="form-control form-control-sm" required>
                  </div>
                </div>
              </div>
            </template>

            <!-- GOOD MORAL TEMPLATE -->
            <template id="tpl-Good Moral">
              <div class="row gy-2">
                <!-- Section Title: Personal Details -->
                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Personal Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 1: Full Name & Civil Status -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="good_moral_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="good_moral_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="good_moral_last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="good_moral_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Row 2: Civil Status, Sex & Age -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="good_moral_civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>

                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Sex</label>
                  <select name="good_moral_sex" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Male</option>
                    <option>Female</option>
                  </select>
                </div>
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Age</label>
                  <input name="good_moral_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>

                <!-- Full Address -->
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Full Address</label>
                  <input name="good_moral_subdivision" type="text" class="form-control form-control-sm" placeholder="Street / Subdivision / Lot / Block" required/>
                </div>

                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Purok</label>
                  <div class="d-flex gap-2">
                    <select name="good_moral_purok" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option>Purok 1</option>
                      <option>Purok 2</option>
                      <option>Purok 3</option>
                      <option>Purok 4</option>
                      <option>Purok 5</option>
                      <option>Purok 6</option>
                    </select>
                  </div>
                </div>

                <!-- Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose</label>
                  <textarea name="good_moral_purpose" class="form-control form-control-sm" rows="2" placeholder="State the purpose of Good Moral" required></textarea>
                </div>
              </div>
            </template> 

            <!-- GUARDIANSHIP TEMPLATE -->
            <template id="tpl-Guardianship">
              <div class="row gy-2">
                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Guardian Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 1: Guardian Full Name, Civil Status, Age -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="guardianship_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="guardianship_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="guardianship_last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="guardianship_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="guardianship_civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Age</label>
                  <input name="guardianship_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3 mb-3">
                  <label class="form-label fw-bold">Purok</label>
                  <div class="d-flex gap-2">
                    <select name="guardianship_purok" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option>Purok 1</option>
                      <option>Purok 2</option>
                      <option>Purok 3</option>
                      <option>Purok 4</option>
                      <option>Purok 5</option>
                      <option>Purok 6</option>
                    </select>
                  </div>
                </div>

                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Child's Details</h6>
                  <hr class="my-2">
                </div>

                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="child_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="child_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="child_last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="child_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Row 3: Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose</label>
                  <textarea name="guardianship_purpose" class="form-control form-control-sm" rows="2" placeholder="State the purpose of guardianship" required></textarea>
                </div>
              </div>
            </template>

            <!-- INDIGENCY TEMPLATE -->
            <template id="tpl-Indigency">
              <div class="row gy-2">
                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Personal Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 1: First, Middle, Last, Suffix -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="indigency_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="indigency_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="indigency_last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="indigency_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Row 2: Civil Status & Age -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Age</label>
                  <input name="indigency_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="indigency_civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>
                
                <!-- Row 3: Full Address -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Purok</label>
                  <div class="d-flex gap-2">
                    <select name="indigency_purok" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option>Purok 1</option>
                      <option>Purok 2</option>
                      <option>Purok 3</option>
                      <option>Purok 4</option>
                      <option>Purok 5</option>
                      <option>Purok 6</option>
                    </select>
                  </div>
                </div>

                <!-- Row 4: Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose</label>
                  <textarea name="indigency_purpose" class="form-control form-control-sm" rows="2" placeholder="State the purpose of indigency" required></textarea>
                </div>
              </div>
            </template>

            <!-- RESIDENCY TEMPLATE -->
            <template id="tpl-Residency">
              <div class="row gy-2">
                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Residency Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 1: First, Middle, Last, Suffix -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="residency_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="residency_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="residency_last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="residency_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Row 2: Civil Status & Age -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Age</label>
                  <input name="residency_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>

                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="residency_civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>
                
                <!-- Row 3: Full Address & Years Residing -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Purok</label>
                  <div class="d-flex gap-2">
                    <select name="residency_purok" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option>Purok 1</option>
                      <option>Purok 2</option>
                      <option>Purok 3</option>
                      <option>Purok 4</option>
                      <option>Purok 5</option>
                      <option>Purok 6</option>
                    </select>
                  </div>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Years Residing Here</label>
                  <input name="residency_residing_years" type="number" min="0" class="form-control form-control-sm" placeholder="e.g. 5" required>
                </div>

                <!-- Row 4: Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose</label>
                  <textarea name="residency_purpose" class="form-control form-control-sm" rows="2" placeholder="State the purpose of residency" required></textarea>
                </div>
              </div>
            </template>
            
            <!-- SOLO PARENT TEMPLATE -->
            <template id="tpl-Solo Parent">
              <div class="row gy-2">
                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Solo Parent Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 1: First, Middle, Last, Suffix -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="solo_parent_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="solo_parent_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="solo_parent_last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="solo_parent_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Row 2: Civil Status & Age -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Age</label>
                  <input name="solo_parent_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="solo_parent_civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>

                <!-- Row 3: Full Address & Years as Solo Parent -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Purok</label>
                  <div class="d-flex gap-2">
                    <select name="solo_parent_purok" class="form-select form-select-sm" required>
                      <option value="">Purok…</option>
                      <option>Purok 1</option>
                      <option>Purok 2</option>
                      <option>Purok 3</option>
                      <option>Purok 4</option>
                      <option>Purok 5</option>
                      <option>Purok 6</option>
                    </select>
                  </div>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Years as Solo Parent</label>
                  <input name="solo_parent_years_solo_parent" type="number" min="0" class="form-control form-control-sm" placeholder="e.g. 2" required>
                </div>

                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Child's Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 4: Child’s Name, Sex & Age -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="solo_parent_child_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="solo_parent_child_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="solo_parent_child_last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="solo_parent_child_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Child’s Sex</label>
                  <select name="solo_parent_child_sex" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Male</option>
                    <option>Female</option>
                  </select>
                </div>

                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Child’s Age</label>
                  <input name="solo_parent_child_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>

                <!-- Row 5: Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose</label>
                  <textarea name="solo_parent_purpose" class="form-control form-control-sm" rows="2" placeholder="State the purpose of solo parent" required></textarea>
                </div>
              </div>
            </template>

          </div>
        </div>
      </div>

      <!-- View Request Modal -->
      <div class="modal fade" id="viewRequestModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:820px; width:100%">
          <div class="modal-content" style="display:flex; flex-direction:column;">
            <div class="modal-header text-white" style="background-color:#13411F;">
              <h5 class="modal-title" id="viewRequestModalLabel">Document Request Preview</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div style="background:#ccc; padding:20px;">
              <iframe id="requestPreviewFrame" style="border:none; width:100%; height:1123px; background:#ccc;" src="" allowfullscreen></iframe>
            </div>
            <div class="modal-footer justify-content-between px-4 py-2" style="background:#f8f9fa;">
              <span class="text-muted">Preview only — use the buttons below to save or print</span>
              <div>
                <button class="btn btn-outline-success me-2" id="printRequestBtn">
                  <i class="bi bi-printer"></i> Print
                </button>
                <a id="downloadRequestPDF" class="btn btn-success" href="#" target="_blank">
                  <i class="bi bi-download"></i> Save as PDF
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Request Modal -->
      <div class="modal fade" id="editRequestModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:90vw;">
          <div class="modal-content">
            
            <!-- Header -->
            <div class="modal-header text-white" style="background-color: #13411F;">
              <h5 class="modal-title" id="editRequestModalLabel">Edit Request</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <!-- Form -->
            <form id="editRequestForm" method="POST" action="functions/process_edit_request.php" enctype="multipart/form-data">
              
              <div class="modal-body">
                <!-- carry over identifying info -->
                <input type="hidden" name="transaction_id" id="editTransactionId" value="">
                <input type="hidden" name="request_type" id="editRequestType" value="">
                <!-- your JS will inject the fields here -->
                <div class="row g-3" id="editDynamicFields"></div>
              </div>
              
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Record Payment Modal -->
      <div class="modal fade" id="recordModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="recordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <!-- Header -->
            <div class="modal-header text-white" style="background-color: #13411F;">
              <h5 class="modal-title" id="recordModalLabel">
                <i class="bi bi-receipt me-2"></i>
                Record Payment
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form id="recordForm" action="functions/process_record_payment.php" method="POST">
              <div class="modal-body">
                <input type="hidden" name="transaction_id" id="recordTransactionId">
                <input type="hidden" name="payment_method" id="recordPaymentMethodHidden">
                <input type="hidden" name="amount_paid" id="recordAmountPaidHidden">

                <div class="row g-3">
                  <!-- Row 1: Payment Method & Amount Paid -->
                  <div class="col-md-6">
                    <label for="paymentMethodRecord" class="form-label fw-bold">Payment Method</label>
                    <input type="text" class="form-control form-control-sm" id="paymentMethodRecord" disabled>
                  </div>
                  <div class="col-md-6">
                    <label for="amountPaidRecord" class="form-label fw-bold">Amount Paid</label>
                    <input type="number" step="0.01" class="form-control form-control-sm" id="amountPaidRecord" name="amount_paid" disabled>
                  </div>

                  <!-- Row 2: OR Number & Issued Date -->
                  <div class="col-md-6">
                    <label for="orNumberRecord" class="form-label fw-bold">OR Number</label>
                    <input type="text" class="form-control form-control-sm" id="orNumberRecord" name="or_number" placeholder="Enter OR Number" required>
                  </div>
                  <div class="col-md-6">
                    <label for="issuedDateRecord" class="form-label fw-bold">Issued Date</label>
                    <input type="date" class="form-control form-control-sm" id="issuedDateRecord" name="issued_date" required>
                  </div>

                  <!-- Row 3: Reference Number (GCash only) -->
                  <div class="col-md-6" id="refRow" style="display:none;">
                    <label for="referenceNumberRecord" class="form-label fw-bold">Reference Number</label>
                    <input type="text" class="form-control form-control-sm" id="referenceNumberRecord" name="reference_number" placeholder="Enter Reference Number">
                  </div>
                </div>
              </div>

              <!-- Footer -->
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm">Save Payment</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Confirm Release Modal -->
      <div class="modal fade" id="confirmReleaseModal" tabindex="-1" aria-labelledby="confirmReleaseLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title" id="confirmReleaseLabel">Confirm Release</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p id="confirmReleaseMessage">Are you sure you want to mark this record as Released?</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" id="confirmReleaseBtn" class="btn btn-success">Release</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Reject Reason Modal -->
      <div class="modal fade" id="rejectReasonModal" tabindex="-1" aria-labelledby="rejectReasonModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <form id="rejectReasonForm" method="POST" action="functions/process_reject_request.php">

              <!-- Header -->
              <div class="modal-header bg-danger text-white">
                <span class="material-symbols-outlined me-2">warning</span>
                <h5 class="modal-title flex-grow-1" id="rejectReasonModalLabel">
                  Reject Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>

              <!-- Body -->
              <div class="modal-body">
                <input type="hidden" name="transaction_id" id="rejectTransactionId">

                <!-- Personalized Description -->
                <p id="rejectDescription" class="mb-3 text-muted fst-italic">
                  <!-- JS will set: e.g. “Please state the reason below for declining Juano Dela Cruz’s request (ID 100000107).” -->
                </p>

                <!-- Reason Field -->
                <div class="mb-3">
                  <label for="rejectionReason" class="form-label fw-bold">
                    Reason for Rejection
                  </label>
                  <textarea class="form-control" name="rejection_reason" id="rejectionReason" rows="4" required placeholder="Enter reason here…"></textarea>
                </div>
              </div>

              <!-- Footer -->
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Request</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <form method="get" id="searchForm" class="d-flex ms-auto me-2">
        <!-- preserve pagination & filters -->
        <input type="hidden" name="page" value="adminRequest">

        <!-- preserve tab + reset to page 1 -->
        <input type="hidden" name="request_source" value="<?= htmlspecialchars($processing_type) ?>">
        <input type="hidden" name="request_page" value="1">

        <?php foreach (['request_type','date_from','date_to','payment_method','payment_status','document_status','processing_type'] as $f): 
            if (!empty($_GET[$f])): ?>
            <input type="hidden" name="<?= $f?>" value="<?= htmlspecialchars($_GET[$f]) ?>">
        <?php endif; endforeach; ?>

        <div class="input-group input-group-sm">
            <input name="search" id="searchInput" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
            <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" id="searchBtn">
            <span class="material-symbols-outlined" id="searchIcon">
                <?= !empty($search) ? 'close' : 'search' ?>
            </span>
            </button>
        </div>
      </form>
    </div>

    <div class="table-responsive admin-table">
      <?php if ($processing_type === 'Official Receipt Logs' && $_SESSION['loggedInUserRole'] === 'Brgy Treasurer'): ?>

        <!-- OFFICIAL RECEIPT LOGS TABLE -->
        <table class="table table-striped align-middle text-start">
          <thead class="table-light">
            <tr>
              <th>Transaction ID</th>
              <th>Request Type</th>
              <th>Full Name</th>
              <th>Payment Method</th>
              <th>OR Number</th>
              <th>Amount Paid</th>
              <th>Issued Date</th>
            </tr>
          </thead>
          <tbody>
            <?php $qr = "SELECT v.transaction_id, v.request_type, v.full_name, o.payment_method, o.or_number, o.amount_paid, o.issued_date FROM view_request AS v JOIN official_receipt_records AS o ON v.transaction_id = o.transaction_id ORDER BY o.issued_date ASC ";
              $resLogs = $conn->query($qr);
              if ($resLogs->num_rows):
                while ($r = $resLogs->fetch_assoc()):
            ?>
            <tr>
              <td><?= htmlspecialchars($r['transaction_id']) ?></td>
              <td><?= htmlspecialchars($r['request_type'])     ?></td>
              <td><?= htmlspecialchars($r['full_name'])        ?></td>
              <td><?= htmlspecialchars($r['payment_method'])   ?></td>
              <td><?= htmlspecialchars($r['or_number'])        ?></td>
              <td><?= number_format($r['amount_paid'],2) ?></td>
              <td><?= htmlspecialchars($r['issued_date'])      ?></td>
            </tr>
            <?php
                endwhile;
              else:
            ?>
            <tr><td colspan="7" class="text-center">No official receipts recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>

      <?php else: ?>

        <!-- REQUESTS TABLE -->
        <table class="table table-hover align-middle text-start">
          <thead class="table-light">
            <tr>
              <th class="text-nowrap">Transaction No.</th>
              <th class="text-nowrap">Name</th>
              <th class="text-nowrap">Request</th>
              <th class="text-nowrap">Payment Status</th>
              <th class="text-nowrap">Document Status</th>
              <th class="text-nowrap">Date Created</th>
              <?php if ($currentRole !== 'Brgy Kagawad'): ?>
                <th class="text-nowrap text-center">Action</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows): ?>
              <?php while ($row = $result->fetch_assoc()):
                $tid = htmlspecialchars($row['transaction_id']);
              ?>
                <tr data-id="<?= $tid ?>">
                  <td><?= $tid ?></td>
                  <td><?= htmlspecialchars($row['full_name']) ?></td>
                  <td><?= htmlspecialchars($row['request_type']) ?></td>
                  <td>
                    <?php 
                      $ps = $row['payment_status'] ?? '';
                      switch ($ps) {
                        case 'Paid': $c = 'bg-success'; break;
                        case 'Unpaid': $c = 'bg-danger';  break;
                        default: $c = 'bg-secondary'; 
                      }
                    ?>
                    <span class="badge <?= $c ?>">
                      <?= htmlspecialchars($ps ?: '—') ?>
                    </span>
                  </td>
                  <td>
                    <?php 
                      $ds = $row['document_status'] ?? '';
                      switch ($ds) {
                        case 'For Verification': $c = 'bg-info'; break;
                        case 'Processing': $c = 'bg-warning'; break;
                        case 'Ready to Release': $c = 'bg-primary'; break;
                        case 'Released': $c = 'bg-success'; break;
                        case 'Rejected': $c = 'bg-danger'; break;
                        default: $c = 'bg-secondary';
                      }
                    ?>
                    <span class="badge <?= $c ?>">
                      <?= htmlspecialchars($ds) ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($row['formatted_date']) ?></td>
                  <?php if (!empty($perms) || $currentRole === 'Brgy Treasurer'): ?>
                    <td class="text-center">
                      <?php
                        // $isIndigency = ($row['request_type'] === 'Indigency');
                        // $isPaid = ($row['payment_status'] === 'Paid');
                        // $isReady = ($row['document_status'] === 'Ready to Release');
                        // $isRejected = ($row['document_status'] === 'Rejected');
                        // $canPrint = in_array('print', $perms, true) && $isPaid;
                        // $canProceed = in_array('proceed', $perms, true) && $isPaid && $isReady;
                        // $canEdit = in_array('edit', $perms, true);
                        // $canReject = in_array('reject', $perms, true) && !$isPaid && ! $isReady && !$isRejected; 
                        // $canView = $isIndigency || $canPrint;

                        $isIndigency = ($row['request_type'] === 'Indigency');
                        $hasPaid = ($row['payment_status'] === 'Paid');
                        $isPaidOrFree = $hasPaid || $isIndigency;
                        $isReady = ($row['document_status'] === 'Ready to Release');
                        $isRejected = ($row['document_status'] === 'Rejected');

                        // permissions
                        $canView = in_array('print', $perms, true) && $isPaidOrFree || $isIndigency; // always allow view on Indigency
                        $canPrint = in_array('print', $perms, true) && $isPaidOrFree;
                        $canProceed = in_array('proceed', $perms, true) && $isPaidOrFree && $isReady;
                        $canEdit = in_array('edit', $perms, true);
                        $canReject = in_array('reject',  $perms, true) && ! $isPaidOrFree && ! $isReady && ! $isRejected;
                      ?>
                      <?php if (in_array($currentRole, ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper'], true)): ?>
                      <!-- Print -->
                      <!-- <button type="button" class="btn btn-sm btn-warning request-btn-print" title="Print <= $tid ?>"
                        <= $canPrint ? '' : 'disabled' ?>>
                        <span class="material-symbols-outlined" style="font-size:13px">print</span>
                      </button> -->

                      <!-- View -->
                      <!-- <button type="button" class="btn btn-sm btn-warning request-btn-view" data-id="<= htmlspecialchars($row['transaction_id']) ?>" title="View <= $tid ?>"
                        <= $canView ? '' : 'disabled' ?>>
                        <span class="material-symbols-outlined" style="font-size:13px">visibility</span>
                      </button> -->

                      <button type="button" class="btn btn-sm btn-warning request-btn-view" data-id="<?= $tid ?>" title="View <?= $tid ?>"
                        <?= $canView ? '' : 'disabled' ?>>
                        <span class="material-symbols-outlined" style="font-size:13px">visibility</span>
                      </button>

                      <!-- Edit -->
                      <button type="button" class="btn btn-sm btn-primary request-btn-edit" title="Edit <?= $tid ?>"
                        <?= $canEdit ? '' : 'disabled' ?>>
                        <span class="material-symbols-outlined" style="font-size:13px">stylus</span>
                      </button>

                      <!-- Proceed -->
                      <button type="button" class="btn btn-sm btn-success request-btn-release" title="Release <?= $tid ?>"
                        <?= $canProceed ? '' : 'disabled' ?>>
                        <span class="material-symbols-outlined" style="font-size:13px">check</span>
                      </button>

                      <!-- Reject -->
                      <button type="button" class="btn btn-sm btn-danger request-btn-reject" title="Reject <?= $tid ?>"
                        <?= $canReject ? '' : 'disabled' ?>>
                        <span class="material-symbols-outlined" style="font-size:13px">close</span>
                      </button>

                      <!-- <php if ($canDelete): ?> -->
                        <!-- <button type="button" class="btn btn-sm btn-danger request-delete-btn" title="Delete <= $tid ?>">
                          <span class="material-symbols-outlined" style="font-size: 13px;">
                            delete
                          </span>
                        </button> -->
                      <!-- <php endif; ?> -->

                      <?php elseif ($currentRole === 'Brgy Treasurer'): ?>
                        <button type="button" class="btn btn-sm btn-info request-record-btn" data-id="<?= htmlspecialchars($row['transaction_id']) ?>" data-payment-method="<?= htmlspecialchars($row['payment_method']) ?>" data-amount-paid="<?= htmlspecialchars($row['amount'] ?? '') ?>">
                          <span class="material-symbols-outlined" style="font-size: 13px;">
                            receipt
                          </span>
                        </button>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center">No records found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        
      <?php endif; ?>

      <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
          <ul class="pagination justify-content-center pagination-sm">
            <!-- Prev Button -->
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['request_page' => $page - 1])) ?>">Previous</a>
            </li>

            <?php
            $range = 2;
            $dots = false;

            for ($i = 1; $i <= $totalPages; $i++) {
              if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                $active = $i == $page ? 'active' : '';
                echo "<li class='page-item {$active}'>
                        <a class='page-link' href='?" . http_build_query(array_merge($_GET, ['request_page' => $i])) . "'>$i</a>
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
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['request_page' => $page + 1])) ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$st->close();
$conn->close();
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  let originalData = {};
  // Search 
  const searchForm = document.getElementById('searchForm');
  const searchInput = document.getElementById('searchInput');
  const searchBtn = document.getElementById('searchBtn');
  const hasSearch = <?= json_encode(!empty($search)) ?>;

  searchBtn.addEventListener('click', () => {
    if (hasSearch) searchInput.value = '';
    searchForm.submit();
  });

  const dynamicFields = document.getElementById('dynamicFields');

  document.querySelectorAll('.request-trigger').forEach(btn => {
    btn.addEventListener('click', e => {
      const type = e.currentTarget.dataset.type;
      document.getElementById('addRequestModalLabel').textContent = type + ' Request';
      document.getElementById('modalRequestType').value = type;

      // inject the template
      dynamicFields.innerHTML = '';
      const tpl = document.getElementById('tpl-' + type);
      if (tpl) {
        dynamicFields.appendChild(tpl.content.cloneNode(true));
      } else {
        dynamicFields.innerHTML = '<div class="col-12 text-muted">No extra fields required.</div>';
      }

      const sel = dynamicFields.querySelector('#religionSelect');
      const other = dynamicFields.querySelector('#religionOtherInput');
      if (sel && other) {
        sel.addEventListener('change', () => {
          other.classList.toggle('d-none', sel.value !== 'Other');
        });
      }

      const chk = dynamicFields.querySelector('#requirePhotoCheck');
      const photo = dynamicFields.querySelector('#photoInput');
      if (chk && photo) {
        chk.addEventListener('change', () => {
          photo.disabled = !chk.checked;
        });
      }

      new bootstrap.Modal(document.getElementById('addRequestModal')).show();
    });
  });

  // document.addEventListener('click', (e) => {
  //   const btn = e.target.closest('.request-btn-print');
  //   if (!btn) return;

  //   // grab the transaction ID from the row
  //   const row = btn.closest('tr');
  //   const tid = row.dataset.id;  // you already set data-id="<=? $tid ?>"

  //   // open the certificate page in a new tab (auto-prints)
  //   window.open(`functions/print_certificate.php?transaction_id=${encodeURIComponent(tid)}`, '_blank');
  // });

  const recordModal = new bootstrap.Modal('#recordModal');
  const tidInput = document.getElementById('recordTransactionId');
  const pmInput = document.getElementById('paymentMethodRecord');
  const pmHidden = document.getElementById('recordPaymentMethodHidden');
  const refRow = document.getElementById('refRow');
  const refInput = document.getElementById('referenceNumberRecord');
  const orInput = document.getElementById('orNumberRecord');
  const issuedInput = document.getElementById('issuedDateRecord');
  const amtInput = document.getElementById('amountPaidRecord');
  const amtHidden = document.getElementById('recordAmountPaidHidden');

  document.querySelectorAll('.request-record-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tid = btn.dataset.id;
      const pm = btn.dataset.paymentMethod || '';
      const ref = btn.dataset.referenceNumber || '';
      const or = btn.dataset.orNumber || '';
      const amt = btn.dataset.amountPaid || '';

      // always start fresh
      tidInput.value = tid;
      pmInput.value = pm;
      pmHidden.value = pm; // ensure it submits

      amtInput.value = amt; // clear old amount
      amtHidden.value = amt;

      orInput.value = or;
      issuedInput.value = '';

      // toggle GCash reference field
      if (pm === 'GCash') {
        refRow.style.display = 'block';
        refInput.required = true;
        refInput.value = ref;

      } else {
        refRow.style.display = 'none';
        refInput.required = false;
        refInput.value = '';
      }

      recordModal.show();
    });
  });

  const confirmModalEl = document.getElementById('confirmReleaseModal');
  const confirmModal = new bootstrap.Modal(confirmModalEl);
  const confirmBtn = document.getElementById('confirmReleaseBtn');
  let pendingTid, pendingType, pendingRow;

  document.querySelectorAll('.request-btn-release').forEach(btn => {
    btn.addEventListener('click', () => {
      // stash context
      pendingRow = btn.closest('tr');
      pendingTid = pendingRow.dataset.id;
      pendingType = pendingRow.querySelector('td:nth-child(3)').textContent.trim();

      if (!pendingTid || !pendingType) {
        // fallback, though this should never happen
        return alert('Missing data');
      }

      // update modal text if you want to personalize:
      document.getElementById('confirmReleaseMessage').textContent = `Are you sure you want to mark ${pendingTid} as Released?`;
      confirmModal.show();
    });
  });

  // Grab our new alerts container
  const alertsDiv = document.getElementById('pageAlerts');

  // When the user clicks “Yes, Release” in the modal:
  confirmBtn.addEventListener('click', () => {
    confirmBtn.disabled = true;

    fetch('functions/update_document_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `transaction_id=${encodeURIComponent(pendingTid)}&request_type=${encodeURIComponent(pendingType)}`
    })
    .then(res => res.text())
    .then(response => {
      confirmBtn.disabled = false;
      confirmModal.hide();

      if (response === 'success') {
        // update the badge inline
        const badge = pendingRow.querySelector('td:nth-child(5) .badge');
        badge?.classList.replace(badge.classList.item(1), 'bg-success');
        badge.textContent = 'Released';

        // disable the button
        pendingRow.querySelector('.request-btn-release').disabled = true;

        // show the alert
        const html = `
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            Record <strong>${pendingTid}</strong> has been marked as <strong>Released</strong>.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        `;
        alertsDiv.insertAdjacentHTML('beforeend', html);

        // **remove this row from the table**
        pendingRow.remove();

      } else {
        alert('Failed to update status: ' + response);
      }
    })
    .catch(err => {
      confirmBtn.disabled = false;
      confirmModal.hide();
      console.error(err);
      alert('An error occurred while updating status.');
    });
  });

  const rejectModalEl = document.getElementById('rejectReasonModal');
  const rejectModal = new bootstrap.Modal(rejectModalEl);

  document.querySelectorAll('.request-btn-reject').forEach(btn => {
    btn.addEventListener('click', () => {
      const row = btn.closest('tr');
      const tid = row.dataset.id;
      const name = row.querySelector('td:nth-child(2)').textContent.trim();

      document.getElementById('rejectTransactionId').value = tid;
      document.getElementById('rejectDescription').textContent = `Action cannot be undone. Please state the reason below for declining ${name}’s request (${tid}).`;

      rejectModal.show();
    });
  });

  const editModalEl = document.getElementById('editRequestModal');
  const editModal = new bootstrap.Modal(editModalEl);
  const editFieldsCont = document.getElementById('editDynamicFields');
  const editTidInput = document.getElementById('editTransactionId');
  const editTypeInput = document.getElementById('editRequestType');
  const editTitle = document.getElementById('editRequestModalLabel');
  
  document.querySelectorAll('.request-btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      // 1) pull transaction ID + request type from the row
      const row = btn.closest('tr');
      const tid = row.dataset.id;
      const type = row.querySelector('td:nth-child(3)').textContent.trim();
      
      // 2) set hidden inputs
      editTidInput.value  = tid;
      editTypeInput.value = type;
      
      // 3) update modal title
      editTitle.textContent = `Edit ${type}`;
      
      // 4) inject the template for this type
      editFieldsCont.innerHTML = '';
      const tpl = document.getElementById('tpl-' + type);
      if (tpl) {
        editFieldsCont.appendChild(tpl.content.cloneNode(true));
      } else {
        editFieldsCont.innerHTML = '<div class="col-12 text-muted">No editable fields for this request type.</div>';
      }
      
      // 5) (optional) prefill any fields you already have in the table
      //    e.g. full name, date, etc:
      const inputs = editFieldsCont.querySelectorAll('input, select, textarea');
      inputs.forEach(input => {
        const name = input.name;
        // a crude example: if your <td>s have data-* attributes you could do:
        // input.value = row.dataset[name] || '';
      });
      
      // 6) show the modal
      editModal.show();
    });
  });

  // --- View Preview for Requests ---
  const viewReqModalEl = document.getElementById('viewRequestModal');
  const viewReqModal = new bootstrap.Modal(viewReqModalEl);
  const previewReqFrame = document.getElementById('requestPreviewFrame');
  const printReqBtn = document.getElementById('printRequestBtn');
  const downloadReqPDF = document.getElementById('downloadRequestPDF');

  document.querySelectorAll('.request-btn-view').forEach(btn => {
    btn.addEventListener('click', () => {
      // const tid = btn.closest('tr').dataset.id;
      const tid = btn.getAttribute('data-id');

      // the endpoint that renders the printable certificate
      const baseUrl = `functions/print_certificate.php?transaction_id=${encodeURIComponent(tid)}`;

      // set the iframe preview
      previewReqFrame.src = baseUrl;

      // set Download link
      // downloadReqPDF.href = baseUrl + '&download=1';

      downloadReqPDF.onclick = () => {
        downloadReqPDF.href = baseUrl + '&download=1';

        // Optional: Add delay so it starts download before alert
        setTimeout(() => {
          alert(`Saved as PDF successfully for ${tid}`);
          location.reload(); // Refresh the page
        }, 300);
      };

      // set Print button
      // printReqBtn.onclick = () => {
      //   window.open(baseUrl + '&print=1', '_blank');
      // };
      
      printReqBtn.onclick = () => {
      window.open(baseUrl + '&print=1');

      // Optional: Add delay so it opens before alert
      setTimeout(() => {
        alert(`Printed successfully for ${tid}`);
        location.reload(); // Refresh the page after alert
      }, 300);
    };

      viewReqModal.show();
    });
  });

  // clear iframe on close
  viewReqModalEl.addEventListener('hidden.bs.modal', () => {
    previewReqFrame.src = '';
  });
});
</script>
