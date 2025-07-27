<?php
session_start();
require 'dbconn.php';

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION['loggedInUserID'];

// 1) Collect posted fields
$transactionType = $_POST['transactiontype'];
$fullName        = $_POST['fullname'];
$address         = $_POST['address'];
$height          = $_POST['height'];
$weight          = $_POST['weight'];
$birthdate       = $_POST['birthday'];
$birthplace      = $_POST['birthplace'];
$civilstatus     = $_POST['civilstatus'];
$religion        = $_POST['religion'];
$contactperson   = $_POST['contactperson'];
$claimDate       = $_POST['claimdate'];
$paymentMethod   = $_POST['paymentMethod'];
$requestSource = 'Online';

// 2) Handle file upload
$formalPicName = null;
if (!empty($_FILES['brgyIDpicture']['name']) && $_FILES['brgyIDpicture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../barangayIDpictures/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $formalPicName = uniqid() . '_' . basename($_FILES['brgyIDpicture']['name']);
    move_uploaded_file($_FILES['brgyIDpicture']['tmp_name'], $uploadDir . $formalPicName);
}

// 3) Generate next transaction_id
$stmt = $conn->prepare("
    SELECT transaction_id 
      FROM barangay_id_requests
     ORDER BY id DESC 
     LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    $num     = intval(substr($lastTid, 7)) + 1;
} else {
    $num = 1;
}
$transactionId = sprintf('BRGYID-%07d', $num);
$stmt->close();

// 4) Insert into barangay_id_requests
$stmt = $conn->prepare("
  INSERT INTO barangay_id_requests
    (account_id, transaction_id, transaction_type, full_name, purok,
     height, weight, birth_date, birth_place, civil_status, religion,
     emergency_contact_person, formal_picture, claim_date, payment_method, request_source)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");
$stmt->bind_param(
    "issssddsssssssss",
    $userId, $transactionId, $transactionType, $fullName, $address,
    $height, $weight, $birthdate, $birthplace, $civilstatus,
    $religion, $contactperson, $formalPicName, $claimDate, $paymentMethod, $requestSource
);
$stmt->execute();
$stmt->close();

// 5) Redirect back to the appropriate panel
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
