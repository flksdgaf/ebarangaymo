<?php
include 'dbconn.php';

$requestTypes = [
  'Barangay ID' => 'barangay_id_requests',
  'Business Permit' => 'business_permit_requests',
  'Certification' => 'certification_requests'
];

$transactionId = $_POST['transaction_id'] ?? '';
$tableType = $_POST['table_type'] ?? '';
$paymentStatus = $_POST['payment_status'] ?? '';
$documentStatus = $_POST['document_status'] ?? '';

// Get the current filter from the GET parameter
$filter = $_GET['filter'] ?? 'All';  // Default to 'All' if filter is not set

if (!$transactionId || !$tableType || !$paymentStatus || !$documentStatus) {
  die("Missing required fields.");
}

if (!array_key_exists($tableType, $requestTypes)) {
  die("Invalid table type.");
}

$tableName = $requestTypes[$tableType];

// Update query
$stmt = $conn->prepare("UPDATE $tableName SET payment_status = ?, document_status = ? WHERE transaction_id = ?");
$stmt->bind_param("sss", $paymentStatus, $documentStatus, $transactionId);

if ($stmt->execute()) {
  // Redirect back to the same page with the current filter
  header("Location: ../adminPanel.php?page=adminRequest&filter=" . urlencode($filter));
  exit;
} else {
  echo "Error updating record: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
