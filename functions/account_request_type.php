<?php
// functions/account_request_type.php
header('Content-Type: application/json; charset=utf-8');

// 2) Log errors instead of printing them
ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/dbconn.php';
session_start();

if (!isset($_SESSION['auth']) || !$_SESSION['auth']) {
    http_response_code(401);
    echo json_encode(['error'=>'Unauthorized']);
    exit;
}

$view = (isset($_GET['view']) && $_GET['view']==='declined') ? 'declined' : 'pending';
if ($view==='pending') {
    $sql = "SELECT * FROM pending_accounts ORDER BY time_creation DESC";
} else {
    $sql = "SELECT * FROM declined_accounts ORDER BY time_declined DESC";
}
$result = $conn->query($sql);
$rows = [];
if ($result) {
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
}
// STEP 8: ECHO JSON with partial output on error
$data = ['view' => $view, 'data' => $rows];
$options = JSON_PRETTY_PRINT
         | JSON_UNESCAPED_UNICODE
         | JSON_PARTIAL_OUTPUT_ON_ERROR;

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, $options);
exit;

