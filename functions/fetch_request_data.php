<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require 'dbconn.php';

$tid  = $_GET['transaction_id'] ?? '';
$type = $_GET['request_type']   ?? '';
if (!$tid || !$type) {
    http_response_code(400);
    echo json_encode(['error'=>'Missing transaction_id or request_type']);
    exit;
}

// map the human‐readable type to your actual table name
$tableMap = [
  'Barangay ID'      => 'barangay_id_requests',
  'Business Permit'  => 'business_permit_requests',
  'Good Moral'       => 'good_moral_requests',
  'Guardianship'     => 'guardianship_requests',
  'Indigency'        => 'indigency_requests',
  'Residency'        => 'residency_requests',
  'Solo Parent'      => 'solo_parent_requests',
];

if (!isset($tableMap[$type])) {
    http_response_code(400);
    echo json_encode(['error'=>'Unknown request_type']);
    exit;
}

$table = $tableMap[$type];
// build & run the query
$sql = "SELECT * FROM `{$table}` WHERE `transaction_id` = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $tid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
$conn->close();

// return the raw row
echo json_encode($row);
exit;
?>

<!-- MAY BABAGUHIN DITO -->
