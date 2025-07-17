<?php
require_once 'dbconn.php';

$transactionId = $_POST['transaction_id'] ?? '';
$requestType   = $_POST['request_type'] ?? '';

if (!$transactionId || !$requestType) {
  http_response_code(400);
  echo 'Missing transaction ID or request type';
  exit;
}

$typeMap = [
  'Barangay ID'       => 'barangay_id_requests',
  'Business Permit'   => 'business_permit_requests',
  'Good Moral'        => 'good_moral_requests',
  'Guardianship'      => 'guardianship_requests',
  'Indigency'         => 'indigency_requests',
  'Residency'         => 'residency_requests',
  'Solo Parent'       => 'solo_parent_requests',
];

if (!isset($typeMap[$requestType])) {
  http_response_code(400);
  echo 'Invalid request type';
  exit;
}

$table = $typeMap[$requestType];

$stmt = $conn->prepare("UPDATE `$table` SET document_status = 'Released' WHERE transaction_id = ?");
$stmt->bind_param('s', $transactionId);
if ($stmt->execute()) {
  echo 'success';
} else {
  http_response_code(500);
  echo 'Failed to update';
}
$stmt->close();
$conn->close();
?>
