<?php
require 'functions/dbconn.php';
$userId = (int)($_SESSION['loggedInUserID'] ?? 0);

// ── FILTER + SEARCH SETUP ───────────────────────────────────────────────
$search      = trim($_GET['search']      ?? '');
$date_from   = $_GET['date_from']        ?? '';
$date_to     = $_GET['date_to']          ?? '';
$requestType = $_GET['request_type']     ?? '';

$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

if ($search !== '') {
  $whereClauses[] = "(transaction_id LIKE ? OR full_name LIKE ? OR request_type LIKE ?)";
  $bindTypes    .= 'sss';
  $term           = "%{$search}%";
  $bindParams   = [$term, $term, $term];
}
if ($requestType) {
  $whereClauses[] = "request_type = ?";
  $bindTypes    .= 's';
  $bindParams[]  = $requestType;
}
if ($date_from && $date_to) {
  $whereClauses[] = 'DATE(claim_date) BETWEEN ? AND ?';
  $bindTypes    .= 'ss';
  $bindParams[]  = $date_from;
  $bindParams[]  = $date_to;
} elseif ($date_from) {
  $whereClauses[] = 'DATE(claim_date) >= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $date_from;
} elseif ($date_to) {
  $whereClauses[] = 'DATE(claim_date) <= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $date_to;
}
// only completed
$whereClauses[] = "payment_status = 'Paid'";
$whereClauses[] = "document_status = 'Released'";

$whereSQL = $whereClauses
  ? 'WHERE ' . implode(' AND ', $whereClauses)
  : '';

// ── FETCH TRANSACTIONS ───────────────────────────────────────────────────
$sql = "
  SELECT transaction_id, full_name, request_type,
         amount AS amount_paid, claim_date AS issued_date
  FROM view_general_requests
  {$whereSQL}
  ORDER BY claim_date DESC
";
$stmt = $conn->prepare($sql);
if ($bindTypes) {
  $refs = [];
  foreach ($bindParams as $i => $v) {
    $refs[$i] = & $bindParams[$i];
  }
  array_unshift($refs, $bindTypes);
  call_user_func_array([$stmt, 'bind_param'], $refs);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container-fluid p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <div class="btn-group me-2" role="group">
        <a href="?export=csv&<?= http_build_query(array_merge($_GET, [])) ?>"
           class="btn btn-sm btn-outline-primary">
          <i class="material-symbols-outlined align-middle">file_download</i> CSV
        </a>
        <a href="?export=pdf&<?= http_build_query(array_merge($_GET, [])) ?>"
           class="btn btn-sm btn-outline-danger">
          <i class="material-symbols-outlined align-middle">picture_as_pdf</i> PDF
        </a>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header py-2">
      <div class="row align-items-center">
        <div class="col-auto">
          <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
            <i class="material-symbols-outlined align-middle">filter_alt</i> Filter/Search
          </button>
        </div>
        <div class="col">
          <form method="get" class="d-flex justify-content-end">
            <input type="hidden" name="page_num" value="1">
            <input type="hidden" name="request_type" value="<?= htmlspecialchars($requestType) ?>">
            <input type="hidden" name="date_from"   value="<?= htmlspecialchars($date_from) ?>">
            <input type="hidden" name="date_to"     value="<?= htmlspecialchars($date_to) ?>">
            <div class="input-group input-group-sm w-50">
              <input name="search" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
              <button class="btn btn-outline-secondary" type="submit"><i class="material-symbols-outlined">search</i></button>
            </div>
          </form>
        </div>
      </div>
      <div class="collapse mt-3" id="filterCollapse">
        <form method="get" class="row g-3">
          <input type="hidden" name="page_num" value="1">
          <div class="col-md-4">
            <label class="form-label small">Request Type</label>
            <select name="request_type" class="form-select form-select-sm">
              <option value="">All Types</option>
              <?php foreach (['Barangay ID','Business Permit','Good Moral','Guardianship','Indigency','Residency','Solo Parent'] as $type): ?>
                <option value="<?= $type ?>" <?= $requestType === $type ? 'selected' : '' ?>><?= $type ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Date From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Date To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>">
          </div>
          <div class="col-md-12 text-end">
            <button type="reset" onclick="window.location='superAdminTransactions.php'" class="btn btn-sm btn-outline-secondary me-2">Reset</button>
            <button type="submit" class="btn btn-sm btn-success">Apply Filter</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive" style="max-height:500px;overflow-y:auto;">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Transaction ID</th>
              <th>Full Name</th>
              <th>Type</th>
              <th>Amount Paid</th>
              <th>Issued Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                  <td><?= htmlspecialchars($row['full_name']) ?></td>
                  <td><?= htmlspecialchars($row['request_type']) ?></td>
                  <td><?= number_format($row['amount_paid'],2) ?></td>
                  <td><?= htmlspecialchars($row['issued_date']) ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5" class="text-center py-4">No completed transactions found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- pagination could go here if needed -->
  </div>
</div>

<script>
// Toggle filter icon if desired
</script>
