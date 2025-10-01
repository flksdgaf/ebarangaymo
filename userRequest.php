<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require 'functions/dbconn.php';
$userId = isset($_SESSION['loggedInUserID']) ? (int) $_SESSION['loggedInUserID'] : 0;

function table_has_column($conn, $tableName, $colName) {
    $dbName = '';
    $q = $conn->query("SELECT DATABASE() AS db");
    if ($q) $dbName = $q->fetch_assoc()['db'] ?? '';
    if (!$dbName) return false;
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $st = $conn->prepare($sql);
    if (!$st) return false;
    $st->bind_param('sss', $dbName, $tableName, $colName);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    $st->close();
    return (bool)$res;
}

function map_payment_progress($pay, $requestType) {
    $p = strtolower(trim((string)$pay));
    $rt = strtolower(trim((string)$requestType));
    if ($rt === 'indigency') return ['pct' => 100, 'label' => 'Free of Charge', 'color' => '#059669'];
    if ($p === '' && $rt === 'equipment borrowing') return ['pct' => 100, 'label' => 'No Payment Needed', 'color' => '#9CA3AF'];
    if ($p === 'paid') return ['pct' => 100, 'label' => 'Paid', 'color' => '#059669'];
    if ($p === 'unpaid' || $p === 'pending') return ['pct' => 35, 'label' => ucfirst($p ?: 'Unpaid'), 'color' => '#F59E0B'];
    if ($p !== '') return ['pct' => 60, 'label' => ucfirst($pay), 'color' => '#2563EB'];
    return ['pct' => 0, 'label' => 'Not set', 'color' => '#6B7280'];
}
function map_document_progress($doc) {
    $d = strtolower(trim($doc));
    if ($d === 'released' || $d === 'completed') return ['pct' => 100, 'label' => ucfirst($d), 'color' => '#059669'];
    if ($d === 'for verification') return ['pct' => 70, 'label' => 'For Verification', 'color' => '#2563EB'];
    if ($d === 'processing' || $d === 'pending' || $d === '') return ['pct' => 35, 'label' => 'Processing', 'color' => '#F59E0B'];
    if ($d === 'ready to release') return ['pct' => 85, 'label' => 'Ready to Release', 'color' => '#10B981'];
    if ($d === 'cancelled' || $d === 'rejected') return ['pct' => 100, 'label' => ucfirst($d), 'color' => '#EF4444'];
    return ['pct' => 50, 'label' => ucfirst($doc), 'color' => '#6B7280'];
}

/* cancellation handler */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    header('Content-Type: application/json; charset=utf-8');
    $tx = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';
    if ($tx === '') { echo json_encode(['ok' => false, 'message' => 'Missing transaction id.']); exit(); }

    $conn->begin_transaction();
    $success = false; $message = 'Unable to cancel request.';
    try {
        if (strpos($tx, 'BRW-') === 0 && table_has_column($conn, 'borrow_requests', 'status')) {
            $where = "transaction_id = ?"; $types = 's'; $params = [$tx];
            $ownerCol = null;
            foreach (['account_id','user_id','requester_id'] as $c) {
                if (table_has_column($conn, 'borrow_requests', $c)) { $ownerCol = $c; break; }
            }
            if ($ownerCol) { $where .= " AND {$ownerCol} = ?"; $types .= 'i'; $params[] = $userId; }
            $sql = "UPDATE borrow_requests SET status = 'Cancelled' WHERE {$where}";
            $st = $conn->prepare($sql);
            if ($st) {
                $st->bind_param($types, ...$params); $st->execute();
                if ($st->affected_rows > 0) { $success = true; $message = 'Request cancelled successfully.'; }
                else { $message = 'No matching borrow request found or cannot cancel.'; }
                $st->close();
            } else { $message = 'Failed preparing cancel statement for borrow_requests.'; }
        } else {
            $requestTables = [
                'request_records','barangay_id_requests','business_clearance_requests','barangay_clearance_requests','certification_requests',
                'indigency_requests','residency_requests','good_moral_requests','solo_parent_requests','guardianship_requests'
            ];
            foreach ($requestTables as $tbl) {
                if (!table_has_column($conn, $tbl, 'transaction_id')) continue;
                if (!table_has_column($conn, $tbl, 'document_status')) continue;
                $where = "transaction_id = ?"; $types = 's'; $params = [$tx];
                $ownerCol = null;
                foreach (['account_id','user_id','requester_id'] as $c) {
                    if (table_has_column($conn, $tbl, $c)) { $ownerCol = $c; break; }
                }
                if ($ownerCol) { $where .= " AND {$ownerCol} = ?"; $types .= 'i'; $params[] = $userId; }
                $sql = "UPDATE {$tbl} SET document_status = 'Cancelled' WHERE {$where}";
                $st = $conn->prepare($sql);
                if (!$st) continue;
                $st->bind_param($types, ...$params); $st->execute();
                if ($st->affected_rows > 0) { $success = true; $message = 'Request cancelled successfully.'; $st->close(); break; }
                $st->close();
            }
        }

        if ($success) { $conn->commit(); echo json_encode(['ok' => true, 'message' => $message]); exit(); }
        else { $conn->rollback(); echo json_encode(['ok' => false, 'message' => $message]); exit(); }
    } catch (Exception $ex) {
        $conn->rollback();
        echo json_encode(['ok' => false, 'message' => 'Exception: ' . $ex->getMessage()]);
        exit();
    }
}

/* DETAIL VIEW (AJAX-friendly) */
if (isset($_GET['transaction_id'])) {
    $tx = $_GET['transaction_id'];
    $isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
    ob_start();

    if (strpos($tx, 'BRW-') === 0) {
        $bst = $conn->prepare("SELECT * FROM borrow_requests WHERE transaction_id = ? LIMIT 1");
        if ($bst) {
            $bst->bind_param('s', $tx); $bst->execute(); $brow = $bst->get_result()->fetch_assoc(); $bst->close();
        } else {
            $brow = null;
        }

        if (!$brow) {
            echo "<div class='text-danger p-3'>Equipment borrowing request not found.</div>";
        } else {
            $payStatus = '';
            $docStatus = $brow['status'] ?? 'Pending';
            $request_type = 'Equipment Borrowing';
            $pmap = map_payment_progress($payStatus, $request_type);
            $dmap = map_document_progress($docStatus);

            echo '<div class="detail-summary">';
            echo '  <div class="summary-left">';
            if (!empty($brow['resident_name'])) {
                echo '    <div class="k-v"><div class="k">Resident</div><div class="v">' . htmlspecialchars($brow['resident_name']) . '</div></div>';
            }
            echo '    <div class="k-v"><div class="k">Request</div><div class="v">Equipment Borrowing</div></div>';
            echo '  </div>';
            echo '</div>';

            echo '<div class="detail-grid">';
            $equipName = null;
            if (!empty($brow['equipment_sn'])) {
                $eqst = $conn->prepare("SELECT name FROM equipment_list WHERE equipment_sn = ? LIMIT 1");
                if ($eqst) {
                    $eqst->bind_param('s', $brow['equipment_sn']); $eqst->execute(); $eqrow = $eqst->get_result()->fetch_assoc(); $eqst->close();
                    $equipName = $eqrow['name'] ?? null;
                }
            }

            // Only render columns with non-empty values
            $printed = false;
            foreach ($brow as $col => $val) {
                if (in_array($col, ['id','transaction_id','resident_name','status'])) continue;
                // skip null/empty values
                if ($val === null || trim((string)$val) === '') continue;

                $label = ucwords(str_replace('_', ' ', $col));
                $lowerCol = strtolower($col);
                if (in_array($lowerCol, ['authorization','authorized_person','authorized_by','authorization_by','authorized'])) {
                    $display = (strtolower(trim((string)$val)) === 'myself') ? 'No Authorization Needed' : htmlspecialchars($val);
                } else {
                    $display = ($col === 'equipment_sn') ? (($equipName ? htmlspecialchars($equipName) . " " : "") . "(" . htmlspecialchars($val) . ")") : htmlspecialchars($val);
                }
                echo "<div class=\"detail-row\"><div class=\"label\">{$label}</div><div class=\"value\">{$display}</div></div>";
                $printed = true;
            }

            if (!$printed) {
                echo "<div class='text-muted p-2'>No additional details</div>";
            }

            echo '</div>';

            echo '<div class="detail-meta" style="display:none"'
                . ' data-status="'.htmlspecialchars($docStatus).'"'
                . ' data-pay="'.htmlspecialchars($payStatus).'"'
                . ' data-pay-pct="'.htmlspecialchars((string)$pmap['pct']).'"'
                . ' data-pay-label="'.htmlspecialchars($pmap['label']).'"'
                . ' data-pay-color="'.htmlspecialchars($pmap['color']).'"'
                . ' data-doc-pct="'.htmlspecialchars((string)$dmap['pct']).'"'
                . ' data-doc-label="'.htmlspecialchars($dmap['label']).'"'
                . ' data-doc-color="'.htmlspecialchars($dmap['color']).'"></div>';
        }
    } else {
        $vst = $conn->prepare("SELECT * FROM view_request WHERE transaction_id = ? AND account_id = ? LIMIT 1");
        if ($vst) {
            $vst->bind_param('si', $tx, $userId); $vst->execute(); $vrow = $vst->get_result()->fetch_assoc(); $vst->close();
        } else {
            $vrow = null;
        }

        if (!$vrow) {
            echo "<div class='text-danger p-3'>Request not found or you don't have access.</div>";
        } else {
            switch ($vrow['request_type']) {
                case 'Barangay ID': $tbl = 'barangay_id_requests'; break;
                case 'Business Clearance': $tbl = 'business_clearance_requests'; break;
                case 'Barangay Clearance': $tbl = 'barangay_clearance_requests'; break;
                case 'Certification': $tbl = 'certification_requests'; break;
                case 'Indigency': $tbl = 'indigency_requests'; break;
                case 'Residency': $tbl = 'residency_requests'; break;
                case 'Good Moral': $tbl = 'good_moral_requests'; break;
                case 'Solo Parent': $tbl = 'solo_parent_requests'; break;
                case 'Guardianship': $tbl = 'guardianship_requests'; break;
                default: $tbl = null;
            }

            $payStatus = $vrow['payment_status'] ?? '';
            $docStatus = $vrow['document_status'] ?? '';
            $request_type = $vrow['request_type'] ?? 'Request';
            $pmap = map_payment_progress($payStatus, $request_type);
            $dmap = map_document_progress($docStatus);

            echo '<div class="detail-summary">';
            echo '  <div class="summary-left">';
            echo '    <div class="k-v"><div class="k">Name</div><div class="v">' . htmlspecialchars($vrow['full_name'] ?? '') . '</div></div>';
            echo '    <div class="k-v"><div class="k">Request</div><div class="v">' . htmlspecialchars($vrow['request_type']) . '</div></div>';
            echo '  </div>';
            echo '</div>';

            echo '<div class="detail-grid">';
            if ($tbl) {
                $dst = $conn->prepare("SELECT * FROM {$tbl} WHERE transaction_id = ? AND account_id = ? LIMIT 1");
                if ($dst) {
                    $dst->bind_param('si', $tx, $userId); $dst->execute(); $drow = $dst->get_result()->fetch_assoc(); $dst->close();
                } else {
                    $drow = null;
                }

                if ($drow) {
                    $printed = false;
                    foreach ($drow as $col => $val) {
                        if (in_array($col, ['id','account_id','transaction_id','created_at'])) continue;
                        // skip null/empty
                        if ($val === null || trim((string)$val) === '') continue;

                        $label = ucwords(str_replace('_',' ',$col));
                        $lowerCol = strtolower($col);
                        if (in_array($lowerCol, ['authorization','authorized_person','authorized_by','authorization_by','authorized'])) {
                            $displayVal = (strtolower(trim((string)$val)) === 'myself') ? 'No Authorization Needed' : htmlspecialchars($val);
                        } else {
                            $displayVal = htmlspecialchars($val);
                        }
                        echo "<div class=\"detail-row\"><div class=\"label\">{$label}</div><div class=\"value\">{$displayVal}</div></div>";
                        $printed = true;
                    }
                    if (!$printed) {
                        echo "<div class='text-muted p-2'>No additional details</div>";
                    }
                } else {
                    echo "<div class=\"detail-row\"><div class=\"label\">No additional details</div></div>";
                }
            } else {
                $printed = false;
                foreach ($vrow as $col => $val) {
                    if (in_array($col, ['id','account_id','transaction_id'])) continue;
                    // skip null/empty values
                    if ($val === null || trim((string)$val) === '') continue;

                    $label = ucwords(str_replace('_',' ',$col));
                    $lowerCol = strtolower($col);
                    if (in_array($lowerCol, ['authorization','authorized_person','authorized_by','authorization_by','authorized'])) {
                        $displayVal = (strtolower(trim((string)$val)) === 'myself') ? 'No Authorization Needed' : htmlspecialchars($val);
                    } else {
                        $displayVal = htmlspecialchars($val);
                    }
                    echo "<div class=\"detail-row\"><div class=\"label\">{$label}</div><div class=\"value\">{$displayVal}</div></div>";
                    $printed = true;
                }
                if (!$printed) {
                    echo "<div class='text-muted p-2'>No additional details</div>";
                }
            }
            echo '</div>';

            echo '<div class="detail-meta" style="display:none"'
                . ' data-status="'.htmlspecialchars($docStatus).'"'
                . ' data-pay="'.htmlspecialchars($payStatus).'"'
                . ' data-pay-pct="'.htmlspecialchars((string)$pmap['pct']).'"'
                . ' data-pay-label="'.htmlspecialchars($pmap['label']).'"'
                . ' data-pay-color="'.htmlspecialchars($pmap['color']).'"'
                . ' data-doc-pct="'.htmlspecialchars((string)$dmap['pct']).'"'
                . ' data-doc-label="'.htmlspecialchars($dmap['label']).'"'
                . ' data-doc-color="'.htmlspecialchars($dmap['color']).'"></div>';
        }
    }

    $content = ob_get_clean();
    if ($isAjax) {
        echo $content;
        exit();
    } else {
        ?>
        <link href="https://fonts.googleapis.com/css2?family=Material+Icons+Outlined" rel="stylesheet">
        <link rel="stylesheet" href="panels_user.css">
        <?php
        echo "<div class='container py-3'><div class='card shadow-sm p-4 mb-4'>";
        echo $content;
        $backPage = isset($_GET['pagination']) ? (int)$_GET['pagination'] : 1;
        $backFilter = isset($_GET['filter']) ? '&filter=' . urlencode($_GET['filter']) : '';
        $backSearch = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
        echo "<a href='?page=userRequest&pagination={$backPage}{$backFilter}{$backSearch}' class='btn btn-secondary mt-3'>← Back to list</a>";
        echo "</div></div>";
        exit();
    }
}

/* LIST + PAGINATION + FILTER + SEARCH */
$limit = 10;
$page = isset($_GET['pagination']) && is_numeric($_GET['pagination']) ? (int) $_GET['pagination'] : 1;
$offset = ($page - 1) * $limit;

$validFilters = ['all','completed','cancelled'];
$filter = isset($_GET['filter']) && in_array($_GET['filter'],$validFilters) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchTerm = '%' . $search . '%';

$vr_where = "account_id = ?";
$vr_params = [$userId];
$vr_types = 'i';

$br_where = "1=1";
$br_params = [];
$br_types = '';

if ($filter === 'completed') {
    $vr_where .= " AND document_status = 'Released'";
    $br_where .= " AND status = 'Released'";
} elseif ($filter === 'cancelled') {
    $vr_where .= " AND document_status = 'Cancelled'";
    $br_where .= " AND status = 'Cancelled'";
}

if ($search !== '') {
    $vr_where .= " AND (transaction_id LIKE ? OR full_name LIKE ? OR request_type LIKE ?)";
    $vr_params[] = $searchTerm; $vr_params[] = $searchTerm; $vr_params[] = $searchTerm; $vr_types .= 'sss';

    $br_where .= " AND (transaction_id LIKE ? OR resident_name LIKE ?)";
    $br_params[] = $searchTerm; $br_params[] = $searchTerm; $br_types .= 'ss';
}

function find_datetime_column($conn,$tableName) {
    $dbName = ''; $q = $conn->query("SELECT DATABASE() AS db"); if ($q) $dbName = $q->fetch_assoc()['db'] ?? '';
    if (!$dbName) return null;
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND DATA_TYPE IN ('datetime','timestamp','date') LIMIT 1";
    $st = $conn->prepare($sql); if (!$st) return null;
    $st->bind_param('ss',$dbName,$tableName); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close(); return $r ? $r['COLUMN_NAME'] : null;
}

if (table_has_column($conn, 'view_request', 'created_at')) {
    $vr_ts_col = 'created_at';
} else {
    $vr_ts_col = find_datetime_column($conn,'view_request');
}

/* --- UPDATED: prefer created_at on borrow_requests for grouping/sorting --- */
if (table_has_column($conn, 'borrow_requests', 'created_at')) {
    $br_ts_col = 'created_at';
} else {
    $br_ts_col = find_datetime_column($conn,'borrow_requests');
}
/* --------------------------------------------------------------------- */

$vr_ts_sql = $vr_ts_col ? "`{$vr_ts_col}`" : "NOW()";
$br_ts_sql = $br_ts_col ? "`{$br_ts_col}`" : "NOW()";

/* counts */
$countVrSql = "SELECT COUNT(*) AS total FROM view_request WHERE $vr_where";
$cst1 = $conn->prepare($countVrSql);
if ($cst1 && $vr_params) $cst1->bind_param($vr_types, ...$vr_params);
if ($cst1) { $cst1->execute(); $total1 = $cst1->get_result()->fetch_assoc()['total']; $cst1->close(); } else $total1 = 0;

$countBrSql = "SELECT COUNT(*) AS total FROM borrow_requests WHERE $br_where";
$cst2 = $conn->prepare($countBrSql);
if ($cst2 && $br_params) $cst2->bind_param($br_types, ...$br_params);
if ($cst2) { $cst2->execute(); $total2 = $cst2->get_result()->fetch_assoc()['total']; $cst2->close(); } else $total2 = 0;

$totalRows = (int)$total1 + (int)$total2;
$totalPages = $totalRows > 0 ? ceil($totalRows / $limit) : 1;

/* combined select */
$selectVr = "
SELECT
  transaction_id,
  full_name,
  request_type,
  COALESCE(payment_status,'') AS pay_status,
  COALESCE(document_status,'') AS doc_status,
  DATE_FORMAT({$vr_ts_sql}, '%M %d, %Y %h:%i %p') AS formatted_date,
  {$vr_ts_sql} AS created_at
FROM view_request
WHERE $vr_where
";

$selectBr = "
SELECT
  transaction_id,
  resident_name AS full_name,
  'Equipment Borrowing' AS request_type,
  '' AS pay_status,
  COALESCE(status,'') AS doc_status,
  DATE_FORMAT({$br_ts_sql}, '%M %d, %Y %h:%i %p') AS formatted_date,
  {$br_ts_sql} AS created_at
FROM borrow_requests
WHERE $br_where
";

$mainSql = "($selectVr) UNION ALL ($selectBr) ORDER BY created_at DESC LIMIT ? OFFSET ?";

$st = $conn->prepare($mainSql);
if (!$st) {
    die("Query preparation failed: " . htmlspecialchars($conn->error));
}
$bindTypes = $vr_types . $br_types . 'ii';
$bindParams = array_merge($vr_params, $br_params, [$limit, $offset]);
if ($bindParams) $st->bind_param($bindTypes, ...$bindParams);
$st->execute();
$result = $st->get_result();

function displayPaymentText($pay, $requestType) {
    $rt = strtolower(trim((string)$requestType));
    if (($pay === '' || $pay === null) && $rt === 'indigency') return 'Free of Charge';
    if ($pay !== '') return htmlspecialchars($pay);
    if ($rt === 'equipment borrowing') return 'No Payment';
    return '-';
}
function statusClass($s) {
    $d = strtolower(trim($s));
    switch ($d) {
        case 'for verification': return 'st-for-verification';
        case 'rejected': return 'st-rejected';
        case 'processing': return 'st-processing';
        case 'ready to release': return 'st-ready';
        case 'released':
        case 'completed': return 'st-released';
        case 'cancelled': return 'st-rejected';
        default: return 'st-pending';
    }
}
function humanDateLabel($dateTimeStr) {
    try { $dt = new DateTime($dateTimeStr); } catch (Exception $e) { return date('F d, Y', strtotime($dateTimeStr)); }
    $today = new DateTime('today'); $y = new DateTime('yesterday');
    if ($dt->format('Y-m-d') === $today->format('Y-m-d')) return 'Today';
    if ($dt->format('Y-m-d') === $y->format('Y-m-d')) return 'Yesterday';
    return $dt->format('F d, Y');
}

$filterMap = ['all'=>'All Requests','completed'=>'Completed Requests','cancelled'=>'Cancelled Requests'];
$pageTitle = $filterMap[$filter] ?? 'My Requests';
?>
<link href="https://fonts.googleapis.com/css2?family=Material+Icons+Outlined" rel="stylesheet">
<link rel="stylesheet" href="panels_user.css">

<div class="container py-3">
    <div class="card shadow-sm p-3">
        <div class="requests-header">
            <div class="header-controls">
                <!-- Enhanced Dropdown Filter -->
                <div class="filter-dropdown-container">
                    <div class="dropdown">
                        <button class="filter-dropdown-btn" type="button" id="requestsFilter" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="filter-title"><?= htmlspecialchars($pageTitle) ?></span>
                            <svg class="dropdown-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6,9 12,15 18,9"></polyline>
                            </svg>
                        </button>
                        <ul class="dropdown-menu enhanced-dropdown" aria-labelledby="requestsFilter">
                            <?php foreach ($filterMap as $key => $label):
                                $active = $filter === $key ? ' active' : '';
                                $url = '?page=userRequest&filter=' . urlencode($key) . '&search=' . urlencode($search);
                                echo "<li><a class='dropdown-item{$active}' href='{$url}' data-filter='{$key}'>{$label}</a></li>";
                            endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Enhanced Search Bar -->
                <div class="search-container">
                    <form class="search-form" method="get">
                        <input type="hidden" name="page" value="userRequest">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                        <div class="search-input-wrapper">
                            <input type="search" name="search" class="search-input" placeholder="Search" value="<?= htmlspecialchars($search) ?>" aria-label="Search">
                            <button class="search-btn" type="submit" aria-label="Search">
                                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="list-wrapper" style="height: 480px;">
            <?php
            if ($result->num_rows):
                $rows = [];
                while ($r = $result->fetch_assoc()) $rows[] = $r;

                $currentGroup = null;
                foreach ($rows as $r):
                    $createdAtRaw = $r['created_at'];
                    $groupLabel = humanDateLabel($createdAtRaw);

                    if ($groupLabel !== $currentGroup):
                        $currentGroup = $groupLabel;
                        echo "<div class='group-label'>{$currentGroup}</div>";
                    endif;

                    $txid = htmlspecialchars($r['transaction_id']);
                    $rtype = htmlspecialchars($r['request_type']);
                    $pay = $r['pay_status'] ?? '';
                    $doc = $r['doc_status'] ?? '';
                    $displayPay = displayPaymentText($pay, $rtype);
                    $statusTxt = $doc === '' ? 'Processing' : $doc;
                    $dataAttrs = "data-tx=\"" . htmlspecialchars($r['transaction_id']) . "\" data-type=\"" . htmlspecialchars($rtype) . "\"";
                    ?>
                    <div class="request-card mb-2" role="button" <?= $dataAttrs ?>>
                        <div class="col-tx"><?= $txid ?></div>
                        <div class="col-type"><div style="white-space:normal;"><?= $rtype ?></div></div>
                        <div class="col-pay"><?= htmlspecialchars($displayPay) ?></div>
                        <div class="col-status <?= statusClass($statusTxt) ?>"><div><?= htmlspecialchars($statusTxt) ?></div></div>
                    </div>
                <?php
                endforeach;
            else:
                ?>
                <div class="text-center text-muted py-4">No requests found.</div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-circle" role="navigation" aria-label="Pagination">
                    <?php
                    $range = 2; $ell = false;
                    for ($i = 1; $i <= $totalPages; $i++) {
                        if ($i == 1 || $i == $totalPages || abs($i - $page) <= $range) {
                            $isActive = $i == $page;
                            $liClass = $isActive ? 'page-item active' : 'page-item';
                            $ariaCurrent = $isActive ? ' aria-current="page"' : '';
                            $url = '?page=userRequest&pagination=' . $i . '&filter=' . urlencode($filter) . '&search=' . urlencode($search);
                            echo "<li class=\"{$liClass}\">";
                            echo "<a class=\"page-link page-circle\" href=\"" . htmlspecialchars($url) . "\"{$ariaCurrent}><span class=\"sr-only\">Page </span>" . $i . "</a>";
                            echo "</li>";
                            $ell = false;
                        } else {
                            if (!$ell) {
                                echo "<li class='page-item disabled'><span class='page-link page-circle page-ellipsis' aria-hidden='true'>…</span></li>";
                                $ell = true;
                            }
                        }
                    }
                    ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="requestDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-minimal">
      <div class="modal-body">
        <button type="button" class="modal-close-icon" data-bs-dismiss="modal" aria-label="Close">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>

        <div class="modal-band">
          <div class="band-icon" id="bandIcon"></div>
          <div class="band-center">
            <div class="band-info">
              <div class="band-type" id="bandType">Request</div>
              <div class="band-sub" id="bandSub" aria-hidden="true"></div>
            </div>
            <div class="band-badge" id="bandBadge" aria-hidden="true"></div>
          </div>
        </div>

        <div class="modal-inner" id="requestDetailInner">
          <div class="text-center text-muted" id="requestDetailLoading">Loading…</div>
        </div>

        <div class="modal-actions">
          <div class="modal-bottom-info" id="modalBottomInfo" aria-hidden="true"></div>
          <div style="display:flex; gap:.5rem; align-items:center;">
            <button id="modalCancelBtn" class="btn-cancel btn" data-tx="">Cancel Request</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function getStatusClass(status) {
    if (!status) return 'st-pending';
    const s = status.toString().trim().toLowerCase();
    if (s === 'for verification') return 'st-for-verification';
    if (s === 'rejected' || s === 'cancelled') return 'st-rejected';
    if (s === 'processing' || s === 'pending' || s === '') return 'st-processing';
    if (s === 'ready to release') return 'st-ready';
    if (s === 'released' || s === 'completed') return 'st-released';
    return 'st-pending';
}
function updateCardStatus(tx, newStatus) {
    document.querySelectorAll('.request-card').forEach(card => {
        if (card.getAttribute('data-tx') === tx) {
            const statusNode = card.querySelector('.col-status');
            if (statusNode) {
                statusNode.className = 'col-status ' + getStatusClass(newStatus);
                statusNode.innerHTML = '<div>' + newStatus + '</div>';
            }
        }
    });
}

function createBandIconHtml(rtype) {
    const low = (rtype || '').toString().toLowerCase();
    let icon = 'description';
    if (low.includes('equipment') || low.includes('borrow')) icon = 'build';
    else if (low.includes('barangay') || (low.includes('id') && !low.includes('residency'))) icon = 'badge';
    else if (low.includes('business') || low.includes('permit') || low.includes('clearance')) icon = 'apartment';
    else if (low.includes('solo') || low.includes('parent')) icon = 'family_restroom';
    else if (low.includes('guard') || low.includes('guardianship')) icon = 'security';
    else if (low.includes('certification') || low.includes('certificate') || low.includes('indigency') || low.includes('residency') || low.includes('good moral')) icon = 'description';
    return '<span class="material-icons-outlined" aria-hidden="true" style="font-size:28px;line-height:1;display:inline-block;">' + icon + '</span>';
}

document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('requestDetailModal');
    const bsModal = new bootstrap.Modal(modalEl);
    const modalInner = document.getElementById('requestDetailInner');
    const modalBandType = document.getElementById('bandType');
    const modalBandIcon = document.getElementById('bandIcon');
    const bandBadgeHolder = document.getElementById('bandBadge');
    const loading = document.getElementById('requestDetailLoading');
    const bandSub = document.getElementById('bandSub');

    const modalCancelBtn = document.getElementById('modalCancelBtn');
    const modalBottomInfo = document.getElementById('modalBottomInfo');

    modalCancelBtn.addEventListener('click', function (ev) {
        const txid = modalCancelBtn.getAttribute('data-tx');
        if (!txid) return alert('Transaction ID not set.');
        if (!confirm('Are you sure you want to cancel this request?')) return;
        modalCancelBtn.disabled = true; modalCancelBtn.textContent = 'Cancelling...';
        fetch(window.location.pathname, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'cancel', transaction_id: txid })
        })
        .then(r => r.json())
        .then(json => {
            if (json.ok) {
                updateCardStatus(txid, 'Cancelled');
                bandBadgeHolder.innerHTML = '';
                bandSub.textContent = 'Cancelled';
                alert(json.message || 'Request cancelled.');
                modalCancelBtn.disabled = true;
            } else {
                alert('Unable to cancel: ' + (json.message || 'Unknown error'));
            }
        })
        .catch(err => { console.error(err); alert('Network error while cancelling.'); })
        .finally(() => { modalCancelBtn.disabled = false; modalCancelBtn.textContent = 'Cancel Request'; });
    });

    document.querySelector('.list-wrapper').addEventListener('click', function (e) {
        let card = e.target.closest('.request-card');
        if (!card) return;
        const tx = card.getAttribute('data-tx');
        const rtype = card.getAttribute('data-type') || 'Request';
        if (!tx) return;

        modalBandType.textContent = rtype;
        bandSub.textContent = '';
        modalBandIcon.innerHTML = createBandIconHtml(rtype);

        modalInner.innerHTML = '';
        loading.style.display = 'block';
        modalInner.appendChild(loading);

        bandBadgeHolder.innerHTML = '';
        modalCancelBtn.setAttribute('data-tx', tx);
        modalCancelBtn.disabled = false;

        bsModal.show();

        const params = new URLSearchParams({ page: 'userRequest', transaction_id: tx, ajax: '1' });
        fetch('?' + params.toString(), { credentials: 'same-origin', method: 'GET' })
            .then(resp => { if (!resp.ok) throw new Error('Network error'); return resp.text(); })
            .then(html => {
                const container = document.createElement('div');
                container.innerHTML = html;
                const summary = container.querySelector('.detail-summary');
                const grid = container.querySelector('.detail-grid');
                const meta = container.querySelector('.detail-meta');

                modalInner.innerHTML = '';

                const txWrap = document.createElement('div');
                txWrap.className = 'tx-highlight';

                const txLabel = document.createElement('div');
                txLabel.className = 'tx-label';
                txLabel.textContent = 'Transaction ID';

                const txText = document.createElement('div');
                txText.className = 'tx-id';
                txText.textContent = tx;

                const txCopyBtn = document.createElement('button');
                txCopyBtn.className = 'tx-copy';
                txCopyBtn.title = 'Copy transaction id';
                txCopyBtn.setAttribute('aria-label','Copy transaction id');
                txCopyBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:16px;height:16px;"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';

                txCopyBtn.addEventListener('click', function () {
                    navigator.clipboard?.writeText(tx).then(()=> {
                        const prev = txCopyBtn.innerHTML;
                        txCopyBtn.innerHTML = '✓';
                        setTimeout(()=> txCopyBtn.innerHTML = prev, 900);
                    }).catch(()=> alert('Copy failed'));
                });

                txWrap.appendChild(txLabel);
                txWrap.appendChild(txText);
                txWrap.appendChild(txCopyBtn);
                modalInner.appendChild(txWrap);

                if (summary) modalInner.appendChild(summary.cloneNode(true));
                if (grid) modalInner.appendChild(grid.cloneNode(true));

                if (!summary && !grid) {
                    const bodyEl = container.querySelector('body');
                    modalInner.innerHTML = bodyEl ? bodyEl.innerHTML : container.innerHTML;
                }

                const usedMeta = meta || modalInner.querySelector('.detail-meta');
                const status = (usedMeta && usedMeta.getAttribute('data-status')) ? usedMeta.getAttribute('data-status') : 'Processing';

                let payMeta = null, docMeta = null;
                if (usedMeta) {
                    const payPct = usedMeta.getAttribute('data-pay-pct');
                    const payLabel = usedMeta.getAttribute('data-pay-label');
                    const payColor = usedMeta.getAttribute('data-pay-color');
                    if (payPct !== null) payMeta = { pct: Number(payPct), label: payLabel || '', color: payColor || '#6B7280' };

                    const docPct = usedMeta.getAttribute('data-doc-pct');
                    const docLabel = usedMeta.getAttribute('data-doc-label');
                    const docColor = usedMeta.getAttribute('data-doc-color');
                    if (docPct !== null) docMeta = { pct: Number(docPct), label: docLabel || '', color: docColor || '#6B7280' };
                }

                const sLow = (status || '').toLowerCase();
                if (sLow === 'released' || sLow === 'completed' || sLow === 'cancelled' || sLow === 'rejected') {
                    modalCancelBtn.disabled = true;
                } else {
                    modalCancelBtn.disabled = false;
                }

                modalBottomInfo.innerHTML = '';

                const pWrap = document.createElement('div'); pWrap.className = 'seg-wrap';
                const pTitle = document.createElement('div'); pTitle.className = 'seg-title'; pTitle.textContent = 'Payment';
                const pBar = document.createElement('div'); pBar.className = 'seg-bar';
                const pFill = document.createElement('div'); pFill.className = 'seg-fill';

                let pPct = (payMeta && typeof payMeta.pct === 'number' && !isNaN(payMeta.pct)) ? Number(payMeta.pct) : null;
                let pLabel = (payMeta && payMeta.label) ? String(payMeta.label) : null;
                let pColor = (payMeta && payMeta.color) ? String(payMeta.color) : null;

                if (pPct === null) {
                    pPct = 0;
                    pLabel = pLabel || 'Not set';
                    pColor = pColor || '#6B7280';
                    const payAttr = (usedMeta && usedMeta.getAttribute('data-pay')) ? usedMeta.getAttribute('data-pay') : '';
                    const payL = (payAttr || '').toString().trim().toLowerCase();
                    if (payL === '' && rtype && rtype.toLowerCase().includes('equipment')) {
                        pPct = 100; pLabel = 'No Payment Needed'; pColor = '#9CA3AF';
                    } else if (payL === 'paid') {
                        pPct = 100; pLabel = 'Paid'; pColor = '#059669';
                    } else if (payL === 'unpaid' || payL === 'pending') {
                        pPct = 35; pLabel = payAttr ? (payAttr.charAt(0).toUpperCase() + payAttr.slice(1)) : 'Unpaid'; pColor = '#F59E0B';
                    } else if (payL !== '') {
                        pPct = 60; pLabel = (payAttr.charAt(0).toUpperCase() + payAttr.slice(1)); pColor = '#2563EB';
                    }
                }

                pPct = Math.max(0, Math.min(100, Number(pPct) || 0));
                pLabel = pLabel || 'Not set';
                pColor = pColor || '#6B7280';

                pFill.style.width = '0%';
                pFill.style.background = pColor;
                pBar.appendChild(pFill);
                const pCaption = document.createElement('div'); pCaption.className = 'seg-caption'; pCaption.textContent = pLabel;
                pWrap.appendChild(pTitle); pWrap.appendChild(pBar); pWrap.appendChild(pCaption);

                const dWrap = document.createElement('div'); dWrap.className = 'seg-wrap';
                const dTitle = document.createElement('div'); dTitle.className = 'seg-title'; dTitle.textContent = 'Document';
                const dBar = document.createElement('div'); dBar.className = 'seg-bar';
                const dFill = document.createElement('div'); dFill.className = 'seg-fill';
                let dPct = 50, dLabel = 'Processing', dColor = '#F59E0B';
                if (sLow === 'released' || sLow === 'completed') { dPct = 100; dLabel = status; dColor = '#059669'; }
                else if (sLow === 'for verification') { dPct = 70; dLabel = 'For Verification'; dColor = '#2563EB'; }
                else if (sLow === 'processing' || sLow === 'pending' || sLow === '') { dPct = 35; dLabel = 'Processing'; dColor = '#F59E0B'; }
                else if (sLow === 'ready to release') { dPct = 85; dLabel = 'Ready to Release'; dColor = '#10B981'; }
                else if (sLow === 'cancelled' || sLow === 'rejected') { dPct = 100; dLabel = status; dColor = '#EF4444'; }
                dFill.style.width = '0%';
                dFill.style.background = dColor;
                dBar.appendChild(dFill);
                const dCaption = document.createElement('div'); dCaption.className = 'seg-caption'; dCaption.textContent = dLabel;
                dWrap.appendChild(dTitle); dWrap.appendChild(dBar); dWrap.appendChild(dCaption);

                modalBottomInfo.appendChild(pWrap); modalBottomInfo.appendChild(dWrap);
                modalBottomInfo.setAttribute('aria-hidden','false');

                setTimeout(()=> {
                    pFill.style.width = pPct + '%';
                    dFill.style.width = dPct + '%';
                }, 40);

                pFill.setAttribute('aria-valuenow', String(Math.round(pPct)));
                dFill.setAttribute('aria-valuenow', String(Math.round(dPct)));

                (function groupDetails() {
                    const grid = modalInner.querySelector('.detail-grid');
                    if (!grid) return;
                    const rows = Array.from(grid.querySelectorAll('.detail-row'));
                    if (!rows.length) return;

                    if ((rtype || '').toLowerCase().includes('equipment') || tx.startsWith('BRW-')) {
                        const sectBorrow = document.createElement('div'); sectBorrow.className = 'modal-section';
                        sectBorrow.innerHTML = '<h5 style="color:var(--green-a);font-weight:700;margin-bottom:8px;">Borrow Details</h5>';
                        const wrapperBorrow = document.createElement('div');
                        wrapperBorrow.className = 'detail-grid';
                        rows.forEach(row => wrapperBorrow.appendChild(row.cloneNode(true)));
                        modalInner.querySelectorAll('.detail-grid').forEach(n => n.remove());
                        sectBorrow.appendChild(wrapperBorrow);
                        modalInner.appendChild(sectBorrow);
                        return;
                    }

                    const applicationFields = ['civil status','purpose','claim date','birthdate','birthday','purok','address','resident','name','contact person'];
                    const paymentFields = ['payment method','amount','amount paid','payment status','fee','total','payment','or number'];
                    const requestFields = ['requested item','request','equipment','equipment sn','service','request type','document status','updated at','created at'];

                    function pickGroup(labelText) {
                        const l = labelText.toLowerCase();
                        if (applicationFields.some(k => l.includes(k))) return 'application';
                        if (paymentFields.some(k => l.includes(k))) return 'payment';
                        if (requestFields.some(k => l.includes(k))) return 'other';
                        return 'application';
                    }

                    const appRows = [], otherRows = [];
                    let payRows = [];

                    rows.forEach(row => {
                        const lab = (row.querySelector('.label') && row.querySelector('.label').textContent) ? row.querySelector('.label').textContent : '';
                        const labNorm = lab.trim().toLowerCase();
                        const duplicates = ['name','full name','resident','resident name','request','request type'];
                        if (duplicates.includes(labNorm)) return;
                        const g = pickGroup(lab);
                        if (g === 'application') appRows.push(row.cloneNode(true));
                        else if (g === 'payment') payRows.push(row.cloneNode(true));
                        else otherRows.push(row.cloneNode(true));
                    });

                    if ((rtype || '').toString().toLowerCase().includes('indigency')) {
                        const forbidden = ['payment method', 'amount'];
                        payRows = payRows.filter(row => {
                            const lbl = (row.querySelector('.label') && row.querySelector('.label').textContent) ? row.querySelector('.label').textContent.trim().toLowerCase() : '';
                            return !forbidden.includes(lbl);
                        });
                    }

                    modalInner.querySelectorAll('.detail-grid').forEach(n => n.remove());

                    // --- UPDATED: make Residency, Barangay Clearance and Barangay ID use three columns ---
                    const rtypeLow = (rtype || '').toLowerCase();
                    const isThreeCol = rtypeLow.includes('barangay') || rtypeLow.includes('residency') || (rtypeLow.includes('id') && !rtypeLow.includes('residency'));
                    if (isThreeCol) {
                        const cols = document.createElement('div');
                        cols.className = 'details-columns';
                        cols.style.display = 'grid';
                        cols.style.gridTemplateColumns = '1fr 1fr 1fr';
                        cols.style.gap = '20px';

                        function makeCol(title, rowsArr) {
                            const c = document.createElement('div');
                            const h = document.createElement('h5'); h.textContent = title;
                            h.style.color = 'var(--green-a)'; h.style.margin = '0 0 10px 0'; h.style.fontWeight = '700';
                            const body = document.createElement('div'); body.style.display = 'flex'; body.style.flexDirection = 'column'; body.style.gap = '10px';
                            rowsArr.forEach(r => body.appendChild(r));
                            c.appendChild(h); c.appendChild(body);
                            return c;
                        }

                        cols.appendChild(makeCol('Application Details', appRows));
                        // If payment rows empty, still include the middle column to keep consistent three-column layout
                        cols.appendChild(makeCol('Payment Details', payRows.length > 0 ? payRows : []));
                        cols.appendChild(makeCol('Other Details', otherRows));
                        modalInner.appendChild(cols);
                        return;
                    }
                    // --------------------------------------------------------------------

                    // fallback / existing behavior for other types
                    const cols = document.createElement('div'); cols.className = 'details-columns';
                    function makeCol(title, rowsArr) {
                        const c = document.createElement('div');
                        const h = document.createElement('h5'); h.textContent = title; h.style.color = 'var(--green-a)'; h.style.margin = '0 0 10px 0'; h.style.fontWeight = '700';
                        const body = document.createElement('div'); body.style.display = 'flex'; body.style.flexDirection = 'column'; body.style.gap = '10px';
                        rowsArr.forEach(r => body.appendChild(r));
                        c.appendChild(h); c.appendChild(body);
                        return c;
                    }

                    cols.appendChild(makeCol('Application Details', appRows));
                    if (payRows.length > 0) cols.appendChild(makeCol('Payment Details', payRows));
                    cols.appendChild(makeCol('Other Details', otherRows));
                    modalInner.appendChild(cols);
                })();

                // --- REPLACED: Insert QR INTO the Payment Details column if present; fallback to previous behavior ---
                (function maybeInsertQR() {
                    try {
                        // find "Payment Method" or "Payment" row inside the modal
                        const rows = Array.from(modalInner.querySelectorAll('.detail-row'));
                        const pmRow = rows.find(r => {
                            const lbl = r.querySelector('.label')?.textContent?.trim().toLowerCase() || '';
                            return lbl === 'payment method' || lbl === 'payment';
                        });
                        const pmValue = pmRow ? (pmRow.querySelector('.value')?.textContent?.trim() || '') : '';

                        if (!pmValue) return; // nothing to do

                        const pmLow = pmValue.toLowerCase();
                        if (!(pmLow.includes('brgy payment') || pmLow.includes('brgy payment device') || pmLow.includes('barangay payment'))) {
                            return; // only insert QR for Barangay Payment methods
                        }

                        // QR size (you can change this value to resize)
                        const qrSize = 160;

                        // Prepare QR section
                        const qrSection = document.createElement('div');
                        qrSection.className = 'modal-section qr-section';
                        qrSection.style.display = 'flex';
                        qrSection.style.flexDirection = 'column';
                        qrSection.style.alignItems = 'left';
                        qrSection.style.gap = '8px';
                        // qrSection.style.borderTop = '1px dashed rgba(0,0,0,0.06)';
                        // added green border, radius and a bit more padding for a cleaner look
                        qrSection.style.border = '1px solid #059669';
                        qrSection.style.borderRadius = '8px';
                        qrSection.style.padding = '15px';

                        const qrContainer = document.createElement('div');
                        qrContainer.id = 'modal-qrcode';
                        qrContainer.style.width = qrSize + 'px';
                        qrContainer.style.height = qrSize + 'px';
                        qrContainer.style.margin = '0 auto';

                        const downloadBtn = document.createElement('button');
                        downloadBtn.type = 'button';
                        downloadBtn.className = 'btn download-btn btn-success';
                        downloadBtn.textContent = 'Download QR Code';
                        downloadBtn.style.marginTop = '6px';

                        const hint = document.createElement('div');
                        hint.className = 'text-muted';
                        hint.style.fontSize = '0.5rem';
                        hint.style.textAlign = 'center';
                        hint.textContent = 'Scan this QR code at the Barangay Payment Device.';

                        qrSection.appendChild(qrContainer);
                        qrSection.appendChild(downloadBtn);
                        qrSection.appendChild(hint);

                        // Try to locate Payment Details column header (case-insensitive)
                        let paymentBody = null;
                        const headers = Array.from(modalInner.querySelectorAll('.details-columns h5'));
                        const paymentHeader = headers.find(h => h.textContent && h.textContent.trim().toLowerCase() === 'payment details');
                        if (paymentHeader) {
                            // the body element is expected to be the next sibling (the body created in makeCol)
                            paymentBody = paymentHeader.nextElementSibling;
                        }

                        // If paymentBody exists, append the QR inside it; otherwise fall back to previous insertion point
                        if (paymentBody) {
                            paymentBody.appendChild(qrSection);
                        } else {
                            // append after the transaction highlight as fallback
                            const txHighlight = modalInner.querySelector('.tx-highlight');
                            if (txHighlight && txHighlight.nextSibling) txHighlight.parentNode.insertBefore(qrSection, txHighlight.nextSibling);
                            else modalInner.appendChild(qrSection);
                        }

                        function generateQRonce() {
                            if (typeof QRCode !== 'undefined') {
                                // clear existing children (if any)
                                qrContainer.innerHTML = '';
                                new QRCode(qrContainer, {
                                    text: tx,
                                    width: qrSize,
                                    height: qrSize,
                                });

                                // hook up download action
                                downloadBtn.addEventListener('click', function () {
                                    // QRCode.js inserts an <img> or <canvas>
                                    const img = qrContainer.querySelector('img');
                                    if (img && img.src) {
                                        const link = document.createElement('a');
                                        link.href = img.src;
                                        link.download = tx + '.png';
                                        document.body.appendChild(link);
                                        link.click();
                                        document.body.removeChild(link);
                                        return;
                                    }
                                    // fallback for canvas
                                    const canvas = qrContainer.querySelector('canvas');
                                    if (canvas) {
                                        const link = document.createElement('a');
                                        link.href = canvas.toDataURL('image/png');
                                        link.download = tx + '.png';
                                        document.body.appendChild(link);
                                        link.click();
                                        document.body.removeChild(link);
                                    }
                                });
                                return true;
                            }
                            return false;
                        }

                        if (!generateQRonce()) {
                            // dynamically load QRCode.js (CDN) only if not present
                            const script = document.createElement('script');
                            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
                            script.onload = function () { generateQRonce(); };
                            script.onerror = function () {
                                // fail silently (or inform user)
                                hint.textContent = 'Unable to load QR generator. Please try downloading the QR from the submission screen.';
                            };
                            document.head.appendChild(script);
                        }

                    } catch (e) {
                        console.error('QR insert error', e);
                    }
                })();


            })
            .catch(err => {
                modalInner.innerHTML = '<div class="text-danger p-3">Failed to load details.</div>';
                console.error(err);
            });
    });

    document.querySelectorAll('.dropdown-menu .dropdown-item').forEach(function (el) {
        el.addEventListener('click', function (ev) {
            const label = this.textContent.trim();
            const titleNode = document.querySelector('#requestsFilter .gradient-title') || document.getElementById('requestsTitle');
            if (titleNode) titleNode.textContent = label;
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        modalInner.innerHTML = '';
        bandBadgeHolder.innerHTML = '';
        modalCancelBtn.removeAttribute('data-tx');
        modalCancelBtn.disabled = false;
        modalBottomInfo.innerHTML = '';
        modalBottomInfo.setAttribute('aria-hidden','true');
        bandSub.textContent = '';
    });

    function createBandIconHtml(rtype) {
        const low = (rtype || '').toString().toLowerCase();
        let icon = 'description';
        if (low.includes('equipment') || low.includes('borrow')) icon = 'build';
        else if (low.includes('barangay') || (low.includes('id') && !low.includes('residency'))) icon = 'badge';
        else if (low.includes('business') || low.includes('permit') || low.includes('clearance')) icon = 'apartment';
        else if (low.includes('solo') || low.includes('parent')) icon = 'family_restroom';
        else if (low.includes('guard') || low.includes('guardianship')) icon = 'security';
        else if (low.includes('certification') || low.includes('certificate') || low.includes('indigency') || low.includes('residency') || low.includes('good moral')) icon = 'description';
        return '<span class="material-icons-outlined" aria-hidden="true" style="font-size:28px;line-height:1;display:inline-block;">' + icon + '</span>';
    }
});
</script>

<?php
if (isset($st) && $st instanceof mysqli_stmt) {
    $st->close();
}
$conn->close();
?>
