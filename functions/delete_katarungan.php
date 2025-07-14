<?php
session_start();
require 'dbconn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// 1) AUTH & INPUTS
$transaction_id = $_POST['transaction_id'] ?? '';
$admin_id = $_SESSION['loggedInUserID'] ?? null;
$role = $_SESSION['loggedInUserRole'] ?? 'Unknown';
$pageNum = (int)($_POST['katarungan_page'] ?? 1);
$search = trim($_POST['katarungan_search'] ?? '');
$date_from = $_POST['katarungan_date_from'] ?? '';
$date_to = $_POST['katarungan_date_to'] ?? '';

if (!$transaction_id || !$admin_id) {
    // missing data — back to adminComplaints, default Katarungan tab
    header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page={$pageNum}&error=missing_data");
    exit;
}

// 2) DELETE the KP schedule
$del = $conn->prepare("
    DELETE FROM katarungang_pambarangay_records
      WHERE transaction_id = ?
");
$del->bind_param('s', $transaction_id);
if (!$del->execute()) {
    header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page={$pageNum}&error=delete_failed");
    exit;
}
$del->close();

// 3) UPDATE the original complaint back to PENDING
$upd = $conn->prepare("UPDATE complaint_records SET complaint_status = 'Pending' WHERE transaction_id = ?");
$upd->bind_param('s', $transaction_id);
$upd->execute();
$upd->close();

// 4) Log the deletion
$log = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description, created_at) VALUES (?, ?, 'DELETE', 'katarungang_pambarangay_records', ?, ?, NOW())");
$desc = "Deleted KP schedule for complaint {$transaction_id}";
$log->bind_param('isss', $admin_id, $role, $transaction_id, $desc);
$log->execute();
$log->close();

// 5) REDIRECT BACK TO KATARUNGAN TAB WITH YOUR FILTERS & PAGINATION
$params = [
  'page' => 'adminComplaints',
  'katarungan_page' => $pageNum,
  'katarungan_search' => $search,
  'katarungan_date_from' => $date_from,
  'katarungan_date_to' => $date_to,
  'katarungan_deleted' => $transaction_id,
];

header('Location: ../adminPanel.php?' . http_build_query($params));
exit;
?>
