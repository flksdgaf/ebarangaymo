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
$address         = $_POST['address'] ?? '';
$civilStatus     = $_POST['civilstatus'] ?? '';
$purok           = $_POST['purok'] ?? '';
$barangay        = $_POST['barangay'] ?? '';
$age             = (int)($_POST['age'] ?? 0);
$claimDate       = $_POST['claimdate'] ?? '';
$businessName    = $_POST['business_name'] ?? '';
$businessType    = $_POST['business_type'] ?? '';
$paymentMethod   = $_POST['paymentMethod'] ?? '';

// 2) Generate next transaction_id
$stmt = $conn->prepare(
    "SELECT transaction_id FROM business_permit_requests ORDER BY id DESC LIMIT 1"
);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    $num     = intval(substr($lastTid, 10)) + 1; // assumes prefix 'BUSPERMIT-'
} else {
    $num = 1;
}
$transactionId = sprintf('BPRMT-%07d', $num);
$stmt->close();

// 3) Insert into business_permit_requests
$insert = $conn->prepare(
    "INSERT INTO business_permit_requests
        (account_id, transaction_id, transaction_type, full_name, full_address,
         status, purok, barangay, age, claim_date,
         name_of_business, type_of_business, payment_method)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$insert->bind_param(
    "isssssssissss",
    $userId,
    $transactionId,
    $transactionType,
    $fullName,
    $address,
    $civilStatus,
    $purok,
    $barangay,
    $age,
    $claimDate,
    $businessName,
    $businessType,
    $paymentMethod
);
$insert->execute();
$insert->close();

// 4) Redirect back to super-admin panel
$redirectUrl = "../superAdminPanel.php?page=superAdminRequest&transaction_id={$transactionId}";
header("Location: {$redirectUrl}");
exit();
?>
