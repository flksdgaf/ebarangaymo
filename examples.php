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

<div>
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
          <?php foreach (['Barangay ID','Business Permit','Good Moral','Guardianship','Indigency','Residency','Solo Parent'] as $type): ?> <!-- 'Equipment Borrowing' -->
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
              <tr data-id="<?= htmlspecialchars($row['transaction_id']) ?>" data-type="<?= htmlspecialchars($row['request_type']) ?>" style="cursor:pointer;"> 
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

  <template data-type="Residency">
    <!-- core hidden value -->
    <input type="hidden" name="request_type" value="Residency">

    <!-- Full Name -->
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input name="name" type="text" class="form-control" required>
    </div>

    <!-- Age & Civil Status -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Age</label>
        <input name="age" type="number" class="form-control" min="0" required>
      </div>
      <div class="col">
        <label class="form-label">Civil Status</label>
        <select name="civil_status" class="form-select" required>
          <option value="" selected>Select…</option>
          <option>Single</option>
          <option>Married</option>
          <option>Divorced</option>
          <option>Separated</option>
          <option>Widowed</option>
        </select>
      </div>
    </div>

    <!-- Purok & Residing Years -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Purok</label>
        <input name="purok" type="text" class="form-control" required>
      </div>
      <div class="col">
        <label class="form-label">Years Residing Here</label>
        <input name="residing_years" type="number" class="form-control" min="0" required>
      </div>
    </div>

    <!-- Preferred Claim Date -->
    <div class="mb-3">
      <label class="form-label">Claim Date</label>
      <input name="claim_date" type="date" class="form-control" required>
    </div>

    <!-- Purpose -->
    <div class="mb-3">
      <label class="form-label">Purpose</label>
      <textarea name="purpose" class="form-control" rows="2" required></textarea>
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
  </template>

  <template data-type="Indigency">
    <!-- core hidden value -->
    <input type="hidden" name="request_type" value="Indigency">

    <!-- Full Name -->
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input name="name" type="text" class="form-control" required>
    </div>

    <!-- Age & Civil Status -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Age</label>
        <input name="age" type="number" class="form-control" min="0" required>
      </div>
      <div class="col">
        <label class="form-label">Civil Status</label>
        <select name="civil_status" class="form-select" required>
          <option value="" selected>Select…</option>
          <option>Single</option>
          <option>Married</option>
          <option>Divorced</option>
          <option>Separated</option>
          <option>Widowed</option>
        </select>
      </div>
    </div>

    <!-- Purok -->
    <div class="mb-3">
      <label class="form-label">Purok</label>
      <input name="purok" type="text" class="form-control" required>
    </div>

    <!-- Preferred Claim Date -->
    <div class="mb-3">
      <label class="form-label">Claim Date</label>
      <input name="claim_date" type="date" class="form-control" required>
    </div>

    <!-- Purpose -->
    <div class="mb-3">
      <label class="form-label">Purpose</label>
      <textarea name="purpose" class="form-control" rows="2" required></textarea>
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
  </template>

  <template data-type="Good Moral">
    <!-- core hidden value -->
    <input type="hidden" name="request_type" value="Good Moral">

    <!-- Full Name -->
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input name="name" type="text" class="form-control" required>
    </div>

    <!-- Age & Civil Status -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Age</label>
        <input name="age" type="number" class="form-control" min="0" required>
      </div>
      <div class="col">
        <label class="form-label">Civil Status</label>
        <select name="civil_status" class="form-select" required>
          <option value="" selected>Select…</option>
          <option>Single</option>
          <option>Married</option>
          <option>Divorced</option>
          <option>Separated</option>
          <option>Widowed</option>
        </select>
      </div>
    </div>

    <!-- Purok -->
    <div class="mb-3">
      <label class="form-label">Purok</label>
      <input name="purok" type="text" class="form-control" required>
    </div>

    <!-- Preferred Claim Date -->
    <div class="mb-3">
      <label class="form-label">Claim Date</label>
      <input name="claim_date" type="date" class="form-control" required>
    </div>

    <!-- Purpose -->
    <div class="mb-3">
      <label class="form-label">Purpose</label>
      <textarea name="purpose" class="form-control" rows="2" required></textarea>
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
  </template>

  <template data-type="Guardianship">
    <!-- core hidden value -->
    <input type="hidden" name="request_type" value="Guardianship">

    <!-- Guardian Full Name -->
    <div class="mb-3">
      <label class="form-label">Guardian Name</label>
      <input name="full_name" type="text" class="form-control" required>
    </div>

    <!-- Age & Civil Status -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Age</label>
        <input name="age" type="number" class="form-control" min="0" required>
      </div>
      <div class="col">
        <label class="form-label">Civil Status</label>
        <select name="civil_status" class="form-select" required>
          <option value="" selected>Select…</option>
          <option>Single</option>
          <option>Married</option>
          <option>Divorced</option>
          <option>Separated</option>
          <option>Widowed</option>
        </select>
      </div>
    </div>

    <!-- Purok -->
    <div class="mb-3">
      <label class="form-label">Purok</label>
      <input name="purok" type="text" class="form-control" required>
    </div>

    <!-- Child Name -->
    <div class="mb-3">
      <label class="form-label">Child's Name</label>
      <input name="child_name" type="text" class="form-control" required>
    </div>

    <!-- Preferred Claim Date -->
    <div class="mb-3">
      <label class="form-label">Claim Date</label>
      <input name="claim_date" type="date" class="form-control" required>
    </div>

    <!-- Purpose -->
    <div class="mb-3">
      <label class="form-label">Purpose</label>
      <textarea name="purpose" class="form-control" rows="2" required></textarea>
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
  </template>

  <template data-type="Solo Parent">
    <!-- core hidden value -->
    <input type="hidden" name="request_type" value="Solo Parent">

    <!-- Full Name -->
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input name="name" type="text" class="form-control" required>
    </div>

    <!-- Age & Civil Status -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Age</label>
        <input name="age" type="number" class="form-control" min="0" required>
      </div>
      <div class="col">
        <label class="form-label">Civil Status</label>
        <select name="civil_status" class="form-select" required>
          <option value="" selected>Select…</option>
          <option>Separated</option>
          <option>Widowed</option>
        </select>
      </div>
    </div>

    <!-- Purok -->
    <div class="mb-3">
      <label class="form-label">Purok</label>
      <input name="purok" type="text" class="form-control" required>
    </div>

    <!-- Child Name & Age -->
    <div class="row mb-3">
      <div class="col">
        <label class="form-label">Child's Name</label>
        <input name="child_name" type="text" class="form-control" required>
      </div>
      <div class="col">
        <label class="form-label">Child's Age</label>
        <input name="child_age" type="number" class="form-control" min="0" required>
      </div>
    </div>

    <!-- Years as Solo Parent -->
    <div class="mb-3">
      <label class="form-label">Years as Solo Parent</label>
      <input name="years_solo_parent" type="number" class="form-control" min="0" required>
    </div>

    <!-- Purpose -->
    <div class="mb-3">
      <label class="form-label">Purpose</label>
      <textarea name="purpose" class="form-control" rows="2" required></textarea>
    </div>

    <!-- Claim Date -->
    <div class="mb-3">
      <label class="form-label">Claim Date</label>
      <input name="claim_date" type="date" class="form-control" required>
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
  </template>

  <!-- View Details Modal -->
  <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <form class="modal-content shadow-lg">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title fw-bold fs-5" id="viewDetailsModalLabel">
            <i class="bi bi-card-list me-2"></i>Request Details
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="viewDetailsBody">
          <!-- JS will inject labeled inputs here -->
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-secondary rounded-pill" id="cancelBtn" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-success rounded-pill" id="editDetailsBtn">Edit</button>
          <button type="button" class="btn btn-primary rounded-pill" id="printCertificateBtn">Generate Certificate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$st->close();
$conn->close();
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // ——— Search & “Add New” modal logic (unchanged) —————————————————————————
  const searchForm     = document.getElementById('searchForm');
  const searchInput    = document.getElementById('searchInput');
  const searchBtn      = document.getElementById('searchBtn');
  const addModalEl     = document.getElementById('addRequestModal');
  const bsAddModal     = new bootstrap.Modal(addModalEl);
  const addTitleElem   = document.getElementById('addRequestModalTitle');
  const addBodyElem    = document.getElementById('addRequestModalBody');
  const addFormElem    = document.getElementById('addRequestForm');
  const hasSearch      = <?= json_encode(!empty($search)) ?>;

  searchBtn.addEventListener('click', () => {
    if (hasSearch) searchInput.value = '';
    searchForm.submit();
  });

  document.querySelectorAll('.request-trigger').forEach(btn => {
    btn.addEventListener('click', () => {
      const type = btn.dataset.type;
      addTitleElem.textContent = `${type} Form`;
      addBodyElem.innerHTML = '';
      addBodyElem.appendChild(
        document.querySelector(`template[data-type="${type}"]`).content.cloneNode(true)
      );
      if (['Barangay ID','Business Permit','Good Moral','Guardianship','Indigency','Residency','Solo Parent'] //'Equipment Borrowing'
          .includes(type)) {
        const flag = document.createElement('input');
        flag.type = 'hidden'; flag.name = 'superAdminRedirect'; flag.value = '1';
        addBodyElem.prepend(flag);
      }
      addFormElem.action = {
        'Barangay ID':'functions/serviceBarangayID_submit.php',
        'Business Permit':'functions/serviceBusinessPermit_submit.php',
        'Residency':'functions/serviceResidency_submit.php',
        'Indigency':'functions/serviceIndigency_submit.php',
        'Good Moral':'functions/serviceGoodMoral_submit.php',
        'Guardianship':'functions/serviceGuardianship_submit.php',
        'Solo Parent':'functions/serviceSoloParent_submit.php',
        // 'Equipment Borrowing':'functions/serviceEquipmentBorrowing_submit.php'
      }[type] || addFormElem.action;
      bsAddModal.show();
    });
  });
  
  // ——— View / Edit Details modal logic ——————————————————————————————————————
  const viewModalEl   = document.getElementById('viewDetailsModal');
  const bsViewModal   = new bootstrap.Modal(viewModalEl);
  const bodyContainer = document.getElementById('viewDetailsBody');
  const editBtn       = document.getElementById('editDetailsBtn');
  const cancelBtn     = document.getElementById('cancelBtn');

  // create “Save Changes” button ahead of time
  const saveBtn = document.createElement('button');
  saveBtn.type = 'button';
  saveBtn.id = 'saveDetailsBtn';
  saveBtn.className = 'btn btn-success rounded-pill';
  saveBtn.textContent = 'Save Changes';

  // at top of your script, after you create saveBtn...
  saveBtn.style.display = 'none';          // hide by default
  cancelBtn.insertAdjacentElement('afterend', saveBtn);  // insert once

  let inEditMode = false;

  function labelize(key) {
    return key.replace(/_/g,' ')
              .replace(/\b\w/g, c => c.toUpperCase());
  }

  document.querySelectorAll('tbody tr[data-id]').forEach(row => {
    row.addEventListener('click', () => {
      const tid  = row.dataset.id;
      const type = row.dataset.type;
      inEditMode = false;

      fetch(`functions/getRequestDetails.php?transaction_id=${tid}`)
        .then(r => r.json())
        .then(json => {
          if (json.error) throw json.error;
          bodyContainer.innerHTML = '';

          // header
          const h5 = document.createElement('h5');
          h5.className = 'mb-3';
          h5.textContent = `${json.request_type} Details`;
          bodyContainer.appendChild(h5);

          // fields
          Object.entries(json.details).forEach(([key,val]) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'mb-2';
            const label = document.createElement('label');
            label.className = 'form-label fw-semibold';
            label.textContent = labelize(key);
            const input = document.createElement('input');
            input.className = 'form-control';
            input.type = 'text';
            input.value = val ?? '';
            input.dataset.field = key;
            input.readOnly = true;
            if (key === 'transaction_id') {
              input.classList.add('bg-light');
            }
            wrapper.append(label, input);
            bodyContainer.appendChild(wrapper);
          });

          // reset footer buttons
          editBtn.textContent   = 'Edit';
          editBtn.className     = 'btn btn-success rounded-pill';
          cancelBtn.textContent = 'Close';
          cancelBtn.className   = 'btn btn-secondary rounded-pill';
          saveBtn.style.display = 'none';          // ensure hidden on open

          // Edit / Cancel toggle
          editBtn.onclick = () => {
            inEditMode = !inEditMode;

            if (inEditMode) {
              // switch to “Cancel” + show Save
              editBtn.textContent     = 'Cancel';
              editBtn.className       = 'btn btn-danger rounded-pill';
              cancelBtn.style.display = 'none';
              saveBtn.style.display   = '';

              bodyContainer.querySelectorAll('input').forEach(inp => {
                if (inp.dataset.field !== 'transaction_id') {
                  inp.readOnly = false;
                  inp.classList.remove('bg-light');
                }
              });
            } else {
              // back to view-only
              editBtn.textContent     = 'Edit';
              editBtn.className       = 'btn btn-success rounded-pill';
              saveBtn.style.display   = 'none';
              cancelBtn.style.display = '';

              bodyContainer.querySelectorAll('input').forEach(inp => {
                inp.readOnly = true;
                if (inp.dataset.field === 'transaction_id') {
                  inp.classList.add('bg-light');
                }
              });
            }
          };

          // Save Changes
          saveBtn.onclick = () => {
            const payload = { transaction_id: tid };
            bodyContainer.querySelectorAll('input').forEach(inp => {
              if (inp.dataset.field !== 'transaction_id') {
                payload[inp.dataset.field] = inp.value;
              }
            });
            fetch('functions/updateRequestDetails.php', {
              method: 'POST',
              headers: { 'Content-Type':'application/json' },
              body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(resp => {
              if (resp.success) {
                bsViewModal.hide();
                location.reload();
              } else {
                alert('Save failed: ' + (resp.error||'Unknown'));
              }
            })
            .catch(err => alert('Error saving: ' + err));
          };

          bsViewModal.show();

          // DI PA FINAL
          const printBtn = document.getElementById('printCertificateBtn');
          printBtn.onclick = () => {
            if (type === 'Residency') {
              const certWin = window.open(
                `functions/generateResidencyCertificate.php?transaction_id=${tid}`,
                '_blank',
                'width=800,height=600'
              );
              certWin.addEventListener('load', () => certWin.print());
            } else if (type === 'Indigency') {
              const certWin = window.open(
                `functions/generateIndigencyCertificate.php?transaction_id=${tid}`,
                '_blank',
                'width=800,height=600'
              );
              certWin.addEventListener('load', () => certWin.print());
            } else if (type === 'Good Moral') {
              const certWin = window.open(
                `functions/generateGoodMoralCertificate.php?transaction_id=${tid}`,
                '_blank',
                'width=800,height=600'
              );
              certWin.addEventListener('load', () => certWin.print());
            } else if (type === 'Guardianship') {
              const certWin = window.open(
                `functions/generateGuardianshipCertificate.php?transaction_id=${tid}`,
                '_blank',
                'width=800,height=600'
              )
              certWin.addEventListener('load', () => certWin.print());
            } else if (type === 'Solo Parent') {
              const certWin = window.open(
                `functions/generateSoloParentCertificate.php?transaction_id=${tid}`,
                '_blank',
                'width=800,height=600'
              );
              certWin.addEventListener('load', () => certWin.print());
            } else {
              alert('Certificate is not yet available.');
            }
          };
        })
        .catch(err => {
          console.error(err);
          alert('Failed to load details: ' + err);
        });
    });
  });
});
</script>