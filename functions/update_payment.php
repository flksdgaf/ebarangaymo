<?php
require_once 'dbconn.php';
header('Content-Type: text/plain');
if (empty($_GET['transaction_id'])) {
  http_response_code(400); echo "Missing txn"; exit;
}
$txn = $conn->real_escape_string($_GET['transaction_id']);
$sql = "
  UPDATE residency_requests
     SET payment_status='Paid'
   WHERE transaction_id='$txn'
";
$conn->query($sql);
$conn->close();
echo "OK";
