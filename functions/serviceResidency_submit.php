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
$name           = trim($_POST['name'] ?? '');
$age            = (int)($_POST['age'] ?? 0);
$civilStatus    = $_POST['civil_status'] ?? '';
$purok          = trim($_POST['purok'] ?? '');
$residingYears  = (int)($_POST['residing_years'] ?? 0);
$claimDate      = $_POST['claim_date'] ?? '';   // YYYY-MM-DD
$purpose        = trim($_POST['purpose'] ?? '');
$paymentMethod  = $_POST['payment_method'] ?? '';

// 3) Basic validation (you can expand this)
$errors = [];
if ($name === '')           $errors[] = 'Name is required';
if ($age <= 0)              $errors[] = 'Age must be greater than zero';
if ($civilStatus === '')    $errors[] = 'Civil status is required';
if ($purok === '')          $errors[] = 'Purok is required';
if ($residingYears < 0)     $errors[] = 'Residing years cannot be negative';
if ($claimDate === '')      $errors[] = 'Claim date is required';
if ($purpose === '')        $errors[] = 'Purpose is required';
if ($paymentMethod === '')  $errors[] = 'Payment method is required';

if (!empty($errors)) {
    // You might store these in session and redirect back instead
    die('Validation error: ' . implode(', ', $errors));
}

// 4) Generate next transaction_id, e.g. RES-0000001
$stmt = $conn->prepare("
    SELECT transaction_id
      FROM residency_requests
     ORDER BY id DESC
     LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    $num     = intval(substr($lastTid, 4)) + 1;  // strip “RES-”
} else {
    $num = 1;
}
$transactionId = sprintf('RES-%07d', $num);
$stmt->close();

// 5) Insert into residency_requests
$insert = $conn->prepare("
  INSERT INTO residency_requests
    (account_id,
     transaction_id,
     full_name,
     age,
     civil_status,
     purok,
     residing_years,
     claim_date,
     purpose,
     payment_method)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$insert->bind_param(
    "issississs",
    $userId,
    $transactionId,
    $name,
    $age,
    $civilStatus,
    $purok,
    $residingYears,
    $claimDate,
    $purpose,
    $paymentMethod
);
if (! $insert->execute()) {
    die('Insert failed: ' . $insert->error);
}
$insert->close();

// 6) Redirect back with success flash
if (!empty($_POST['superAdminRedirect'])) {
    // Came from the super‐admin panel → send back there
    header("Location: ../superAdminPanel.php?page=superAdminRequest&transaction_id={$transactionId}");
    exit();
}

if (!empty($_POST['adminRedirect'])) {
    // Came from the admin panel → send back there
    header("Location: ../adminPanel.php?page=adminRequest&transaction_id={$transactionId}");
    exit();
}

// Default: user panel
header("Location: ../userPanel.php?page=serviceBarangayID&tid={$transactionId}");
exit();

// Instead of returning to the panel, go straight to the generator:
// header("Location: ../functions/generateResidencyCertificate.php?transaction_id={$transactionId}");
// exit();


