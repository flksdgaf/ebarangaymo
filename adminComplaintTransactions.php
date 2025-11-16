<?php
require 'functions/dbconn.php';
$userId = (int) ($_SESSION['loggedInUserID'] ?? 0);
$currentRole = $_SESSION['loggedInUserRole'] ?? '';

// Only Brgy Treasurer can access this page
if ($currentRole !== 'Brgy Treasurer') {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit;
}

// PAGINATION & FILTERS
$search = trim($_GET['transactions_search'] ?? '');
$page = max((int)($_GET['transactions_page'] ?? 1), 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$whereClauses = ["payment_status = 'Pending'"];
$bindTypes = '';
$bindParams = [];

if ($search !== '') {
    $term = "%{$search}%";
    $whereClauses[] = "(transaction_id LIKE ? OR case_no LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ?)";
    $bindTypes .= str_repeat('s', 4);
    $bindParams = array_merge($bindParams, array_fill(0, 4, $term));
}

$whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);

// Count total
$countSQL = "SELECT COUNT(*) AS total FROM barangay_complaints $whereSQL";
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
        transaction_id, case_no, complainant_name, respondent_name,
        complaint_title, amount, payment_method, payment_status,
        DATE_FORMAT(date_filed, '%b %e, %Y') AS formatted_date_filed
    FROM barangay_complaints
    $whereSQL
    ORDER BY date_filed ASC
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
    <?php if (isset($_GET['payment_complaint_id'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Payment for complaint <strong><?= htmlspecialchars($_GET['payment_complaint_id']) ?></strong> recorded successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div id="js-complaint-alert-container"></div>

    <div class="card shadow-sm p-3">
        <div class="d-flex align-items-center mb-3">
            <h5 class="mb-0">Pending Complaint Payments</h5>
            
            <!-- Search form -->
            <form method="get" action="?page=adminComplaints" class="d-flex ms-auto">
                <input type="hidden" name="page" value="adminComplaints">
                <input type="hidden" name="transactions_page" value="1">
                
                <div class="input-group input-group-sm">
                    <input name="transactions_search" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
                    <button type="button" class="btn btn-outline-secondary" id="complaintSearchBtn">
                        <span class="material-symbols-outlined"><?= !empty($search) ? 'close' : 'search' ?></span>
                    </button>
                </div>
            </form>
        </div>

        <!-- COMPLAINTS TABLE -->
        <div class="table-responsive admin-table">
            <table class="table table-hover align-middle text-start">
                <thead class="table-light">
                    <tr>
                        <th class="text-nowrap">Transaction ID</th>
                        <th class="text-nowrap">Case No.</th>
                        <th class="text-nowrap">Complainant</th>
                        <th class="text-nowrap">Respondent</th>
                        <th class="text-nowrap">Complaint Title</th>
                        <th class="text-nowrap">Amount</th>
                        <th class="text-nowrap">Payment Method</th>
                        <th class="text-nowrap">Date Filed</th>
                        <th class="text-nowrap text-center">Action</th>
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
                                <td>₱<?= number_format($row['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                <td><?= htmlspecialchars($row['formatted_date_filed']) ?></td>
                                <td class="text-center text-nowrap">
                                    <button type="button" 
                                            class="btn btn-sm btn-info complaint-record-btn"
                                            data-id="<?= htmlspecialchars($row['transaction_id']) ?>"
                                            data-payment-method="<?= htmlspecialchars($row['payment_method']) ?>"
                                            data-amount-paid="<?= htmlspecialchars($row['amount']) ?>"
                                            title="Record Payment">
                                        <span class="material-symbols-outlined" style="font-size: 13px;">receipt</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center">No pending complaint payments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center pagination-sm">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=adminComplaints&transactions_page=<?= $page - 1 ?>&transactions_search=<?= urlencode($search) ?>">Previous</a>
                    </li>
                    <?php
                        $range = 2;
                        $dots = false;
                        for ($i = 1; $i <= $totalPages; $i++) {
                            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                                $active = $i == $page ? 'active' : '';
                                echo "<li class='page-item {$active}'><a class='page-link' href='?page=adminComplaints&transactions_page={$i}&transactions_search=" . urlencode($search) . "'>{$i}</a></li>";
                                $dots = true;
                            } elseif ($dots) {
                                echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                                $dots = false;
                            }
                        }
                    ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=adminComplaints&transactions_page=<?= $page + 1 ?>&transactions_search=<?= urlencode($search) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Record Complaint Payment Modal -->
<div class="modal fade" id="recordComplaintModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #13411F;">
                <h5 class="modal-title">
                    <i class="bi bi-receipt me-2"></i>
                    Record Complaint Payment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form id="recordComplaintForm" action="functions/process_record_complaint_payment.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="transaction_id" id="recordComplaintTransactionId">
                    <input type="hidden" name="payment_method" id="recordComplaintPaymentMethodHidden">
                    <input type="hidden" name="amount_paid" id="recordComplaintAmountPaidHidden">

                    <div class="row g-3">
                        <!-- Row 1: Payment Method & Amount Paid -->
                        <div class="col-md-6">
                            <label for="paymentMethodRecordComplaint" class="form-label fw-bold">Payment Method</label>
                            <input type="text" class="form-control form-control-sm" id="paymentMethodRecordComplaint" disabled>
                        </div>
                        <div class="col-md-6">
                            <label for="amountPaidRecordComplaint" class="form-label fw-bold">Amount to Pay</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amountPaidRecordComplaint" disabled>
                        </div>

                        <!-- Row 2: OR Number & Issued Date -->
                        <div class="col-md-6">
                            <label for="orNumberRecordComplaint" class="form-label fw-bold">OR Number</label>
                            <input type="text" class="form-control form-control-sm" id="orNumberRecordComplaint" name="or_number" placeholder="Enter OR Number" required>
                        </div>
                        <div class="col-md-6">
                            <label for="issuedDateRecordComplaint" class="form-label fw-bold">Issued Date</label>
                            <input type="date" class="form-control form-control-sm" id="issuedDateRecordComplaint" name="issued_date" required>
                        </div>

                        <!-- Row 3: Reference Number (GCash only) -->
                        <div class="col-md-6" id="refRowComplaint" style="display:none;">
                            <label for="referenceNumberRecordComplaint" class="form-label fw-bold">Reference Number</label>
                            <input type="text" class="form-control form-control-sm" id="referenceNumberRecordComplaint" name="reference_number" placeholder="Enter Reference Number">
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="background-color: #f8f9fa;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Save Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Search functionality
    const searchBtn = document.getElementById('complaintSearchBtn');
    const searchInput = document.querySelector('input[name="transactions_search"]');
    const hasSearch = <?= json_encode(!empty($search)) ?>;

    if (searchBtn && searchInput) {
        searchBtn.addEventListener('click', () => {
            if (hasSearch) searchInput.value = '';
            searchBtn.closest('form').submit();
        });
    }

    // Record Payment Modal
    const recordModal = new bootstrap.Modal(document.getElementById('recordComplaintModal'));
    const tidInput = document.getElementById('recordComplaintTransactionId');
    const pmInput = document.getElementById('paymentMethodRecordComplaint');
    const pmHidden = document.getElementById('recordComplaintPaymentMethodHidden');
    const refRow = document.getElementById('refRowComplaint');
    const refInput = document.getElementById('referenceNumberRecordComplaint');
    const orInput = document.getElementById('orNumberRecordComplaint');
    const issuedInput = document.getElementById('issuedDateRecordComplaint');
    const amtInput = document.getElementById('amountPaidRecordComplaint');
    const amtHidden = document.getElementById('recordComplaintAmountPaidHidden');

    document.querySelectorAll('.complaint-record-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tid = btn.dataset.id;
            const pm = btn.dataset.paymentMethod || '';
            const amt = btn.dataset.amountPaid || '';

            tidInput.value = tid;
            pmInput.value = pm;
            pmHidden.value = pm;
            amtInput.value = amt;
            amtHidden.value = amt;
            orInput.value = '';
            issuedInput.value = '';

            // Show/hide GCash reference field
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
});
</script>