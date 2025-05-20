<?php
session_start();
require 'dbconn.php';

// 1) Authentication check
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = (int)$_SESSION['loggedInUserID'];

// 2) Collect posted fields
$guardianName   = trim($_POST['full_name'] ?? '');
$age            = (int)($_POST['age'] ?? 0);
$civilStatus    = $_POST['civil_status'] ?? '';
$purok          = trim($_POST['purok'] ?? '');
$childName      = trim($_POST['child_name'] ?? '');
$claimDate      = $_POST['claim_date'] ?? '';
$purpose        = trim($_POST['purpose'] ?? '');
$paymentMethod  = $_POST['payment_method'] ?? '';

// 3) Basic validation
$errors = [];
if ($guardianName === '')   $errors[] = 'Guardian name is required';
if ($age <= 0)              $errors[] = 'Age must be greater than zero';
if ($civilStatus === '')    $errors[] = 'Civil status is required';
if ($purok === '')          $errors[] = 'Purok is required';
if ($childName === '')      $errors[] = 'Child name is required';
if ($claimDate === '')      $errors[] = 'Claim date is required';
if ($purpose === '')        $errors[] = 'Purpose is required';
if ($paymentMethod === '')  $errors[] = 'Payment method is required';

if (!empty($errors)) {
    die('Validation error: ' . implode(', ', $errors));
}

// 4) Generate next transaction_id, e.g. GUA-0000001
$stmt = $conn->prepare("SELECT transaction_id FROM guardianship_requests ORDER BY id DESC LIMIT 1");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    $num     = intval(substr($lastTid, 4)) + 1;  // strip "GUA-"
} else {
    $num = 1;
}
$transactionId = sprintf('GUA-%07d', $num);
$stmt->close();

// 5) Insert into guardianship_requests
$insert = $conn->prepare(
  "INSERT INTO guardianship_requests
     (account_id, transaction_id, full_name, age, civil_status, purok, child_name, claim_date, purpose, payment_method)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$insert->bind_param(
    'ississssss',
    $userId,
    $transactionId,
    $guardianName,
    $age,
    $civilStatus,
    $purok,
    $childName,
    $claimDate,
    $purpose,
    $paymentMethod
);
if (!$insert->execute()) {
    die('Insert failed: ' . $insert->error);
}
$insert->close();

// 6) Redirect back with success flash
if (!empty($_POST['superAdminRedirect'])) {
    header("Location: ../superAdminPanel.php?page=superAdminRequest&transaction_id={$transactionId}");
    exit();
}
if (!empty($_POST['adminRedirect'])) {
    header("Location: ../adminPanel.php?page=adminRequest&transaction_id={$transactionId}");
    exit();
}

// Default: user panel for Guardianship
header("Location: ../userPanel.php?page=serviceGuardianship&tid={$transactionId}");
exit();
?>