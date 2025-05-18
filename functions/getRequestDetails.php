<?php
require 'dbconn.php';
header('Content-Type: application/json');

$tid  = $_GET['transaction_id'] ?? '';
$type = $_GET['request_type']   ?? '';

if (!$tid || !$type) {
  http_response_code(400);
  echo json_encode(['error'=>'Missing parameters']);
  exit;
}

// pick table
switch ($type) {
  case 'Barangay ID':       $tbl='barangay_id_requests';    break;
  case 'Business Permit':   $tbl='business_permit_requests';break;
  case 'Certification':     $tbl='certification_requests';  break;
  default:
    http_response_code(400);
    echo json_encode(['error'=>'Unknown type']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM {$tbl} WHERE transaction_id=? LIMIT 1");
$stmt->bind_param('s',$tid);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
$conn->close();

// strip internal keys
foreach (['id','account_id','request_type','created_at'] as $k) {
  unset($data[$k]);
}

echo json_encode($data);
