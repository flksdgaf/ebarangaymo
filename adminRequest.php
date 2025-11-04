<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];
$newTid = $_GET['transaction_id'] ?? '';

$currentRole = $_SESSION['loggedInUserRole'] ?? '';

// Status Class
if (! function_exists('status_class')) {
    function status_class(string $ps): string {
        $ps = trim($ps);

        // if it starts with "Refund" (case-insensitive), return success
        if (preg_match('/^Refund\b/i', $ps)) {
            return 'bg-success';
        }

        switch ($ps) {
            case 'Paid':             return 'bg-success';
            case 'Free of Charge':   return 'bg-success';
            case 'Unpaid':           return 'bg-danger';
            case 'Pending':          return 'bg-warning';
            case 'Failed':           return 'bg-danger';
            default:                 return 'bg-secondary';
        }
    }
}

// New Walk-In requests are considered ones that are in "Processing"
$walkInCount = (int) $conn->query(
  "SELECT COUNT(*) FROM view_request WHERE request_source = 'Walk-In' AND payment_status = 'Unpaid'"
)->fetch_row()[0];

// New Online requests are considered ones that are in "For Verification"
$onlineCount = (int) $conn->query(
  "SELECT COUNT(*) FROM view_request WHERE request_source = 'Online' AND payment_method = 'GCash' AND (document_status = 'Processing' OR document_status = 'For Verification')"
)->fetch_row()[0];

$brgyPaymentDevice = (int) $conn->query(
  "SELECT COUNT(*) FROM view_request WHERE request_source = 'Online' AND payment_method = 'Brgy Payment Device' AND (document_status = 'Processing' OR document_status = 'For Verification')"
)->fetch_row()[0];

// combined online count (GCash + Brgy Payment Device) — used for "Online" tab for non-treasurer roles
$combinedOnlineCount = (int)$onlineCount + (int)$brgyPaymentDevice;

// what each role is allowed to do on the request page
$rolePermissions = [
  'Brgy Captain' => ['add'],
  'Brgy Secretary' => ['add'],
  'Brgy Bookkeeper' => ['add'],
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
$validSources = ['Walk-In','Online','Brgy Payment Device','Official Receipt Logs','Document Records'];
$processing_type = $_GET['request_source'] ?? 'Walk-In';
if (! in_array($processing_type, $validSources, true)) {
  $processing_type = 'Walk-In';
}

$whereClauses = [];
$bindTypes = '';
$bindParams = [];

// Show released and rejected records in the "Document Records" tab
if ($processing_type === 'Document Records') {
    $whereClauses[] = "r.document_status IN ('Released', 'Rejected', 'Refund')";
} else {
    // Hide released/rejected/refund from other tabs
    $whereClauses[] = "r.document_status NOT IN ('Released','Rejected', 'Refund')";
}

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

// ---------- processing_type / pane filters ----------
if ($processing_type !== '' && $processing_type !== 'Official Receipt Logs') {
    $adminRoles = ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad'];
    $isAdminView = in_array($currentRole, $adminRoles, true);
    $isTreasurer = ($currentRole === 'Brgy Treasurer');

    // Treasurer: only show rows whose document_status is Processing
    if ($isTreasurer) {
        $whereClauses[] = "r.document_status = 'Processing'";
    }

    switch ($processing_type) {
        case 'Walk-In':
            // Walk-In pane: show Walk-In + Over-the-Counter.
            // Include Indigency AND First Time Job Seeker (both free services).
            if ($isAdminView) {
                $whereClauses[] = "(
                    (r.request_source = 'Walk-In' AND r.payment_method = 'Over-the-Counter')
                    OR (r.request_type = 'Indigency' AND r.request_source = 'Walk-In')
                    OR (r.request_type = 'First Time Job Seeker' AND r.request_source = 'Walk-In')
                )";
            } else {
                // Treasurer / default: show Walk-In or Online but only Over-the-Counter (or NULL).
                // EXCLUDE Indigency and First Time Job Seeker (free services, no OR needed)
                $whereClauses[] = "(
                    ((r.request_source = 'Walk-In' OR r.request_source = 'Online')
                      AND (r.payment_method = 'Over-the-Counter' OR r.payment_method IS NULL))
                    AND r.request_type NOT IN ('Indigency', 'First Time Job Seeker')
                )";
            }
            break;

        case 'Online':
            // Online pane: show Online source. Admin roles see multiple payment methods;
            // include Indigency only when its source is Online.
            if ($isAdminView) {
                $whereClauses[] = "(
                    (r.request_source = 'Online' AND r.payment_method IN ('Over-the-Counter','Brgy Payment Device','GCash'))
                    OR (r.request_type = 'Indigency' AND r.request_source = 'Online')
                    OR (r.request_type = 'First Time Job Seeker' AND r.request_source = 'Online')
                )";
            } else {
                // Treasurer / default: show Online + GCash ONLY
                // EXCLUDE Indigency and First Time Job Seeker (free services, no OR needed)
                $whereClauses[] = "(
                    r.request_source = 'Online' 
                    AND r.payment_method = 'GCash'
                    AND r.request_type NOT IN ('Indigency', 'First Time Job Seeker')
                )";
            }
            break;

        case 'Brgy Payment Device':
            // Brgy Payment Device pane: Online source with specific payment method.
            // Do NOT include Indigency here.
            $whereClauses[] = "(r.request_source = 'Online' AND r.payment_method = 'Brgy Payment Device')";
            break;

        case 'GCash':
            $whereClauses[] = "(r.request_source = 'Online' AND r.payment_method = 'GCash')";
            break;

        default:
            // fallback - no additional clause
            break;
    }
}

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

// If current user is Treasurer, exclude "For Verification" rows
if ($currentRole === 'Brgy Treasurer') {
    $whereClauses[] = "document_status <> 'For Verification'";
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
    <?php
      // roles that should see consolidated Walk-In + Online tabs
      $twoTabRoles = ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad'];

      if (in_array($currentRole, $twoTabRoles, true)) :
        // show only Walk-In and a single Online (combined)
    ?>
      <li class="nav-item">
        <a href="?<?= http_build_query(array_merge($_GET, ['request_source'=>'Walk-In','request_page'=>1])) ?>"
          class="nav-link <?= $processing_type==='Walk-In' ? 'active' : '' ?>">
          Walk-In <span class="badge bg-secondary"></span> <!-- <= $walkInCount ?> -->
        </a>
      </li>

      <li class="nav-item">
        <a href="?<?= http_build_query(array_merge($_GET, ['request_source'=>'Online','request_page'=>1])) ?>"
          class="nav-link <?= $processing_type==='Online' ? 'active' : '' ?>">
          Online <span class="badge bg-secondary"></span> <!-- <= $combinedOnlineCount ?> -->
        </a>
      </li>

    <?php else: ?>
      <!-- Treasurer or others: keep the detailed panes -->
      <li class="nav-item">
        <a href="?<?= http_build_query(array_merge($_GET, ['request_source'=>'Walk-In','request_page'=>1])) ?>"
          class="nav-link <?= $processing_type==='Walk-In' ? 'active' : '' ?>">
          Over-the-Counter <span class="badge bg-secondary"></span> <!-- <= $walkInCount ?> -->
        </a>
      </li>

      <li class="nav-item">
        <a href="?<?= http_build_query(array_merge($_GET, ['request_source'=>'Online','request_page'=>1])) ?>"
          class="nav-link <?= $processing_type==='Online' ? 'active' : '' ?>">
          GCash <span class="badge bg-secondary"></span> <!-- <= $onlineCount ?> -->
        </a>
      </li>

      <li class="nav-item">
        <a href="?<?= http_build_query(array_merge($_GET, ['request_source'=>'Brgy Payment Device','request_page'=> 1])) ?>"
          class="nav-link <?= $processing_type ==='Brgy Payment Device' ? 'active' : '' ?>">
          Brgy Payment Device <span class="badge bg-secondary"></span> <!-- <= $brgyPaymentDevice ?> -->
        </a>
      </li>

      <?php if ($currentRole === 'Brgy Treasurer'): ?>
        <li class="nav-item">
          <a href="?<?= http_build_query(array_merge($_GET, ['request_source'=>'Official Receipt Logs','request_page'=>1])) ?>"
            class="nav-link <?= $processing_type==='Official Receipt Logs' ? 'active' : '' ?>">
            Official Receipt Logs
          </a>
        </li>
      <?php endif; ?>
    <?php endif; ?>
    <!-- Add this as a new tab option for Captain/Secretary/Bookkeeper -->
    <?php if (in_array($currentRole, ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper'], true)): ?>
      <li class="nav-item">
        <a href="?<?= http_build_query(array_merge($_GET, ['request_source'=>'Document Records','request_page'=>1])) ?>"
          class="nav-link <?= $processing_type==='Document Records' ? 'active' : '' ?>">
          Document Records
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
                  <!-- <option <= $request_type==='Business Permit'?'selected':''?> value="Business Permit">Business Permit</option> -->
                  <option <?= $request_type==='Barangay Clearance'?'selected':''?> value="Barangay Clearance">Barangay Clearance</option>
                  <option <?= $request_type==='Business Clearance'?'selected':''?> value="Business Clearance">Business Clearance</option>
                  <option <?= $request_type==='First Time Job Seeker'?'selected':''?> value="First Time Job Seeker">First Time Job Seeker</option>
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
                  <div class="grow"> <!-- flex-grow-1 -->
                    <small class="text-muted">From</small>
                    <input type="date" name="date_from" class="form-control form-control-sm" style="font-size:.75rem;" value="<?=htmlspecialchars($date_from)?>">
                  </div>
                  <div class="grow"> <!-- flex-grow-1 -->
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
                <button type="submit" class="btn btn-sm btn-success grow">Apply</button> <!-- flex-grow-1 -->
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
              <?php foreach (['Barangay ID','Barangay Clearance','Business Clearance','First Time Job Seeker','Good Moral','Guardianship','Indigency','Residency','Solo Parent'] as $type): ?> <!-- ,'Business Permit' -->
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
            Showing <strong><?= htmlspecialchars($processing_type) ?></strong><?= $processing_type !== 'Document Records' ? ' Requests' : '' ?>
          </small>
        </div>
      <?php endif; ?>

      <!-- Add New Request Modal -->
      <div class="modal fade" id="addRequestModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:90vw; max-height:90vh; margin: 1.75rem auto;">
          <div class="modal-content" style="max-height: calc(100vh - 3.5rem); display: flex; flex-direction: column;">
            <div class="modal-header text-white" style="background-color: #13411F; flex-shrink: 0;">
              <h5 class="modal-title" id="addRequestModalLabel">New Request</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addRequestForm" method="POST" action="functions/process_new_request.php" enctype="multipart/form-data" style="display: flex; flex-direction: column; flex: 1; min-height: 0;">
              <div class="modal-body" style="flex: 1; overflow-y: auto; overflow-x: hidden;">
                <input type="hidden" name="request_type" id="modalRequestType" value="">
                <input type="hidden" name="admin_account_id" value="<?= $userId ?>">
                <!-- Dynamic fields get injected here -->
                <div class="row g-3" id="dynamicFields"></div>
              </div>
              <div class="modal-footer" style="flex-shrink: 0; background-color: #f8f9fa;">
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
                    <input class="form-check-input" type="radio" name="barangay_id_transaction_type" id="txNew" value="New Application" checked required>
                    <label class="form-check-label mb-0" for="txNew">New Application</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="barangay_id_transaction_type" id="txRenewal" value="Renewal">
                    <label class="form-check-label mb-0" for="txRenewal">Renewal</label>
                  </div>
                </div>

                <!-- Search for Existing Record (shown only when Renewal is selected) -->
                <div class="col-12" id="renewalSearchContainer" style="display: none;">
                  <label class="form-label fw-bold">Search Existing Record</label>
                  <input type="text" id="renewalSearchInput" class="form-control form-control-sm" placeholder="Type name to search..." autocomplete="off">
                  <div id="renewalSearchResults" class="list-group mt-2" style="max-height: 200px; overflow-y: auto;"></div>
                  <input type="hidden" id="renewalTransactionId" name="renewal_transaction_id">
                </div>

                <!-- Full Name -->
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                  <input name="barangay_id_first_name" id="bid_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="barangay_id_middle_name" id="bid_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                  <input name="barangay_id_last_name" id="bid_last_name" type="text" class="form-control form-control-sm" required>
                </div>

                <!-- Purok, Birthday & Birth Place -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Purok <span class="text-danger">*</span></label>
                  <div class="d-flex gap-2">
                    <select name="barangay_id_purok" id="bid_purok" class="form-select form-select-sm" required>
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
                  <label class="form-label fw-bold">Birthday <span class="text-danger">*</span></label>
                  <input name="barangay_id_dob" id="bid_dob" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Birth Place <span class="text-danger">*</span></label>
                  <div class="row">
                    <div class="col">
                      <input type="text" name="barangay_id_birth_place" id="bid_birth_place" class="form-control form-control-sm" placeholder="Municipality / Province" required/>
                    </div>
                  </div>
                </div>

                <!-- Civil Status, Religion, Height & Weight-->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status <span class="text-danger">*</span></label>
                  <select name="barangay_id_civil_status" id="bid_civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Religion <span class="text-danger">*</span></label>
                  <select name="barangay_id_religion" id="bid_religion" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Roman Catholic</option>
                    <option>Islam</option>
                    <option>Iglesia ni Cristo</option>
                    <option value="Other">Others</option>
                  </select>
                  <input name="barangay_id_religion_other" id="bid_religion_other" type="text" class="form-control form-control-sm mt-2 d-none" placeholder="Please specify religion">
                </div>
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Height (ft) <span class="text-danger">*</span></label>
                  <input name="barangay_id_height" id="bid_height" type="number" step="0.01" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Weight (kg) <span class="text-danger">*</span></label>
                  <input name="barangay_id_weight" id="bid_weight" type="number" step="0.1" min="0" class="form-control form-control-sm" required>
                </div>

                <!-- Emergency Contact Person Name & Number -->
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Emergency Contact Person <small class="fw-normal">(optional)</small></label>
                  <input name="barangay_id_emergency_contact_person" id="bid_emergency_contact" type="text" class="form-control form-control-sm">
                </div>     
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Emergency Contact Address <small class="fw-normal">(optional)</small></label>
                  <input name="barangay_id_emergency_contact_address" id="bid_emergency_address" type="text" class="form-control form-control-sm">
                </div>    

                <!-- Formal Picture -->
                <div class="col-12 col-md-12">
                  <label class="form-label fw-bold">Formal Picture <span class="text-danger">*</span></label>
                  <div class="d-flex gap-2 align-items-start flex-wrap">
                    <!-- Hidden file input -->
                    <input type="file" id="photoInput" name="barangay_id_photo" class="form-control form-control-sm d-none" accept="image/*">
                    
                    <!-- Camera Button -->
                    <button type="button" id="openCameraBtnBID" class="btn btn-sm btn-outline-success">
                      <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">photo_camera</span> Take Photo
                    </button>
                    
                    <!-- Preview Container -->
                    <div id="photoPreviewContainerBID" class="w-100 mt-2 d-none">
                      <img id="photoPreviewBID" src="" alt="Photo Preview" class="img-thumbnail" style="max-width: 200px;">
                      <p class="text-success small mt-1 mb-0">
                        <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">check_circle</span>
                        Photo ready for upload
                      </p>
                    </div>
                    
                    <!-- Current photo display for renewals -->
                    <div id="currentPhotoName" class="form-text text-muted d-none mt-2"></div>
                  </div>
                  <input type="hidden" id="bid_existing_photo" name="barangay_id_existing_photo">
                  <small class="form-text text-muted mt-1 d-block">
                    Please ensure the picture is recent, clear, front-facing, with a plain background.
                  </small>
                </div>
              </div>
            </template>

            <!-- BARANGAY CLEARANCE TEMPLATE -->
            <template id="tpl-Barangay Clearance">
              <div class="row gy-2">
                <!-- Section Title: Applicant Details -->
                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Applicant Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Name -->
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                  <input name="clearance_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="clearance_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                  <input name="clearance_last_name" type="text" class="form-control form-control-sm" required>
                </div>

                <!-- Address: Street, Purok, Barangay -->
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Street <span class="text-danger">*</span></label>
                  <input name="clearance_street" type="text" class="form-control form-control-sm" placeholder="Street / Block">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Purok <span class="text-danger">*</span></label>
                  <select name="clearance_purok" class="form-select form-select-sm" required>
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
                  <label class="form-label fw-bold">Barangay <span class="text-danger">*</span></label>
                  <input name="clearance_barangay" type="text" class="form-control form-control-sm" value="MAGANG" required>
                </div>

                <!-- Municipality & Province -->
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Municipality / City <span class="text-danger">*</span></label>
                  <input name="clearance_municipality" type="text" class="form-control form-control-sm" value="DAET" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Province <span class="text-danger">*</span></label>
                  <input name="clearance_province" type="text" class="form-control form-control-sm" value="CAMARINES NORTE" required>
                </div>

                <!-- Birthdate, Age, Birthplace -->
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Birthdate <span class="text-danger">*</span></label>
                  <input name="clearance_birthdate" id="clearance_birthdate" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Age <span class="text-danger">*</span></label>
                  <input name="clearance_age" id="clearance_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Birth Place <span class="text-danger">*</span></label>
                  <input name="clearance_birthplace" type="text" class="form-control form-control-sm" placeholder="Municipality / Province" required>
                </div>

                <!-- Marital Status, CTC No., Purpose -->
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Marital Status <span class="text-danger">*</span></label>
                  <select name="clearance_marital_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">CTC Number <small class="fw-normal">(if applicable)</small></label>
                  <input name="clearance_ctc_number" type="text" class="form-control form-control-sm" placeholder="CTC No.">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Purpose <span class="text-danger">*</span></label>
                  <input name="clearance_purpose" type="text" class="form-control form-control-sm" placeholder="e.g., Employment, Travel" required>
                </div>

                <!-- Formal Picture -->
                <div class="col-12 col-md-12">
                  <label class="form-label fw-bold">Formal Picture <span class="text-danger">*</span></label>
                  <div class="d-flex gap-2 align-items-start flex-wrap">
                    <!-- Hidden file input -->
                    <input type="file" id="clearance_photoInput" name="clearance_photo" class="form-control form-control-sm d-none" accept="image/*">
                    
                    <!-- Camera Button -->
                    <button type="button" id="openCameraBtnClearance" class="btn btn-sm btn-outline-success">
                      <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">photo_camera</span> Take Photo
                    </button>
                    
                    <!-- Preview Container -->
                    <div id="photoPreviewContainerClearance" class="w-100 mt-2 d-none">
                      <img id="photoPreviewClearance" src="" alt="Photo Preview" class="img-thumbnail" style="max-width: 200px;">
                      <p class="text-success small mt-1 mb-0">
                        <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">check_circle</span>
                        Photo ready for upload
                      </p>
                    </div>
                  </div>
                  <small class="form-text text-muted mt-1 d-block">
                    Please ensure the picture is recent, clear, front-facing, with a plain background.
                  </small>
                </div>
              </div>
            </template>

            <!-- BUSINESS CLEARANCE TEMPLATE -->
            <template id="tpl-Business Clearance">
              <div class="row gy-2">
                <!-- Section Title: Applicant / Business Details -->
                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Business Clearance - Applicant & Business Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Name -->
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                  <input name="business_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="business_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                  <input name="business_last_name" type="text" class="form-control form-control-sm" required>
                </div>

                <!-- Address: Purok, Barangay -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Purok <span class="text-danger">*</span></label>
                  <select name="business_purok" class="form-select form-select-sm" required>
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
                  <label class="form-label fw-bold">Barangay <span class="text-danger">*</span></label>
                  <input name="business_barangay" type="text" class="form-control form-control-sm" value="MAGANG" required>
                </div>

                <!-- Municipality & Province -->
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Municipality / City <span class="text-danger">*</span></label>
                  <input name="business_municipality" type="text" class="form-control form-control-sm" value="DAET" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Province <span class="text-danger">*</span></label>
                  <input name="business_province" type="text" class="form-control form-control-sm" value="CAMARINES NORTE" required>
                </div>

                <!-- Age & Marital Status -->
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Age <span class="text-danger">*</span></label>
                  <input name="business_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Marital Status <span class="text-danger">*</span></label>
                  <select name="business_marital_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>

                <!-- Business Name & Type -->
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Name of Business <span class="text-danger">*</span></label>
                  <input name="business_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">Type of Business <span class="text-danger">*</span></label>
                  <input name="business_type" type="text" class="form-control form-control-sm" placeholder="e.g., Retail, Food Service, Manufacturing" required>
                </div>

                <!-- Business Address -->
                <div class="col-12">
                  <label class="form-label fw-bold">Business Address <span class="text-danger">*</span></label>
                  <input name="business_address" type="text" class="form-control form-control-sm" placeholder="Street / Block / Lot / Purok" required>
                </div>

                <!-- CTC Number & Picture -->
                <div class="col-12 col-md-6">
                  <label class="form-label fw-bold">CTC Number <small class="fw-normal">(if applicable)</small></label>
                  <input name="business_ctc_number" type="number" class="form-control form-control-sm" placeholder="CTC No.">
                </div>
                <div class="col-12 col-md-12">
                  <label class="form-label fw-bold">Owner's Picture <span class="text-danger">*</span></label>
                  <div class="d-flex gap-2 align-items-start flex-wrap">
                    <!-- Hidden file input -->
                    <input type="file" id="business_photoInput" name="business_photo" class="form-control form-control-sm d-none" accept="image/*">
                    
                    <!-- Camera Button -->
                    <button type="button" id="openCameraBtnBusiness" class="btn btn-sm btn-outline-success">
                      <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">photo_camera</span> Take Photo
                    </button>
                    
                    <!-- Preview Container -->
                    <div id="photoPreviewContainerBusiness" class="w-100 mt-2 d-none">
                      <img id="photoPreviewBusiness" src="" alt="Photo Preview" class="img-thumbnail" style="max-width: 200px;">
                      <p class="text-success small mt-1 mb-0">
                        <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">check_circle</span>
                        Photo ready for upload
                      </p>
                    </div>
                  </div>
                  <small class="form-text text-muted mt-1 d-block">
                    Please ensure the picture is recent, clear, front-facing, with a plain background.
                  </small>
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

            <!-- FIRST TIME JOB SEEKER TEMPLATE -->
            <template id="tpl-First Time Job Seeker">
              <div class="row gy-2">
                <!-- Section Title: Personal Details -->
                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Personal Details</h6>
                  <hr class="my-2">
                </div>
                
                <!-- Row 1: Full Name -->
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                  <input name="first_time_job_seeker_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="first_time_job_seeker_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                  <input name="first_time_job_seeker_last_name" type="text" class="form-control form-control-sm" required>
                </div>

                <!-- Row 2: Age, Sex & Civil Status -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Age <span class="text-danger">*</span></label>
                  <input name="first_time_job_seeker_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Sex <span class="text-danger">*</span></label>
                  <select name="first_time_job_seeker_sex" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Male</option>
                    <option>Female</option>
                  </select>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status <span class="text-danger">*</span></label>
                  <select name="first_time_job_seeker_civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>

                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Purok <span class="text-danger">*</span></label>
                  <div class="d-flex gap-2">
                    <select name="first_time_job_seeker_purok" class="form-select form-select-sm" required>
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
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                  <input name="good_moral_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="good_moral_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                  <input name="good_moral_last_name" type="text" class="form-control form-control-sm" required>
                </div>

                <!-- Row 2: Civil Status, Sex & Age -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status <span class="text-danger">*</span></label>
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
                  <label class="form-label fw-bold">Sex <span class="text-danger">*</span></label>
                  <select name="good_moral_sex" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Male</option>
                    <option>Female</option>
                  </select>
                </div>
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Age <span class="text-danger">*</span></label>
                  <input name="good_moral_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>

                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Purok <span class="text-danger">*</span></label>
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
                  <label class="form-label fw-bold">Purpose <span class="text-danger">*</span></label>
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
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                  <input name="guardianship_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="guardianship_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                  <input name="guardianship_last_name" type="text" class="form-control form-control-sm" required>
                </div>

                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status <span class="text-danger">*</span></label>
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
                  <label class="form-label fw-bold">Age <span class="text-danger">*</span></label>
                  <input name="guardianship_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>
                
                <div class="col-12 col-md-2 mb-3">
                  <label class="form-label fw-bold">Purok <span class="text-danger">*</span></label>
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
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Children Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Container for dynamic child fields -->
                <div class="col-12" id="guardianshipChildrenContainer">
                  <!-- First child (required) -->
                  <div class="guardianship-child-entry border rounded p-3 mb-3" data-child-index="0">
                    <div class="row gy-2">
                      <div class="col-12 col-md-6">
                        <label class="form-label fw-bold">Child's Full Name <span class="text-danger">*</span></label>
                        <input name="guardianship_children[0][name]" type="text" class="form-control form-control-sm" required>
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label fw-bold">Relationship to Guardian <small class="fw-normal">(optional)</small></label>
                        <input name="guardianship_children[0][relationship]" type="text" class="form-control form-control-sm" placeholder="e.g., Son, Daughter, Nephew, Niece">
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Add Child Button -->
                <div class="col-12">
                  <button type="button" class="btn btn-sm btn-outline-success" id="addGuardianshipChildBtn">
                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">add</span>
                    Add Another Child
                  </button>
                </div>

                <!-- Row 3: Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose <span class="text-danger">*</span></label>
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
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                  <input name="indigency_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="indigency_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                  <input name="indigency_last_name" type="text" class="form-control form-control-sm" required>
                </div>

                <!-- Row 2: Civil Status & Age -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Age <span class="text-danger">*</span></label>
                  <input name="indigency_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status <span class="text-danger">*</span></label>
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
                <div class="col-12 col-md-2">
                  <label class="form-label fw-bold">Purok <span class="text-danger">*</span></label>
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
                  <label class="form-label fw-bold">Purpose <span class="text-danger">*</span></label>
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
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                  <input name="residency_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="residency_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                  <input name="residency_last_name" type="text" class="form-control form-control-sm" required>
                </div>

                <!-- Row 2: Civil Status & Age -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Age <span class="text-danger">*</span></label>
                  <input name="residency_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>

                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status <span class="text-danger">*</span></label>
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
                  <label class="form-label fw-bold">Purok <span class="text-danger">*</span></label>
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
                  <label class="form-label fw-bold">Years Residing Here <span class="text-danger">*</span></label>
                  <input name="residency_residing_years" type="number" min="0" class="form-control form-control-sm" placeholder="e.g. 5" required>
                </div>

                <!-- Row 4: Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose <span class="text-danger">*</span></label>
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
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                  <input name="solo_parent_first_name" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Middle Name <small class="fw-normal">(optional)</small></label>
                  <input name="solo_parent_middle_name" type="text" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                  <input name="solo_parent_last_name" type="text" class="form-control form-control-sm" required>
                </div>

                <!-- Row 2: Age, Sex, Civil Status -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Age <span class="text-danger">*</span></label>
                  <input name="solo_parent_age" type="number" min="0" class="form-control form-control-sm" required>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Sex <span class="text-danger">*</span></label>
                  <select name="solo_parent_sex" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Male</option>
                    <option>Female</option>
                  </select>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Civil Status <span class="text-danger">*</span></label>
                  <select name="solo_parent_civil_status" class="form-select form-select-sm" required>
                    <option value="">Select…</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Divorced</option>
                    <option>Separated</option>
                    <option>Widowed</option>
                  </select>
                </div>

                <!-- Row 3: Purok & Years as Solo Parent -->
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Purok <span class="text-danger">*</span></label>
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
                <div class="col-12 col-md-3">
                  <label class="form-label fw-bold">Years as Solo Parent <span class="text-danger">*</span></label>
                  <input name="solo_parent_years_solo_parent" type="number" min="0" class="form-control form-control-sm" placeholder="e.g. 2" required>
                </div>

                <div class="col-12">
                  <h6 class="fw-bold fs-5" style="color: #13411F;">Children Details</h6>
                  <hr class="my-2">
                </div>

                <!-- Container for dynamic child fields -->
                <div class="col-12" id="childrenContainer">
                  <!-- First child (required) -->
                  <div class="child-entry border rounded p-3 mb-3" data-child-index="0">
                    <div class="row gy-2">
                      <div class="col-12 col-md-4">
                        <label class="form-label fw-bold">Child's Full Name <span class="text-danger">*</span></label>
                        <input name="children[0][name]" type="text" class="form-control form-control-sm" required>
                      </div>
                      <div class="col-12 col-md-2">
                        <label class="form-label fw-bold">Sex <span class="text-danger">*</span></label>
                        <select name="children[0][sex]" class="form-select form-select-sm" required>
                          <option value="">Select…</option>
                          <option>Male</option>
                          <option>Female</option>
                        </select>
                      </div>
                      <div class="col-12 col-md-3">
                        <label class="form-label fw-bold">Birthdate <span class="text-danger">*</span></label>
                        <input name="children[0][birthdate]" type="date" class="form-control form-control-sm child-birthdate" required>
                      </div>
                      <div class="col-12 col-md-3">
                        <label class="form-label fw-bold">Age</label>
                        <input name="children[0][age_display]" type="text" class="form-control form-control-sm child-age-display" readonly style="background-color: #e9ecef;">
                        <input name="children[0][age]" type="hidden" class="child-age-value">
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Add Child Button -->
                <div class="col-12">
                  <button type="button" class="btn btn-sm btn-outline-success" id="addChildBtn">
                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">add</span>
                    Add Another Child
                  </button>
                </div>

                <!-- Purpose -->
                <div class="col-12">
                  <label class="form-label fw-bold">Purpose <span class="text-danger">*</span></label>
                  <textarea name="solo_parent_purpose" class="form-control form-control-sm" rows="2" placeholder="State the purpose of solo parent" required></textarea>
                </div>
              </div>
            </template>

          </div>
        </div>
      </div>

      <!-- View Request Modal -->
      <div class="modal fade" id="viewRequestModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 820px; max-height: 90vh; margin: 1.75rem auto;">
          <div class="modal-content" style="display: flex; flex-direction: column; max-height: calc(100vh - 3.5rem);">
            <div class="modal-header text-white" style="background-color:#13411F; flex-shrink: 0;">
              <h5 class="modal-title" id="viewRequestModalLabel">Request Details</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-3" style="flex: 1; overflow-y: auto; overflow-x: hidden; min-height: 0;">
              <div class="container-fluid">
                <div id="viewRequestSummary" class="mb-2 text-muted small"></div>

                <!-- fields injected here -->
                <form id="viewRequestForm" autocomplete="off" onsubmit="return false;">
                  <div id="viewRequestFields" class="row g-3">
                    <!-- JS will insert grouped sections here as .col-12 blocks -->
                  </div>
                </form>

                <!-- image/thumbs -->
                <div id="viewRequestImage" class="mt-3"></div>
              </div>
            </div>

            <div class="modal-footer justify-content-end px-4 py-2" style="background-color:#f8f9fa; flex-shrink: 0;">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Accept Request Modal -->
      <div class="modal fade" id="acceptRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
          <div class="modal-content border-success">
            <form id="acceptRequestForm">
              <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Accept Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <p>Are you sure you want to <strong>accept</strong> this request?</p>

                <!-- IMPORTANT: these names must match what update_request_status.php expects -->
                <input type="hidden" name="transaction_id" id="acceptTransactionId" value="">
                <input type="hidden" name="status" value="Processing">

              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-success">Confirm Accept</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Reject Request Modal -->
      <div class="modal fade" id="rejectRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <form id="rejectRequestForm">
              <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <p>Please provide a reason for rejection:</p>
                <input type="hidden" name="transaction_id" id="rejectTransactionId" value="">
                <input type="hidden" name="status" value="Rejected">
                <textarea name="remarks" id="rejectRemarks" class="form-control" required></textarea>

              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-danger">Reject</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- View Request Modal -->
      <div class="modal fade" id="previewRequestModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="previewRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 820px; max-height: 90vh; margin: 1.75rem auto;">
          <div class="modal-content" style="display: flex; flex-direction: column; max-height: calc(100vh - 3.5rem);">
            <!-- Modal Header -->
            <div class="modal-header text-white" style="background-color: #13411F; flex-shrink: 0;">
              <h5 class="modal-title" id="previewRequestModalLabel">Document Request Preview</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Scrollable Modal Body -->
            <div class="modal-body p-0" style="flex: 1; overflow: hidden; min-height: 0;">
              <div class="preview-container" style="height: 100%; overflow-y: auto; background-color: #ccc; padding: 20px;">
                <iframe
                  id="requestPreviewFrame"
                  src=""
                  allowfullscreen
                  style="width: 100%; height: 500px; border: none; background-color: #fff;"
                ></iframe>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer justify-content-between px-4 py-2" style="background-color: #f8f9fa; flex-shrink: 0;">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="includeHeader">
                <label class="form-check-label" for="includeHeader">
                  Include Header
                </label>
              </div>
              <div>
                <button class="btn btn-outline-success me-2" id="printRequestBtn">
                  <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">print</span>
                  Print
                </button>

                <button class="btn btn-success" id="downloadRequestPDF" href="#" target="_blank">
                  <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">download</span>
                  Save as PDF
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Request Modal -->
      <div class="modal fade" id="editRequestModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:90vw; max-height:90vh; margin: 1.75rem auto;">
          <div class="modal-content" style="max-height: calc(100vh - 3.5rem); display: flex; flex-direction: column;">
            <!-- Header -->
            <div class="modal-header text-white" style="background-color: #13411F; flex-shrink: 0;">
              <h5 class="modal-title" id="editRequestModalLabel">Edit Request</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <!-- Form -->
            <form id="editRequestForm" method="POST" action="functions/process_edit_request.php" enctype="multipart/form-data" style="display: flex; flex-direction: column; flex: 1; min-height: 0;">
              <div class="modal-body" style="flex: 1; overflow-y: auto; overflow-x: hidden; min-height: 0;">
                <!-- carry over identifying info -->
                <input type="hidden" name="transaction_id" id="editTransactionId" value="">
                <input type="hidden" name="request_type" id="editRequestType" value="">
                <!-- your JS will inject the fields here -->
                <div class="row g-3" id="editDynamicFields"></div>
              </div>
              
              <div class="modal-footer" style="flex-shrink: 0; background-color: #f8f9fa;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Camera Modal for Barangay ID -->
      <div class="modal fade" id="cameraModalBID" tabindex="-1" aria-labelledby="cameraModalBIDLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" style="max-height: 90vh; margin: 1.75rem auto;">
          <div class="modal-content" style="max-height: calc(100vh - 3.5rem); display: flex; flex-direction: column;">
            <div class="modal-header" style="flex-shrink: 0;">
              <h5 class="modal-title" id="cameraModalBIDLabel">Take Photo</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" style="flex: 1; overflow-y: auto; min-height: 0;">
              <!-- Camera View -->
              <div id="cameraViewBID" class="position-relative">
                <video id="cameraStreamBID" autoplay playsinline class="w-100 rounded" style="max-height: 400px; object-fit: cover; background: #000;"></video>
                <button type="button" id="captureBtnBID" class="btn btn-success btn-lg mt-3">
                  <span class="material-symbols-outlined" style="vertical-align:middle;">photo_camera</span> Capture Photo
                </button>
              </div>
              
              <!-- Preview View (hidden initially) -->
              <div id="previewViewBID" class="d-none">
                <canvas id="photoCanvasBID" class="w-100 rounded" style="max-height: 400px; max-width: 400px; border: 2px solid #198754; aspect-ratio: 1 / 1;"></canvas>
                <div class="mt-3 d-flex gap-2 justify-content-center">
                  <button type="button" id="retakeBtnBID" class="btn btn-warning" style="min-width: 140px;">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">refresh</span> Retake
                  </button>
                  <button type="button" id="uploadPhotoBtnBID" class="btn btn-success" style="min-width: 140px;">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">check_circle</span> Use This Photo
                  </button>
                </div>
              </div>
              
              <!-- Error Message -->
              <div id="cameraErrorBID" class="alert alert-danger d-none mt-3" role="alert"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Camera Modal for Barangay Clearance -->
      <div class="modal fade" id="cameraModalClearance" tabindex="-1" aria-labelledby="cameraModalClearanceLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" style="max-height: 90vh; margin: 1.75rem auto;">
          <div class="modal-content" style="max-height: calc(100vh - 3.5rem); display: flex; flex-direction: column;">
            <div class="modal-header" style="flex-shrink: 0;">
              <h5 class="modal-title" id="cameraModalClearanceLabel">Take Photo</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" style="flex: 1; overflow-y: auto; min-height: 0;">
              <!-- Camera View -->
              <div id="cameraViewClearance" class="position-relative">
                <video id="cameraStreamClearance" autoplay playsinline class="w-100 rounded" style="max-height: 400px; object-fit: cover; background: #000;"></video>
                <button type="button" id="captureBtnClearance" class="btn btn-success btn-lg mt-3">
                  <span class="material-symbols-outlined" style="vertical-align:middle;">photo_camera</span> Capture Photo
                </button>
              </div>
              
              <!-- Preview View -->
              <div id="previewViewClearance" class="d-none">
                <canvas id="photoCanvasClearance" class="w-100 rounded" style="max-height: 400px; max-width: 400px; border: 2px solid #198754; aspect-ratio: 1 / 1;"></canvas>
                <div class="mt-3 d-flex gap-2 justify-content-center">
                  <button type="button" id="retakeBtnClearance" class="btn btn-warning" style="min-width: 140px;">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">refresh</span> Retake
                  </button>
                  <button type="button" id="uploadPhotoBtnClearance" class="btn btn-success" style="min-width: 140px;">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">check_circle</span> Use This Photo
                  </button>
                </div>
              </div>
              
              <!-- Error Message -->
              <div id="cameraErrorClearance" class="alert alert-danger d-none mt-3" role="alert"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Camera Modal for Business Clearance -->
      <div class="modal fade" id="cameraModalBusiness" tabindex="-1" aria-labelledby="cameraModalBusinessLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" style="max-height: 90vh; margin: 1.75rem auto;">
          <div class="modal-content" style="max-height: calc(100vh - 3.5rem); display: flex; flex-direction: column;">
            <div class="modal-header" style="flex-shrink: 0;">
              <h5 class="modal-title" id="cameraModalBusinessLabel">Take Photo</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" style="flex: 1; overflow-y: auto; min-height: 0;">
              <!-- Camera View -->
              <div id="cameraViewBusiness" class="position-relative">
                <video id="cameraStreamBusiness" autoplay playsinline class="w-100 rounded" style="max-height: 400px; object-fit: cover; background: #000;"></video>
                <button type="button" id="captureBtnBusiness" class="btn btn-success btn-lg mt-3">
                  <span class="material-symbols-outlined" style="vertical-align:middle;">photo_camera</span> Capture Photo
                </button>
              </div>
              
              <!-- Preview View -->
              <div id="previewViewBusiness" class="d-none">
                <canvas id="photoCanvasBusiness" class="w-100 rounded" style="max-height: 400px; max-width: 400px; border: 2px solid #198754; aspect-ratio: 1 / 1;"></canvas>
                <div class="mt-3 d-flex gap-2 justify-content-center">
                  <button type="button" id="retakeBtnBusiness" class="btn btn-warning" style="min-width: 140px;">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">refresh</span> Retake
                  </button>
                  <button type="button" id="uploadPhotoBtnBusiness" class="btn btn-success" style="min-width: 140px;">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">check_circle</span> Use This Photo
                  </button>
                </div>
              </div>
              
              <!-- Error Message -->
              <div id="cameraErrorBusiness" class="alert alert-danger d-none mt-3" role="alert"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Record Payment Modal -->
      <div class="modal fade" id="recordModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="recordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" style="max-height: 90vh; margin: 1.75rem auto;">
          <div class="modal-content" style="max-height: calc(100vh - 3.5rem); display: flex; flex-direction: column;">
            <!-- Header -->
            <div class="modal-header text-white" style="background-color: #13411F; flex-shrink: 0;">
              <h5 class="modal-title" id="recordModalLabel">
                <i class="bi bi-receipt me-2"></i>
                Record Payment
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form id="recordForm" action="functions/process_record_payment.php" method="POST" style="display: flex; flex-direction: column; flex: 1; min-height: 0;">
              <div class="modal-body" style="flex: 1; overflow-y: auto; min-height: 0;">
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
                    <label for="amountPaidRecord" class="form-label fw-bold">Amount to Pay</label>
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
              <div class="modal-footer" style="flex-shrink: 0; background-color: #f8f9fa;">
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

    <div class="table-responsive admin-table"> <!--  style="height:500px;overflow-y:auto;"  -->
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
              <td><?= htmlspecialchars($r['request_type']) ?></td>
              <td><?= htmlspecialchars($r['full_name']) ?></td>
              <td><?= htmlspecialchars($r['payment_method']) ?></td>
              <td><?= htmlspecialchars($r['or_number']) ?></td>
              <td><?= number_format($r['amount_paid'],2) ?></td>
              <td><?= htmlspecialchars($r['issued_date']) ?></td>
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
              <?php if ($processing_type !== 'Document Records'): ?>
                <th class="text-nowrap">Payment Status</th>
                <th class="text-nowrap">Document Status</th>
              <?php else: ?>
                <th class="text-nowrap">Status</th>
              <?php endif; ?>
              <th class="text-nowrap">Date Created</th>
              <?php if ($currentRole !== 'Brgy Kagawad' && $processing_type !== 'Document Records'): ?>
                <th class="text-nowrap text-center">Action</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows): ?>
              <?php while ($row = $result->fetch_assoc()):
                $tid = htmlspecialchars($row['transaction_id']);
                $ps = $row['payment_status'] ?? '';

                $c = status_class($ps);

                $ds = $row['document_status'] ?? '';
                switch ($ds) {
                  case 'For Verification': $d = 'bg-info'; break;
                  case 'Processing': $d = 'bg-warning'; break;
                  case 'Ready to Release': $d = 'bg-primary'; break;
                  case 'Released': $d = 'bg-success'; break;
                  case 'Rejected': $d = 'bg-danger'; break;
                  default: $d = 'bg-secondary';
                }

                // Hide records from Brgy Treasurer unless document_status is 'Processing'
                if (isset($currentRole) && $currentRole === 'Brgy Treasurer') {
                  $docStatus = trim((string)($row['document_status'] ?? ''));
                  if (strcasecmp($docStatus, 'Processing') !== 0) {
                    continue;
                  }
                }
              ?>
                <tr data-id="<?= $tid ?>">
                  <td><?= $tid ?></td>
                  <td><?= htmlspecialchars($row['full_name']) ?></td>
                  <td><?= htmlspecialchars($row['request_type']) ?></td>
                  <?php if ($processing_type !== 'Document Records'): ?>
                    <td><span class="badge <?= $c ?>"><?= htmlspecialchars($ps ?: '—') ?></span></td>
                    <td><span class="badge <?= $d ?>"><?= htmlspecialchars($ds) ?></span></td>
                  <?php else: ?>
                    <td><span class="badge <?= $d ?>"><?= htmlspecialchars($ds) ?></span></td>
                  <?php endif; ?>
                  <td><?= htmlspecialchars($row['formatted_date']) ?></td>
                  <?php if ($processing_type !== 'Document Records'): ?>
                    <?php if ($currentRole !== 'Brgy Treasurer'): ?>
                      <td class="text-nowrap text-center">
                        <!-- Action buttons remain the same -->
                        <?php if (in_array($currentRole, ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper'], true) && (($row['document_status'] ?? '') === 'For Verification')): ?>
                          <!-- Existing For Verification buttons -->
                          <button class="btn btn-sm btn-primary btn-view-request" data-id="<?= $tid ?>" title="View">
                            <span class="material-symbols-outlined" style="font-size:12px;">visibility</span>
                          </button>
                          <button class="btn btn-sm btn-success btn-accept-request" data-id="<?= $tid ?>" data-action="Processing" title="Accept">
                            <span class="material-symbols-outlined" style="font-size:12px;">check</span>
                          </button>
                          <button class="btn btn-sm btn-danger btn-reject-request" data-id="<?= $tid ?>" data-action="Rejected" title="Reject">
                            <span class="material-symbols-outlined" style="font-size:12px;">close</span>
                          </button>
                        <?php elseif (in_array($currentRole, ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper'], true) && (($row['document_status'] ?? '') === 'Processing' || ($row['document_status'] ?? '') === 'Ready to Release')): ?>
                          <!-- Existing Processing/Ready to Release buttons -->
                          <button type="button" class="btn btn-sm btn-warning request-btn-view" data-id="<?= $tid ?>" title="View <?= $tid ?>" data-payment-status="<?= htmlspecialchars($row['payment_status'] ?? '') ?>" data-request-type="<?= htmlspecialchars($row['request_type'] ?? '') ?>">
                            <span class="material-symbols-outlined" style="font-size:13px">visibility</span>
                          </button>
                          <button type="button" class="btn btn-sm btn-primary request-btn-edit" title="Edit <?= $tid ?>">
                            <span class="material-symbols-outlined" style="font-size:13px">stylus</span>
                          </button>
                          <?php $releaseDisabled = (($row['document_status'] ?? '') !== 'Ready to Release') ? 'disabled' : ''; ?>
                          <button type="button" class="btn btn-sm btn-success request-btn-release" title="Release <?= $tid ?>" data-transaction-id="<?= htmlspecialchars($row['transaction_id']) ?>" data-request-type="<?= htmlspecialchars($row['request_type']) ?>" <?= $releaseDisabled ?>>
                            <span class="material-symbols-outlined" style="font-size:13px">check</span>
                          </button>
                        <?php endif; ?>
                      </td>
                    <?php else: ?>
                      <!-- Treasurer actions remain the same -->
                      <td class="text-nowrap text-center">
                        <?php
                          $docStatus = $row['document_status'] ?? '';
                          $payMethod = $row['payment_method'] ?? '';
                          $payStatus = $row['payment_status'] ?? '';
                          $requestType = $row['request_type'] ?? '';
                          $accepted = in_array($docStatus, ['Processing','Ready to Release'], true);
                          
                          // Free services that don't need OR
                          $freeServices = ['Indigency', 'First Time Job Seeker'];
                          $isFreeService = in_array($requestType, $freeServices, true);
                        ?>
                        <?php if (! $accepted || $isFreeService): ?>
                          <span class="text-muted small">—</span>
                        <?php else: ?>
                          <?php if ($payMethod === 'Over-the-Counter' || $payMethod === null || $payMethod === ''): ?>
                            <button type="button" class="btn btn-sm btn-info request-record-btn"
                                    data-id="<?= htmlspecialchars($row['transaction_id']) ?>"
                                    data-payment-method="Over-the-Counter"
                                    data-amount-paid="<?= htmlspecialchars($row['amount'] ?? '') ?>"
                                    title="Record Receipt <?= htmlspecialchars($row['transaction_id']) ?>">
                              <span class="material-symbols-outlined" style="font-size: 13px;">receipt</span>
                            </button>
                          <?php elseif (in_array($payMethod, ['GCash','Brgy Payment Device'], true)): ?>
                            <?php if ($payStatus === 'Paid'): ?>
                              <button type="button" class="btn btn-sm btn-info request-record-btn"
                                      data-id="<?= htmlspecialchars($row['transaction_id']) ?>"
                                      data-payment-method="<?= htmlspecialchars($payMethod) ?>"
                                      data-amount-paid="<?= htmlspecialchars($row['amount'] ?? '') ?>"
                                      title="Record Receipt <?= htmlspecialchars($row['transaction_id']) ?>">
                                <span class="material-symbols-outlined" style="font-size: 13px;">receipt</span>
                              </button>
                            <?php else: ?>
                              <span class="text-muted small">—</span>
                            <?php endif; ?>
                          <?php else: ?>
                            <span class="text-muted small">—</span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                  <?php endif; ?>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="<?= $processing_type !== 'Document Records' ? '7' : '5' ?>" class="text-center">No Document Requests Found.</td></tr>
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
        dynamicFields.innerHTML = '<div class="col-12 text-muted">No template found.</div>';
      }

      const sel = dynamicFields.querySelector('#religionSelect');
      const other = dynamicFields.querySelector('#religionOtherInput');
      if (sel && other) {
        sel.addEventListener('change', () => {
          other.classList.toggle('d-none', sel.value !== 'Other');
        });
      }

      // const chk = dynamicFields.querySelector('#requirePhotoCheck');
      // const photo = dynamicFields.querySelector('#photoInput');
      // if (chk && photo) {
      //   chk.addEventListener('change', () => {
      //     photo.disabled = !chk.checked;
      //   });
      // }

      // Initialize camera functionality based on request type
      if (type === 'Barangay ID') {
        initBarangayIDCamera();
      } else if (type === 'Barangay Clearance') {
        initClearanceCamera();
      } else if (type === 'Business Clearance') {
        initBusinessCamera();
      }

      new bootstrap.Modal(document.getElementById('addRequestModal')).show();
    });
  });

  const REQUEST_GROUP_MAP = {
    barangay_id_requests: [
      { title: 'Personal Information', fields: ['full_name','purok','birth_date','birth_place','civil_status','religion','height','weight'] },
      { title: 'Emergency Contact', fields: ['emergency_contact_person','emergency_contact_address'] },
      { title: 'Photo', fields: ['formal_picture'] },
      { title: 'Request Details', fields: ['transaction_id','request_type','transaction_type','payment_method','created_at'] }, //'amount',
    ],

    business_permit_requests: [
      { title: 'Owner Information', fields: ['full_name','age','civil_status','purok','barangay'] },
      { title: 'Business Information', fields: ['name_of_business','type_of_business','full_address'] },
      { title: 'Request Details', fields: ['transaction_id','request_type','transaction_type','payment_method','created_at'] }, //'amount',
    ],

    good_moral_requests: [
      { title: 'Personal Information', fields: ['full_name','sex','age','civil_status','purok','address'] },
      { title: 'Request Details', fields: ['purpose','transaction_id','request_type','payment_method','created_at'] }, //'amount',
    ],

    guardianship_requests: [
      { title: 'Applicant Information', fields: ['full_name','age','civil_status','purok'] },
      { title: 'Children Details', fields: ['child_name','child_relationship','purpose'] },
      { title: 'Request Details', fields: ['transaction_id','request_type','payment_method','created_at'] }, //'amount',
    ],

    indigency_requests: [
      { title: 'Personal Information', fields: ['full_name','age','civil_status','purok'] },
      { title: 'Request Details', fields: ['purpose','transaction_id','request_type','created_at'] },
    ],

    residency_requests: [
      { title: 'Personal Information', fields: ['full_name','age','civil_status','purok'] },
      { title: 'Residency Information', fields: ['residing_years','purpose'] },
      { title: 'Request Details', fields: ['transaction_id','request_type','payment_method','created_at'] }, //'amount',
    ],

    solo_parent_requests: [
      { title: 'Personal Information', fields: ['full_name','age','civil_status','purok','years_solo_parent'] },
      { title: 'Child Information', fields: ['child_name','child_age','child_sex'] },
      { title: 'Request Details', fields: ['purpose','transaction_id','request_type','payment_method','created_at'] }, //'amount',
    ],

    barangay_clearance_requests: [
      { title: 'Personal Information', fields: ['full_name','age','civil_status','purok'] },
      { title: 'Request Details', fields: ['transaction_id','request_type','created_at'] }
    ],

    business_clearance_requests: [
      { title: 'Personal Information', fields: ['full_name','age','civil_status','purok'] },
      { title: 'Request Details', fields: ['transaction_id','request_type','created_at'] }
    ],

    job_seeker_requests: [
      { title: 'Personal Information', fields: ['full_name','age','civil_status','purok'] },
      { title: 'Request Details', fields: ['transaction_id','request_type','created_at'] }
    ],
  };

  // small helpers
  function prettyLabel(key) {
    return key.replace(/_/g,' ').replace(/\b\w/g, ch => ch.toUpperCase());
  }
  function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (!isNaN(d.getTime())) {
      return d.toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
    }
    return value || '—';
  }
  function formatCurrency(value) {
    if (value == null || value === '') return '—';
    const n = Number(value);
    if (isNaN(n)) return value;
    return new Intl.NumberFormat('en-PH', { style:'currency', currency:'PHP' }).format(n);
  }

  // returns canonical map key from returned data
  function detectMapKey(data) {
    let typeRaw = (data.request_type || data.request || data.type || '').toString().trim();
    const lower = typeRaw.toLowerCase();
    const candidates = [
      lower,
      lower.replace(/\s+/g,'_'),
      lower.replace(/\s+/g,'_') + '_requests',
      lower + '_requests',
      (data.source_table || '').toString().toLowerCase(),
      (data.table_name || '').toString().toLowerCase()
    ];
    for (const c of candidates) {
      if (!c) continue;
      if (REQUEST_GROUP_MAP.hasOwnProperty(c)) return c;
    }
    // heuristics
    if (typeof data.birth_date !== 'undefined' || typeof data.formal_picture !== 'undefined') return 'barangay_id_requests';
    if (typeof data.name_of_business !== 'undefined') return 'business_permit_requests';
    return null;
  }

  // create a readonly input element (label + input)
  function makeReadonlyField(key, value) {
    const wrapper = document.createElement('div');
    wrapper.className = 'col-12 col-md-6';

    const label = document.createElement('label');
    label.className = 'form-label fw-semibold mb-1';
    label.textContent = prettyLabel(key);

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm';
    input.readOnly = true; // not editable but not heavily greyed like disabled
    // formatting rules
    if (/date|created_at|birth_date/i.test(key)) input.value = formatDate(value);
    else if (/amount|price|fee/i.test(key)) input.value = formatCurrency(value);
    else input.value = (value === null || value === undefined || value === '') ? '—' : String(value);

    wrapper.appendChild(label);
    wrapper.appendChild(input);
    return wrapper;
  }

  /**
   * Render grouped sections; data is merged row from get_request.php
   */
  function populateViewModal(data) {
    const container = document.getElementById('viewRequestFields');
    const imageContainer = document.getElementById('viewRequestImage');
    const summary = document.getElementById('viewRequestSummary');
    container.innerHTML = '';
    imageContainer.innerHTML = '';
    summary.textContent = '';

    // summary line
    const tid = data.transaction_id || data.transaction || data.id || '';
    const created = data.created_at || data.created || data.formatted_date || '';
    if (tid) summary.textContent = `Transaction: ${tid}` + (created ? ` • Requested: ${formatDate(created)}` : '');

    // detect groups
    const mapKey = detectMapKey(data);
    let groups = [];
    if (mapKey && REQUEST_GROUP_MAP[mapKey]) {
      groups = REQUEST_GROUP_MAP[mapKey].slice(); // copy
    } else {
      // fallback: lump all keys into one Details section
      const allKeys = Object.keys(data).sort();
      groups = [{ title: 'Details', fields: allKeys }];
    }

    // render each group
    groups.forEach(group => {
      // group header row (full width)
      const headerRow = document.createElement('div');
      headerRow.className = 'col-12';
      const h = document.createElement('h6');
      h.className = 'fw-bold fs-6';
      h.style.color = '#13411F';
      h.textContent = group.title;
      headerRow.appendChild(h);
      container.appendChild(headerRow);

      // fields container for this group
      group.fields.forEach(key => {
        // if the key is formal_picture, skip here — handle below
        if (key === 'formal_picture') return;
        const el = makeReadonlyField(key, data[key]);
        container.appendChild(el);
      });

      // small spacer between groups
      const spacer = document.createElement('div');
      spacer.className = 'col-12';
      spacer.innerHTML = '<hr class="my-2">';
      container.appendChild(spacer);
    });

    // show image(s) if any (formal_picture)
    if (data.formal_picture) {
      const wrapper = document.createElement('div');
      wrapper.className = 'col-12';
      const cap = document.createElement('div');
      cap.className = 'mb-1 fw-bold';
      cap.textContent = 'Formal Picture';
      const img = document.createElement('img');
      img.src = data.formal_picture;
      img.alt = 'Formal Picture';
      img.style.maxWidth = '220px';
      img.style.maxHeight = '220px';
      img.className = 'img-thumbnail';
      // open in new tab when clicked
      img.style.cursor = 'pointer';
      img.onclick = () => window.open(data.formal_picture, '_blank');
      wrapper.appendChild(cap);
      wrapper.appendChild(img);
      imageContainer.appendChild(wrapper);
    }

    // finally show modal
    const vm = new bootstrap.Modal(document.getElementById('viewRequestModal'));
    vm.show();
  }

  document.addEventListener('click', async (evt) => {
    // VIEW button
    const viewBtn = evt.target.closest('.btn-view-request');
    if (viewBtn) {
      const tid = viewBtn.dataset.id;
      try {
        const res = await fetch(`functions/get_request.php?transaction_id=${encodeURIComponent(tid)}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`Server returned ${res.status}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'No data');
        populateViewModal(json.data || {});
      } catch (err) {
        const msg = 'Failed to load request details: ' + (err.message || err);
        if (document.getElementById('resultModal')) {
          document.getElementById('resultMessage').textContent = msg;
          new bootstrap.Modal(document.getElementById('resultModal')).show();
        } else {
          alert(msg);
        }
      }
      return;
    }

    // Accept button → open modal
    const acceptBtn = evt.target.closest('.btn-accept-request');
    if (acceptBtn) {
      document.getElementById('acceptTransactionId').value = acceptBtn.dataset.id;
      new bootstrap.Modal(document.getElementById('acceptRequestModal')).show();
      return;
    }

    // Reject button → open modal
    const rejectBtn = evt.target.closest('.btn-reject-request');
    if (rejectBtn) {
      document.getElementById('rejectTransactionId').value = rejectBtn.dataset.id;
      new bootstrap.Modal(document.getElementById('rejectRequestModal')).show();
      return;
    }
  });

  // --- Accept form submit ---
  const acceptForm = document.getElementById('acceptRequestForm');
  if (acceptForm) {
    acceptForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      // debug: show what's being sent
      const fd = new FormData(acceptForm);
      // uncomment next lines while debugging to see the payload in console:
      // for (const pair of fd.entries()) console.log('accept form', pair[0], pair[1]);

      try {
        const res = await fetch('functions/update_request_status.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        });

        // if server returns non-JSON or non-2xx, handle it
        if (!res.ok) {
          const text = await res.text();
          throw new Error(`Server error ${res.status}: ${text}`);
        }

        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Update failed');

        // success — close modal and reload (or update row in-place)
        const vmEl = document.getElementById('acceptRequestModal');
        const vm = bootstrap.Modal.getInstance(vmEl) || new bootstrap.Modal(vmEl);
        vm.hide();

        // optional: show result modal or toast; we'll reload to reflect change
        location.reload();
      } catch (err) {
        alert('Failed to update: ' + (err.message || err));
      }
    });
  }

  // --- Reject form submit ---
  document.getElementById('rejectRequestForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    try {
      const res = await fetch('functions/update_request_status.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Update failed');
      location.reload();
    } catch (err) {
      alert('Failed to update: ' + err.message);
    }
  });

  // --- View Preview for Requests (updated to support includeHeader) ---
  const viewReqModalEl = document.getElementById('previewRequestModal');
  const viewReqModal = new bootstrap.Modal(viewReqModalEl);
  const previewReqFrame = document.getElementById('requestPreviewFrame');
  const printReqBtn = document.getElementById('printRequestBtn');
  const downloadReqPDF = document.getElementById('downloadRequestPDF');
  const includeHeaderCheckbox = document.getElementById('includeHeader');

  // Helper: append includeHeader param (1 or 0)
  function withIncludeHeader(url, includeHeader) {
    try {
      const u = new URL(url, window.location.origin);
      u.searchParams.set('includeHeader', includeHeader ? '1' : '0');
      return u.toString();
    } catch (e) {
      const sep = url.includes('?') ? '&' : '?';
      return url + sep + 'includeHeader=' + (includeHeader ? '1' : '0');
    }
  }

  // Update iframe + print/download links
  function updatePreviewAndLinks(basePreviewUrl) {
    const include = includeHeaderCheckbox.checked;
    const previewUrl = withIncludeHeader(basePreviewUrl, include);

    // set iframe preview
    previewReqFrame.src = previewUrl;

    // prepare print URL (print=1)
    try {
      const u = new URL(previewUrl, window.location.origin);
      u.searchParams.set('print', '1');
      printReqBtn.dataset.printUrl = u.toString();
    } catch (e) {
      printReqBtn.dataset.printUrl = previewUrl + (previewUrl.includes('?') ? '&' : '?') + 'print=1';
    }

    // prepare download URL (download=1)
    try {
      const d = new URL(previewUrl, window.location.origin);
      d.searchParams.set('download', '1');
      downloadReqPDF.dataset.href = d.toString();
      downloadReqPDF.href = '#';
    } catch (e) {
      const dl = previewUrl + (previewUrl.includes('?') ? '&' : '?') + 'download=1';
      downloadReqPDF.dataset.href = dl;
      downloadReqPDF.href = '#';
    }
  }

  // normalize and set print/download enabled state
  function normalizeStatus(s) {
    if (!s && s !== '') return '';
    // Replace NBSP with space, collapse spaces, trim
    return String(s).replace(/\u00A0/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function statusIsAllowed(status, serviceType) {
      const norm = normalizeStatus(status).toLowerCase();

      // Treat these values as allowed to print/download
      const allowed = ['paid', 'free of charge', 'refund', 'refunded'];

      if (allowed.includes(norm)) return true;

      // Also check if status starts with "refund" (case-insensitive)
      if (norm.startsWith('refund')) return true;

      // Check if it contains "refund" anywhere (catches "Refund - reason")
      if (norm.includes('refund')) return true;

      // Also allow by service type, e.g. Indigency, First Time Job Seeker
      if (serviceType) {
          const normType = normalizeStatus(serviceType).toLowerCase();
          if (normType === 'indigency' || normType === 'first time job seeker') return true;
      }

      return false;
  }

  function setPrintDownloadStateByStatus(status, serviceType) {
    const allow = statusIsAllowed(status, serviceType);

    if (allow) {
      printReqBtn.removeAttribute('disabled');
      downloadReqPDF.removeAttribute('disabled');
    } else {
      printReqBtn.setAttribute('disabled', 'disabled');
      downloadReqPDF.setAttribute('disabled', 'disabled');
    }
  }

  // Click handlers for your "view" buttons
  document.querySelectorAll('.request-btn-view').forEach(btn => {
    btn.addEventListener('click', (ev) => {
      const tid = btn.getAttribute('data-id');
      // prefer explicit data-preview-url on the button; otherwise build default
      let baseUrl = btn.dataset.previewUrl || `functions/print_certificate.php?transaction_id=${encodeURIComponent(tid)}`;

      // sanitize baseUrl: remove includeHeader/print/download if present
      try {
        const u = new URL(baseUrl, window.location.origin);
        u.searchParams.delete('includeHeader');
        u.searchParams.delete('print');
        u.searchParams.delete('download');
        baseUrl = u.toString();
      } catch (e) {
        // if malformed or relative, try removing via string replace (lenient)
        baseUrl = baseUrl.replace(/([?&])(includeHeader|print|download)=[^&]*/g, '').replace(/\?&/, '?').replace(/[?&]$/, '');
      }

      // default: header NOT included
      includeHeaderCheckbox.checked = false;

      // store clean base URL on modal element for later use
      viewReqModalEl.dataset.basePreviewUrl = baseUrl;

      // update iframe + links with includeHeader=0
      updatePreviewAndLinks(baseUrl);

      // NEW: set print/download enablement based on data attribute
      const paymentStatus = btn.dataset.paymentStatus || '';
      const serviceType = btn.dataset.requestType || '';  // Variable is named serviceType
      // Temporary debug logging
      console.log('Payment Status:', paymentStatus, 'Service Type:', serviceType);
      console.log('Status Allowed:', statusIsAllowed(paymentStatus, serviceType));
      setPrintDownloadStateByStatus(paymentStatus, serviceType);  // Use serviceType here!

      // show modal
      viewReqModal.show();
    });
  });

  // When Include Header toggles, re-render using base URL
  includeHeaderCheckbox.addEventListener('change', () => {
    const base = viewReqModalEl.dataset.basePreviewUrl || previewReqFrame.src;
    if (!base) return;
    // clean base from any params (just in case)
    try {
      const u = new URL(base, window.location.origin);
      u.searchParams.delete('includeHeader');
      u.searchParams.delete('print');
      u.searchParams.delete('download');
      updatePreviewAndLinks(u.toString());
    } catch (e) {
      updatePreviewAndLinks(base);
    }
  });

  // Print action
  printReqBtn.addEventListener('click', () => {
    const url = printReqBtn.dataset.printUrl;
    if (url) {
      window.open(url, '_blank');
    } else {
      try {
        const u = new URL(previewReqFrame.src, window.location.origin);
        u.searchParams.set('print', '1');
        window.open(u.toString(), '_blank');
      } catch (e) {
        window.open(previewReqFrame.src, '_blank');
      }
    }

    // Close the modal and reload the page to reflect status change
    viewReqModal.hide();
    setTimeout(() => {
      location.reload();
    }, 500);
  });

  // Download action
  downloadReqPDF.addEventListener('click', (e) => {
    e.preventDefault();
    const href = downloadReqPDF.dataset.href || '';
    if (!href) return alert('Download URL not set.');

    window.open(href, '_blank');

    // Close the modal and reload the page to reflect status change
    viewReqModal.hide();
    setTimeout(() => {
      location.reload();
    }, 500);
  });

  // Clear iframe and reset state when modal hides
  viewReqModalEl.addEventListener('hidden.bs.modal', () => {
    printReqBtn.removeAttribute('disabled');
    downloadReqPDF.removeAttribute('disabled');
    previewReqFrame.src = '';
    includeHeaderCheckbox.checked = false;
    delete viewReqModalEl.dataset.basePreviewUrl;
    delete printReqBtn.dataset.printUrl;
    delete downloadReqPDF.dataset.href;
    downloadReqPDF.href = '#';
  });

  // Handle transaction type changes for Barangay ID
  document.addEventListener('change', (e) => {
    if (e.target && (e.target.id === 'txNew' || e.target.id === 'txRenewal')) {
      const isRenewal = document.getElementById('txRenewal').checked;
      const searchContainer = document.getElementById('renewalSearchContainer');
      
      // Define fields that should be readonly during renewal
      const editableFields = [
        'bid_first_name', 'bid_middle_name', 'bid_last_name',
        'bid_purok', 'bid_dob', 'bid_birth_place',
        'bid_civil_status', 'bid_religion', 'bid_height',
        'bid_weight', 'bid_emergency_contact', 'bid_emergency_address'
      ];
      
      if (isRenewal) {
        searchContainer.style.display = 'block';
        
        // Make fields readonly with inline styles
        editableFields.forEach(fieldId => {
          const field = document.getElementById(fieldId);
          if (field) {
            field.readOnly = true;
            field.style.backgroundColor = '#e9ecef';
            field.style.cursor = 'not-allowed';
          }
        });
        
        // Make religion select disabled with inline styles
        const religionSelect = document.getElementById('bid_religion');
        if (religionSelect) {
          religionSelect.disabled = true;
          religionSelect.style.backgroundColor = '#e9ecef';
          religionSelect.style.cursor = 'not-allowed';
        }
      } else {
        searchContainer.style.display = 'none';
        document.getElementById('renewalSearchInput').value = '';
        document.getElementById('renewalSearchResults').innerHTML = '';
        document.getElementById('renewalTransactionId').value = '';
        clearBarangayIDFields();
        
        // Make fields editable and remove inline styles
        editableFields.forEach(fieldId => {
          const field = document.getElementById(fieldId);
          if (field) {
            field.readOnly = false;
            field.style.backgroundColor = '';
            field.style.cursor = '';
          }
        });
        
        // Make religion select enabled and remove inline styles
        const religionSelect = document.getElementById('bid_religion');
        if (religionSelect) {
          religionSelect.disabled = false;
          religionSelect.style.backgroundColor = '';
          religionSelect.style.cursor = '';
        }
      }
    }
  });

  // Dynamic child fields for Solo Parent
  let childCounter = 1;

  document.addEventListener('click', (e) => {
    // Add child button
    if (e.target.closest('#addChildBtn')) {
      const container = document.getElementById('childrenContainer');
      const newChild = document.createElement('div');
      const today = new Date().toISOString().split('T')[0]; // Get today's date
      newChild.className = 'child-entry border rounded p-3 mb-3 position-relative';
      newChild.dataset.childIndex = childCounter;
      newChild.innerHTML = `
        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 remove-child-btn" style="z-index:10;">
          <span class="material-symbols-outlined" style="font-size:14px;">close</span>
        </button>
        <div class="row gy-2">
          <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Child's Full Name <span class="text-danger">*</span></label>
            <input name="children[${childCounter}][name]" type="text" class="form-control form-control-sm" required>
          </div>
          <div class="col-12 col-md-2">
            <label class="form-label fw-bold">Sex <span class="text-danger">*</span></label>
            <select name="children[${childCounter}][sex]" class="form-select form-select-sm" required>
              <option value="">Select…</option>
              <option>Male</option>
              <option>Female</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Birthdate <span class="text-danger">*</span></label>
            <input name="children[${childCounter}][birthdate]" type="date" class="form-control form-control-sm child-birthdate" max="${today}" required>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Age</label>
            <input name="children[${childCounter}][age_display]" type="text" class="form-control form-control-sm child-age-display" readonly style="background-color: #e9ecef;">
            <input name="children[${childCounter}][age]" type="hidden" class="child-age-value">
          </div>
        </div>
      `;
      container.appendChild(newChild);
      childCounter++;
    }

    // Remove child button
    if (e.target.closest('.remove-child-btn')) {
      const entry = e.target.closest('.child-entry');
      if (document.querySelectorAll('.child-entry').length > 1) {
        entry.remove();
      } else {
        alert('At least one child is required.');
      }
    }
  });

  // Dynamic child fields for Guardianship
  let guardianshipChildCounter = 1;

  document.addEventListener('click', (e) => {
    // Add guardianship child button
    if (e.target.closest('#addGuardianshipChildBtn')) {
      const container = document.getElementById('guardianshipChildrenContainer');
      const newChild = document.createElement('div');
      newChild.className = 'guardianship-child-entry border rounded p-3 mb-3 position-relative';
      newChild.dataset.childIndex = guardianshipChildCounter;
      newChild.innerHTML = `
        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 remove-guardianship-child-btn" style="z-index:10;">
          <span class="material-symbols-outlined" style="font-size:14px;">close</span>
        </button>
        <div class="row gy-2">
          <div class="col-12 col-md-6">
            <label class="form-label fw-bold">Child's Full Name <span class="text-danger">*</span></label>
            <input name="guardianship_children[${guardianshipChildCounter}][name]" type="text" class="form-control form-control-sm" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-bold">Relationship to Guardian <small class="fw-normal">(optional)</small></label>
            <input name="guardianship_children[${guardianshipChildCounter}][relationship]" type="text" class="form-control form-control-sm" placeholder="e.g., Son, Daughter, Nephew, Niece">
          </div>
        </div>
      `;
      container.appendChild(newChild);
      guardianshipChildCounter++;
    }

    // Remove guardianship child button
    if (e.target.closest('.remove-guardianship-child-btn')) {
      const entry = e.target.closest('.guardianship-child-entry');
      if (document.querySelectorAll('.guardianship-child-entry').length > 1) {
        entry.remove();
      } else {
        alert('At least one child is required.');
      }
    }
  });

  // --- Record Payment modal handler ---
  (function(){
    const recordModal = new bootstrap.Modal(document.getElementById('recordModal'));
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
        const or = btn.dataset.orNumber || '';
        const issued = btn.dataset.issuedDate || '';
        const amt = btn.dataset.amountPaid || '';

        tidInput.value = tid;
        pmInput.value = pm;
        pmHidden.value = pm;

        amtInput.value = amt;
        amtHidden.value = amt;

        orInput.value = or;
        issuedInput.value = issued;

        // show / hide GCASH ref field
        if (pm === 'GCash') {
          refRow.style.display = 'block';
          refInput.required = true;
        } else {
          refRow.style.display = 'none';
          refInput.required = false;
          refInput.value = '';
        }

        recordModal.show();
      });
    });

    // Confirm release modal handler (if not already present)
    const confirmModalEl = document.getElementById('confirmReleaseModal');
    if (confirmModalEl) {
      const confirmModal = new bootstrap.Modal(confirmModalEl);
      const confirmBtn = document.getElementById('confirmReleaseBtn');
      let pendingTid, pendingRow;

      document.querySelectorAll('.request-btn-release').forEach(btn => {
        btn.addEventListener('click', () => {
          pendingRow = btn.closest('tr');
          pendingTid = btn.dataset.transactionId;    // use the new data attribute
          pendingType = btn.dataset.requestType;     // capture request_type too

          if (!pendingTid || !pendingType) {
            return alert('Missing transaction id or request type');
          }

          document.getElementById('confirmReleaseMessage').textContent = 
            `Are you sure you want to mark ${pendingTid} (${pendingType}) as Released?`;
          confirmModal.show();
        });
      });

      if (confirmBtn) {
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
              // Show success message and reload
              const alertsDiv = document.getElementById('pageAlerts');
              if (alertsDiv) {
                const html = `
                  <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Record <strong>${pendingTid}</strong> has been marked as <strong>Released</strong>.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>
                `;
                alertsDiv.insertAdjacentHTML('beforeend', html);
              }
              
              // Reload to update the view (record will be hidden from current tab)
              setTimeout(() => location.reload(), 1500);
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
      }
    }
  })();

  // Barangay ID Renewal Search Functionality
  let renewalSearchTimeout;

  // document.addEventListener('change', (e) => {
  //   if (e.target && (e.target.id === 'txNew' || e.target.id === 'txRenewal')) {
  //     const isRenewal = document.getElementById('txRenewal').checked;
  //     const searchContainer = document.getElementById('renewalSearchContainer');
  //     const photoCheckbox = document.getElementById('requirePhotoCheck');
  //     const photoInput = document.getElementById('photoInput');
      
  //     if (isRenewal) {
  //       searchContainer.style.display = 'block';
  //       // For renewal, photo is optional by default
  //       if (photoCheckbox) {
  //         photoCheckbox.checked = false;
  //         photoInput.disabled = true;
  //       }
  //     } else {
  //       searchContainer.style.display = 'none';
  //       document.getElementById('renewalSearchInput').value = '';
  //       document.getElementById('renewalSearchResults').innerHTML = '';
  //       document.getElementById('renewalTransactionId').value = '';
  //       clearBarangayIDFields();
  //     }
  //   }
  // });

  document.addEventListener('input', (e) => {
    if (e.target && e.target.id === 'renewalSearchInput') {
      clearTimeout(renewalSearchTimeout);
      const searchTerm = e.target.value.trim();
      
      if (searchTerm.length < 2) {
        document.getElementById('renewalSearchResults').innerHTML = '';
        return;
      }
      
      renewalSearchTimeout = setTimeout(() => {
        fetch(`functions/search_barangay_id.php?search=${encodeURIComponent(searchTerm)}`)
          .then(res => res.json())
          .then(data => {
            const resultsContainer = document.getElementById('renewalSearchResults');
            resultsContainer.innerHTML = '';
            
            if (data.success && data.results.length > 0) {
              data.results.forEach(record => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = `${record.full_name} - ${record.transaction_id}`;
                item.onclick = () => populateBarangayIDFields(record);
                resultsContainer.appendChild(item);
              });
            } else {
              resultsContainer.innerHTML = '<div class="list-group-item">No records found</div>';
            }
          })
          .catch(err => console.error('Search error:', err));
      }, 300);
    }
  });

  function populateBarangayIDFields(record) {
    // Parse the full name
    const nameParts = record.full_name.split(',');
    let lastName = '', firstName = '', middleName = '';
    
    if (nameParts.length >= 2) {
      lastName = nameParts[0].trim();
      const firstMiddlePart = nameParts[1].trim();
      
      // Split first and middle name
      const firstMiddleArr = firstMiddlePart.split(' ');
      firstName = firstMiddleArr[0] || '';
      middleName = firstMiddleArr.slice(1).join(' ') || '';
    }
    
    // Populate fields
    document.getElementById('bid_first_name').value = firstName;
    document.getElementById('bid_middle_name').value = middleName;
    document.getElementById('bid_last_name').value = lastName;
    document.getElementById('bid_purok').value = record.purok || '';
    document.getElementById('bid_dob').value = record.birth_date || '';
    document.getElementById('bid_birth_place').value = record.birth_place || '';
    document.getElementById('bid_civil_status').value = record.civil_status || '';
    document.getElementById('bid_religion').value = record.religion || '';
    document.getElementById('bid_height').value = record.height || '';
    document.getElementById('bid_weight').value = record.weight || '';
    document.getElementById('bid_emergency_contact').value = record.emergency_contact_person || '';
    document.getElementById('bid_emergency_address').value = record.emergency_contact_address || '';
    
    // Store transaction ID and existing photo
    document.getElementById('renewalTransactionId').value = record.transaction_id;
    document.getElementById('bid_existing_photo').value = record.formal_picture || '';
    
    // Show current photo if exists
    if (record.formal_picture) {
      const photoNameDiv = document.getElementById('currentPhotoName');
      photoNameDiv.textContent = `Current photo: ${record.formal_picture}`;
      photoNameDiv.classList.remove('d-none');
    }
    
    // Clear search
    document.getElementById('renewalSearchInput').value = record.full_name;
    document.getElementById('renewalSearchResults').innerHTML = '';
  }

  function clearBarangayIDFields() {
    const fields = ['bid_first_name', 'bid_middle_name', 'bid_last_name',
                    'bid_purok', 'bid_dob', 'bid_birth_place', 'bid_civil_status', 
                    'bid_religion', 'bid_height', 'bid_weight', 'bid_emergency_contact', 
                    'bid_emergency_address'];
    fields.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    document.getElementById('renewalTransactionId').value = '';
    document.getElementById('bid_existing_photo').value = '';
    document.getElementById('currentPhotoName').classList.add('d-none');
  }

});

// ========== UNIVERSAL CAMERA FUNCTIONALITY ==========
function initCameraForRequest(config) {
  const {
    openCameraBtnId,
    uploadFileBtnId,
    fileInputId,
    cameraModalId,
    cameraStreamId,
    cameraViewId,
    previewViewId,
    photoCanvasId,
    cameraErrorId,
    captureBtnId,
    retakeBtnId,
    uploadPhotoBtnId,
    photoPreviewId,
    photoPreviewContainerId,
    photoFileName
  } = config;

  const openCameraBtn = document.getElementById(openCameraBtnId);
  const uploadFileBtn = document.getElementById(uploadFileBtnId);
  const fileInput = document.getElementById(fileInputId);
  const cameraModalElement = document.getElementById(cameraModalId);
  const cameraStream = document.getElementById(cameraStreamId);
  const cameraView = document.getElementById(cameraViewId);
  const previewView = document.getElementById(previewViewId);
  const photoCanvas = document.getElementById(photoCanvasId);
  const cameraError = document.getElementById(cameraErrorId);
  const captureBtn = document.getElementById(captureBtnId);
  const retakeBtn = document.getElementById(retakeBtnId);
  const uploadPhotoBtn = document.getElementById(uploadPhotoBtnId);
  const photoPreview = document.getElementById(photoPreviewId);
  const photoPreviewContainer = document.getElementById(photoPreviewContainerId);
  
  let stream = null;
  let cameraModal = null;

  // Check if elements exist
  if (!openCameraBtn || !fileInput) {
    console.warn(`Camera buttons not found for ${photoFileName}`);
    return;
  }

  // Initialize modal
  function initModal() {
    if (!cameraModal && cameraModalElement) {
      cameraModal = new bootstrap.Modal(cameraModalElement);
    }
    return cameraModal;
  }

  // Open camera
  openCameraBtn.addEventListener('click', async function() {
    const modal = initModal();
    if (!modal) {
      console.error('Camera modal not found');
      return;
    }
    
    modal.show();
    cameraView.classList.remove('d-none');
    previewView.classList.add('d-none');
    cameraError.classList.add('d-none');
    
    try {
      // Check if mediaDevices is supported
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('Camera access is not supported in this browser.');
      }
      
      stream = await navigator.mediaDevices.getUserMedia({ 
        video: { facingMode: 'user', width: 1280, height: 720 } 
      });
      cameraStream.srcObject = stream;
      
      // Wait for video to be ready
      await new Promise((resolve, reject) => {
        cameraStream.onloadedmetadata = () => {
          cameraStream.play()
            .then(resolve)
            .catch(reject);
        };
        // Timeout after 10 seconds
        setTimeout(() => reject(new Error('Camera timeout')), 10000);
      });
      
    } catch (err) {
      let errorMessage = 'Unable to access camera. ';
      
      if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        errorMessage += 'Camera permission was denied. Please allow camera access in your browser settings.';
      } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        errorMessage += 'No camera device found. Please connect a camera and try again.';
      } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
        errorMessage += 'Camera is already in use by another application. Please close other apps using the camera.';
      } else if (err.message && err.message.includes('not supported')) {
        errorMessage += 'Camera access is not supported in this browser. Please use the upload option instead.';
      } else {
        errorMessage += 'Please check permissions or use the upload option. Error: ' + (err.message || 'Unknown error');
      }
      
      cameraError.textContent = errorMessage;
      cameraError.classList.remove('d-none');
      console.error('Camera error:', err);
      
      // Disable capture button if camera failed
      if (captureBtn) {
        captureBtn.disabled = true;
      }
    }
  });

  // Capture photo
  captureBtn.addEventListener('click', function() {
    // Check if camera stream is active and ready
    if (!stream || !cameraStream.srcObject || cameraStream.readyState !== cameraStream.HAVE_ENOUGH_DATA) {
      cameraError.textContent = 'Camera not ready. Please wait for the camera to load or refresh and try again.';
      cameraError.classList.remove('d-none');
      return;
    }

    const context = photoCanvas.getContext('2d');
    const videoWidth = cameraStream.videoWidth;
    const videoHeight = cameraStream.videoHeight;
    
    // Validate video dimensions
    if (!videoWidth || !videoHeight || videoWidth === 0 || videoHeight === 0) {
      cameraError.textContent = 'Camera feed is not available. Please check camera permissions and try again.';
      cameraError.classList.remove('d-none');
      return;
    }
    
    const minDimension = Math.min(videoWidth, videoHeight);
    const cropX = (videoWidth - minDimension) / 2;
    const cropY = (videoHeight - minDimension) / 2;
    const outputSize = 800;
    
    photoCanvas.width = outputSize;
    photoCanvas.height = outputSize;
    
    try {
      context.drawImage(cameraStream, cropX, cropY, minDimension, minDimension, 0, 0, outputSize, outputSize);
    } catch (err) {
      cameraError.textContent = 'Failed to capture photo. Please try again.';
      cameraError.classList.remove('d-none');
      console.error('Capture error:', err);
      return;
    }
    
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
    }
    
    cameraView.classList.add('d-none');
    previewView.classList.remove('d-none');
  });

  // Retake photo
  retakeBtn.addEventListener('click', async function() {
    previewView.classList.add('d-none');
    cameraView.classList.remove('d-none');
    
    try {
      stream = await navigator.mediaDevices.getUserMedia({ 
        video: { facingMode: 'user', width: 1280, height: 720 } 
      });
      cameraStream.srcObject = stream;
    } catch (err) {
      cameraError.textContent = 'Unable to restart camera.';
      cameraError.classList.remove('d-none');
    }
  });

  // Use captured photo
  uploadPhotoBtn.addEventListener('click', function() {
    // Validate that canvas has actual image data
    const context = photoCanvas.getContext('2d');
    const imageData = context.getImageData(0, 0, photoCanvas.width, photoCanvas.height);
    const hasData = imageData.data.some(channel => channel !== 0);
    
    if (!hasData) {
      alert('No photo captured. Please take a photo first.');
      return;
    }
    
    photoCanvas.toBlob(function(blob) {
      if (!blob) {
        alert('Failed to process photo. Please try again.');
        return;
      }
      
      const file = new File([blob], photoFileName, { type: 'image/jpeg' });
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      fileInput.files = dataTransfer.files;
      
      photoPreview.src = URL.createObjectURL(blob);
      photoPreviewContainer.classList.remove('d-none');
      
      if (cameraModal) {
        cameraModal.hide();
      }
    }, 'image/jpeg', 0.9);
  });

  // Upload file button - only if it exists
  if (uploadFileBtn) {
    uploadFileBtn.addEventListener('click', function() {
      fileInput.click();
    });
  }

  // Handle file input change
  fileInput.addEventListener('change', function(e) {
    if (e.target.files && e.target.files[0]) {
      const reader = new FileReader();
      reader.onload = function(event) {
        photoPreview.src = event.target.result;
        photoPreviewContainer.classList.remove('d-none');
      };
      reader.readAsDataURL(e.target.files[0]);
    }
  });

  // Clean up camera when modal closes
  if (cameraModalElement) {
    cameraModalElement.addEventListener('hidden.bs.modal', function() {
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
      }
      if (cameraStream) {
        cameraStream.srcObject = null;
      }
      // Re-enable capture button for next time
      if (captureBtn) {
        captureBtn.disabled = false;
      }
      // Hide error message
      if (cameraError) {
        cameraError.classList.add('d-none');
      }
    });
  }
}

// ========== INITIALIZE SPECIFIC CAMERAS ==========
function initBarangayIDCamera() {
  initCameraForRequest({
    openCameraBtnId: 'openCameraBtnBID',
    uploadFileBtnId: null,
    fileInputId: 'photoInput',
    cameraModalId: 'cameraModalBID',
    cameraStreamId: 'cameraStreamBID',
    cameraViewId: 'cameraViewBID',
    previewViewId: 'previewViewBID',
    photoCanvasId: 'photoCanvasBID',
    cameraErrorId: 'cameraErrorBID',
    captureBtnId: 'captureBtnBID',
    retakeBtnId: 'retakeBtnBID',
    uploadPhotoBtnId: 'uploadPhotoBtnBID',
    photoPreviewId: 'photoPreviewBID',
    photoPreviewContainerId: 'photoPreviewContainerBID',
    photoFileName: 'barangay-id-photo.jpg'
  });
}

function initClearanceCamera() {
  initCameraForRequest({
    openCameraBtnId: 'openCameraBtnClearance',
    uploadFileBtnId: null,
    fileInputId: 'clearance_photoInput',
    cameraModalId: 'cameraModalClearance',
    cameraStreamId: 'cameraStreamClearance',
    cameraViewId: 'cameraViewClearance',
    previewViewId: 'previewViewClearance',
    photoCanvasId: 'photoCanvasClearance',
    cameraErrorId: 'cameraErrorClearance',
    captureBtnId: 'captureBtnClearance',
    retakeBtnId: 'retakeBtnClearance',
    uploadPhotoBtnId: 'uploadPhotoBtnClearance',
    photoPreviewId: 'photoPreviewClearance',
    photoPreviewContainerId: 'photoPreviewContainerClearance',
    photoFileName: 'clearance-photo.jpg'
  });
}

function initBusinessCamera() {
  initCameraForRequest({
    openCameraBtnId: 'openCameraBtnBusiness',
    uploadFileBtnId: null,
    fileInputId: 'business_photoInput',
    cameraModalId: 'cameraModalBusiness',
    cameraStreamId: 'cameraStreamBusiness',
    cameraViewId: 'cameraViewBusiness',
    previewViewId: 'previewViewBusiness',
    photoCanvasId: 'photoCanvasBusiness',
    cameraErrorId: 'cameraErrorBusiness',
    captureBtnId: 'captureBtnBusiness',
    retakeBtnId: 'retakeBtnBusiness',
    uploadPhotoBtnId: 'uploadPhotoBtnBusiness',
    photoPreviewId: 'photoPreviewBusiness',
    photoPreviewContainerId: 'photoPreviewContainerBusiness',
    photoFileName: 'business-photo.jpg'
  });
}

// ========== AGE CALCULATION HELPER ==========
function calculateAge(birthdate) {
  if (!birthdate) return { display: '', value: 0 };
  
  const birth = new Date(birthdate);
  const today = new Date();
  
  // Calculate differences
  let years = today.getFullYear() - birth.getFullYear();
  let months = today.getMonth() - birth.getMonth();
  let days = today.getDate() - birth.getDate();
  
  // Adjust for negative days
  if (days < 0) {
    months--;
    const lastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
    days += lastMonth.getDate();
  }
  
  // Adjust for negative months
  if (months < 0) {
    years--;
    months += 12;
  }
  
  // Determine display format
  let display = '';
  let value = 0;
  
  if (years > 0) {
    // Show years (with proper singular/plural)
    const yearText = years === 1 ? 'year' : 'years';
    display = `${years} ${yearText} old`;
    value = years;
  } else if (months > 0) {
    // Show months (with proper singular/plural)
    const monthText = months === 1 ? 'month' : 'months';
    display = `${months} ${monthText} old`;
    value = months / 12; // Convert to fractional years for storage
  } else if (days >= 7) {
    // Show weeks (with proper singular/plural)
    const weeks = Math.floor(days / 7);
    const weekText = weeks === 1 ? 'week' : 'weeks';
    display = `${weeks} ${weekText} old`;
    value = weeks / 52; // Convert to fractional years for storage
  } else {
    // Show days (with proper singular/plural)
    const dayText = days === 1 ? 'day' : 'days';
    display = `${days} ${dayText} old`;
    value = days / 365; // Convert to fractional years for storage
  }
  
  return { display, value };
}

// ========== SET MAX DATE TO TODAY (Prevent Future Dates) ==========
document.addEventListener('DOMContentLoaded', () => {
  // Set max date to today for all birthdate inputs
  const today = new Date().toISOString().split('T')[0];
  
  // Function to set max date on birthdate inputs
  function setMaxDateOnBirthdates() {
    document.querySelectorAll('.child-birthdate').forEach(input => {
      input.setAttribute('max', today);
    });
  }
  
  // Set initially
  setMaxDateOnBirthdates();
  
  // Also set when modal opens (for dynamically added children)
  const addRequestModal = document.getElementById('addRequestModal');
  if (addRequestModal) {
    addRequestModal.addEventListener('shown.bs.modal', setMaxDateOnBirthdates);
  }
});

// Validate birthdate on change
document.addEventListener('change', (e) => {
  if (e.target && e.target.classList.contains('child-birthdate')) {
    const selectedDate = new Date(e.target.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Reset time to start of day
    
    if (selectedDate > today) {
      alert('Birthdate cannot be in the future. Please select a valid date.');
      e.target.value = ''; // Clear the invalid date
      
      // Clear the age display fields
      const container = e.target.closest('.child-entry');
      if (container) {
        const ageDisplay = container.querySelector('.child-age-display');
        const ageValue = container.querySelector('.child-age-value');
        if (ageDisplay) ageDisplay.value = '';
        if (ageValue) ageValue.value = '';
      }
      return;
    }
    
    // If valid, calculate age normally
    const container = e.target.closest('.child-entry');
    if (container) {
      const ageDisplay = container.querySelector('.child-age-display');
      const ageValue = container.querySelector('.child-age-value');
      const birthdate = e.target.value;
      
      const ageResult = calculateAge(birthdate);
      if (ageDisplay) ageDisplay.value = ageResult.display;
      if (ageValue) ageValue.value = ageResult.value;
    }
  }
});

// Auto-calculate age when birthdate changes
document.addEventListener('change', (e) => {
  if (e.target && e.target.classList.contains('child-birthdate')) {
    const container = e.target.closest('.child-entry');
    if (container) {
      const ageDisplay = container.querySelector('.child-age-display');
      const ageValue = container.querySelector('.child-age-value');
      const birthdate = e.target.value;
      
      const ageResult = calculateAge(birthdate);
      if (ageDisplay) ageDisplay.value = ageResult.display;
      if (ageValue) ageValue.value = ageResult.value;
    }
  }
});
</script>
