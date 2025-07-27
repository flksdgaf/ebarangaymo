<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require '../functions/dbconn.php';

$transactionId = $_POST['transaction_id'] ?? '';
$reason = trim($_POST['rejection_reason'] ?? '');
$userId = $_SESSION['loggedInUserID'] ?? null;

if (!$transactionId || !$reason || !$userId) {
  header("Location: ../adminPanel.php?page=adminRequest&error=missing_data");
  exit;
}

// 1. Get request info from view_request
$stmt = $conn->prepare("SELECT full_name, request_type, payment_method FROM view_request WHERE transaction_id = ?");
$stmt->bind_param("s", $transactionId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
  header("Location: ../adminPanel.php?page=adminRequest&error=not_found");
  exit;
}

$fullName = $request['full_name'];
$requestType = $request['request_type'];
$paymentMethod = $request['payment_method'];

// 2. Determine actual table to update based on request_type
$mapping = [
  'Barangay ID'       => 'barangay_id_requests',
  'Business Permit'   => 'business_permit_requests',
  'Good Moral'        => 'good_moral_requests',
  'Guardianship'      => 'guardianship_requests',
  'Indigency'         => 'indigency_requests',
  'Residency'         => 'residency_requests',
  'Solo Parent'       => 'solo_parent_requests'
];

$table = $mapping[$requestType] ?? null;

if (!$table) {
  header("Location: ../adminPanel.php?page=adminRequest&error=unknown_type");
  exit;
}

// 3. Update document_status to Rejected
$updateStmt = $conn->prepare("UPDATE {$table} SET document_status = 'Rejected' WHERE transaction_id = ?");
$updateStmt->bind_param("s", $transactionId);
$updateStmt->execute();
$updateStmt->close();

// 4. Insert rejection into transaction_history
$insertStmt = $conn->prepare("
  INSERT INTO transaction_history (
    transaction_id, full_name, request_type, payment_method,
    amount_paid, issued_date,
    action_type, action_details) VALUES (?, ?, ?, ?, NULL, NULL, 'Rejected', ?)
");
$insertStmt->bind_param("sssss", $transactionId, $fullName, $requestType, $paymentMethod, $reason);
$insertStmt->execute();
$insertStmt->close();

// 5. Redirect with success
header("Location: ../adminPanel.php?page=adminRequest&rejected_id=" . urlencode($transactionId));
exit;
?>
