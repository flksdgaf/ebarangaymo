<?php
// get_amount.php
require_once 'dbconn.php';
header('Content-Type: application/json');

if (empty($_GET['transaction_id'])) {
  http_response_code(400);
  echo json_encode(['error'=>'missing transaction_id']);
  exit;
}

$txn = $conn->real_escape_string($_GET['transaction_id']);
$sql = "
  SELECT amount, payment_status
    FROM residency_requests
   WHERE transaction_id='{$txn}'
   LIMIT 1
";
$res = $conn->query($sql);
if ($res && $row = $res->fetch_assoc()) {
  echo json_encode([
    'amount'         => floatval($row['amount']),
    'payment_status'=> $row['payment_status']
  ]);
} else {
  http_response_code(404);
  echo json_encode(['error'=>'not found']);
}
$conn->close();
