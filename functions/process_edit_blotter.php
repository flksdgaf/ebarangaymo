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

// client name
$cf = trim($_POST['client_first_name'] ?? '');
$cm = trim($_POST['client_middle_name'] ?? '');
$cl = trim($_POST['client_last_name'] ?? '');
$cs = trim($_POST['client_suffix'] ?? '');
$clientName = "{$cl}" . ($cs ? " {$cs}" : '') . ", {$cf}" . ($cm ? " {$cm}" : '');
$clientAddress = trim($_POST['client_address'] ?? '');

// respondent
$hasResp = isset($_POST['has_respondent']);
$respondentName = '';
$respondentAddress = '';
if ($hasResp && trim($_POST['respondent_first_name'] ?? '') !== '') {
    $rf = trim($_POST['respondent_first_name']);
    $rm = trim($_POST['respondent_middle_name'] ?? '');
    $rl = trim($_POST['respondent_last_name']);
    $rs = trim($_POST['respondent_suffix'] ?? '');
    $respondentName = "{$rl}" . ($rs ? " {$rs}" : '') . ", {$rf}" . ($rm ? " {$rm}" : '');
    $respondentAddress = trim($_POST['respondent_address'] ?? '');
}

// incident
$incidentType = trim($_POST['incident_type'] ?? '');
$incidentDesc = trim($_POST['incident_description'] ?? '');
$incidentPlace = trim($_POST['incident_place'] ?? '');
$incidentDate = $_POST['incident_date'] ?? '';
$incidentTime  = $_POST['incident_time'] ?? '';
// $blotterStatus = trim($_POST['blotter_status'] ?? '');

// 3) UPDATE
// ensure every bind slot is a variable
$respNameVar = $respondentName;
$respAddrVar = $respondentAddress;
$incDateVar = $incidentDate;
$incTimeVar = $incidentTime;

$sets = [
  "account_id = ?",
  "client_name = ?",
  "client_address = ?",
  "incident_type = ?",
  "incident_description = ?",
  "incident_place = ?",
  "incident_date = ?",
  "incident_time = ?",
  // "blotter_status = ?",
];

$params  = [
  &$userId,
  &$clientName,
  &$clientAddress,
  &$incidentType,
  &$incidentDesc,
  &$incidentPlace,
  &$incidentDate,
  &$incidentTime,
  // &$blotterStatus,
];

$types = 'isssssss';

// only if has respondent do we update those two:
if ($hasResp) {
  $sets[] = "respondent_name = ?";
  $types .= 's';
  $params[] = &$respondentName;

  $sets[] = "respondent_address = ?";
  $types .= 's';
  $params[] = &$respondentAddress;

} else {
  // user has unchecked “has respondent” → force those columns to NULL
  $sets[] = "respondent_name = NULL";
  $sets[] = "respondent_address = NULL";
  // no extra bind types or params here
}

// always end with WHERE:
$sql = "UPDATE blotter_records SET " . implode(",\n ", $sets) . " WHERE transaction_id = ?";

$types .= 's';  // transaction_id is a string
$params[] = &$tid;

$stmt = $conn->prepare($sql);
// bind_param wants first arg types, then references to each var
array_unshift($params, $types);
call_user_func_array([$stmt, 'bind_param'], $params);

$stmt->execute();

// if ($respondentName) {
//   // choose the target purok table(s) – if you want to update ALL puroks, loop 1 through 6
//   $purokTables = ['purok1_rbi','purok2_rbi','purok3_rbi','purok4_rbi','purok5_rbi','purok6_rbi'];

//   foreach ($purokTables as $tbl) {
//     if ($blotterStatus === 'Pending') {
//       $upd = $conn->prepare("UPDATE `{$tbl}` SET remarks = 'On Hold' WHERE full_name = ?");
//     } else { // 'Cleared'
//       $upd = $conn->prepare("UPDATE `{$tbl}` SET remarks = NULL WHERE full_name = ?");
//     }
//     $upd->bind_param('s', $respondentName);
//     $upd->execute();
//     $upd->close();
//   }
// }

$adminId = $_SESSION['loggedInUserID'];
$role = $_SESSION['loggedInUserRole'];
$action = 'UPDATE';
$tableName = 'blotter_records';
$recordId  = $tid;
$desc = 'Edited blotter record';

// 4) LOG + REDIRECT
if ($stmt->affected_rows > 0) {
  $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
  $logStmt->bind_param('isssss', $adminId, $role, $action, $tableName, $recordId, $desc);
  $logStmt->execute();
  $logStmt->close();

  header("Location: ../adminPanel.php?page=adminComplaints&blotter_updated={$recordId}");
} else {
  header("Location: ../adminPanel.php?page=adminComplaints&blotter_nochange=1");
}

$stmt->close();
exit;
?>
