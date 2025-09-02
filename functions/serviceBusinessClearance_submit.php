<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'dbconn.php';

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = (int)($_SESSION['loggedInUserID'] ?? 0);

// Collect posted fields
$lastName       = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
$firstName      = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$middleName     = isset($_POST['middlename']) ? trim($_POST['middlename']) : '';
$purok          = isset($_POST['purok']) ? trim($_POST['purok']) : '';
$barangay       = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
$municipality   = isset($_POST['municipality']) ? trim($_POST['municipality']) : '';
$province       = isset($_POST['province']) ? trim($_POST['province']) : '';
$age            = (isset($_POST['age']) && $_POST['age'] !== '') ? (int) $_POST['age'] : null;
$maritalStatus  = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : '';
$businessName   = isset($_POST['business_name']) ? trim($_POST['business_name']) : '';
$businessType   = isset($_POST['business_type']) ? trim($_POST['business_type']) : '';
$address        = isset($_POST['address']) ? trim($_POST['address']) : '';
$ctcNumber      = isset($_POST['ctc_number']) ? trim($_POST['ctc_number']) : '';
$claimDate      = (isset($_POST['claim_date']) && $_POST['claim_date'] !== '') ? trim($_POST['claim_date']) : null;
$paymentMethod  = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
$requestSource  = 'Online';

// Defaults for location fields
if (empty($barangay)) $barangay = 'Magang';
if (empty($municipality)) $municipality = 'Daet';
if (empty($province)) $province = 'Camarines Norte';

// Build full_name "LAST, FIRST MIDDLE"
$fullName = trim($lastName);
if ($firstName !== '') {
    $fullName .= ', ' . $firstName;
    if ($middleName !== '') $fullName .= ' ' . $middleName;
}

// Handle optional picture upload
$pictureFilePath = null;
if (!empty($_FILES['picture']['name']) && isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../businessClearancePictures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $orig = basename($_FILES['picture']['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $ext = $ext ? strtolower($ext) : '';
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (in_array($ext, $allowed, true)) {
        $safeName = 'bc_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
        $target = $uploadDir . $safeName;
        if (move_uploaded_file($_FILES['picture']['tmp_name'], $target)) {
            $pictureFilePath = $safeName;
        } else {
            $pictureFilePath = null;
        }
    } else {
        $pictureFilePath = null;
    }
}

// Generate next transaction_id (BUSCLR-0000001)
$lastNumber = 0;
$stmt = $conn->prepare("SELECT transaction_id FROM business_clearance_requests ORDER BY id DESC LIMIT 1");
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
$transactionId = sprintf('BUSCLR-%07d', $num);

// Prepare insert
$createdAt = date('Y-m-d H:i:s');
$updatedAt = $createdAt;
$dateIssued = null;
$placeIssued = null;
$orNumber = null;
$paymentStatus = 'Pending';

// DOCUMENT STATUS: default changed to "For Verification" (but will accept posted value if present)
$documentStatus = isset($_POST['document_status']) && $_POST['document_status'] !== '' ? trim($_POST['document_status']) : 'For Verification';

// AMOUNT: default to 130 (will accept posted amount if provided)
$amount = (isset($_POST['amount']) && $_POST['amount'] !== '') ? floatval($_POST['amount']) : 130.00;

$requestType = 'Business Clearance';

$sql = "
INSERT INTO business_clearance_requests
    (account_id, transaction_id, request_type, full_name, purok, barangay, municipality, province,
     age, marital_status, business_name, business_type, address, ctc_number,
     date_issued, place_issued, amount, or_number, picture, claim_date,
     payment_method, payment_status, document_status, created_at, updated_at, request_source)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed (business_clearance insert): " . $conn->error);
    $_SESSION['svc_error'] = 'Server error. Please try again later.';
    header("Location: ../serviceBusinessClearance.php");
    exit();
}

$bindVars = [
    $userId,
    $transactionId,
    $requestType,
    $fullName,
    $purok,
    $barangay,
    $municipality,
    $province,
    $age,
    $maritalStatus,
    $businessName,
    $businessType,
    $address,
    $ctcNumber,
    $dateIssued,
    $placeIssued,
    $amount,
    $orNumber,
    $pictureFilePath,
    $claimDate,
    $paymentMethod,
    $paymentStatus,
    $documentStatus,
    $createdAt,
    $updatedAt,
    $requestSource
];

// Build a matching type string for bind_param
$typeString = '';
$typeString .= 'i';                   // account_id
$typeString .= str_repeat('s', 7);    // transaction_id .. province (7 strings)
$typeString .= 'i';                   // age
$typeString .= str_repeat('s', 5);    // marital_status .. ctc_number (5 strings)
$typeString .= 's';                   // date_issued
$typeString .= 's';                   // place_issued
$typeString .= 'd';                   // amount (double)
$typeString .= str_repeat('s', 9);    // or_number .. request_source (9 strings)

// bind parameters by reference
$refs = [];
foreach ($bindVars as $k => $v) $refs[$k] = &$bindVars[$k];
array_unshift($refs, $typeString);
call_user_func_array([$stmt, 'bind_param'], $refs);

if (!$stmt->execute()) {
    error_log("Insert failed (business_clearance): " . $stmt->error);
    $_SESSION['svc_error'] = 'Failed to save request. Please try again.';
    $stmt->close();
    header("Location: ../serviceBusinessClearance.php");
    exit();
}

$stmt->close();

header("Location: ../userPanel.php?page=serviceBusinessClearance&tid=" . urlencode($transactionId));
exit();
