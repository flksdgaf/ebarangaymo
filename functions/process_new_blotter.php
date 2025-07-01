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
// Client name parts
// $cf = trim($_POST['client_first_name'] ?? '');
// $cm = trim($_POST['client_middle_name'] ?? '');
// $cl = trim($_POST['client_last_name'] ?? '');
// $cs = trim($_POST['client_suffix'] ?? '');
// $middlePart = $cm ? " {$cm}" : '';
// $suffixPart = $cs ? " {$cs}" : '';
// $clientName = "{$cl}, {$cf}{$middlePart}{$suffixPart}";
$clientName = trim($_POST['client_name'] ?? '');
$clientAddress = trim($_POST['client_address'] ?? '');

// Respondent (only if checkbox was checked, otherwise fields disabled)
// $respondentName = null;
// $respondentAddress = null;
// if (!empty($_POST['respondent_first_name'])) {
//     $rf = trim($_POST['respondent_first_name']);
//     $rm = trim($_POST['respondent_middle_name'] ?? '');
//     $rl = trim($_POST['respondent_last_name']);
//     $rs = trim($_POST['respondent_suffix'] ?? '');
//     $rMiddle = $rm ? " {$rm}" : '';
//     $rSuffix = $rs ? " {$rs}" : '';
//     $respondentName = "{$rl}, {$rf}{$rMiddle}{$rSuffix}";
//     $respondentAddress = trim($_POST['respondent_address'] ?? '');
// }

// Respondent (only if checkbox was checked):
$respondentName    = null;
$respondentAddress = null;
if (isset($_POST['has_respondent'])) {
    // Pull both fields, defaulting to empty string if not set
    $rawName    = trim($_POST['respondent_name']    ?? '');
    $rawAddress = trim($_POST['respondent_address'] ?? '');

    // Only set them if non‐empty
    if ($rawName !== '') {
        $respondentName    = $rawName;
    }
    if ($rawAddress !== '') {
        $respondentAddress = $rawAddress;
    }
}

// Incident details
$incidentType = trim($_POST['incident_type'] ?? '');
$incidentDesc = trim($_POST['incident_description'] ?? '');
$incidentPlace = trim($_POST['incident_place'] ?? '');
$incidentDate = $_POST['incident_date']  ?? null;  // YYYY-MM-DD
$incidentTime = $_POST['incident_time']  ?? null;  // HH:MM

// 3) GENERATE NEXT TRANSACTION_ID
$stmt = $conn->prepare("SELECT transaction_id FROM blotter_records ORDER BY id DESC LIMIT 1");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    // strip prefix "BLTR-" and increment
    $num = intval(substr($lastTid, 5)) + 1;
} else {
    $num = 1;
}
$transactionId = sprintf('BLTR-%07d', $num);
$stmt->close();

// 4) INSERT INTO blotter_records
// $sql = "INSERT INTO blotter_records (account_id, transaction_id, client_name, client_address, respondent_name, respondent_address, incident_type, incident_description, incident_place, incident_date, incident_time) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
// $ins = $conn->prepare($sql);
// $ins->bind_param('issssssssss', $userId, $transactionId, $clientName, $clientAddress, $respondentName, $respondentAddress, $incidentType, $incidentDesc, $incidentPlace, $incidentDate, $incidentTime);

$ins = $conn->prepare(
  "INSERT INTO blotter_records
     (account_id, transaction_id, client_name, client_address,
      respondent_name, respondent_address,
      incident_type, incident_description, incident_place,
      incident_date, incident_time)
   VALUES (?,?,?,?,?,?,?,?,?,?,?)"
);
$ins->bind_param(
  'issssssssss',
  $userId, $transactionId,
  $clientName, $clientAddress,
  $respondentName, $respondentAddress,  // now properly set or NULL
  $incidentType, $incidentDesc, $incidentPlace,
  $incidentDate, $incidentTime
);
$ins->execute();
$ins->close();

// 5) ACTIVITY LOGGING
$admin_roles = ['SuperAdmin','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Lupon'];
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
// header("Location: ../superAdminPanel.php?page=superAdminComplaints&transaction_id={$transactionId}");
// header("Location: ../adminPanel.php?page=adminComplaints&transaction_id={$transactionId}");
header("Location: ../adminPanel.php?page=adminComplaints&new_blotter_id={$transactionId}");

exit();
?>
