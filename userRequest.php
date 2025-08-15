<?php
require 'functions/dbconn.php';
$userId = (int) $_SESSION['loggedInUserID'];

// ── 1) DETAIL VIEW ─────────────────────────────────────────────────────────── 
if (isset($_GET['transaction_id'])) {
    $tx = $_GET['transaction_id'];
    
    // Check if it's an equipment borrowing request (starts with BRW-)
    if (strpos($tx, 'BRW-') === 0) {
        // Handle equipment borrowing request details
        $bsql = "SELECT * FROM borrow_requests WHERE transaction_id = ? LIMIT 1";
        $bst = $conn->prepare($bsql);
        $bst->bind_param('s', $tx);
        $bst->execute();
        $brow = $bst->get_result()->fetch_assoc();
        $bst->close();
        
        if (!$brow) {
            echo "<div class='alert alert-danger'>Equipment borrowing request not found.</div>";
            exit();
        }
        
        echo "<div class='container py-3'><div class='card shadow-sm p-4 mb-4'>";
        echo "<h5 class='fw-bold mb-3'>Equipment Borrowing Details for {$tx}</h5>";
        
        // Get equipment name from equipment_list
        $eqsql = "SELECT name FROM equipment_list WHERE equipment_sn = ? LIMIT 1";
        $eqst = $conn->prepare($eqsql);
        $eqst->bind_param('s', $brow['equipment_sn']);
        $eqst->execute();
        $eqrow = $eqst->get_result()->fetch_assoc();
        $eqst->close();
        
        $exclude = ['id', 'transaction_id'];
        echo "<dl class='row'>";
        
        foreach ($brow as $col => $val) {
            if ($val === null || in_array($col, $exclude, true)) {
                continue;
            }
            
            if ($col === 'equipment_sn') {
                echo "<dt class='col-sm-3'>Equipment</dt>";
                echo "<dd class='col-sm-9'>" . htmlspecialchars($eqrow['name'] ?? 'Unknown Equipment') . " (SN: " . htmlspecialchars($val) . ")</dd>";
            } else {
                $label = ucwords(str_replace('_', ' ', $col));
                echo "<dt class='col-sm-3'>{$label}</dt>";
                echo "<dd class='col-sm-9'>" . htmlspecialchars($val) . "</dd>";
            }
        }
        echo "</dl>";
        
    } else {
        // Handle regular document requests (existing code)
        $vsql = "SELECT * FROM view_request WHERE transaction_id = ? AND account_id = ? LIMIT 1";
        $vst = $conn->prepare($vsql);
        $vst->bind_param('si', $tx, $userId);
        $vst->execute();
        $vrow = $vst->get_result()->fetch_assoc();
        $vst->close();
        
        if (!$vrow) {
            echo "<div class='alert alert-danger'>Request not found or you don't have access.</div>";
            exit();
        }
        
        // Pick the details table
        switch ($vrow['request_type']) {
            case 'Barangay ID': $tbl = 'barangay_id_requests'; break;
            case 'Business Permit': $tbl = 'business_permit_requests'; break;
            case 'Certification': $tbl = 'certification_requests'; break;
            case 'Indigency': $tbl = 'indigency_requests'; break;
            case 'Residency': $tbl = 'residency_requests'; break;
            case 'Good Moral': $tbl = 'good_moral_requests'; break;
            case 'Solo Parent': $tbl = 'solo_parent_requests'; break;
            case 'Guardianship': $tbl = 'guardianship_requests'; break;
            default: $tbl = null;
        }
        
        echo "<div class='container py-3'><div class='card shadow-sm p-4 mb-4'>";
        echo "<h5 class='fw-bold mb-3'>Full Details for {$tx}</h5>";
        
        if ($tbl) {
            $dsql = "SELECT * FROM {$tbl} WHERE transaction_id = ? AND account_id = ? LIMIT 1";
            $dst = $conn->prepare($dsql);
            $dst->bind_param('si', $tx, $userId);
            $dst->execute();
            $drow = $dst->get_result()->fetch_assoc();
            $dst->close();
            
            if ($drow) {
                $exclude = ['id', 'account_id', 'transaction_id', 'created_at'];
                echo "<dl class='row'>";
                foreach ($drow as $col => $val) {
                    if ($val === null || in_array($col, $exclude, true)) {
                        continue;
                    }
                    $label = ucwords(str_replace('_', ' ', $col));
                    echo "<dt class='col-sm-3'>{$label}</dt>";
                    echo "<dd class='col-sm-9'>" . htmlspecialchars($val) . "</dd>";
                }
                echo "</dl>";
            } else {
                echo "<p class='text-danger'>No details found in <code>{$tbl}</code> (or not yours).</p>";
            }
        } else {
            echo "<p class='text-danger'>Unknown request type: <strong>" . htmlspecialchars($vrow['request_type']) . "</strong></p>";
        }
    }
    
    // Back link
    $backPage = isset($_GET['pagination']) ? (int)$_GET['pagination'] : 1;
    echo "<a href='?page=userRequest&pagination={$backPage}' class='btn btn-secondary mt-3'>← Back to list</a>";
    echo "</div></div>";
    exit();
}

// ── 2) LIST + PAGINATION ─────────────────────────────────────────────────────
$limit = 10;
$page = isset($_GET['pagination']) && is_numeric($_GET['pagination']) ? (int)$_GET['pagination'] : 1;
$offset = ($page - 1) * $limit;

// Count total (combining both document requests and equipment borrowing)
$countSql1 = "SELECT COUNT(*) AS total FROM view_request WHERE account_id = ? AND document_status <> 'Released'";
$countSql2 = "SELECT COUNT(*) AS total FROM borrow_requests WHERE status <> 'Released'";

$cst1 = $conn->prepare($countSql1);
$cst1->bind_param('i', $userId);
$cst1->execute();
$total1 = $cst1->get_result()->fetch_assoc()['total'];
$cst1->close();

$cst2 = $conn->prepare($countSql2);
$cst2->execute();
$total2 = $cst2->get_result()->fetch_assoc()['total'];
$cst2->close();

$totalRows = $total1 + $total2;
$totalPages = ceil($totalRows / $limit);

// Fetch combined results using UNION
$sql = "
(SELECT 
    transaction_id, 
    full_name, 
    request_type, 
    DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') AS formatted_date,
    created_at
FROM view_request 
WHERE account_id = ? AND document_status <> 'Released')

UNION ALL

(SELECT 
    transaction_id, 
    resident_name AS full_name, 
    'Equipment Borrowing' AS request_type, 
    DATE_FORMAT(NOW(), '%M %d, %Y %h:%i %p') AS formatted_date,
    NOW() as created_at
FROM borrow_requests 
WHERE status <> 'Released')

ORDER BY created_at ASC 
LIMIT ? OFFSET ?
";

$st = $conn->prepare($sql);
$st->bind_param('iii', $userId, $limit, $offset);
$st->execute();
$result = $st->get_result();
?>

<title>eBarangay Mo | My Requests</title>
<div class="container py-3">
    <div class="card shadow-sm p-3">
        <div class="table-responsive" style="height:500px; overflow-y:auto;">
            <table class="table align-middle text-start table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Transaction No.</th>
                        <th>Name</th>
                        <th>Request</th>
                        <th>Date Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr style="cursor:pointer" onclick="window.location.href='?page=userRequest&pagination=<?= $page ?>&transaction_id=<?= $row['transaction_id'] ?>'">
                                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <td><?= htmlspecialchars($row['request_type']) ?></td>
                                <td><?= $row['formatted_date'] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">No requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Smart Bootstrap pagination with ellipsis -->
        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <!-- Previous -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=userRequest&pagination=<?= max(1, $page - 1) ?>">
                            Previous
                        </a>
                    </li>

                    <?php
                    $range = 2;
                    $ell = false;
                    for ($i = 1; $i <= $totalPages; $i++) {
                        if ($i == 1 || $i == $totalPages || abs($i - $page) <= $range) {
                            echo "<li class='page-item " . ($i == $page ? 'active' : '') . "'>
                                    <a class='page-link' href='?page=userRequest&pagination=$i'>$i</a>
                                  </li>";
                            $ell = false;
                        } else {
                            if (!$ell) {
                                echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                                $ell = true;
                            }
                        }
                    }
                    ?>

                    <!-- Next -->
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=userRequest&pagination=<?= min($totalPages, $page + 1) ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php
$st->close();
$conn->close();
?>