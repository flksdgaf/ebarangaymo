<?php
session_start();
require 'dbconn.php';

// 1. Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit;
}

// 2. Inputs
$transaction_id = $_POST['transaction_id'] ?? '';
$admin_id = $_SESSION['loggedInUserID'] ?? null;
$role = $_SESSION['loggedInUserRole'] ?? 'Unknown';

// Preserve filters
$pageNum = $_POST['summon_page'] ?? 1;
$search = trim($_POST['summon_search'] ?? '');
$date_from = $_POST['summon_date_from'] ?? '';
$date_to = $_POST['summon_date_to'] ?? '';

if (!$transaction_id || !$admin_id) {
  header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&error=missing_data");
  exit;
}

// 3. Delete associated Katarungan record if it exists
$delKP = $conn->prepare("DELETE FROM katarungang_pambarangay_records WHERE transaction_id = ?");
$delKP->bind_param("s", $transaction_id);
$delKP->execute(); // ignore success/failure, just attempt
$delKP->close();

// 4. Delete the complaint record
$del = $conn->prepare("DELETE FROM complaint_records WHERE transaction_id = ?");
$del->bind_param("s", $transaction_id);
if (!$del->execute()) {
  $del->close();
  header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&error=delete_failed");
  exit;
}
$del->close();

// 5. Log the deletion of the complaint
$desc = "Deleted summon (complaint) record with transaction ID: $transaction_id";
$log = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description, created_at) VALUES (?, ?, 'DELETE', 'complaint_records', ?, ?, NOW())");
$log->bind_param("isss", $admin_id, $role, $transaction_id, $desc);
$log->execute();
$log->close();

// 6. Optionally: log the Katarungan schedule deletion (if needed)
$descKP = "Auto-deleted KP schedule for complaint $transaction_id (along with complaint)";
$logKP = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description, created_at) VALUES (?, ?, 'DELETE', 'katarungang_pambarangay_records', ?, ?, NOW())");
$logKP->bind_param("isss", $admin_id, $role, $transaction_id, $descKP);
$logKP->execute();
$logKP->close();

// 7. Redirect back with filters + alert
$params = [
  'page' => 'adminComplaints',
  'summon_page' => $pageNum,
  'summon_search' => $search,
  'summon_date_from' => $date_from,
  'summon_date_to' => $date_to,
  'deleted_complaint_id' => $transaction_id,
];

header('Location: ../adminPanel.php?' . http_build_query($params));
exit;
