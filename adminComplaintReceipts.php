<?php
require 'functions/dbconn.php';
$currentRole = $_SESSION['loggedInUserRole'] ?? '';

// Only Brgy Treasurer can access this page
if ($currentRole !== 'Brgy Treasurer') {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit;
}

// PAGINATION & FILTERS
$search = trim($_GET['receipts_search'] ?? '');
$page = max((int)($_GET['receipts_page'] ?? 1), 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query - only get complaints (transaction_id starts with CMPL-)
$whereClauses = ["bc.transaction_id LIKE 'CMPL-%'"];
$bindTypes = '';
$bindParams = [];

if ($search !== '') {
    $term = "%{$search}%";
    $whereClauses[] = "(bc.transaction_id LIKE ? OR bc.case_no LIKE ? OR bc.complainant_name LIKE ? OR bc.respondent_name LIKE ? OR orr.or_number LIKE ?)";
    $bindTypes .= str_repeat('s', 5);
    $bindParams = array_merge($bindParams, array_fill(0, 5, $term));
}

$whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);

// Count total
$countSQL = "
    SELECT COUNT(*) AS total 
    FROM barangay_complaints bc
    JOIN official_receipt_records orr ON bc.transaction_id = orr.transaction_id
    $whereSQL
";
$countStmt = $conn->prepare($countSQL);
if (!empty($bindTypes)) {
    $refs = [];
    foreach ($bindParams as $i => &$val) {
        $refs[$i] = &$val;
    }
    array_unshift($refs, $bindTypes);
    call_user_func_array([$countStmt, 'bind_param'], $refs);
}
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

// Fetch records
$sql = "
    SELECT 
        bc.transaction_id,
        bc.case_no,
        bc.complainant_name,
        bc.respondent_name,
        bc.complaint_title,
        orr.payment_method,
        orr.or_number,
        orr.amount_paid,
        orr.issued_date,
        orr.reference_number,
        DATE_FORMAT(orr.issued_date, '%b %e, %Y') AS formatted_issued_date
    FROM barangay_complaints bc
    JOIN official_receipt_records orr ON bc.transaction_id = orr.transaction_id
    $whereSQL
    ORDER BY orr.issued_date DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$types = $bindTypes . 'ii';
$params = array_merge($bindParams, [$limit, $offset]);
$refs = [];
foreach ($params as $i => &$val) {
    $refs[$i] = &$val;
}
array_unshift($refs, $types);
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<div>
    <div class="card shadow-sm p-3">
        <div class="d-flex align-items-center mb-3">
            <h5 class="mb-0">Official Receipt Logs - Complaints</h5>
            
            <!-- Search form -->
            <form method="get" action="?page=adminComplaints" class="d-flex ms-auto">
                <input type="hidden" name="page" value="adminComplaints">
                <input type="hidden" name="receipts_page" value="1">
                
                <div class="input-group input-group-sm">
                    <input name="receipts_search" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
                    <button type="button" class="btn btn-outline-secondary" id="receiptsSearchBtn">
                        <span class="material-symbols-outlined"><?= !empty($search) ? 'close' : 'search' ?></span>
                    </button>
                </div>
            </form>
        </div>

        <!-- RECEIPTS TABLE -->
        <div class="table-responsive admin-table">
            <table class="table table-striped align-middle text-start">
                <thead class="table-light">
                    <tr>
                        <th class="text-nowrap">Transaction ID</th>
                        <th class="text-nowrap">Case No.</th>
                        <th class="text-nowrap">Complainant</th>
                        <th class="text-nowrap">Respondent</th>
                        <th class="text-nowrap">Complaint Title</th>
                        <th class="text-nowrap">Payment Method</th>
                        <th class="text-nowrap">OR Number</th>
                        <th class="text-nowrap">Amount Paid</th>
                        <th class="text-nowrap">Issued Date</th>
                        <th class="text-nowrap">Reference No.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                                <td><?= htmlspecialchars($row['case_no']) ?></td>
                                <td><?= htmlspecialchars($row['complainant_name']) ?></td>
                                <td><?= htmlspecialchars($row['respondent_name']) ?></td>
                                <td><?= htmlspecialchars($row['complaint_title']) ?></td>
                                <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                <td><?= htmlspecialchars($row['or_number']) ?></td>
                                <td>₱<?= number_format($row['amount_paid'], 2) ?></td>
                                <td><?= htmlspecialchars($row['formatted_issued_date']) ?></td>
                                <td><?= htmlspecialchars($row['reference_number'] ?: '—') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center">No official receipts recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center pagination-sm">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=adminComplaints&receipts_page=<?= $page - 1 ?>&receipts_search=<?= urlencode($search) ?>">Previous</a>
                    </li>
                    <?php
                        $range = 2;
                        $dots = false;
                        for ($i = 1; $i <= $totalPages; $i++) {
                            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                                $active = $i == $page ? 'active' : '';
                                echo "<li class='page-item {$active}'><a class='page-link' href='?page=adminComplaints&receipts_page={$i}&receipts_search=" . urlencode($search) . "'>{$i}</a></li>";
                                $dots = true;
                            } elseif ($dots) {
                                echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                                $dots = false;
                            }
                        }
                    ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=adminComplaints&receipts_page=<?= $page + 1 ?>&receipts_search=<?= urlencode($search) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Search functionality
    const searchBtn = document.getElementById('receiptsSearchBtn');
    const searchInput = document.querySelector('input[name="receipts_search"]');
    const hasSearch = <?= json_encode(!empty($search)) ?>;

    if (searchBtn && searchInput) {
        searchBtn.addEventListener('click', () => {
            if (hasSearch) searchInput.value = '';
            searchBtn.closest('form').submit();
        });
    }
});
</script>