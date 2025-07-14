<?php
session_start();
require 'dbconn.php';

$pageNum = $_POST['katarungan_page'] ?? 1;

// AUTH
if (!($_SESSION['auth'] ?? false)) {
  header("Location: ../index.php");
  exit();
}

$tid = $_POST['transaction_id'] ?? '';
if (!$tid) {
  header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&error=missing_tid");
  exit();
}

// Check current stage
$stage = '';
$getStage = $conn->prepare("SELECT complaint_stage FROM katarungang_pambarangay_records WHERE transaction_id = ?");
$getStage->bind_param('s', $tid);
$getStage->execute();
$getStage->bind_result($stage);
$getStage->fetch();
$getStage->close();

$nextStage = '';
$scheduledDate = '';
$scheduledTime = '';
$scheduledAt = '';

switch ($stage) {
  case 'Punong Barangay':
    $nextStage = 'Unang Patawag';
    $scheduledDate = $_POST['scheduled_date_1st'] ?? '';
    $scheduledTime = $_POST['scheduled_time_1st'] ?? '';
    break;
  case 'Unang Patawag':
    $nextStage = 'Ikalawang Patawag';
    $scheduledDate = $_POST['scheduled_date_2nd'] ?? '';
    $scheduledTime = $_POST['scheduled_time_2nd'] ?? '';
    break;
  case 'Ikalawang Patawag':
    $nextStage = 'Ikatlong Patawag';
    $scheduledDate = $_POST['scheduled_date_3rd'] ?? '';
    $scheduledTime = $_POST['scheduled_time_3rd'] ?? '';
    break;
  default:
    header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&error=invalid_stage");
    exit();
}

if (!$scheduledDate || !$scheduledTime) {
  header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&error=missing_schedule");
  exit();
}

$scheduledAt = "$scheduledDate $scheduledTime:00"; // formatted for MySQL DATETIME

// Proceed to next stage
$update = $conn->prepare("UPDATE katarungang_pambarangay_records SET complaint_stage = ?, scheduled_at = ?, complainant_affidavit = NULL, respondent_affidavit = NULL WHERE transaction_id = ?");
$update->bind_param('sss', $nextStage, $scheduledAt, $tid);
$success = $update->execute();
$update->close();

if ($success) {
  header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&updated_tid={$tid}&proceeded_stage={$nextStage}");
} else {
  header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&error=db_update_failed");
}
exit();
?>
