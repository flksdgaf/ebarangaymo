<?php
header('Content-Type: application/json');

require_once 'dbconn.php';

if (!isset($_GET['transaction_id'])) {
    echo json_encode(['error'=>'No transaction_id']);
    exit;
}

$txn = $conn->real_escape_string($_GET['transaction_id']);
$sql = "SELECT amount FROM barangay_id_requestss WHERE transaction_id='$txn' LIMIT 1";
$res = $conn->query($sql);

if ($res && $row = $res->fetch_assoc()) {
    echo json_encode(['amount' => $row['amount']]);
} else {
    echo json_encode(['error'=>'Not found']);
}

$conn->close();
?>
