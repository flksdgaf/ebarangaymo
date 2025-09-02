<?php
session_start();
require 'dbconn.php';

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = (int)($_SESSION['loggedInUserID'] ?? 0);

// 1) Collect posted fields (map names used in serviceBarangayClearance.php)
$lastName        = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
$firstName       = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$middleName      = isset($_POST['middlename']) ? trim($_POST['middlename']) : '';
$street          = isset($_POST['street']) ? trim($_POST['street']) : '';
$purok           = isset($_POST['purok']) ? trim($_POST['purok']) : '';
$barangay        = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
$municipality    = isset($_POST['municipality']) ? trim($_POST['municipality']) : '';
$province        = isset($_POST['province']) ? trim($_POST['province']) : '';
$birthDate       = (isset($_POST['birthdate']) && $_POST['birthdate'] !== '') ? trim($_POST['birthdate']) : null;
$age             = (isset($_POST['age']) && $_POST['age'] !== '') ? (int) $_POST['age'] : null;
$birthPlace      = isset($_POST['birth_place']) ? trim($_POST['birth_place']) : '';
$maritalStatus   = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : '';
// remarks removed from form — keep empty so DB column is satisfied
$remarks         = '';
$ctcNumber       = isset($_POST['ctc_number']) ? trim($_POST['ctc_number']) : '';
$claimDate       = (isset($_POST['claim_date']) && $_POST['claim_date'] !== '') ? trim($_POST['claim_date']) : null;
$paymentMethod   = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
$requestSource   = 'Online';

// NEW: purpose fields
// Your form now provides a final 'purpose' hidden input that contains either the selected option or the custom text.
// We'll prefer that. Keep purpose_other only for optional logging but DO NOT attempt to write it to DB.
$purpose         = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
$purposeOther    = isset($_POST['purpose_other']) ? trim($_POST['purpose_other']) : '';

// If purpose is empty, set to null so DB can accept NULL if allowed
if ($purpose === '') $purpose = null;

// 1.a) Apply defaults for barangay/municipality/province if form omitted or empty
if (empty($barangay)) $barangay = 'Magang';
if (empty($municipality)) $municipality = 'Daet';
if (empty($province)) $province = 'Camarines Norte'; // align with serviceBarangayClearance.php default

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
    $ext = $ext ? strtolower($ext) : '';
    // Allow common image extensions only (basic check)
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) {
        // skip upload if extension not allowed
        $pictureFileName = null;
    } else {
        $pictureFileName = uniqid('clr_') . ($ext ? '.' . $ext : '');
        $target = $uploadDir . $pictureFileName;

        if (!move_uploaded_file($_FILES['picture']['tmp_name'], $target)) {
            // If upload fails, null the filename and continue
            $pictureFileName = null;
        }
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

// NOTE: DO NOT include purpose_other as a DB column (your table list didn't include it).
$sql = "
  INSERT INTO barangay_clearance_requests
    (account_id, transaction_id, request_type, full_name, street, purok, barangay, municipality, province,
     birth_date, age, birth_place, marital_status, remarks, ctc_number,
     purpose,
     date_issued, place_issued, amount, or_number, picture,
     claim_date, payment_method, payment_status, document_status, created_at, updated_at, request_source)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    // Debug/troubleshooting - in production you may want to log instead
    error_log("Prepare failed: " . $conn->error);
    header("Location: ../userPanel.php?page=serviceBarangayClearance");
    exit();
}

// Build the type string. Positions:
// 1 account_id (i)
// 2 transaction_id (s)
// 3 request_type (s)
// 4 full_name (s)
// 5 street (s)
// 6 purok (s)
// 7 barangay (s)
// 8 municipality (s)
// 9 province (s)
// 10 birth_date (s)
// 11 age (i)
// 12 birth_place (s)
// 13 marital_status (s)
// 14 remarks (s)
// 15 ctc_number (s)
// 16 purpose (s)
// 17 date_issued (s)
// 18 place_issued (s)
// 19 amount (d)
// 20 or_number (s)
// 21 picture (s)
// 22 claim_date (s)
// 23 payment_method (s)
// 24 payment_status (s)
// 25 document_status (s)
// 26 created_at (s)
// 27 updated_at (s)
// 28 request_source (s)

$typeString = 'i' . str_repeat('s', 9) . 'i' . str_repeat('s', 6) . 's' . 'd' . str_repeat('s', 9);
// Explanation:
// - 'i' (account_id)
// - 9 's' (transaction_id..birth_date -> positions 2..10)
// - 'i' (age position 11)
// - 6 's' (birth_place..ctc_number -> positions 12..17)  <-- careful: we need to match counts below
// The simpler accurate way is to rebuild deterministically:

// To avoid confusion, let's rebuild string explicitly:
$typeString = implode('', [
    'i',          // account_id
    's','s','s','s','s','s','s','s','s', // transaction_id..birth_date (9 s)
    'i',          // age
    's','s','s','s','s', // birth_place, marital_status, remarks, ctc_number, purpose (5 s)
    's','s',      // date_issued, place_issued (2 s)
    'd',          // amount
    's','s','s','s','s','s','s','s','s' // or_number, picture, claim_date, payment_method, payment_status, document_status, created_at, updated_at, request_source (9 s)
]);

// Prepare variables in exact same order as SQL columns
$bindVars = [
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
    $purpose,
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
];

// bind_param requires references
$refs = [];
foreach ($bindVars as $k => $v) {
    $refs[$k] = &$bindVars[$k];
}

// prepend type string
array_unshift($refs, $typeString);

// call bind_param with dynamic args
call_user_func_array([$stmt, 'bind_param'], $refs);

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
