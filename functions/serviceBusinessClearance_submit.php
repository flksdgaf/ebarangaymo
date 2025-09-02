<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'dbconn.php'; // adjust path relative to this file

// Basic auth check (align with your other handlers)
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = (int)($_SESSION['loggedInUserID'] ?? 0);

// -------------------- 1) Collect posted fields --------------------
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
$requestSource  = 'Online'; // fixed

// 1.a) Apply defaults for barangay/municipality/province if omitted
if (empty($barangay)) $barangay = 'Magang';
if (empty($municipality)) $municipality = 'Daet';
if (empty($province)) $province = 'Camarines Norte';

// Build full_name in "LAST, FIRST MIDDLE" format
$fullName = trim($lastName);
if ($firstName !== '') {
    $fullName .= ', ' . $firstName;
    if ($middleName !== '') $fullName .= ' ' . $middleName;
}

// -------------------- 2) Handle picture upload (optional) --------------------
$pictureFilePath = null;
if (!empty($_FILES['picture']['name']) && isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/business_clearance/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $orig = basename($_FILES['picture']['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $ext = $ext ? strtolower($ext) : '';
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (in_array($ext, $allowed, true)) {
        // create safe unique name
        $safeName = 'bc_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
        $target = $uploadDir . $safeName;
        if (move_uploaded_file($_FILES['picture']['tmp_name'], $target)) {
            // store web-accessible relative path
            $pictureFilePath = 'uploads/business_clearance/' . $safeName;
        } else {
            // upload failed: continue but do not stop the whole flow
            $pictureFilePath = null;
        }
    } else {
        // unsupported extension -> ignore file
        $pictureFilePath = null;
    }
}

// -------------------- 3) Generate next transaction_id --------------------
// We'll attempt to parse the last transaction_id and increment its trailing number.
// Format used: BUSCLR-0000001 (7 digits padded)
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

// -------------------- 4) Prepare insert into business_clearance_requests --------------------
$createdAt = date('Y-m-d H:i:s');
$updatedAt = $createdAt;
$dateIssued = null;
$placeIssued = null;
$amountPaid = 0.00;      // not paid yet
$orNumber = null;
$paymentStatus = 'Unpaid';
$documentStatus = 'Pending';
$requestType = 'Business Clearance';

// Build SQL and prepared statement
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
    // log and redirect back
    error_log("Prepare failed (business_clearance insert): " . $conn->error);
    $_SESSION['svc_error'] = 'Server error. Please try again later.';
    header("Location: ../serviceBusinessClearance.php");
    exit();
}

// Build type string and bind variables
// Types per column:
// 1 account_id (i)
// 2 transaction_id (s)
// 3 request_type (s)
// 4 full_name (s)
// 5 purok (s)
// 6 barangay (s)
// 7 municipality (s)
// 8 province (s)
// 9 age (i)
// 10 marital_status (s)
// 11 business_name (s)
// 12 business_type (s)
// 13 address (s)
// 14 ctc_number (s)
// 15 date_issued (s)
// 16 place_issued (s)
// 17 amount (d)
// 18 or_number (s)
// 19 picture (s)
// 20 claim_date (s)
// 21 payment_method (s)
// 22 payment_status (s)
// 23 document_status (s)
// 24 created_at (s)
// 25 updated_at (s)
// 26 request_source (s)
$typeString = 'i' . str_repeat('s', 7) . 'i' . str_repeat('s', 6) . 's' . 'd' . str_repeat('s', 8);
// Explanation: i + 7*s (positions 2-8) + i (age) + 6*s (pos 10-15) + s (pos16) + d (pos17) + 8*s (pos18-25)
// To avoid confusion, we'll construct the array to match the SQL column order exactly:

$bindVars = [
    $userId,            // i
    $transactionId,     // s
    $requestType,       // s
    $fullName,          // s
    $purok,             // s
    $barangay,          // s
    $municipality,      // s
    $province,          // s
    $age,               // i
    $maritalStatus,     // s
    $businessName,      // s
    $businessType,      // s
    $address,           // s
    $ctcNumber,         // s
    $dateIssued,        // s (nullable)
    $placeIssued,       // s (nullable)
    $amountPaid,        // d
    $orNumber,          // s
    $pictureFilePath,   // s (nullable)
    $claimDate,         // s (nullable)
    $paymentMethod,     // s
    $paymentStatus,     // s
    $documentStatus,    // s
    $createdAt,         // s
    $updatedAt,         // s
    $requestSource      // s
];

// Create an explicit type string matching the bindVars
// account_id (i) + transaction..province (7 s) + age (i) + marital..ctc (5 s) + date_issued (s) + place_issued (s) + amount (d) + remaining 9 strings
$typeString = 'i' . str_repeat('s', 7) . 'i' . str_repeat('s', 5) . 's' . 's' . 'd' . str_repeat('s', 9);
// Validate length: should match 26
if (strlen($typeString) !== count($bindVars)) {
    // fallback simpler construction to exactly match 26 positions:
    $typeString = '';
    $typeString .= 'i';                   // account_id
    $typeString .= str_repeat('s', 7);    // transaction_id .. province (7)
    $typeString .= 'i';                   // age
    $typeString .= str_repeat('s', 5);    // marital_status .. ctc_number (5)
    $typeString .= 's';                   // date_issued
    $typeString .= 's';                   // place_issued
    $typeString .= 'd';                   // amount
    $typeString .= str_repeat('s', 9);    // or_number .. request_source (9)
}

// bind_param needs references
$refs = [];
foreach ($bindVars as $key => $value) {
    $refs[$key] = &$bindVars[$key];
}
// prepend type string
array_unshift($refs, $typeString);

// Use call_user_func_array to bind dynamic params
call_user_func_array([$stmt, 'bind_param'], $refs);

// Execute
$exec = $stmt->execute();
if (!$exec) {
    error_log("Insert failed (business_clearance): " . $stmt->error);
    $_SESSION['svc_error'] = 'Failed to save request. Please try again.';
    $stmt->close();
    header("Location: ../serviceBusinessClearance.php");
    exit();
}

$stmt->close();

// -------------------- 5) Success — redirect to serviceBusinessClearance.php with tid --------------------
header("Location: ../userPanel.php?page=serviceBusinessClearance&tid=" . urlencode($transactionId));
exit();
