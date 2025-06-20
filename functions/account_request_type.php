<?php
// functions/get_requests.php
require '../functions/dbconn.php'; // adjust path
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
header('Content-Type: application/json');
echo json_encode(['view'=>$view, 'data'=>$rows]);
