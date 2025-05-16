<?php
session_start();
require 'dbconn.php';

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION['loggedInUserID'];

// 1) Collect posted fields
$transactionType = $_POST['transactiontype'] ?? '';
$fullName        = $_POST['fullname'] ?? '';
$street          = $_POST['street'] ?? '';
$purok           = $_POST['purok'] ?? '';
$birthdate       = $_POST['birthdate'] ?? '';
$birthplace      = $_POST['birthplace'] ?? '';
$age             = isset($_POST['age']) ? (int)$_POST['age'] : 0;
$civilStatus     = $_POST['civilstatus'] ?? '';
$purpose         = $_POST['purpose'] ?? '';
$claimDate       = $_POST['claimdate'] ?? '';
$paymentMethod   = $_POST['paymentMethod'] ?? '';

// 2) Generate next transaction_id
//    We’ll assume your table is `certification_requests`
//    and you want prefix "CERT-0000001", adjust as needed.
$stmt = $conn->prepare("
    SELECT transaction_id 
      FROM certification_requests
     ORDER BY id DESC
     LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    // strip off prefix "CERT-" (5 chars), then increment
    $num     = intval(substr($lastTid, 5)) + 1;
} else {
    $num = 1;
}
$transactionId = sprintf('CERT-%07d', $num);
$stmt->close();

// 3) Insert into certification_requests
$insert = $conn->prepare("
  INSERT INTO certification_requests
    (account_id,
     transaction_id,
     transaction_type,
     full_name,
     street,
     purok,
     birthdate,
     birthplace,
     age,
     status,
     purpose,
     claim_date,
     payment_method)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
");
$insert->bind_param(
    "issssssssssss",
    $userId,
    $transactionId,
    $transactionType,
    $fullName,
    $street,
    $purok,
    $birthdate,
    $birthplace,
    $age,
    $civilStatus,
    $purpose,
    $claimDate,
    $paymentMethod
);
$insert->execute();
$insert->close();

// 4) Redirect back to the super-admin panel with success alert
header("Location: ../superAdminPanel.php?page=superAdminRequest&transaction_id={$transactionId}");
exit();
