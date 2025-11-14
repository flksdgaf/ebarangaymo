<?php
// functions/serviceBarangayClearance_submit.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'dbconn.php';

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

$userId = (int)($_SESSION['loggedInUserID'] ?? 0);
if ($userId <= 0) {
    $_SESSION['svc_error'] = 'You must be logged in to submit a request.';
    header("Location: ../serviceBarangayClearance.php");
    exit();
}

// 1) Collect posted fields (map names used in serviceBarangayClearance.php)
// Prefer single fullname (new), but keep fallback support for old last/first/middle fields.
$transactionType = isset($_POST['transactiontype']) ? trim($_POST['transactiontype']) : 'New Application';
$fullName        = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';

// Legacy fallbacks (kept for compatibility)
$lastName        = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
$firstName       = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$middleName      = isset($_POST['middlename']) ? trim($_POST['middlename']) : '';

$street          = isset($_POST['street']) ? trim($_POST['street']) : '';
$purok           = isset($_POST['purok']) ? trim($_POST['purok']) : '';
$barangay        = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
$municipality    = isset($_POST['municipality']) ? trim($_POST['municipality']) : '';
$province        = isset($_POST['province']) ? trim($_POST['province']) : '';
$birthDate       = isset($_POST['birthdate']) ? trim($_POST['birthdate']) : null;
$age             = (isset($_POST['age']) && $_POST['age'] !== '') ? (int) $_POST['age'] : null;
$birthPlace      = isset($_POST['birth_place']) ? trim($_POST['birth_place']) : '';
$maritalStatus   = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : '';

// REMARKS: default to "NO DEROGATORY RECORD" if empty
$remarks         = isset($_POST['remarks']) && trim($_POST['remarks']) !== '' ? trim($_POST['remarks']) : 'NO DEROGATORY RECORD';

// IMPORTANT CHANGE: set ctcNumber to NULL when input is empty so DB default NULL applies
$ctcNumber = null;
if (isset($_POST['ctc_number']) && trim($_POST['ctc_number']) !== '') {
    $ctcNumber = trim($_POST['ctc_number']);
}

// Purpose priority: hidden 'purpose' -> purpose_other -> purpose_select
$purpose = '';
if (isset($_POST['purpose']) && trim($_POST['purpose']) !== '') {
    $purpose = trim($_POST['purpose']);
} elseif (isset($_POST['purpose_other']) && trim($_POST['purpose_other']) !== '') {
    $purpose = trim($_POST['purpose_other']);
} elseif (isset($_POST['purpose_select']) && trim($_POST['purpose_select']) !== '') {
    $purpose = trim($_POST['purpose_select']);
} else {
    $purpose = '';
}

// Claim inputs (new & legacy support)
$postedClaimDate = isset($_POST['claim_date']) ? trim($_POST['claim_date']) : null;
$postedClaimTime = isset($_POST['claim_time']) ? trim($_POST['claim_time']) : null;
$postedClaimSlot = isset($_POST['claim_slot']) ? trim($_POST['claim_slot']) : null;

$paymentMethod   = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
$requestSource   = 'Online';

// Defaults for location fields (form no longer provides them but DB expects them — keep sensible defaults)
if (empty($barangay)) $barangay = 'Magang';
if (empty($municipality)) $municipality = 'Daet';
if (empty($province)) $province = 'Camarines Norte';

// If fullname not provided but legacy parts exist, assemble fallback name as "First Middle Surname"
if ($fullName === '') {
    $assembled = '';
    if ($firstName !== '') $assembled .= $firstName;
    if ($middleName !== '') $assembled .= ($assembled === '' ? '' : ' ') . $middleName;
    if ($lastName !== '')  $assembled .= ($assembled === '' ? '' : ' ') . $lastName;
    $fullName = trim($assembled);
}

// Basic required validation (server-side)
$errors = [];
if ($fullName === '') $errors[] = 'Full name is required.';
if ($purok === '') $errors[] = 'Purok is required.';
if ($birthDate === '' || $birthDate === null) $errors[] = 'Birthdate is required.';
if ($age === null) $errors[] = 'Age is required.';
if ($birthPlace === '') $errors[] = 'Birthplace is required.';
if ($maritalStatus === '') $errors[] = 'Marital status is required.';

// Parse claim inputs with graceful fallback (prefer separate fields)
$claimDate = null; // YYYY-MM-DD
$claimPart = null; // Morning | Afternoon

function is_valid_date($d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}
$allowedParts = ['Morning', 'Afternoon'];

// 1) If both separate fields present, prefer them
if (!empty($postedClaimDate) && !empty($postedClaimTime)) {
    if (is_valid_date($postedClaimDate) && in_array($postedClaimTime, $allowedParts, true)) {
        $claimDate = $postedClaimDate;
        $claimPart = $postedClaimTime;
    } else {
        $errors[] = 'Invalid preferred claim date or time part.';
    }
} else {
    // 2) If postedClaimSlot exists (legacy radio with value "YYYY-MM-DD|Morning"), parse it
    if (!empty($postedClaimSlot) && strpos($postedClaimSlot, '|') !== false) {
        [$d, $p] = explode('|', $postedClaimSlot, 2);
        $d = trim($d); $p = trim($p);
        if (is_valid_date($d) && in_array($p, $allowedParts, true)) {
            $claimDate = $d;
            $claimPart = $p;
        } else {
            $errors[] = 'Invalid preferred claim format.';
        }
    } elseif (!empty($postedClaimDate) && strpos($postedClaimDate, '|') !== false) {
        // 3) If claim_date contains legacy pipe (older frontend), parse it
        [$d, $p] = explode('|', $postedClaimDate, 2);
        $d = trim($d); $p = trim($p);
        if (is_valid_date($d) && in_array($p, $allowedParts, true)) {
            $claimDate = $d;
            $claimPart = $p;
        } else {
            $errors[] = 'Invalid preferred claim format.';
        }
    } elseif (!empty($postedClaimDate) && is_valid_date($postedClaimDate)) {
        // 4) If only a plain date provided, accept date and default to Morning
        $claimDate = $postedClaimDate;
        $claimPart = 'Morning';
    } else {
        // Not provided — will error
    }
}

if ($claimDate === null) {
    $errors[] = 'Preferred claim date is required.';
}

// If any required missing -> redirect back with error
if (!empty($errors)) {
    $_SESSION['svc_error'] = implode(' ', $errors);
    header("Location: ../serviceBarangayClearance.php");
    exit();
}

// Server-side compute allowed claim dates matching serviceBusinessClearance.php behavior:
// - If the request is made on Saturday (6) or Sunday (7), start options from next Monday.
// - Otherwise (Mon-Fri), options start from TOMORROW.
// - Only include Mon-Fri; take the next 3 business days.
$today = new DateTime('now', new DateTimeZone('Asia/Manila'));
$weekdayNow = (int)$today->format('N'); // 1=Mon .. 7=Sun

if ($weekdayNow === 6) { // Saturday -> next Monday (+2)
    $start = (clone $today)->modify('+2 days');
} elseif ($weekdayNow === 7) { // Sunday -> next Monday (+1)
    $start = (clone $today)->modify('+1 day');
} else {
    // Monday - Friday -> start tomorrow
    $start = (clone $today)->modify('+1 day');
}

$allowedDates = [];
$cursor = clone $start;
while (count($allowedDates) < 3) {
    $dow = (int)$cursor->format('N'); // 1..7
    if ($dow <= 5) { // Mon-Fri
        $allowedDates[] = $cursor->format('Y-m-d');
    }
    $cursor->modify('+1 day');
}

// validate claimDate against allowedDates
if (!in_array($claimDate, $allowedDates, true)) {
    $_SESSION['svc_error'] = 'Invalid preferred claim date. Available dates: ' . implode(', ', $allowedDates);
    header("Location: ../serviceBarangayClearance.php");
    exit();
}

// 2) Handle file upload (picture) — optional (validate extension)
$pictureFileName = null;
if (!empty($_FILES['picture']['name']) && isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../barangayClearancePictures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $orig = basename($_FILES['picture']['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $ext = $ext ? strtolower($ext) : '';
    $allowedExt = ['jpg','jpeg','png','gif','webp'];
    if (in_array($ext, $allowedExt, true)) {
        try {
            $safeName = 'clr_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
        } catch (Exception $e) {
            $safeName = 'clr_' . time() . '_' . mt_rand(1000,9999) . ($ext ? '.' . $ext : '');
        }
        $target = $uploadDir . $safeName;
        if (move_uploaded_file($_FILES['picture']['tmp_name'], $target)) {
            $pictureFileName = $safeName;
        } else {
            $pictureFileName = null; // continue without picture
        }
    } else {
        // unsupported file type: ignore file
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
$transactionId = sprintf('CLR-%07d', $num); //BRGYCLR

// 4) Detect if DB has 'claim_time' column (preferred) or older 'claim_part'
$claimTimeColumn = null;
$check = $conn->query("SHOW COLUMNS FROM barangay_clearance_requests LIKE 'claim_time'");
if ($check && $check->num_rows > 0) {
    $claimTimeColumn = 'claim_time';
} else {
    $check2 = $conn->query("SHOW COLUMNS FROM barangay_clearance_requests LIKE 'claim_part'");
    if ($check2 && $check2->num_rows > 0) {
        $claimTimeColumn = 'claim_part'; // legacy support
    }
}

// 5) Build column list dynamically and values
$columns = [
    'account_id','transaction_id','request_type','full_name','street','purok','barangay','municipality','province',
    'birth_date','age','birth_place','marital_status','remarks','ctc_number','purpose',
    'date_issued','place_issued','amount','or_number','picture','claim_date',
    'payment_method','payment_status','document_status','created_at','updated_at','request_source'
];

// Insert claim_time/claim_part column if DB supports it (place after claim_date)
if ($claimTimeColumn) {
    $pos = array_search('claim_date', $columns, true);
    if ($pos === false) $columns[] = $claimTimeColumn;
    else array_splice($columns, $pos + 1, 0, $claimTimeColumn);
}

// prepare placeholders
$placeholders = array_map(function($c){ return '?'; }, $columns);

// Build values mapping
$createdAt = date('Y-m-d H:i:s');
$updatedAt = $createdAt;
$dateIssued = null;
$placeIssued = null;
$orNumber = null;
$paymentStatus = 'Pending';
$documentStatus = isset($_POST['document_status']) && $_POST['document_status'] !== '' ? trim($_POST['document_status']) : 'For Verification';
$amount = (isset($_POST['amount']) && $_POST['amount'] !== '') ? floatval($_POST['amount']) : 130.00;
$requestType = 'Barangay Clearance';

$colValues = [
    'account_id' => $userId,
    'transaction_id' => $transactionId,
    'request_type' => $requestType,
    'full_name' => $fullName,
    'street' => $street,
    'purok' => $purok,
    'barangay' => $barangay,
    'municipality' => $municipality,
    'province' => $province,
    'birth_date' => $birthDate,
    'age' => $age,
    'birth_place' => $birthPlace,
    'marital_status' => $maritalStatus,
    'remarks' => $remarks,
    'ctc_number' => $ctcNumber,
    'purpose' => $purpose,
    'date_issued' => $dateIssued,
    'place_issued' => $placeIssued,
    'amount' => $amount,
    'or_number' => $orNumber,
    'picture' => $pictureFileName,
    'claim_date' => $claimDate,
    'payment_method' => $paymentMethod,
    'payment_status' => $paymentStatus,
    'document_status' => $documentStatus,
    'created_at' => $createdAt,
    'updated_at' => $updatedAt,
    'request_source' => $requestSource
];

// Add claim_time/claim_part value if DB supports it
if ($claimTimeColumn) {
    $colValues[$claimTimeColumn] = $claimPart;
}

// 6) Build SQL and prepare
$sql = "INSERT INTO barangay_clearance_requests (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed (barangay_clearance insert): " . $conn->error . " SQL: " . $sql);
    $_SESSION['svc_error'] = 'Server error. Please try again later.';
    header("Location: ../serviceBarangayClearance.php");
    exit();
}

// Build bind types and values in correct order
$types = '';
$bindValues = [];
foreach ($columns as $col) {
    $val = array_key_exists($col, $colValues) ? $colValues[$col] : null;
    if ($col === 'account_id' || $col === 'age') {
        $types .= 'i';
        $bindValues[] = ($val === null ? null : (int)$val);
    } elseif ($col === 'amount') {
        $types .= 'd';
        $bindValues[] = ($val === null ? null : (float)$val);
    } else {
        $types .= 's';
        $bindValues[] = ($val === null ? null : (string)$val);
    }
}

// Convert to references for call_user_func_array
$refs = [];
$refs[] = $types;
for ($i = 0; $i < count($bindValues); $i++) {
    $refs[] = &$bindValues[$i];
}

// Bind and execute
if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
    error_log("bind_param failed (barangay): " . $stmt->error . ' / ' . $conn->error);
    $_SESSION['svc_error'] = 'Server error (binding). Please try again later.';
    $stmt->close();
    header("Location: ../serviceBarangayClearance.php");
    exit();
}

if (!$stmt->execute()) {
    error_log("Insert failed (barangay_clearance): " . $stmt->error);
    $_SESSION['svc_error'] = 'Failed to save request. Please try again.';
    $stmt->close();
    header("Location: ../serviceBarangayClearance.php");
    exit();
}

$stmt->close();

// --- Universal GCash Payment Integration ---
if ($paymentMethod === 'GCash') {
    require_once __DIR__ . '/gcash/handler.php';
    
    try {
        $handler = new UniversalGCashHandler($conn);
        $gcashResult = $handler->createPaymentSource($transactionId, $amount);
        
        if ($gcashResult['success'] && isset($gcashResult['checkout_url'])) {
            $_SESSION['pending_gcash_payment'] = $transactionId;
            $conn->close();
            header("Location: " . $gcashResult['checkout_url']);
            exit();
        } else {
            $_SESSION['svc_error'] = $gcashResult['error'] ?? 'Failed to initialize GCash payment';
            
            $stmtFail = $conn->prepare("UPDATE barangay_clearance_requests SET payment_status = 'failed' WHERE transaction_id = ?");
            $stmtFail->bind_param("s", $transactionId);
            $stmtFail->execute();
            $stmtFail->close();
            
            $conn->close();
            header("Location: ../serviceBarangayClearance.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("GCash Error (Barangay Clearance): " . $e->getMessage());
        $_SESSION['svc_error'] = 'Payment system error';
        $conn->close();
        header("Location: ../serviceBarangayClearance.php");
        exit();
    }
}

$conn->close();

// 7) Redirects (keep your original admin/superAdmin handling if present)
if (!empty($_POST['superAdminRedirect'])) {
    header("Location: ../superAdminPanel.php?page=superAdminRequest&transaction_id=" . urlencode($transactionId));
    exit();
}

if (!empty($_POST['adminRedirect'])) {
    header("Location: ../adminPanel.php?page=adminRequest&transaction_id=" . urlencode($transactionId));
    exit();
}

// Default: user panel – show submission screen with tid
header("Location: ../userPanel.php?page=serviceBarangayClearance&tid=" . urlencode($transactionId));
exit();
