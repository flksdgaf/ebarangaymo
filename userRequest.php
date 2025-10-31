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
                
                // Special handling for Solo Parent children_data JSON
                if ($tbl === 'solo_parent_requests' && !empty($drow['children_data'])) {
                    $childrenJson = $drow['children_data'];
                    $childrenArray = json_decode($childrenJson, true);
                    
                    if (is_array($childrenArray) && count($childrenArray) > 0) {
                        foreach ($childrenArray as $index => $child) {
                            $childNum = $index + 1;
                            if (!empty($child['name'])) {
                                echo "<div class=\"detail-row\"><div class=\"label\">Child #{$childNum} Name</div><div class=\"value\">" . htmlspecialchars($child['name']) . "</div></div>";
                            }
                            if (!empty($child['age'])) {
                                echo "<div class=\"detail-row\"><div class=\"label\">Child #{$childNum} Age</div><div class=\"value\">" . htmlspecialchars($child['age']) . "</div></div>";
                            }
                            if (!empty($child['sex'])) {
                                echo "<div class=\"detail-row\"><div class=\"label\">Child #{$childNum} Sex</div><div class=\"value\">" . htmlspecialchars($child['sex']) . "</div></div>";
                            }
                            $printed = true;
                        }
                    }
                }
                
                foreach ($drow as $col => $val) {
                    // Skip children_data column since we handled it above
                    if ($col === 'children_data') continue;
                    
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

<title>eBarangay Mo | Requests</title>

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

        <div class="list-wrapper">
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
                        <div class="col-type"><?= $rtype ?></></div>
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
  <div class="modal-dialog modal-dialog-centered modal-xl">
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

        <!-- Header Info Section -->
        <div class="modal-header-info">
          <div class="header-info-grid">
            <div>
              <div class="info-label">Transaction ID</div>
              <div class="info-value" id="modalTransactionId">-</div>
            </div>
            <div>
              <div class="info-label">Request by</div>
              <div class="info-value" id="modalRequester">-</div>
            </div>
          </div>
        </div>

        <!-- Two-Column Layout: Details + Status -->
        <div class="modal-main-grid">
          
          <!-- Left Column: Request Details -->
          <div>
            <h3 style="color: var(--green-a); font-weight: 700; font-size: 1.15rem; margin-bottom: 12px;">Request Details</h3>
            
            <div id="requestDetailInner">
              <div class="text-center text-muted">Loading…</div>
            </div>
          </div>

          <!-- Right Column: Request Status -->
          <div>
            <h3 style="color: var(--green-a); font-weight: 700; font-size: 1.25rem; margin-bottom: 16px;">Request Status</h3>
            
            <div id="statusTimeline" style="display: flex; flex-direction: column; gap: 12px;">
              <!-- Status items will be inserted here -->
            </div>
          </div>

        </div>

        <!-- Modal Actions at Bottom -->
        <div class="modal-actions">
          <button id="modalCancelBtn" class="btn-cancel" data-tx="">Cancel Request</button>
        </div>
        
        <div class="modal-bottom-info" id="modalBottomInfo" aria-hidden="true" style="display:none;"></div>
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
    let icon = 'description'; // Default: document icon
    
    if (low.includes('equipment') || low.includes('borrow')) {
        icon = 'construction'; // Tools/construction icon
    } else if (low.includes('barangay id') || (low.includes('id') && !low.includes('residency'))) {
        icon = 'badge'; // ID badge icon
    } else if (low.includes('business')) {
        icon = 'business'; // Business building icon
    } else if (low.includes('clearance')) {
        icon = 'verified_user'; // Clearance/verification icon
    } else if (low.includes('solo') || low.includes('parent')) {
        icon = 'family_restroom'; // Family icon
    } else if (low.includes('guard') || low.includes('guardianship')) {
        icon = 'shield'; // Protection/guardian icon
    } else if (low.includes('indigency')) {
        icon = 'volunteer_activism'; // Helping hands icon
    } else if (low.includes('residency') || low.includes('resident')) {
        icon = 'home'; // Home/residence icon
    } else if (low.includes('good moral') || low.includes('moral')) {
        icon = 'workspace_premium'; // Certificate/award icon
    } else if (low.includes('certification') || low.includes('certificate')) {
        icon = 'verified'; // Verified/certified icon
    }
    
    return '<span class="material-icons-outlined" aria-hidden="true" style="font-size:28px;line-height:1;display:inline-block;">' + icon + '</span>';
}

document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('requestDetailModal');
    const bsModal = new bootstrap.Modal(modalEl);
    const modalInner = document.getElementById('requestDetailInner');
    const modalBandType = document.getElementById('bandType');
    const modalBandIcon = document.getElementById('bandIcon');
    const bandBadgeHolder = document.getElementById('bandBadge');
    const loading = document.createElement('div');
    loading.id = 'requestDetailLoading';
    loading.className = 'text-center text-muted';
    loading.textContent = 'Loading…';
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
                const meta = container.querySelector('.detail-meta');

                // Update Transaction ID
                const txIdEl = document.getElementById('modalTransactionId');
                if (txIdEl) txIdEl.textContent = tx;
                
                // Setup copy button
                const copyBtn = document.getElementById('txCopyBtn');
                if (copyBtn) {
                    copyBtn.onclick = function() {
                        navigator.clipboard?.writeText(tx).then(()=> {
                            const prev = copyBtn.innerHTML;
                            copyBtn.innerHTML = '✓';
                            setTimeout(()=> copyBtn.innerHTML = prev, 900);
                        }).catch(()=> alert('Copy failed'));
                    };
                }

                // Get requester name
                const summary = container.querySelector('.detail-summary');
                let requesterName = 'N/A';
                if (summary) {
                    const nameDiv = summary.querySelector('.v');
                    if (nameDiv) requesterName = nameDiv.textContent.trim();
                }
                const requesterEl = document.getElementById('modalRequester');
                if (requesterEl) requesterEl.textContent = requesterName;

                // Clear and populate detail sections
                const detailInner = document.getElementById('requestDetailInner');
                if (!detailInner) return;
                detailInner.innerHTML = '';

                const grid = container.querySelector('.detail-grid');
                if (grid) {
                    const rows = Array.from(grid.querySelectorAll('.detail-row'));
                    
                    const applicationFields = ['civil status','purpose','claim date','birthdate','birthday','purok','address','resident','name','contact person','age','residing years','claim time'];
                    const paymentFields = ['payment method','amount','amount paid','payment status','fee','total','payment','or number'];
                    const otherFields = ['request for','document status','updated at','request source','equipment','authorization'];

                    const appRows = [], payRows = [], otherRows = [];

                    rows.forEach(row => {
                        const lab = row.querySelector('.label')?.textContent.trim().toLowerCase() || '';
                        
                        // Skip duplicates
                        const duplicates = ['name','full name','resident','resident name','request','request type'];
                        if (duplicates.includes(lab)) return;
                        
                        // Skip empty values
                        const val = row.querySelector('.value')?.textContent.trim() || '';
                        if (!val) return;

                        if (applicationFields.some(k => lab.includes(k))) appRows.push(row.cloneNode(true));
                        else if (paymentFields.some(k => lab.includes(k))) payRows.push(row.cloneNode(true));
                        else if (otherFields.some(k => lab.includes(k))) otherRows.push(row.cloneNode(true));
                        else appRows.push(row.cloneNode(true));
                    });

                    // Create three-column layout
                    const cols = document.createElement('div');
                    cols.style.display = 'grid';
                    cols.style.gridTemplateColumns = '1fr 1fr 1fr';
                    cols.style.gap = '20px';

                    function makeCol(title, rowsArr) {
                        const c = document.createElement('div');
                        const h = document.createElement('h5');
                        h.textContent = title;
                        h.style.color = 'var(--green-a)';
                        h.style.margin = '0 0 8px 0';
                        h.style.fontWeight = '600';
                        h.style.fontSize = '0.9rem';
                        
                        const body = document.createElement('div');
                        body.style.display = 'flex';
                        body.style.flexDirection = 'column';
                        body.style.gap = '6px';
                        
                        rowsArr.forEach(r => body.appendChild(r));
                        
                        c.appendChild(h);
                        c.appendChild(body);
                        return c;
                    }

                    // Determine if we should show Payment Details
                    const rtypeLower = (rtype || '').toLowerCase();
                    const hidePayment = rtypeLower.includes('indigency') || 
                                    rtypeLower.includes('first time job seeker') || 
                                    rtypeLower.includes('equipment') || 
                                    rtypeLower.includes('borrow');

                    cols.appendChild(makeCol('Application Details', appRows));

                    // Only add Payment Details column if it should be shown
                    if (!hidePayment && payRows.length > 0) {
                        cols.appendChild(makeCol('Payment Details', payRows));
                        // Adjust grid to 3 columns
                        cols.style.gridTemplateColumns = '1fr 1fr 1fr';
                    } else {
                        // Adjust grid to 2 columns when Payment Details is hidden
                        cols.style.gridTemplateColumns = '1.2fr 1fr';
                    }

                    cols.appendChild(makeCol('Other Details', otherRows));
                    
                    detailInner.appendChild(cols);

                    // Handle QR code for payment
                    const pmRow = rows.find(r => {
                        const lbl = r.querySelector('.label')?.textContent?.trim().toLowerCase() || '';
                        return lbl === 'payment method' || lbl === 'payment';
                    });
                    const pmValue = pmRow?.querySelector('.value')?.textContent?.trim() || '';
                    const pmLow = pmValue.toLowerCase();

                    if (pmLow.includes('brgy payment') || pmLow.includes('barangay payment')) {
                        const qrSize = 140;
                        const qrSection = document.createElement('div');
                        qrSection.style.marginTop = '16px';
                        qrSection.style.padding = '12px';
                        qrSection.style.border = '1px solid #059669';
                        qrSection.style.borderRadius = '8px';
                        qrSection.style.textAlign = 'center';

                        const qrContainer = document.createElement('div');
                        qrContainer.id = 'modal-qrcode';
                        qrContainer.style.width = qrSize + 'px';
                        qrContainer.style.height = qrSize + 'px';
                        qrContainer.style.margin = '0 auto 8px';

                        const downloadBtn = document.createElement('button');
                        downloadBtn.type = 'button';
                        downloadBtn.className = 'btn btn-success btn-sm';
                        downloadBtn.textContent = 'Download QR Code';

                        const hint = document.createElement('div');
                        hint.style.fontSize = '0.75rem';
                        hint.style.color = '#6c757d';
                        hint.style.marginTop = '6px';
                        hint.textContent = 'Scan this QR code at the Barangay Payment Device.';

                        qrSection.appendChild(qrContainer);
                        qrSection.appendChild(downloadBtn);
                        qrSection.appendChild(hint);

                        // Find payment column and insert QR
                        const paymentCol = Array.from(cols.querySelectorAll('h5')).find(h => h.textContent === 'Payment Details')?.parentElement;
                        if (paymentCol) paymentCol.appendChild(qrSection);

                        // Generate QR
                        function generateQRonce() {
                            if (typeof QRCode !== 'undefined') {
                                qrContainer.innerHTML = '';
                                new QRCode(qrContainer, { text: tx, width: qrSize, height: qrSize });
                                
                                downloadBtn.onclick = function() {
                                    const img = qrContainer.querySelector('img');
                                    if (img?.src) {
                                        const link = document.createElement('a');
                                        link.href = img.src;
                                        link.download = tx + '.png';
                                        document.body.appendChild(link);
                                        link.click();
                                        document.body.removeChild(link);
                                    }
                                };
                                return true;
                            }
                            return false;
                        }

                        if (!generateQRonce()) {
                            const script = document.createElement('script');
                            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
                            script.onload = generateQRonce;
                            document.head.appendChild(script);
                        }
                    }
                }

                // Build status timeline
                const payStatus = meta?.getAttribute('data-pay') || '';
                const docStatus = meta?.getAttribute('data-status') || 'Processing';
                
                buildStatusTimeline(docStatus, payStatus, rtype);

                // Handle cancel button state
                const sLow = docStatus.toLowerCase();
                if (modalCancelBtn) {
                    modalCancelBtn.disabled = (sLow === 'released' || sLow === 'completed' || sLow === 'cancelled' || sLow === 'rejected');
                    modalCancelBtn.setAttribute('data-tx', tx);
                }
            })
            .catch(err => {
                const detailInner = document.getElementById('requestDetailInner');
                if (detailInner) detailInner.innerHTML = '<div class="text-danger p-3">Failed to load details.</div>';
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
        const inner = document.getElementById('requestDetailInner');
        const badge = document.getElementById('bandBadge');
        const cancelBtn = document.getElementById('modalCancelBtn');
        const bottomInfo = document.getElementById('modalBottomInfo');
        const sub = document.getElementById('bandSub');
        
        if (inner) inner.innerHTML = '';
        if (badge) badge.innerHTML = '';
        if (cancelBtn) {
            cancelBtn.removeAttribute('data-tx');
            cancelBtn.disabled = false;
        }
        if (bottomInfo) {
            bottomInfo.innerHTML = '';
            bottomInfo.setAttribute('aria-hidden','true');
        }
        if (sub) sub.textContent = '';
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

    function buildStatusTimeline(docStatus, payStatus, requestType) {
        const timeline = document.getElementById('statusTimeline');
        timeline.innerHTML = '';

        const rtLow = (requestType || '').toLowerCase();
        const isIndigency = rtLow.includes('indigency');
        const isBorrowing = rtLow.includes('equipment') || rtLow.includes('borrow');

        const statuses = [
            { key: 'submitted', label: 'Request Submitted', icon: 'check_circle' },
            { key: 'verification', label: 'Request for Verification', icon: 'search' },
            { key: 'payment', label: 'Payment Paid', icon: 'payments', hideFor: ['indigency', 'borrowing'] },
            { key: 'processing', label: 'Processing', icon: 'sync' },
            { key: 'ready', label: 'Ready for Release', icon: 'inventory' },
            { key: 'released', label: 'Released', icon: 'done_all' }
        ];

        function getStatusState(key) {
            const dLow = (docStatus || '').toLowerCase();
            const pLow = (payStatus || '').toLowerCase();

            if (key === 'submitted') return 'completed';
            if (key === 'verification') {
                if (dLow === 'for verification' || dLow === 'processing' || dLow === 'ready to release' || dLow === 'released' || dLow === 'completed') return 'completed';
                return 'pending';
            }
            if (key === 'payment') {
                if (isIndigency) return 'completed'; // Free
                if (isBorrowing) return 'skipped';
                if (pLow === 'paid') return 'completed';
                if (dLow === 'processing' || dLow === 'ready to release' || dLow === 'released' || dLow === 'completed') return 'active';
                return 'pending';
            }
            if (key === 'processing') {
                if (dLow === 'processing' || dLow === 'ready to release' || dLow === 'released' || dLow === 'completed') return 'completed';
                return 'pending';
            }
            if (key === 'ready') {
                if (dLow === 'ready to release' || dLow === 'released' || dLow === 'completed') return 'completed';
                if (dLow === 'processing') return 'active';
                return 'pending';
            }
            if (key === 'released') {
                if (dLow === 'released' || dLow === 'completed') return 'completed';
                if (dLow === 'ready to release') return 'active';
                return 'pending';
            }
            return 'pending';
        }

        statuses.forEach((status, idx) => {
            if (status.hideFor) {
                if (isIndigency && status.hideFor.includes('indigency')) return;
                if (isBorrowing && status.hideFor.includes('borrowing')) return;
            }

            const state = getStatusState(status.key);
            if (state === 'skipped') return;

            const item = document.createElement('div');
            item.style.display = 'flex';
            item.style.alignItems = 'flex-start';
            item.style.gap = '10px';
            item.style.position = 'relative';

            const iconWrapper = document.createElement('div');
            iconWrapper.style.width = '32px';
            iconWrapper.style.height = '32px';
            iconWrapper.style.borderRadius = '50%';
            iconWrapper.style.display = 'flex';
            iconWrapper.style.alignItems = 'center';
            iconWrapper.style.justifyContent = 'center';
            iconWrapper.style.flexShrink = '0';
            iconWrapper.style.zIndex = '1';

            if (state === 'completed') {
                iconWrapper.style.background = '#059669';
                iconWrapper.style.color = 'white';
            } else if (state === 'active') {
                iconWrapper.style.background = '#F59E0B';
                iconWrapper.style.color = 'white';
            } else {
                iconWrapper.style.background = '#E5E7EB';
                iconWrapper.style.color = '#9CA3AF';
            }

            iconWrapper.innerHTML = `<span class="material-icons-outlined" style="font-size:18px;">${status.icon}</span>`;

            const textWrapper = document.createElement('div');
            textWrapper.style.flex = '1';
            textWrapper.style.paddingTop = '4px';

            const label = document.createElement('div');
            label.textContent = status.label;
            label.style.fontWeight = state === 'active' ? '600' : '500';
            label.style.fontSize = '0.82rem';
            label.style.color = state === 'completed' || state === 'active' ? '#059669' : '#6B7280';

            textWrapper.appendChild(label);

            item.appendChild(iconWrapper);
            item.appendChild(textWrapper);

            // Add connecting line
            if (idx < statuses.length - 1) {
                const line = document.createElement('div');
                line.style.position = 'absolute';
                line.style.left = '15px';
                line.style.top = '32px';
                line.style.bottom = '-12px';
                line.style.width = '2px';
                line.style.background = state === 'completed' ? '#059669' : '#E5E7EB';
                item.appendChild(line);
            }

            timeline.appendChild(item);
        });
    }

    (function autoOpenModal() {
        const urlParams = new URLSearchParams(window.location.search);
        const openTx = urlParams.get('open_tx');
        
        if (openTx) {
            // Wait for page to fully load
            setTimeout(() => {
                // Find the card with matching transaction ID
                const targetCard = document.querySelector(`.request-card[data-tx="${openTx}"]`);
                
                if (targetCard) {
                    // Trigger click on the card to open modal
                    targetCard.click();
                    
                    // Clean up URL (remove the open_tx parameter)
                    const newUrl = new URL(window.location);
                    newUrl.searchParams.delete('open_tx');
                    window.history.replaceState({}, '', newUrl);
                }
            }, 300); // Small delay to ensure modal handlers are ready
        }
    })();
</script>

<?php
if (isset($st) && $st instanceof mysqli_stmt) {
    $st->close();
}
$conn->close();
?>
