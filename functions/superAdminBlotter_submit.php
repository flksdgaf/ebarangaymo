<?php
session_start();
require 'dbconn.php';

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION['loggedInUserID'];

// 1) Collect posted fields
$complainantsArr  = $_POST['complainants']  ?? [];
$respondentsArr   = $_POST['respondents']   ?? [];

// implode into comma-space lists (e.g. "Alice, Bob")
$complainants    = is_array($complainantsArr) ? implode(', ', array_filter($complainantsArr, fn($v) => strlen(trim($v))>0)) : '';
$respondents     = is_array($respondentsArr) ? implode(', ', array_filter($respondentsArr, fn($v) => strlen(trim($v))>0)) : '';
$dateFiled            = $_POST['date_filed']           ?? '';
$dateOccurrence       = $_POST['date_occurrence']      ?? '';
$incidencePlace       = $_POST['incidence_place']      ?? '';
$complaintNature      = $_POST['complaint_nature']     ?? '';
$complaintDescription = $_POST['complaint_description']?? '';
$remarks              = $_POST['remarks']              ?? '';
$paymentMethod        = $_POST['payment_method']       ?? '';

// 2) Generate next transaction_id
//    Format: BLTR-0000001, etc.
$stmt = $conn->prepare("
    SELECT transaction_id
      FROM blotter_records
     ORDER BY id DESC
     LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    // strip prefix and parse numeric
    $num = intval(substr($lastTid, 5)) + 1;
} else {
    $num = 1;
}
$transactionId = sprintf('BLTR-%07d', $num);
$stmt->close();

// 3) Insert into blotter_records
$stmt = $conn->prepare("
    INSERT INTO blotter_records
      (account_id,
       transaction_id,
       complainants,
       respondents,
       date_filed,
       date_occurrence,
       incidence_place,
       complaint_nature,
       complaint_description,
       remarks,
       payment_method)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)
");
$stmt->bind_param(
    'issssssssss',
    $userId,
    $transactionId,
    $complainants,
    $respondents,
    $dateFiled,
    $dateOccurrence,
    $incidencePlace,
    $complaintNature,
    $complaintDescription,
    $remarks,
    $paymentMethod
);
$stmt->execute();
$stmt->close();

// 4) Redirect back to the super‐admin blotter page,
if (!empty($_POST['superAdminRedirect'])) {
    // Came from the super‐admin panel → send back there
    header("Location: ../superAdminPanel.php?page=superAdminBlotter&transaction_id={$transactionId}");
    exit();
}

if (!empty($_POST['adminRedirect'])) {
    // Came from the admin panel → send back there
    header("Location: ../adminPanel.php?page=adminBlotter&transaction_id={$transactionId}");
    exit();
}

// Default: user panel
header("Location: ../userPanel.php?page=blotter&transaction_id={$transactionId}");
exit();
