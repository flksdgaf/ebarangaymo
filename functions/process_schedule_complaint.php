<?php
session_start();
require 'dbconn.php';

// 1) AUTH CHECK
if (!isset($_SESSION['auth'], $_SESSION['loggedInUserID']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit;
}

$pageNum        = $_POST['summon_page'] ?? 1;
$transaction_id = $_POST['transaction_id'] ?? '';
$date           = $_POST['scheduled_date'] ?? '';
$time           = $_POST['scheduled_time'] ?? '';
$account_id     = $_SESSION['loggedInUserID'];

// 2) VALIDATE
if (!$transaction_id || !$date || !$time) {
    header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&error=missing_fields");
    exit;
}
$scheduled_at = $date . ' ' . $time . ':00';

// 3) FETCH complaint_type
$stmt = $conn->prepare("
    SELECT complaint_type
      FROM complaint_records
     WHERE transaction_id = ?
");
$stmt->bind_param('s', $transaction_id);
$stmt->execute();
$result = $stmt->get_result();
if (!($row = $result->fetch_assoc())) {
    $stmt->close();
    header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&error=not_found&transaction_id={$transaction_id}");
    exit;
}
$complaint_type = $row['complaint_type'];
$stmt->close();

// 4) CHECK IF ALREADY SCHEDULED
$stmt = $conn->prepare("
    SELECT 1
      FROM katarungang_pambarangay_records
     WHERE transaction_id = ?
");
$stmt->bind_param('s', $transaction_id);
$stmt->execute();
if ($stmt->get_result()->fetch_row()) {
    $stmt->close();
    header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&error=summon_exists&transaction_id={$transaction_id}");
    exit;
}
$stmt->close();

// 5) INSERT INTO katarungang_pambarangay_records
$created_at = date('Y-m-d H:i:s');
$ins = $conn->prepare("
    INSERT INTO katarungang_pambarangay_records
      (account_id, transaction_id, complaint_type, complainant_affidavit, respondent_affidavit, complaint_stage, scheduled_at, created_at)
    VALUES (?, ?, ?, NULL, NULL, 'Punong Barangay', ?, ?)
");
$ins->bind_param(
    "issss",
    $account_id,
    $transaction_id,
    $complaint_type,
    $scheduled_at,
    $created_at
);

if (!$ins->execute()) {
    $ins->close();
    header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&error=insert_failed");
    exit;
}
$ins->close();

// 6) UPDATE ORIGINAL complaint_records
$upd = $conn->prepare("
    UPDATE complaint_records
       SET complaint_status = 'Scheduled'
     WHERE transaction_id = ?
");
$upd->bind_param("s", $transaction_id);
$upd->execute();
$upd->close();

// 7) SUCCESS REDIRECT
header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&scheduled_complaint_id={$transaction_id}");
exit;
?>
