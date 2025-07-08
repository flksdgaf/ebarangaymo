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
// build complainant full name
$cf = trim($_POST['complainant_first_name'] ?? '');
$cm = trim($_POST['complainant_middle_name'] ?? '');
$cl = trim($_POST['complainant_last_name'] ?? '');
$cs = trim($_POST['complainant_suffix'] ?? '');
$cMiddle = $cm ? " {$cm}" : '';
$cSuffix = $cs ? " {$cs}" : '';
$complainantName = "{$cl}{$cSuffix}, {$cf}{$cMiddle}";
// $complainantName = trim($_POST['complainant_name'] ?? '');
$complainantAddress = trim($_POST['complainant_address'] ?? '');

// respondent (may be empty if no respondent)
$respondentName = null;
$respondentAddress = null;
if (!empty($_POST['respondent_first_name'])) {
    $rf = trim($_POST['respondent_first_name']);
    $rm = trim($_POST['respondent_middle_name'] ?? '');
    $rl = trim($_POST['respondent_last_name']);
    $rs = trim($_POST['respondent_suffix'] ?? '');
    $rMiddle = $rm ? " {$rm}" : '';
    $rSuffix = $rs ? " {$rs}" : '';
    $respondentName = "{$rl}{$rSuffix}, {$rf}{$rMiddle}";
    $respondentAddress = trim($_POST['respondent_address'] ?? '');
}

// $respondentName = trim($_POST['respondent_name'] ?? '');
// $respondentAddress = trim($_POST['respondent_address'] ?? '');

// other fields
$complaintType = trim($_POST['complaint_type'] ?? '');
$complaintAffidavit = trim($_POST['complaint_affidavit'] ?? '');
$pleadingStatement = trim($_POST['pleading_statement'] ?? '');

// 3) GENERATE NEXT TRANSACTION_ID
$stmt = $conn->prepare("SELECT transaction_id FROM complaint_records ORDER BY id DESC LIMIT 1");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    // strip "CMPL-" prefix and increment
    $num = intval(substr($lastTid, 5)) + 1;
} else {
    $num = 1;
}
$transactionId = sprintf('CMPL-%07d', $num);
$stmt->close();

// 4) INSERT INTO complaint_records
$sql = "INSERT INTO complaint_records (account_id, transaction_id, blotter_id, complainant_name, complainant_address, respondent_name, respondent_address, complaint_type, complaint_affidavit, pleading_statement) VALUES (?,?,?,?,?,?,?,?,?,?)";
$ins = $conn->prepare($sql);
$blotterId = null; // if you have a blotter link, set it here
$ins->bind_param('isssssssss', $userId, $transactionId, $blotterId, $complainantName, $complainantAddress, $respondentName, $respondentAddress, $complaintType, $complaintAffidavit, $pleadingStatement);
$ins->execute();
$ins->close();

// 5) ACTIVITY LOGGING
$admin_roles = ['SuperAdmin','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Lupon'];
if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
    $admin_id = $_SESSION['loggedInUserID'];
    $role = $_SESSION['loggedInUserRole'];
    $action = 'CREATE';
    $table_name = 'complaint_records';
    $record_id = $transactionId;
    $description = 'Created Complaint Record';
    
    $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
    $logStmt->execute();
    $logStmt->close();
}

// if ($respondentName) {
//   $purokTables = [
//     'purok1_rbi','purok2_rbi','purok3_rbi',
//     'purok4_rbi','purok5_rbi','purok6_rbi'
//   ];
//   foreach ($purokTables as $tbl) {
//     if ($blotterStatus === 'Pending') {
//       $upd = $conn->prepare("UPDATE `{$tbl}` SET remarks = 'On Hold' WHERE full_name = ?");
//     } else {
//       $upd = $conn->prepare("UPDATE `{$tbl}` SET remarks = NULL WHERE full_name = ?");
//     }
//     $upd->bind_param('s', $respondentName);
//     $upd->execute();
//     $upd->close();
//   }
// }

// 6) REDIRECT BACK WITH SUCCESS
// header("Location: ../superAdminPanel.php?page=superAdminComplaints&transaction_id={$transactionId}");
$pageNum = $_POST['summon_page'] ?? 1;

header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&new_complaint_id={$transactionId}");

exit();
?>
