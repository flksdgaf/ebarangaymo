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

// Handle multiple clients
$clients = $_POST['clients'] ?? [];
$clientNames = [];
$clientAddresses = [];

foreach ($clients as $client) {
    $cf = ucwords(strtolower(trim($client['first_name'] ?? '')));
    $cm = trim($client['middle_name'] ?? '') ? ucwords(strtolower(trim($client['middle_name'] ?? ''))) : '';
    $cl = ucwords(strtolower(trim($client['last_name'] ?? '')));
    $cs = ucwords(strtolower(trim($client['suffix'] ?? '')));
    
    if ($cf && $cl) { // Only process if at least first and last name exist
        $middlePart = $cm ? ", {$cm}" : '';
        $suffixPart = $cs ? " {$cs}" : '';
        $clientNames[] = "{$cl}{$suffixPart}, {$cf}{$middlePart}";
        $clientAddresses[] = ucwords(strtolower(trim($client['address'] ?? '')));
    }
}

// Join multiple clients with " | " (pipe separator)
$clientName = !empty($clientNames) ? implode(' | ', $clientNames) : '';
$clientAddress = !empty($clientAddresses) ? implode(' | ', $clientAddresses) : '';

// Handle multiple respondents
$respondents = $_POST['respondents'] ?? [];
$respondentNames = [];
$respondentAddresses = [];

foreach ($respondents as $respondent) {
    $rf = ucwords(strtolower(trim($respondent['first_name'] ?? '')));
    $rm = trim($respondent['middle_name'] ?? '') ? ucwords(strtolower(trim($respondent['middle_name'] ?? ''))) : '';
    $rl = ucwords(strtolower(trim($respondent['last_name'] ?? '')));
    $rs = ucwords(strtolower(trim($respondent['suffix'] ?? '')));
    
    if ($rf && $rl) { // Only process if at least first and last name exist
        $rMiddle = $rm ? ", {$rm}" : '';
        $rSuffix = $rs ? " {$rs}" : '';
        $respondentNames[] = "{$rl}{$rSuffix}, {$rf}{$rMiddle}";
        $respondentAddresses[] = ucwords(strtolower(trim($respondent['address'] ?? '')));
    }
}

// Join multiple respondents with " | " (pipe separator), or set to NULL if none
$respondentName = !empty($respondentNames) ? implode(' | ', $respondentNames) : null;
$respondentAddress = !empty($respondentAddresses) ? implode(' | ', $respondentAddresses) : null;

// incident
$incidentType = ucwords(strtolower(trim($_POST['incident_type'] ?? '')));
// Capitalize first letter of each sentence in description
$incidentDesc = trim($_POST['incident_description'] ?? '');
$incidentDesc = preg_replace_callback('/([.!?]\s+)([a-z])/', function($matches) {
    return $matches[1] . strtoupper($matches[2]);
}, ucfirst(strtolower($incidentDesc)));
$incidentPlace = ucwords(strtolower(trim($_POST['incident_place'] ?? '')));
$incidentDate = $_POST['incident_date'] ?? '';
$incidentTime  = $_POST['incident_time'] ?? '';

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
  "respondent_name = ?",
  "respondent_address = ?",
  "incident_type = ?",
  "incident_description = ?",
  "incident_place = ?",
  "incident_date = ?",
  "incident_time = ?",
];

$params  = [
  &$userId,
  &$clientName,
  &$clientAddress,
  &$respondentName,
  &$respondentAddress,
  &$incidentType,
  &$incidentDesc,
  &$incidentPlace,
  &$incidentDate,
  &$incidentTime,
];

$types = 'isssssssss';

// always end with WHERE:
$sql = "UPDATE blotter_records SET " . implode(",\n ", $sets) . " WHERE transaction_id = ?";

$types .= 's';  // transaction_id is a string
$params[] = &$tid;

$stmt = $conn->prepare($sql);
// bind_param wants first arg types, then references to each var
array_unshift($params, $types);
call_user_func_array([$stmt, 'bind_param'], $params);

$stmt->execute();

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
