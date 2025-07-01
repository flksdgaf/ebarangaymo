<?php
// functions/fetch_complaint_data.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require 'dbconn.php';

$tid = $_GET['transaction_id'] ?? '';
if (!$tid) {
  http_response_code(400);
  echo json_encode(['error'=>'Missing transaction_id']);
  exit;
}

$stmt = $conn->prepare("
  SELECT * 
    FROM complaint_records 
   WHERE transaction_id = ? 
   LIMIT 1
");
$stmt->bind_param('s', $tid);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
$conn->close();

echo json_encode($data);
exit;
?>
