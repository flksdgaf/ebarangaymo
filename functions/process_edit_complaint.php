<?php
session_start();
require 'dbconn.php';

// 1) AUTH
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}
$userId = (int)$_SESSION['loggedInUserID'];

// 2) COLLECT + SANITIZE
$tid = trim($_POST['transaction_id'] ?? '');

// Complainant 
$cf = trim($_POST['complainant_first_name'] ?? '');
$cm = trim($_POST['complainant_middle_name'] ?? '');
$cl = trim($_POST['complainant_last_name'] ?? '');
$cs = trim($_POST['complainant_suffix'] ?? '');
$complainantName = "{$cl}" . ($cs ? " {$cs}" : '') . ", {$cf}" . ($cm ? " {$cm}" : '');
$complainantAddress = trim($_POST['complainant_address'] ?? '');

// Respondent
$rf = trim($_POST['respondent_first_name']);
$rm = trim($_POST['respondent_middle_name'] ?? '');
$rl = trim($_POST['respondent_last_name']);
$rs = trim($_POST['respondent_suffix'] ?? '');
$respondentName = "{$rl}" . ($rs ? " {$rs}" : '') . ", {$rf}" . ($rm ? " {$rm}" : '');
$respondentAddress = trim($_POST['respondent_address'] ?? '');

// Complaint
$complaintType = trim($_POST['complaint_type'] ?? '');
$complaintAffidavit = trim($_POST['complaint_affidavit'] ?? '');
$pleadingStatement = trim($_POST['pleading_statement'] ?? '');

$pageNum = $_POST['summon_page'] ?? 1;

// 3) UPDATE
$sql = "UPDATE complaint_records SET account_id = ?, complainant_name = ?, complainant_address = ?, respondent_name = ?, respondent_address = ?, complaint_type = ?, complaint_affidavit = ?, pleading_statement = ? WHERE transaction_id = ?";
$stmt = $conn->prepare($sql);

$stmt->bind_param('issssssss', $userId, $complainantName, $complainantAddress, $respondentName, $respondentAddress, $complaintType, $complaintAffidavit, $pleadingStatement, $tid);
$stmt->execute();

// 4) LOG + REDIRECT
if ($stmt->affected_rows > 0) {
  $adminId = $_SESSION['loggedInUserID'];
  $role = $_SESSION['loggedInUserRole'];
  $action = 'UPDATE';
  $table = 'complaint_records';
  $desc = 'Edited complaint record';

  $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?, ?, ?, ?, ?, ?)");
  $logStmt->bind_param('isssss', $adminId, $role, $action, $table, $tid, $desc);
  $logStmt->execute();
  $logStmt->close();

  header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&updated_complaint_id={$tid}");
} else {
  header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&complaint_nochange=1");
}

$stmt->close();
exit;
?>
