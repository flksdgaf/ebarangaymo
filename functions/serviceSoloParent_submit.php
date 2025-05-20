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
$name               = trim($_POST['name'] ?? '');
$age                = (int)($_POST['age'] ?? 0);
$civilStatus        = $_POST['civil_status'] ?? '';
$purok              = trim($_POST['purok'] ?? '');
$childName          = trim($_POST['child_name'] ?? '');
$childAge           = (int)($_POST['child_age'] ?? 0);
$yearsSoloParent    = (int)($_POST['years_solo_parent'] ?? 0);
$purpose            = trim($_POST['purpose'] ?? '');
$claimDate          = $_POST['claim_date'] ?? '';
$paymentMethod      = $_POST['payment_method'] ?? '';

// 3) Basic validation
$errors = [];
if ($name === '')               $errors[] = 'Name is required';
if ($age <= 0)                  $errors[] = 'Age must be greater than zero';
if ($civilStatus === '')        $errors[] = 'Civil status is required';
if ($purok === '')              $errors[] = 'Purok is required';
if ($childName === '')          $errors[] = 'Child name is required';
if ($childAge < 0)              $errors[] = 'Child age cannot be negative';
if ($yearsSoloParent < 0)       $errors[] = 'Years as solo parent cannot be negative';
if ($purpose === '')            $errors[] = 'Purpose is required';
if ($claimDate === '')          $errors[] = 'Claim date is required';
if ($paymentMethod === '')      $errors[] = 'Payment method is required';

if (!empty($errors)) {
    die('Validation error: ' . implode(', ', $errors));
}

// 4) Generate next transaction_id, e.g. SP-0000001
$stmt = $conn->prepare("SELECT transaction_id FROM solo_parent_requests ORDER BY id DESC LIMIT 1");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    $num     = intval(substr($lastTid, 3)) + 1;  // strip "SP-"
} else {
    $num = 1;
}
$transactionId = sprintf('SP-%07d', $num);
$stmt->close();

// 5) Insert into solo_parent_requests
$insert = $conn->prepare(
  "INSERT INTO solo_parent_requests
     (account_id, transaction_id, full_name, age, civil_status, purok,
      child_name, child_age, years_solo_parent, purpose, claim_date, payment_method)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$insert->bind_param(
    'ississsiisss',
    $userId,
    $transactionId,
    $name,
    $age,
    $civilStatus,
    $purok,
    $childName,
    $childAge,
    $yearsSoloParent,
    $purpose,
    $claimDate,
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

// Default: user panel for Solo Parent
header("Location: ../userPanel.php?page=serviceSoloParent&tid={$transactionId}");
exit();
?>