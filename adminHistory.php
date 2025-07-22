<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];

// ── 0) FILTER + SEARCH SETUP ───────────────────────────────────────────────────
$search      = trim($_GET['search']      ?? '');
$date_from   = $_GET['date_from']        ?? '';
$date_to     = $_GET['date_to']          ?? '';
$requestType = $_GET['request_type']     ?? '';

$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

// Global search
if ($search !== '') {
  $whereClauses[] = "(transaction_id LIKE ? OR full_name LIKE ? OR request_type LIKE ?)";
  $bindTypes     .= 'sss';
  $term = "%{$search}%";
  $bindParams[] = $term;
  $bindParams[] = $term;
  $bindParams[] = $term;
}

// Filter by request type
if ($requestType) {
  $whereClauses[] = "request_type = ?";
  $bindTypes     .= 's';
  $bindParams[]   = $requestType;
}

// Filter by issued_date
if ($date_from && $date_to) {
  $whereClauses[] = 'DATE(issued_date) BETWEEN ? AND ?';
  $bindTypes     .= 'ss';
  $bindParams[]   = $date_from;
  $bindParams[]   = $date_to;
} elseif ($date_from) {
  $whereClauses[] = 'DATE(issued_date) >= ?';
  $bindTypes     .= 's';
  $bindParams[]   = $date_from;
} elseif ($date_to) {
  $whereClauses[] = 'DATE(issued_date) <= ?';
  $bindTypes     .= 's';
  $bindParams[]   = $date_to;
}

// ── NO NEED for additional paid/released filter — handled by view ──────────────
$whereSQL = $whereClauses
  ? 'WHERE ' . implode(' AND ', $whereClauses)
  : '';

// ── QUERY FROM view_history ─────────────────────────────────────────────────────
$sql = "
  SELECT
    transaction_id,
    full_name,
    request_type,
    amount_paid,
    or_number,     
    issued_date
  FROM view_history
  {$whereSQL}
  ORDER BY issued_date DESC
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


<title>eBarangay Mo | Transaction History</title>

<div class="container py-3">
  <div class="card shadow-sm p-3">
    <!-- Filter & Search UI here… (unchanged) -->

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
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                <td><?= htmlspecialchars($row['full_name'])       ?></td>
                <td><?= htmlspecialchars($row['request_type'])     ?></td>
                <td><?= number_format($row['amount_paid'], 2)     ?></td>
                <td><?= htmlspecialchars($row['or_number'] ?? '—')?></td>
                <td><?= htmlspecialchars($row['issued_date'])      ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center">No completed transactions found.</td>
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


<script>
document.addEventListener('DOMContentLoaded', () => {
  const form      = document.getElementById('searchForm');
  const input     = document.getElementById('searchInput');
  const btn       = document.getElementById('searchBtn');
  const icon      = document.getElementById('searchIcon');
  const hasSearch = <?= json_encode(!empty($search)) ?>;

  btn.addEventListener('click', () => {
    if (hasSearch) {
      input.value = '';
      icon.textContent = 'search';
    }
    form.submit();
  });
});
</script>