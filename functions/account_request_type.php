<?php
// functions/account_request_type.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/dbconn.php';
session_start();

if (!isset($_SESSION['auth']) || !$_SESSION['auth']) {
    http_response_code(401);
    echo json_encode(['error'=>'Unauthorized']);
    exit;
}

$view = (isset($_GET['view']) && $_GET['view']==='declined') ? 'declined' : 'pending';

// Pagination parameters
$limit = 8;
$page_num = max((int)($_GET['page_num'] ?? 1), 1);
$offset = ($page_num - 1) * $limit;

// Determine table and order
if ($view === 'pending') {
    $tableName = 'pending_accounts';
    $orderBy = 'time_creation DESC';
} else {
    $tableName = 'declined_accounts';
    $orderBy = 'time_declined DESC';
}

// Get total count
$countSQL = "SELECT COUNT(*) AS total FROM `{$tableName}`";
$countStmt = $conn->prepare($countSQL);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);
if ($totalPages < 1) $totalPages = 1;
if ($page_num > $totalPages) $page_num = $totalPages;
$offset = ($page_num - 1) * $limit;

// Fetch paginated data
$sql = "SELECT * FROM `{$tableName}` ORDER BY {$orderBy} LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
if ($result) {
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
}
$stmt->close();

// Calculate display counters
$shownCount = count($rows);
$startDisplay = $totalRows > 0 ? ($offset + 1) : 0;
$endDisplay = $offset + $shownCount;

$data = [
    'view' => $view,
    'data' => $rows,
    'pagination' => [
        'current_page' => $page_num,
        'total_pages' => $totalPages,
        'total_rows' => $totalRows,
        'limit' => $limit,
        'start_display' => $startDisplay,
        'end_display' => $endDisplay,
        'shown_count' => $shownCount
    ]
];

$options = JSON_PRETTY_PRINT
         | JSON_UNESCAPED_UNICODE
         | JSON_PARTIAL_OUTPUT_ON_ERROR;

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, $options);
exit;
?>
