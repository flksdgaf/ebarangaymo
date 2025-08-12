<?php
require 'functions/dbconn.php';
$userId = (int)($_SESSION['loggedInUserID'] ?? 0);

// Which pane/tab is selected
$tab = $_GET['tab'] ?? 'all';

$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

// ── TAB FILTERS ────────────────────────────────────────────────────────────────
// Use simple LIKE filters for now. Adjust keywords to match your actual request_type values.
if ($tab === 'documents') {
  // Match document-like request types (certificate, document, clearance, etc)
  $whereClauses[] = "(LOWER(request_type) LIKE ? OR LOWER(request_type) LIKE ?)";
  $bindTypes .= 'ss';
  $bindParams[] = '%document%';
  $bindParams[] = '%certificate%';
} elseif ($tab === 'complaints') {
  // Match complaint/blotter-like request types
  $whereClauses[] = "(LOWER(request_type) LIKE ? OR LOWER(request_type) LIKE ?)";
  $bindTypes .= 'ss';
  $bindParams[] = '%complaint%';
  $bindParams[] = '%blotter%';
}
// else: if tab is 'all' or unknown, don't add extra filter

$whereSQL = $whereClauses
  ? 'WHERE ' . implode(' AND ', $whereClauses)
  : '';

// ── QUERY FROM view_history ─────────────────────────────────────────────────────
$sql = "
  SELECT
    transaction_id,full_name,request_type,amount_paid,or_number,issued_date,action,sort_date
  FROM view_transaction_history
    {$whereSQL} ORDER BY sort_date DESC
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
  die("Prepare failed: " . htmlspecialchars($conn->error));
}

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

// Helper: build current query string (preserve other GET params) for tab links
function qs_with($overrides = []) {
  $qs = $_GET;
  foreach ($overrides as $k => $v) $qs[$k] = $v;
  return http_build_query($qs);
}
?>

<title>eBarangay Mo | Transaction History</title>

<div class="container p-3">
  <!-- TABS -->
  <ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
      <a class="nav-link <?= $tab === 'all' ? 'active' : '' ?>"
          href="?<?= qs_with(['tab' => 'all']) ?>">
        All Transactions
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?= $tab === 'documents' ? 'active' : '' ?>"
          href="?<?= qs_with(['tab' => 'documents']) ?>">
        Document Requests
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?= $tab === 'complaints' ? 'active' : '' ?>"
          href="?<?= qs_with(['tab' => 'complaints']) ?>">
        Complaint Requests
      </a>
    </li>
  </ul>

  <div class="card shadow-sm p-3">
    <!-- RESULTS TABLE -->
    <div class="table-responsive admin-table" style="height:500px;overflow-y:auto;">
      <table class="table table-hover align-middle text-start">
        <thead class="table-light">
          <tr>
            <th>Transaction ID</th>
            <th>Full Name</th>
            <th>Request Type</th>
            <th>Amount Paid</th>
            <th>OR Number</th>
            <th>Issued Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['request_type']) ?></td>
                <td><?= is_null($row['amount_paid']) ? '—' : number_format($row['amount_paid'], 2) ?></td>
                <td><?= htmlspecialchars($row['or_number'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['sort_date'] ?? $row['issued_date'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['action']) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="text-center">
                <?php if ($tab === 'documents'): ?>
                  No document requests found.
                <?php elseif ($tab === 'complaints'): ?>
                  No complaints found.
                <?php else: ?>
                  No transactions found.
                <?php endif; ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$stmt->close();
$conn->close();
?>
