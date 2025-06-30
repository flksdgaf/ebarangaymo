<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];
$newTid = $_GET['transaction_id'] ?? '';

// FILTER SETUP
$request_type = $_GET['request_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$document_status = $_GET['document_status'] ?? '';

$whereClauses = [];
$bindTypes = '';
$bindParams = [];

// GLOBAL SEARCH
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
  $whereClauses[] = "(transaction_id LIKE ? OR full_name LIKE ? OR request_type LIKE ? OR payment_method LIKE ? OR payment_status LIKE ? OR document_status LIKE ?)";
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

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$limit = 10; // records per page
$page = isset($_GET['page_num']) ? max((int)$_GET['page_num'], 1) : 1;
$offset = ($page - 1) * $limit;
  
$countSQL = "SELECT COUNT(*) AS total FROM view_general_requests {$whereSQL}";
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
$countResult = $countStmt->get_result()->fetch_assoc();
$totalRows = $countResult['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

// If there’s a new transaction, fetch its request_type
$newType = '';
if ($newTid) {
  $q = $conn->prepare("SELECT request_type FROM view_general_requests WHERE transaction_id = ? LIMIT 1");
  $q->bind_param('s', $newTid);
  $q->execute();
  $r = $q->get_result()->fetch_assoc();
  if ($r) {
    $newType = $r['request_type'];
  }
  $q->close();
}

// LIST + FILTERED QUERY 
$sql = "SELECT transaction_id, full_name, request_type, payment_method, payment_status, document_status, DATE_FORMAT(created_at, '%b %e, %Y') AS formatted_date FROM view_general_requests {$whereSQL} ORDER BY created_at ASC LIMIT ? OFFSET ?";
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

<div class="container-fluid p-3">
  <?php if ($newTid && $newType): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($newType) ?> request <strong><?= htmlspecialchars($newTid) ?></strong> added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <strong><?= htmlspecialchars($_GET['deleted']) ?></strong> has been permanently deleted.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm p-3">
    <!-- 2a) SEARCH FORM -->
    <div class="d-flex align-items-center mb-3">
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">filter_list</span>
          Filter
        </button>
        <div class="dropdown-menu p-3" aria-labelledby="filterDropdown" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
          <form method="get" class="mb-0" id="filterForm">
            <!-- preserve the page -->
            <input type="hidden" name="page" value="superAdminRequest">

            <!-- Request Type -->
            <div class="mb-2">
              <label class="form-label mb-1">Request Type</label>
              <select name="request_type" class="form-select form-select-sm" style="font-size:.75rem;">
                <option value="">All</option>
                <option <?= $request_type==='Barangay ID'?'selected':''?> value="Barangay ID">Barangay ID</option>
                <option <?= $request_type==='Business Permit'?'selected':''?> value="Business Permit">Business Permit</option>
                <!-- <option <?= $request_type==='Equipment Borrowing'?'selected':''?> value="Equipment Borrowing">Equipment Borrowing</option> -->
                <option <?= $request_type==='Good Moral'?'selected':''?> value="Good Moral">Good Moral</option>
                <option <?= $request_type==='Guardianship'?'selected':''?> value="Guardianship">Guardianship</option>
                <option <?= $request_type==='Indigency'?'selected':''?> value="Indigency">Indigency</option>
                <option <?= $request_type==='Residency'?'selected':''?> value="Residency">Residency</option>
                <option <?= $request_type==='Solo Parent'?'selected':''?> value="Solo Parent">Solo Parent</option>
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

            <!-- Payment Method -->
            <div class="mb-2">
              <label class="form-label mb-1">Payment Method</label>
              <select name="payment_method" class="form-select form-select-sm" style="font-size:.75rem;">
                <option value="">All</option>
                <option <?= $payment_method==='GCash'?'selected':''?> value="GCash">GCash</option>
                <option <?= $payment_method==='Brgy Payment Device'?'selected':''?> value="Brgy Payment Device">Brgy Payment Device</option>
                <option <?= $payment_method==='Over-the-Counter'?'selected':''?> value="Over-the-Counter">Over-the-Counter</option>
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

            <div class="d-flex">
              <a href="?page=superAdminRequest" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
              <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Add New Request button -->
      <div class="dropdown ms-3">
        <button class="btn btn-sm btn-success dropdown-toggle" type="button" id="addRequestDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="material-symbols-outlined" style="font-size:1rem; vertical-align:middle;">add</span>
          <i class="bi bi-plus-lg me-1"></i> Add New Request
        </button>
        <ul class="dropdown-menu" aria-labelledby="addRequestDropdown">
          <?php foreach (['Barangay ID','Business Permit','Good Moral','Guardianship','Indigency','Residency','Solo Parent'] as $type): ?> <!-- 'Equipment Borrowing' -->
            <li>
              <button type="button" class="dropdown-item request-trigger" data-type="<?= $type ?>"><?= $type ?></button>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

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
                  <h6 class="fw-bold">Personal Details</h6>
                  <hr class="my-2">
                </div>
                
                <!-- Type of Transaction -->
                <div class="col-12 d-flex align-items-center">
                  <span class="form-label fw-bold mb-0 me-4">Type of Transaction:</span>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="transaction_type" id="txNew" value="New Application" required>
                    <label class="form-check-label mb-0" for="txNew">New Application</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="transaction_type" id="txRenewal" value="Renewal">
                    <label class="form-check-label mb-0" for="txRenewal">Renewal</label>
                  </div>
                </div>

                <!-- Full Name -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>          
                  <input name="suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Purok, Birthday & Birth Place -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Purok</label>
                  <div class="d-flex gap-2">
                    <select name="purok" class="form-select form-select-sm" required>
                      <option value="">Select</option>
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
                  <input name="dob" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Birth Place</label>
                  <div class="row">
                    <div class="col">
                      <input type="text" name="birth_municipality" class="form-control form-control-sm" placeholder="Locality / Municipality" required/>
                    </div>
                    <div class="col">
                      <input type="text" name="birth_province" class="form-control form-control-sm" placeholder="Province" required/>
                    </div>
                  </div>
                </div>

                <!-- Civil Status, Religion, Height & Weight-->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="civil_status" class="form-select form-select-sm" required>
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
                  <select name="religion" id="religionSelect" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Roman Catholic</option>
                    <option>Islam</option>
                    <option>Iglesia ni Cristo</option>
                    <option value="Other">Others</option>
                  </select>
                  <!-- This appears only when “Others” is selected -->
                  <input name="religion_other" id="religionOtherInput" type="text" class="form-control form-control-sm mt-2 d-none" placeholder="Please specify religion">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Height (ft)</label>
                  <input name="height_ft" type="decimal" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Weight (kg)</label>
                  <input name="weight_kg" type="decimal" min="0" class="form-control form-control-sm" required>
                </div>

                <!-- Emergency Contact Person Name & Number -->
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Emergency Contact Person</label>
                  <input name="emergency_name" type="text" class="form-control form-control-sm" required>
                </div>     
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Emergency Contact Number</label>
                  <input name="emergency_phone" type="tel" class="form-control form-control-sm" required>
                </div>    

                <!-- Formal Picture -->
                <div class="col-12 col-md-6">
                  <div class="form-check d-inline-block">
                    <input class="form-check-input" type="checkbox" id="requirePhotoCheck">
                    <label class="form-check-label fs-6" for="requirePhotoCheck"></label>
                  </div>
                  <label class="form-label fw-bold">1×1 Formal Picture</label>
                  <input id="photoInput" name="photo" type="file" accept="image/*" class="form-control form-control-sm" disabled>
                </div>

                <!-- Section Title: Payment Details -->
                <div class="col-12 mt-3">
                  <h6 class="fw-bold">Payment Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Payment Method & Preferred Claim Date -->  
                <div class="row gy-2">
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Payment Method</label>
                    <select name="payment_method" id="paymentMethodSelect" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option value="GCash">GCash</option>
                      <option value="Brgy Payment Device">Brgy Payment Device</option>
                      <option value="Over-the-Counter">Over-the-Counter</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Preferred Claim Date</label>
                    <input name="claim_date" type="date" class="form-control form-control-sm" required>
                  </div>
                </div>
              </div>
            </template>

            <!-- BUSINESS PERMIT TEMPLATE -->
            <template id="tpl-Business Permit">
              <div class="row gy-2">
                <!-- Section Title -->
                <div class="col-12">
                  <h6 class="fw-bold">Business Permit Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Type of Transaction -->
                <div class="col-12 d-flex align-items-center">
                  <span class="form-label fw-bold mb-0 me-4">Type of Transaction:</span>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="transaction_type" id="bpNew" value="New Application" required>
                    <label class="form-check-label mb-0" for="bpNew">New Application</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="transaction_type" id="bpRenewal" value="Renewal">
                    <label class="form-check-label mb-0" for="bpRenewal">Renewal</label>
                  </div>
                </div>

                <!-- Full Name -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Barangay, Purok, Age, & Civil Status -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Barangay</label>
                  <input name="barangay" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Purok</label>
                  <select name="purok" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Purok 1</option>
                    <option>Purok 2</option>
                    <option>Purok 3</option>
                    <option>Purok 4</option>
                    <option>Purok 5</option>
                    <option>Purok 6</option>
                  </select>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Age</label>
                  <input name="age" type="number" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>

                <!-- Name of Business & Type of Business -->
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Name of Business</label>
                  <input name="name_of_business" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Type of Business</label>
                  <input name="type_of_business" type="text" class="form-control form-control-sm" required>
                </div>

                <!-- Business Address -->
                <div class="col-12">
                  <label class="form-label fw-bold">Business Full Address</label>
                  <div class="d-flex gap-2">
                    <input name="full_address" type="text" class="form-control form-control-sm" required>
                  </div>
                </div>

                <!-- Section Title: Payment Details -->
                <div class="col-12 mt-5">
                  <h6 class="fw-bold">Payment Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Payment Method & Preferred Claim Date -->
                <div class="row gy-2">
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Payment Method</label>
                    <select name="payment_method" id="paymentMethodSelect" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option value="GCash">GCash</option>
                      <option value="Brgy Payment Device">Brgy Payment Device</option>
                      <option value="Over-the-Counter">Over-the-Counter</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Preferred Claim Date</label>
                    <input name="claim_date" type="date" class="form-control form-control-sm" required>
                  </div>
                </div>
              </div>
            </template>

            <!-- GOOD MORAL TEMPLATE -->
            <template id="tpl-Good Moral">
              <div class="row gy-2">
                <!-- Section Title: Personal Details -->
                <div class="col-12">
                  <h6 class="fw-bold">Personal Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 1: Full Name & Civil Status -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>


                <!-- Row 2: Civil Status, Sex & Age -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="civil_status" class="form-select form-select-sm" required>
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
                  <select name="sex" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Male</option>
                    <option>Female</option>
                    <option>Others</option>
                  </select>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Age</label>
                  <input name="age" type="number" min="0" class="form-control form-control-sm" required>
                </div>

                <!-- Full Address -->
                <div class="col-12">
                  <label class="form-label fw-bold">Full Address</label>
                  <div class="row">
                    <!-- Barangay (read‑only) -->
                    <div class="col-12 col-md-3">
                      <input name="barangay" type="text" value="Brgy. Magang" readonly class="form-control form-control-sm"/>
                    </div>

                    <!-- Purok selector -->
                    <div class="col-12 col-md-2">
                      <select name="purok" class="form-select form-select-sm" required>
                        <option value="">Purok</option>
                        <option>Purok 1</option>
                        <option>Purok 2</option>
                        <option>Purok 3</option>
                        <option>Purok 4</option>
                        <option>Purok 5</option>
                        <option>Purok 6</option>
                      </select>
                    </div>

                    <!-- Subdivision / Street / Lot / Block -->
                    <div class="col-12 col-md-4">
                      <input name="subdivision" type="text" class="form-control form-control-sm" placeholder="Subdivision / Street / Lot / Block" required/>
                    </div>
                  </div>
                </div>

                <!-- Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose</label>
                  <textarea name="purpose" class="form-control form-control-sm" rows="2" placeholder="State the purpose of Good Moral" required></textarea>
                </div>

                <!-- Section Title: Payment Details -->
                <div class="col-12 mt-5">
                  <h6 class="fw-bold">Payment Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Payment Method & Preferred Claim Date -->
                <div class="row gy-2">
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Payment Method</label>
                    <select name="payment_method" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option>GCash</option>
                      <option>Brgy Payment Device</option>
                      <option>Over-the-Counter</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Preferred Claim Date</label>
                    <input name="claim_date" type="date" class="form-control form-control-sm" required >
                  </div>
                </div>
              </div>
            </template> 

            <!-- GUARDIANSHIP TEMPLATE -->
            <template id="tpl-Guardianship">
              <div class="row gy-2">
                <div class="col-12">
                  <h6 class="fw-bold">Guardian Details</h6>
                  <hr class="my-2">
                </div>
                <!-- Row 1: Guardian Full Name, Civil Status, Age -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="guardian_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="guardian_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="guardian_last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="guardian_suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <div class="col-6 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="guardian_civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label fw-bold">Age</label>
                  <input name="guardian_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>

                <!-- Row 2: Guardian Full Address & Child's Name -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Guardian Full Address</label>
                  <div class="d-flex gap-3">
                    <input name="guardian_barangay" type="text" value="Brgy. Magang" readonly class="form-control form-control-sm">
                    <select name="guardian_purok" class="form-select form-select-sm" required>
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
                  <label class="form-label fw-bold">Child's Name</label>
                  <input name="child_name" type="text" class="form-control form-control-sm" required>
                </div>

                <!-- Row 3: Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose</label>
                  <textarea name="purpose" class="form-control form-control-sm" rows="2" placeholder="State the purpose of guardianship" required></textarea>
                </div>

                <!-- Section Title: Payment Details -->
                <div class="col-12 mt-5">
                  <h6 class="fw-bold">Payment Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Payment Method & Preferred Claim Date -->
                <div class="row gy-2">
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Payment Method</label>
                    <select name="payment_method" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option>GCash</option>
                      <option>Brgy Payment Device</option>
                      <option>Over-the-Counter</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Preferred Claim Date</label>
                    <input name="claim_date" type="date" class="form-control form-control-sm" required >
                  </div>
                </div>
              </div>
            </template>

            <!-- INDIGENCY TEMPLATE -->
            <template id="tpl-Indigency">
              <div class="row gy-2">
                <div class="col-12">
                  <h6 class="fw-bold">Indigency Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 1: First, Middle, Last, Suffix -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Row 2: Civil Status & Age -->
                <div class="col-6 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label fw-bold">Age</label>
                  <input name="age" type="number" min="0" class="form-control form-control-sm" required>
                </div>

                <!-- Row 3: Full Address -->
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Full Address</label>
                  <div class="row gx-2">
                    <!-- Barangay (read‑only) -->
                    <div class="col-12 col-md-3">
                      <input name="barangay" type="text" value="Brgy. Magang" readonly class="form-control form-control-sm"/>
                    </div>

                    <!-- Purok selector -->
                    <div class="col-12 col-md-2">
                      <select name="purok" class="form-select form-select-sm" required>
                        <option value="">Purok…</option>
                        <option>Purok 1</option>
                        <option>Purok 2</option>
                        <option>Purok 3</option>
                        <option>Purok 4</option>
                        <option>Purok 5</option>
                        <option>Purok 6</option>
                      </select>
                    </div>

                    <!-- Subdivision / Street / Lot / Block -->
                    <div class="col-12 col-md-7">
                      <input name="subdivision" type="text" class="form-control form-control-sm" placeholder="Subdivision / Street / Lot / Block"/>
                    </div>
                  </div>
                </div>

                <!-- Row 4: Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose</label>
                  <textarea name="purpose" class="form-control form-control-sm" rows="2" placeholder="State the purpose of indigency" required></textarea>
                </div>

                <!-- Row 5: Claim Date -->
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Preferred Claim Date</label>
                  <input name="claim_date" type="date" class="form-control form-control-sm" required>
                </div>
              </div>
            </template>

            <!-- RESIDENCY TEMPLATE -->
            <template id="tpl-Residency">
              <div class="row gy-2">
                <div class="col-12">
                  <h6 class="fw-bold">Residency Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 1: First, Middle, Last, Suffix -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Row 2: Civil Status & Age -->
                <div class="col-6 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label fw-bold">Age</label>
                  <input name="age" type="number" min="0" class="form-control form-control-sm" required>
                </div>

                <!-- Row 3: Full Address & Years Residing -->
                <div class="col-12 col-md-5">
                  <label class="form-label fw-bold">Full Address</label>
                  <div class="d-flex gap-2">
                    <input name="barangay" type="text" value="Brgy. Magang" readonly class="form-control form-control-sm">
                    <select name="purok" class="form-select form-select-sm" required>
                      <option value="">Purok…</option>
                      <option>Purok 1</option>
                      <option>Purok 2</option>
                      <option>Purok 3</option>
                      <option>Purok 4</option>
                      <option>Purok 5</option>
                      <option>Purok 6</option>
                    </select>
                    <input name="subdivision" type="text" class="form-control form-control-sm" placeholder="Subdivision / Street / Lot / Block">
                  </div>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Years Residing Here</label>
                  <input name="residing_years" type="number" min="0" class="form-control form-control-sm" placeholder="e.g. 5" required>
                </div>

                <!-- Row 4: Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose</label>
                  <textarea name="purpose" class="form-control form-control-sm" rows="2" placeholder="State the purpose of residency" required></textarea>
                </div>

                <!-- Section Title: Payment Details -->
                <div class="col-12 mt-4">
                  <h6 class="fw-bold">Payment Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 5: Payment Method & Preferred Claim Date -->
                <div class="row gy-2">
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Payment Method</label>
                    <select name="payment_method" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option>GCash</option>
                      <option>Brgy Payment Device</option>
                      <option>Over-the-Counter</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Preferred Claim Date</label>
                    <input name="claim_date" type="date" class="form-control form-control-sm" required >
                  </div>
                </div>
              </div>
            </template>
            
            <!-- SOLO PARENT TEMPLATE -->
            <template id="tpl-Solo Parent">
              <div class="row gy-2">
                <div class="col-12">
                  <h6 class="fw-bold">Solo Parent Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 1: First, Middle, Last, Suffix -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">First Name</label>
                  <input name="first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Last Name</label>
                  <input name="last_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Suffix <small class="fw-normal">(optional)</small></label>
                  <input name="suffix" type="text" class="form-control form-control-sm" placeholder="Jr., Sr., III…">
                </div>

                <!-- Row 2: Civil Status & Age -->
                <div class="col-6 col-md-3">
                  <label class="form-label fw-bold">Civil Status</label>
                  <select name="civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label fw-bold">Age</label>
                  <input name="age" type="number" min="0" class="form-control form-control-sm" required>
                </div>

                <!-- Row 3: Full Address & Years as Solo Parent -->
                <div class="col-12 col-md-5">
                  <label class="form-label fw-bold">Full Address</label>
                  <div class="d-flex gap-2">
                    <input name="barangay" type="text" value="Brgy. Magang" readonly class="form-control form-control-sm">
                    <select name="purok" class="form-select form-select-sm" required>
                      <option value="">Purok…</option>
                      <option>Purok 1</option>
                      <option>Purok 2</option>
                      <option>Purok 3</option>
                      <option>Purok 4</option>
                      <option>Purok 5</option>
                      <option>Purok 6</option>
                    </select>
                    <input name="subdivision" type="text" class="form-control form-control-sm" placeholder="Subdivision / Street / Lot / Block">
                  </div>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Years as Solo Parent</label>
                  <input name="years_solo_parent" type="number" min="0" class="form-control form-control-sm" placeholder="e.g. 2" required>
                </div>

                <!-- Row 4: Child’s Name, Sex & Age -->
                <div class="col-12 col-md-5">
                  <label class="form-label fw-bold">Child’s Full Name</label>
                  <input name="child_name" type="text" class="form-control form-control-sm" required>
                </div>
                <!-- <div class="col-6 col-md-3">
                  <label class="form-label fw-bold">Child’s Sex</label>
                  <select name="child_sex" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Male</option>
                    <option>Female</option>
                    <option>Others</option>
                  </select>
                </div> -->
                <div class="col-6 col-md-4">
                  <label class="form-label fw-bold">Child’s Age</label>
                  <input name="child_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>

                <!-- Row 5: Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose</label>
                  <textarea name="purpose" class="form-control form-control-sm" rows="2" placeholder="State the purpose of solo parent" required></textarea>
                </div>

                <!-- Section Title: Payment Details -->
                <div class="col-12 mt-4">
                  <h6 class="fw-bold">Payment Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Row 6: Payment Method & Preferred Claim Date -->
                <div class="row gy-2">
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Payment Method</label>
                    <select name="payment_method" class="form-select form-select-sm" required>
                      <option value="">Select…</option>
                      <option>GCash</option>
                      <option>Brgy Payment Device</option>
                      <option>Over-the-Counter</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Preferred Claim Date</label>
                    <input name="claim_date" type="date" class="form-control form-control-sm" required >
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Edit Request Modal -->
      <div class="modal fade" id="editRequestModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:90vw;">
          <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #13411F;">
              <h5 class="modal-title" id="editRequestModalLabel">Edit Request</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editRequestForm" method="POST" action="functions/process_edit_request.php" enctype="multipart/form-data">
              <div class="modal-body">
                <!-- carry over the request type and transaction ID -->
                <input type="hidden" name="request_type" id="editModalRequestType" value="">
                <input type="hidden" name="transaction_id" id="editModalTransactionId" value="">
                
                <!-- Dynamic fields get injected here -->
                <div class="row g-3" id="dynamicEditFields"></div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>


      <!-- Delete Confirmation Modal -->
      <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title" id="deleteConfirmLabel">
                <i class="material-symbols-outlined align-middle" style="font-size:1.2rem;">warning</i>
                Confirm Deletion
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p id="deleteConfirmMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
          </div>
        </div>
      </div>

      <form method="get" id="searchForm" class="d-flex ms-auto me-2">
      <!-- preserve pagination & filters -->
      <input type="hidden" name="page" value="superAdminRequest">
      <input type="hidden" name="page_num" value="1">
      <?php foreach (['request_type','date_from','date_to','payment_method','payment_status','document_status'] as $f): 
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
      <table class="table table-hover align-middle text-start">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">Transaction No.</th>
            <th class="text-nowrap">Name</th>
            <th class="text-nowrap">Request</th>
            <th class="text-nowrap">Payment Status</th>
            <th class="text-nowrap">Document Status</th>
            <th class="text-nowrap">Date Created</th>
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
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['request_type']) ?></td>
                <td><?= htmlspecialchars($row['payment_status']) ?></td>
                <td><?= htmlspecialchars($row['document_status']) ?></td>
                <td><?= htmlspecialchars($row['formatted_date']) ?></td>
                <td>
                  <!-- Print Button -->
                  <a href="print_requuest.php?transaction_id=<?= $tid ?>" class="btn btn-sm btn-warning" title="Print <?= $tid ?>">
                    <span class="material-symbols-outlined" style="font-size: 13px;">
                      print
                    </span>
                  </a>

                  <!-- Edit Button -->
                  <button type="button" class="btn btn-sm btn-success btn-edit" data-tid="<?= $tid ?>" data-type="<?= htmlspecialchars($row['request_type']) ?>" title="Edit <?= $tid ?>">
                    <span class="material-symbols-outlined" style="font-size: 13px;">
                      stylus
                    </span>
                  </button>

                  <!-- Delete Button -->
                  <button class="btn btn-sm btn-danger delete-btn" data-transaction="<?= $tid ?>" data-url="delete_request.php?transaction_id=<?= $tid ?>" title="Delete <?= $tid ?>">
                    <span class="material-symbols-outlined" style="font-size: 13px;">
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

      <?php if ($totalPages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center pagination-sm">
          <!-- Prev Button -->
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_num' => $page - 1])) ?>">Previous</a>
          </li>

          <?php
          $range = 2;
          $dots = false;

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

          <!-- Next Button -->
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_num' => $page + 1])) ?>">Next</a>
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

  const deleteModalEl = document.getElementById('deleteConfirmModal');
  const deleteModal = new bootstrap.Modal(deleteModalEl);
  const messageP = document.getElementById('deleteConfirmMessage');
  const confirmDeleteBtn  = document.getElementById('confirmDeleteBtn');

  // Delegate clicks on any .delete-btn
  document.querySelector('table').addEventListener('click', e => {
    const btn = e.target.closest('.delete-btn');
    if (!btn) return;

    const txn = btn.dataset.transaction;
    const url = btn.dataset.url;

    // Set the warning message
    messageP.textContent = `Are you sure you want to permanently delete ${txn}? This action cannot be undone.`;

    // Update the confirm button’s target
    confirmDeleteBtn.setAttribute('href', url);

    // Show the modal
    deleteModal.show();
  }); 
});
</script>
