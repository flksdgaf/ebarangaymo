<?php
session_start();
require 'dbconn.php';

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = (int)$_SESSION['loggedInUserID'];

// 1) Collect posted fields (map names used in serviceBarangayClearance.php)
$lastName        = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
$firstName       = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$middleName      = isset($_POST['middlename']) ? trim($_POST['middlename']) : '';
$street          = isset($_POST['street']) ? trim($_POST['street']) : '';
$purok           = isset($_POST['purok']) ? trim($_POST['purok']) : '';
$barangay        = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
$municipality    = isset($_POST['municipality']) ? trim($_POST['municipality']) : '';
$province        = isset($_POST['province']) ? trim($_POST['province']) : '';
$birthDate       = isset($_POST['birthdate']) && $_POST['birthdate'] !== '' ? trim($_POST['birthdate']) : null;
$age             = isset($_POST['age']) && $_POST['age'] !== '' ? (int) $_POST['age'] : null;
$birthPlace      = isset($_POST['birth_place']) ? trim($_POST['birth_place']) : '';
$maritalStatus   = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : '';
// remarks removed from form — keep empty so DB column is satisfied
$remarks         = '';
$ctcNumber       = isset($_POST['ctc_number']) ? trim($_POST['ctc_number']) : '';
$claimDate       = isset($_POST['claim_date']) && $_POST['claim_date'] !== '' ? trim($_POST['claim_date']) : null;
$paymentMethod   = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
$requestSource   = 'Online';

// 1.a) Apply defaults for barangay/municipality/province if form omitted or empty
if (empty($barangay)) $barangay = 'Magang';
if (empty($municipality)) $municipality = 'Daet';
if (empty($province)) $province = 'Daet';

// Build full_name in "LAST, FIRST MIDDLE" format (middle optional)
$fullName = trim($lastName);
if ($firstName !== '') {
    $fullName .= ', ' . $firstName;
    if ($middleName !== '') $fullName .= ' ' . $middleName;
}

// 2) Handle file upload (picture) — optional
$pictureFileName = null;
if (!empty($_FILES['picture']['name']) && isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../barangayClearancePictures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // sanitize filename and make unique
    $orig = basename($_FILES['picture']['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $pictureFileName = uniqid('clr_') . ($ext ? '.' . $ext : '');
    $target = $uploadDir . $pictureFileName;

    if (!move_uploaded_file($_FILES['picture']['tmp_name'], $target)) {
        // If upload fails, null the filename and continue (you may want to handle differently)
        $pictureFileName = null;
    }
}

// 3) Generate next transaction_id (robustly parse trailing digits)
$stmt = $conn->prepare("
    SELECT transaction_id
      FROM barangay_clearance_requests
     ORDER BY id DESC
     LIMIT 1
");
$lastNumber = 0;
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $lastTid = $res->fetch_assoc()['transaction_id'];
        if (preg_match('/(\d+)$/', $lastTid, $m)) {
            $lastNumber = intval($m[1]);
        }
    }
    $stmt->close();
}
$num = $lastNumber + 1;
$transactionId = sprintf('BRGYCLR-%07d', $num);

// 4) Insert into barangay_clearance_requests
$createdAt = date('Y-m-d H:i:s');
$updatedAt = $createdAt;
$dateIssued = null;    // default null
$placeIssued = null;   // default null
$amountPaid = 0.00;    // not paid yet
$orNumber = null;
$paymentStatus = 'Unpaid';
$documentStatus = 'Pending';
$requestType = 'Barangay Clearance'; // fixed request type

$sql = "
  INSERT INTO barangay_clearance_requests
    (account_id, transaction_id, request_type, full_name, street, purok, barangay, municipality, province,
     birth_date, age, birth_place, marital_status, remarks, ctc_number,
     date_issued, place_issued, amount_paid, or_number, picture,
     claim_date, payment_method, payment_status, document_status, created_at, updated_at, request_source)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    // Debug/troubleshooting - in production you may want to log instead
    error_log("Prepare failed: " . $conn->error);
    header("Location: ../userPanel.php?page=serviceBarangayClearance");
    exit();
}

// NOTE: type string MUST match number of variables (27)
$typeString = 'isssssssssissssssdsssssssss'; // 27 chars, corrected

$stmt->bind_param(
    $typeString,
    $userId,
    $transactionId,
    $requestType,
    $fullName,
    $street,
    $purok,
    $barangay,
    $municipality,
    $province,
    $birthDate,
    $age,
    $birthPlace,
    $maritalStatus,
    $remarks,
    $ctcNumber,
    $dateIssued,
    $placeIssued,
    $amountPaid,
    $orNumber,
    $pictureFileName,
    $claimDate,
    $paymentMethod,
    $paymentStatus,
    $documentStatus,
    $createdAt,
    $updatedAt,
    $requestSource
);

if (!$stmt->execute()) {
    // insert failed - log and redirect back (adjust behavior as you prefer)
    error_log("Insert failed: " . $stmt->error);
    $stmt->close();
    header("Location: ../userPanel.php?page=serviceBarangayClearance");
    exit();
}
$stmt->close();

// 5) Redirect back to appropriate panel (preserve reference tid)
if (!empty($_POST['superAdminRedirect'])) {
    header("Location: ../superAdminPanel.php?page=superAdminRequest&transaction_id={$transactionId}");
    exit();
}

if (!empty($_POST['adminRedirect'])) {
    header("Location: ../adminPanel.php?page=adminRequest&transaction_id={$transactionId}");
    exit();
}

// Default: user panel — show submission screen with tid
header("Location: ../userPanel.php?page=serviceBarangayClearance&tid={$transactionId}");
exit();
