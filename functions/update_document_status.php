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
  'Barangay Clearance' => 'barangay_clearance_requests',
  'Business Clearance' => 'business_clearance_requests',
  'First Time Job Seeker' => 'job_seeker_requests'
];

if (!isset($typeMap[$requestType])) {
  http_response_code(400);
  echo 'Invalid request type';
  exit;
}

$table = $typeMap[$requestType];

// $stmt = $conn->prepare("UPDATE `$table` SET document_status = 'Released' WHERE transaction_id = ?");
// $stmt->bind_param('s', $transactionId);
// if ($stmt->execute()) {
//   echo 'success';
// } else {
//   http_response_code(500);
//   echo 'Failed to update';
// }
// $stmt->close();
// $conn->close();

// ── 1) Update the document_status ────────────────────────────
$update = $conn->prepare("UPDATE `$table` SET document_status = 'Released' WHERE transaction_id = ?");
$update->bind_param('s', $transactionId);

if (! $update->execute()) {
  http_response_code(500);
  echo 'Failed to update';
  exit;
}
$update->close();

// ── 2) Get full name from view_request ───────────────────────
$fetch = $conn->prepare("SELECT full_name FROM view_request WHERE transaction_id = ?");
$fetch->bind_param('s', $transactionId);
$fetch->execute();
$result = $fetch->get_result();

$fullName = '';
if ($result && $row = $result->fetch_assoc()) {
  $fullName = $row['full_name'];
}
$fetch->close();

// ── 3) Insert into transaction_history ───────────────────────
$log = $conn->prepare(
  "INSERT INTO transaction_history (transaction_id, full_name, request_type, action_details)
   VALUES (?, ?, ?, 'Released')"
);
$log->bind_param('sss', $transactionId, $fullName, $requestType);
$log->execute();
$log->close();

// ── 4) Done ──────────────────────────────────────────────────
echo 'success';
$conn->close();
?>
