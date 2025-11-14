<?php
session_start();
require 'dbconn.php';

// 1) AUTH CHECK
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = (int)$_SESSION['loggedInUserID'];

// 2) COLLECT + SANITIZE
// Build complainant full name
$cf = trim($_POST['complainant_first_name'] ?? '');
$cm = trim($_POST['complainant_middle_name'] ?? '');
$cl = trim($_POST['complainant_last_name'] ?? '');
$cMiddle = $cm ? " {$cm}" : '';
$complainantName = "{$cl}, {$cf}{$cMiddle}";
$complainantAddress = trim($_POST['complainant_address'] ?? '');

// Build respondent full name
$rf = trim($_POST['respondent_first_name'] ?? '');
$rm = trim($_POST['respondent_middle_name'] ?? '');
$rl = trim($_POST['respondent_last_name'] ?? '');
$rMiddle = $rm ? " {$rm}" : '';
$respondentName = "{$rl}, {$rf}{$rMiddle}";
$respondentAddress = trim($_POST['respondent_address'] ?? '');

// Complaint details
$complaintTitle = trim($_POST['complaint_title'] ?? '');
$natureOfCase = trim($_POST['nature_of_case'] ?? 'Civil');
$complaintAffidavit = trim($_POST['complaint_affidavit'] ?? '');
$pleadingStatement = trim($_POST['pleading_statement'] ?? '');

// 3) GENERATE NEXT TRANSACTION_ID
$stmt = $conn->prepare("SELECT transaction_id FROM barangay_complaints ORDER BY id DESC LIMIT 1");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    // Strip "CMPL-" prefix and increment
    $num = intval(substr($lastTid, 5)) + 1;
} else {
    $num = 1;
}
$transactionId = sprintf('CMPL-%07d', $num);
$stmt->close();

// 4) GENERATE CASE_NO (format: 001-2025)
$year = date('Y');
$caseStmt = $conn->prepare("SELECT case_no FROM barangay_complaints WHERE case_no LIKE ? ORDER BY id DESC LIMIT 1");
$yearPattern = "%-{$year}";
$caseStmt->bind_param('s', $yearPattern);
$caseStmt->execute();
$caseRes = $caseStmt->get_result();
if ($caseRes && $caseRes->num_rows === 1) {
    $lastCase = $caseRes->fetch_assoc()['case_no'];
    // Extract number from "001-2025"
    $caseNum = intval(explode('-', $lastCase)[0]) + 1;
} else {
    $caseNum = 1;
}
$caseNo = sprintf('%03d-%s', $caseNum, $year);
$caseStmt->close();

// 5) INSERT INTO barangay_complaints
$sql = "INSERT INTO barangay_complaints 
    (account_id, transaction_id, case_no, complainant_name, complainant_address, 
     respondent_name, respondent_address, complaint_title, nature_of_case, 
     complaint_affidavit, pleading_statement, date_filed, action_taken, complaint_stage) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Incoming', 'Filing')";

$ins = $conn->prepare($sql);
$ins->bind_param(
    'issssssssss', 
    $userId, $transactionId, $caseNo, $complainantName, $complainantAddress,
    $respondentName, $respondentAddress, $complaintTitle, $natureOfCase,
    $complaintAffidavit, $pleadingStatement
);
$ins->execute();
$ins->close();

// 6) ACTIVITY LOGGING
$admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer', 'Lupon Tagapamayapa'];
if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
    $admin_id = $_SESSION['loggedInUserID'];
    $role = $_SESSION['loggedInUserRole'];
    $action = 'CREATE';
    $table_name = 'barangay_complaints';
    $record_id = $transactionId;
    $description = "Created Complaint Record: {$caseNo}";
    
    $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
    $logStmt->execute();
    $logStmt->close();
}

// 7) UPDATE PUROK TABLES (Set remarks to 'On Hold' for respondent)
$purokTables = ['purok1_rbi','purok2_rbi','purok3_rbi','purok4_rbi','purok5_rbi','purok6_rbi'];
foreach ($purokTables as $tbl) {
    $upd = $conn->prepare("UPDATE `{$tbl}` SET remarks = 'On Hold' WHERE full_name = ?");
    $upd->bind_param('s', $respondentName);
    $upd->execute();
    $upd->close();
}

// 8) REDIRECT BACK WITH SUCCESS
header("Location: ../adminPanel.php?page=adminComplaints&new_complaint_id={$transactionId}");
exit();
?>
