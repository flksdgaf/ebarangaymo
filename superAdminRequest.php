<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];
$newTid    = $_GET['transaction_id'] ?? '';

// ── 0) FILTER SETUP ──────────────────────────────────────────────────────────
$request_type    = $_GET['request_type']    ?? '';
$date_from       = $_GET['date_from']       ?? '';
$date_to         = $_GET['date_to']         ?? '';
$payment_method  = $_GET['payment_method']  ?? '';
$payment_status  = $_GET['payment_status']  ?? '';
$document_status = $_GET['document_status'] ?? '';

$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

// ── 1) GLOBAL SEARCH ─────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
  $whereClauses[] = "("
    . "transaction_id   LIKE ? OR "
    . "full_name        LIKE ? OR "
    . "request_type     LIKE ? OR "
    . "payment_method   LIKE ? OR "
    . "payment_status   LIKE ? OR "
    . "document_status  LIKE ?"
    . ")";
  $bindTypes .= str_repeat('s', 6);
  $term = "{$search}%";
  for ($i = 0; $i < 6; $i++) {
    $bindParams[] = $term;
  }
}

if ($request_type) {
  $whereClauses[] = 'request_type = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $request_type;
}
if ($payment_method) {
  $whereClauses[] = 'payment_method = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $payment_method;
}
if ($payment_status) {
  $whereClauses[] = 'payment_status = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $payment_status;
}
if ($document_status) {
  $whereClauses[] = 'document_status = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $document_status;
}
if ($date_from && $date_to) {
  $whereClauses[] = 'DATE(created_at) BETWEEN ? AND ?';
  $bindTypes    .= 'ss';
  $bindParams[]  = $date_from;
  $bindParams[]  = $date_to;
} elseif ($date_from) {
  $whereClauses[] = 'DATE(created_at) >= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $date_from;
} elseif ($date_to) {
  $whereClauses[] = 'DATE(created_at) <= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $date_to;
}

$whereSQL = $whereClauses
  ? 'WHERE ' . implode(' AND ', $whereClauses)
  : '';

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
  $q = $conn->prepare("
    SELECT request_type
      FROM view_general_requests
     WHERE transaction_id = ?
     LIMIT 1
  ");
  $q->bind_param('s', $newTid);
  $q->execute();
  $r = $q->get_result()->fetch_assoc();
  if ($r) {
    $newType = $r['request_type'];
  }
  $q->close();
}

// ── 2) LIST + FILTERED QUERY ─────────────────────────────────────────────────
$sql = "
  SELECT transaction_id,
         full_name,
         request_type,
         payment_method,
         payment_status,
         document_status,
         DATE_FORMAT(created_at, '%M %d, %Y') AS formatted_date
    FROM view_general_requests
    {$whereSQL}
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";
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

<div class="container py-3">
  <?php if ($newTid && $newType): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($newType) ?> request 
      <strong><?= htmlspecialchars($newTid) ?></strong> added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <div class="card shadow-sm p-3">
    <!-- 2a) SEARCH FORM -->
    <div class="d-flex align-items-center mb-3">
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
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
                <option <?= $request_type==='Certification'?'selected':''?> value="Certification">Certification</option>
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
                <option <?= $payment_method==='GCash'?'selected':''?>          value="GCash">GCash</option>
                <option <?= $payment_method==='Brgy Payment Device'?'selected':''?> value="Brgy Payment Device">Brgy Payment Device</option>
                <option <?= $payment_method==='Over-the-Counter'?'selected':''?>    value="Over-the-Counter">Over-the-Counter</option>
              </select>
            </div>

            <!-- Payment Status -->
            <div class="mb-2">
              <label class="form-label mb-1">Payment Status</label>
              <select name="payment_status" class="form-select form-select-sm" style="font-size:.75rem;">
                <option value="">All</option>
                <option <?= $payment_status==='Paid'?'selected':''?>   value="Paid">Paid</option>
                <option <?= $payment_status==='Unpaid'?'selected':''?> value="Unpaid">Unpaid</option>
              </select>
            </div>

            <!-- Document Status -->
            <div class="mb-2">
              <label class="form-label mb-1">Document Status</label>
              <select name="document_status" class="form-select form-select-sm" style="font-size:.75rem;">
                <option value="">All</option>
                <option <?= $document_status==='For Verification'?'selected':''?> value="For Verification">For Verification</option>
                <option <?= $document_status==='Processing'?'selected':''?>       value="Processing">Processing</option>
                <option <?= $document_status==='Ready To Release'?'selected':''?> value="Ready To Release">Ready To Release</option>
                <option <?= $document_status==='Released'?'selected':''?>         value="Released">Released</option>
                <option <?= $document_status==='Rejected'?'selected':''?>         value="Rejected">Rejected</option>
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
          <i class="bi bi-plus-lg me-1"></i> Add New Request
        </button>
        <ul class="dropdown-menu" aria-labelledby="addRequestDropdown">
          <?php foreach (['Barangay ID','Business Permit','Certification'] as $type): ?>
            <li>
              <button
                type="button"
                class="dropdown-item request-trigger"
                data-type="<?= $type ?>"
              ><?= $type ?></button>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <form method="get" id="searchForm" class="d-flex ms-auto me-2">
      <!-- preserve pagination & filters -->
      <input type="hidden" name="page"     value="superAdminRequest">
      <input type="hidden" name="page_num" value="1">
      <?php foreach (['request_type','date_from','date_to','payment_method','payment_status','document_status'] as $f): 
          if (!empty($_GET[$f])): ?>
          <input type="hidden" name="<?= $f?>" value="<?= htmlspecialchars($_GET[$f]) ?>">
      <?php endif; endforeach; ?>

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

    <div class="table-responsive admin-table" style="height:500px; overflow-y:auto;">
      <table class="table table-hover align-middle text-start">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">Transaction No.</th>
            <th class="text-nowrap">Name</th>
            <th class="text-nowrap">Request</th>
            <th class="text-nowrap">Payment Method</th>
            <th class="text-nowrap">Payment Status</th>
            <th class="text-nowrap">Document Status</th>
            <th class="text-nowrap">Created At</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr class="clickable-row" data-tid="<?= htmlspecialchars($row['transaction_id']) ?>">
                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['request_type']) ?></td>
                <td><?= htmlspecialchars($row['payment_method']) ?></td>
                <td><?= htmlspecialchars($row['payment_status']) ?></td>
                <td><?= htmlspecialchars($row['document_status']) ?></td>
                <td><?= $row['formatted_date'] ?></td>
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

          <!-- Next Button -->
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_num' => $page + 1])) ?>">Next</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>
    </div>
  </div>

  <!-- Details Modal -->
  <div class="modal fade" id="rowModal" tabindex="-1" aria-labelledby="rowModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content shadow-lg">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title" id="rowModalLabel">
            <i class="bi bi-card-list me-2"></i>Request Details
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- Basic Information -->
          <div class="mb-4">
            <h6 class="fw-bold fs-5 text-secondary">Basic Information</h6>
            <dl class="row">
              <dt class="col-sm-4">Transaction No.</dt>
              <dd class="col-sm-8" id="modal-transaction_id">—</dd>
              <dt class="col-sm-4">Name</dt>
              <dd class="col-sm-8" id="modal-full_name">—</dd>
              <dt class="col-sm-4">Request Type</dt>
              <dd class="col-sm-8" id="modal-request_type">—</dd>
            </dl>
          </div>
          <!-- Dates -->
          <div class="mb-4">
            <h6 class="fw-bold fs-5 text-secondary">Dates</h6>
            <dl class="row">
              <dt class="col-sm-4">Created At</dt>
              <dd class="col-sm-8" id="modal-created_at">—</dd>
              <dt class="col-sm-4">Claim Date</dt>
              <dd class="col-sm-8" id="modal-claim_date">—</dd>
            </dl>
          </div>
          <!-- Payment & Status -->
          <div>
            <h6 class="fw-bold fs-5 text-secondary">Payment & Status</h6>
            <dl class="row">
              <dt class="col-sm-4">Payment Method</dt>
              <dd class="col-sm-8" id="modal-payment_method">—</dd>
              <dt class="col-sm-4">Payment Status</dt>
              <dd class="col-sm-8" id="modal-payment_status">—</dd>
              <dt class="col-sm-4">Document Status</dt>
              <dd class="col-sm-8" id="modal-document_status">—</dd>
            </dl>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Universal “Add New Request” Modal -->
  <div class="modal fade" id="addRequestModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content shadow-sm">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title">
            <i class="bi bi-plus-lg me-2"></i>
            <span id="addRequestModalTitle">New Request</span>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form id="addRequestForm" action="functions/serviceBarangayID_submit.php" method="POST" enctype="multipart/form-data">
          <div class="modal-body" id="addRequestModalBody">
            <!-- fields will be injected here -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Templates for Barangay ID -->
  <template data-type="Barangay ID">
    <!-- core hidden value -->
    <input type="hidden" name="request_type" value="Barangay ID">

    <!-- Type of Transaction -->
    <div class="mb-3">
      <label class="form-label">Type of Transaction</label>
      <select name="transactiontype" class="form-select" required>
        <option value="New Application">New Application</option>
        <option value="Renewal">Renewal</option>
      </select>
    </div>

    <!-- Full Name -->
    <div class="mb-3">
      <label class="form-label">Full Name</label>
      <input name="fullname" type="text" class="form-control" required>
    </div>

    <!-- Full Address -->
    <div class="mb-3">
      <label class="form-label">Full Address</label>
      <input name="address" type="text" class="form-control" required>
    </div>

    <!-- Height & Weight -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Height (cm)</label>
        <input name="height" type="number" class="form-control" min="0" required>
      </div>
      <div class="col">
        <label class="form-label">Weight (kg)</label>
        <input name="weight" type="number" class="form-control" min="0" required>
      </div>
    </div>

    <!-- Birthdate & Birthplace -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Birthday</label>
        <input name="birthday" type="date" class="form-control" required>
      </div>
      <div class="col">
        <label class="form-label">Birthplace</label>
        <input name="birthplace" type="text" class="form-control" required>
      </div>
    </div>

    <!-- Civil Status & Religion -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Civil Status</label>
        <select name="civilstatus" class="form-select" required>
          <option value="">Select…</option>
          <option>Single</option>
          <option>Married</option>
          <option>Divorced</option> 
          <option>Separated</option>
          <option>Widowed</option>
        </select>
      </div>
      <div class="col">
        <label class="form-label">Religion</label>
        <input name="religion" type="text" class="form-control" required>
      </div>
    </div>

    <!-- Contact Person -->
    <div class="mb-3">
      <label class="form-label">Contact Person</label>
      <input name="contactperson" type="text" class="form-control" required>
    </div>

    <!-- Formal Picture -->
    <div class="mb-3">
      <label class="form-label">1x1 Formal Picture</label>
      <input name="brgyIDpicture" type="file" class="form-control" accept="image/*" required>
    </div>

    <!-- Preferred Claim Date -->
    <div class="mb-3">
      <label class="form-label">Preferred Claim Date</label>
      <input name="claimdate" type="date" class="form-control" required>
    </div>

    <!-- Payment Method -->
    <div class="mb-3">
      <label class="form-label">Payment Method</label>
      <select name="paymentMethod" class="form-select" required>
        <option value="GCash">GCash</option>
        <option value="Brgy Payment Device">Brgy Payment Device</option>
        <option value="Over-the-Counter">Over-the-Counter</option>
      </select>
    </div>
  </template>
  
  <!-- Templates for Business Permit -->
  <template data-type="Business Permit">
    <!-- core hidden value -->
    <input type="hidden" name="request_type" value="Business Permit">

    <!-- Transaction Type -->
    <div class="mb-3">
      <label class="form-label">Transaction Type</label>
      <select name="transactiontype" class="form-select" required>
        <option value="New Application">New Application</option>
        <option value="Renewal">Renewal</option>
      </select>
    </div>

    <!-- Full Name -->
    <div class="mb-3">
      <label class="form-label">Full Name</label>
      <input name="fullname" type="text" class="form-control" required>
    </div>

    <!-- Full Address -->
    <div class="mb-3">
      <label class="form-label">Full Address</label>
      <input name="address" type="text" class="form-control" required>
    </div>

    <!-- Civil Status -->
    <div class="mb-3">
      <label class="form-label">Civil Status</label>
      <select name="civilstatus" class="form-select" required>
        <option value="">Select…</option>
        <option>Single</option>
        <option>Married</option>
        <option>Divorced</option>
        <option>Separated</option>
        <option>Widowed</option>
      </select>
    </div>

    <!-- Purok & Barangay -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Purok</label>
        <input name="purok" type="text" class="form-control" required>
      </div>
      <div class="col">
        <label class="form-label">Barangay</label>
        <input name="barangay" type="text" class="form-control" required>
      </div>
    </div>

    <!-- Age & Preferred Claim Date -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Age</label>
        <input name="age" type="number" class="form-control" min="0" required>
      </div>
      <div class="col">
        <label class="form-label">Preferred Claim Date</label>
        <input name="claimdate" type="date" class="form-control" required>
      </div>
    </div>

    <!-- Business Name & Type of Business -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Name of Business</label>
        <input name="business_name" type="text" class="form-control" required>
      </div>
      <div class="col">
        <label class="form-label">Type of Business</label>
        <input name="business_type" type="text" class="form-control" required>
      </div>
    </div>

    <!-- Payment Method -->
    <div class="mb-3">
      <label class="form-label">Payment Method</label>
      <select name="paymentMethod" class="form-select" required>
        <option value="GCash">GCash</option>
        <option value="Brgy Payment Device">Brgy Payment Device</option>
        <option value="Over-the-Counter">Over-the-Counter</option>
      </select>
    </div>
  </template>

  <template data-type="Certification">
    <!-- core hidden value -->
    <input type="hidden" name="request_type" value="Certification">

    <!-- Transaction Type -->
    <div class="mb-3">
      <label class="form-label">Transaction Type</label>
      <select name="transactiontype" class="form-select" required>
        <option value="New Application">New Application</option>
        <option value="Renewal">Renewal</option>
      </select>
    </div>

    <!-- Full Name -->
    <div class="mb-3">
      <label class="form-label">Full Name</label>
      <input name="fullname" type="text" class="form-control" required>
    </div>

    <!-- Street & Purok -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Street</label>
        <input name="street" type="text" class="form-control" required>
      </div>
      <div class="col">
        <label class="form-label">Purok</label>
        <input name="purok" type="text" class="form-control" required>
      </div>
    </div>

    <!-- Birthdate & Birthplace -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Birthdate</label>
        <input name="birthdate" type="date" class="form-control" required>
      </div>
      <div class="col">
        <label class="form-label">Birthplace</label>
        <input name="birthplace" type="text" class="form-control" required>
      </div>
    </div>

    <!-- Age & Civil Status -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Age</label>
        <input name="age" type="number" class="form-control" min="0" required>
      </div>
      <div class="col">
        <label class="form-label">Civil Status</label>
        <select name="civilstatus" class="form-select" required>
          <option value="">Select…</option>
          <option>Single</option>
          <option>Married</option>
          <option>Divorced</option>
          <option>Separated</option>
          <option>Widowed</option>
        </select>
      </div>
    </div>

    <!-- Certification Purpose -->
    <div class="mb-3">
      <label class="form-label">Certification Purpose</label>
      <input name="purpose" type="text" class="form-control" required>
    </div>

    <!-- Preferred Claim Date & Payment Method -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Preferred Claim Date</label>
        <input name="claimdate" type="date" class="form-control" required>
      </div>
      <div class="col">
        <label class="form-label">Payment Method</label>
        <select name="paymentMethod" class="form-select" required>
          <option value="GCash">GCash</option>
          <option value="Brgy Payment Device">Brgy Payment Device</option>
          <option value="Over-the-Counter">Over-the-Counter</option>
        </select>
      </div>
    </div>
  </template>
</div>

<?php
$st->close();
$conn->close();
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // ——— Cache DOM nodes —————————————————————————————————————————————
  const searchForm     = document.getElementById('searchForm');
  const searchInput    = document.getElementById('searchInput');
  const searchBtn      = document.getElementById('searchBtn');
  const modalEl        = document.getElementById('addRequestModal');
  const bsModal        = new bootstrap.Modal(modalEl);
  const titleElem      = document.getElementById('addRequestModalTitle');
  const bodyElem       = document.getElementById('addRequestModalBody');
  const formElem       = document.getElementById('addRequestForm');

  const hasSearch      = <?= json_encode(!empty($search)) ?>;

  // ——— Helper: clear+submit search ——————————————————————————————————————
  const handleSearch = () => {
    if (hasSearch) searchInput.value = '';
    searchForm.submit();
  };
  searchBtn.addEventListener('click', handleSearch);

  // ——— Helper: open request modal —————————————————————————————————————
  const openRequestModal = (type) => {
    // title
    titleElem.textContent = `${type} Form`;

    // inject fields
    const tpl = document.querySelector(`template[data-type="${type}"]`);
    bodyElem.innerHTML = '';
    bodyElem.appendChild(tpl.content.cloneNode(true));

    // if this is coming from super-admin, tack on the hidden flag
    if (['Barangay ID','Business Permit','Certification'].includes(type)) {
      const flag = document.createElement('input');
      flag.type  = 'hidden';
      flag.name  = 'superAdminRedirect';
      flag.value = '1';
      bodyElem.prepend(flag);
    }


    // point at the correct handler
    if (type === 'Barangay ID') {
      formElem.action = 'functions/serviceBarangayID_submit.php';
    } 
    else if (type === 'Business Permit') {
      formElem.action = 'functions/serviceBusinessPermit_submit.php';
    } 
    else if (type === 'Certification') {
      formElem.action = 'functions/serviceCertification_submit.php';
    } 
    else {
      console.warn('Unknown request type:', type);
    }
    
    bsModal.show();
  };

  // ——— Wire up all “Add New Request” buttons —————————————————————————
  document.querySelectorAll('.request-trigger').forEach(btn => {
    btn.addEventListener('click', () => {
      openRequestModal(btn.dataset.type);
    });
  });
});
</script>
