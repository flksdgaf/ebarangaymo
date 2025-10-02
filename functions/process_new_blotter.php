<?php
session_start();
require 'dbconn.php';

// 1) AUTH CHECK
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = (int)$_SESSION['loggedInUserID'];

// 2) COLLECT + SANITIZE - Handle multiple clients
$clients = $_POST['clients'] ?? [];
$clientNames = [];
$clientAddresses = [];

foreach ($clients as $client) {
    $cf = trim($client['first_name'] ?? '');
    $cm = trim($client['middle_name'] ?? '');
    $cl = trim($client['last_name'] ?? '');
    $cs = trim($client['suffix'] ?? '');
    
    if ($cf && $cl) { // Only process if at least first and last name exist
        $middlePart = $cm ? " {$cm}" : '';
        $suffixPart = $cs ? " {$cs}" : '';
        $clientNames[] = "{$cl}{$suffixPart}, {$cf}{$middlePart}";
        $clientAddresses[] = trim($client['address'] ?? '');
    }
}

// Join multiple clients with "at"
$clientName = implode(' at ', $clientNames);
$clientAddress = implode('; ', $clientAddresses);

// Handle multiple respondents
$respondents = $_POST['respondents'] ?? [];
$respondentNames = [];
$respondentAddresses = [];

foreach ($respondents as $respondent) {
    $rf = trim($respondent['first_name'] ?? '');
    $rm = trim($respondent['middle_name'] ?? '');
    $rl = trim($respondent['last_name'] ?? '');
    $rs = trim($respondent['suffix'] ?? '');
    
    if ($rf && $rl) { // Only process if at least first and last name exist
        $rMiddle = $rm ? " {$rm}" : '';
        $rSuffix = $rs ? " {$rs}" : '';
        $respondentNames[] = "{$rl}{$rSuffix}, {$rf}{$rMiddle}";
        $respondentAddresses[] = trim($respondent['address'] ?? '');
    }
}

// Join multiple respondents, or set to NULL if none
$respondentName = !empty($respondentNames) ? implode(' at ', $respondentNames) : null;
$respondentAddress = !empty($respondentAddresses) ? implode('; ', $respondentAddresses) : null;

// Incident details
$incidentType = trim($_POST['incident_type'] ?? '');
$incidentDesc = trim($_POST['incident_description'] ?? '');
$incidentPlace = trim($_POST['incident_place'] ?? '');
$incidentDate = $_POST['incident_date'] ?? null;
$incidentTime = $_POST['incident_time'] ?? null;

// 3) GENERATE NEXT TRANSACTION_ID
$stmt = $conn->prepare("SELECT transaction_id FROM blotter_records ORDER BY id DESC LIMIT 1");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    $num = intval(substr($lastTid, 5)) + 1;
} else {
    $num = 1;
}
$transactionId = sprintf('BLTR-%07d', $num);
$stmt->close();

// 4) INSERT INTO blotter_records
$ins = $conn->prepare("INSERT INTO blotter_records (account_id, transaction_id, client_name, client_address, respondent_name, respondent_address, incident_type, incident_description, incident_place, incident_date, incident_time) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
$ins->bind_param('issssssssss', $userId, $transactionId, $clientName, $clientAddress, $respondentName, $respondentAddress, $incidentType, $incidentDesc, $incidentPlace, $incidentDate, $incidentTime);
$ins->execute();
$ins->close();

// 5) ACTIVITY LOGGING
$admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer', 'Lupon Tagapamayapa'];
if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
    $admin_id = $_SESSION['loggedInUserID'];
    $role = $_SESSION['loggedInUserRole'];
    $action = 'CREATE';
    $table_name = 'blotter_records';
    $record_id = $transactionId;
    $description = 'Created Blotter Record';

    $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
    $logStmt->execute();
    $logStmt->close();
}

// 6) REDIRECT BACK
header("Location: ../adminPanel.php?page=adminComplaints&new_blotter_id={$transactionId}");
exit();
?>
