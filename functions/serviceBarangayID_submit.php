<?php
// functions/serviceBarangayID_submit.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'dbconn.php';

// auth
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

$userId = (int)($_SESSION['loggedInUserID'] ?? 0);
if ($userId <= 0) {
    $_SESSION['svc_error'] = 'You must be logged in to submit a request.';
    header("Location: ../serviceBarangayID.php");
    exit();
}

// 1) Collect posted fields (trim safely)
$transactionType = isset($_POST['transactiontype']) ? trim($_POST['transactiontype']) : '';
$fullName        = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
$purok           = isset($_POST['purok']) ? trim($_POST['purok']) : '';
$height          = isset($_POST['height']) ? trim($_POST['height']) : '';
$weight          = isset($_POST['weight']) ? trim($_POST['weight']) : '';
// NEW optional valid ID
$validIdNumber   = isset($_POST['valid_id_number']) ? trim($_POST['valid_id_number']) : '';
$birthdate       = isset($_POST['birthday']) ? trim($_POST['birthday']) : '';
$birthplace      = isset($_POST['birthplace']) ? trim($_POST['birthplace']) : '';
$civilstatus     = isset($_POST['civilstatus']) ? trim($_POST['civilstatus']) : '';
$religion        = isset($_POST['religion']) ? trim($_POST['religion']) : '';
$contactperson   = isset($_POST['contactperson']) ? trim($_POST['contactperson']) : '';
$contactaddress  = isset($_POST['emergency_contact_address']) ? trim($_POST['emergency_contact_address']) : '';
// New frontend posts separate hidden fields claim_date (YYYY-MM-DD) and claim_time (Morning|Afternoon).
$postedClaimDate = isset($_POST['claim_date']) ? trim($_POST['claim_date']) : null;
$postedClaimTime = isset($_POST['claim_time']) ? trim($_POST['claim_time']) : null;
// Backwards compatibility: legacy radio with value "YYYY-MM-DD|Morning"
$postedClaimSlot = isset($_POST['claim_slot']) ? trim($_POST['claim_slot']) : null;

$paymentMethod   = isset($_POST['paymentMethod']) ? trim($_POST['paymentMethod']) : '';
$requestSource   = 'Online';

// Basic required validation (server-side)
$errors = [];
if ($fullName === '') $errors[] = 'Full name is required.';
if ($purok === '') $errors[] = 'Purok is required.';
if ($height === '') $errors[] = 'Height is required.';
if ($weight === '') $errors[] = 'Weight is required.';
if ($birthdate === '') $errors[] = 'Birthdate is required.';
if ($birthplace === '') $errors[] = 'Birthplace is required.';
if ($civilstatus === '') $errors[] = 'Civil status is required.';
if ($religion === '') $errors[] = 'Religion is required.';
if ($contactperson === '') $errors[] = 'Contact person is required.';
if ($contactaddress === '') $errors[] = 'Contact person address is required.';

// Validate optional valid ID length (if provided) - keep it reasonable
if ($validIdNumber !== '' && mb_strlen($validIdNumber) > 100) {
    $errors[] = 'Valid ID number is too long.';
}

// 2) Handle file upload (formal picture). Required per form.
$formalPicName = null;
if (!empty($_FILES['brgyIDpicture']['name']) && isset($_FILES['brgyIDpicture']) && $_FILES['brgyIDpicture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../barangayIDpictures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $orig = basename($_FILES['brgyIDpicture']['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $ext = $ext ? strtolower($ext) : '';
    $allowedExt = ['jpg','jpeg','png','gif','webp'];
    if (in_array($ext, $allowedExt, true)) {
        try {
            $safeName = 'brgyid_' . time() . '_' . bin2hex(random_bytes(5)) . ($ext ? '.' . $ext : '');
        } catch (Exception $e) {
            $safeName = 'brgyid_' . time() . '_' . mt_rand(1000,9999) . ($ext ? '.' . $ext : '');
        }
        $target = $uploadDir . $safeName;
        if (move_uploaded_file($_FILES['brgyIDpicture']['tmp_name'], $target)) {
            $formalPicName = $safeName;
        } else {
            // upload failed
            $errors[] = 'Failed to upload formal picture.';
        }
    } else {
        $errors[] = 'Unsupported formal picture file type.';
    }
} else {
    // no file provided or error
    $errors[] = 'Formal picture is required.';
}

// Parse claim inputs with graceful fallback
$claimDate = null; // YYYY-MM-DD
$claimPart = null; // Morning | Afternoon

// Helper to validate date & part
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
        // not provided — error later
    }
}

if ($claimDate === null) {
    $errors[] = 'Preferred claim date is required.';
}

// If any required missing -> redirect back
if (!empty($errors)) {
    $_SESSION['svc_error'] = implode(' ', $errors);
    header("Location: ../serviceBarangayID.php");
    exit();
}

// Server-side compute allowed claim dates matching serviceBarangayID.php behavior:
// - If request is made on Saturday (6) or Sunday (7), start options from next Monday.
// - Otherwise (Mon-Fri), options start from TOMORROW.
// - Always include only Mon-Fri dates; take the next 3 business days.
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
    header("Location: ../serviceBarangayID.php");
    exit();
}

// Generate next transaction_id (BRGYID-0000001)
$lastNumber = 0;
$stmt = $conn->prepare("SELECT transaction_id FROM barangay_id_requests ORDER BY id DESC LIMIT 1");
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
$transactionId = sprintf('BID-%07d', $num); // BRGYID

// Detect if DB has 'claim_time' column (preferred) or older 'claim_part'
$claimTimeColumn = null;
$check = $conn->query("SHOW COLUMNS FROM barangay_id_requests LIKE 'claim_time'");
if ($check && $check->num_rows > 0) {
    $claimTimeColumn = 'claim_time';
} else {
    $check2 = $conn->query("SHOW COLUMNS FROM barangay_id_requests LIKE 'claim_part'");
    if ($check2 && $check2->num_rows > 0) {
        $claimTimeColumn = 'claim_part'; // legacy support
    }
}

// Build column list and placeholders dynamically
$columns = [
    'account_id','transaction_id','request_type','transaction_type','full_name','purok',
    'birth_date','birth_place','civil_status','religion','height','weight',
    // NEW: store valid_id_number here (optional)
    'valid_id_number',
    'emergency_contact_person','emergency_contact_address','formal_picture','claim_date',
    // optionally claim_time/claim_part will be inserted next
    'payment_method','amount','payment_status','document_status','created_at','updated_at','request_source','valid_until'
];

if ($claimTimeColumn) {
    // insert claim_time/claim_part right after claim_date
    $pos = array_search('claim_date', $columns, true);
    if ($pos === false) $columns[] = $claimTimeColumn;
    else array_splice($columns, $pos + 1, 0, $claimTimeColumn);
}

// Prepare values in the same order as columns
$createdAt = date('Y-m-d H:i:s');
$updatedAt = $createdAt;
$paymentStatus = 'Pending';
$documentStatus = isset($_POST['document_status']) && $_POST['document_status'] !== '' ? trim($_POST['document_status']) : 'For Verification';
$amount = (isset($_POST['amount']) && $_POST['amount'] !== '') ? floatval($_POST['amount']) : 20.00;
$requestType = 'Barangay ID';

// Calculate valid_until date (1 year from creation)
$validUntil = date('Y-m-d', strtotime('+1 year', strtotime($createdAt)));

// Map column -> value
$colValues = [
    'account_id' => $userId,
    'transaction_id' => $transactionId,
    'request_type' => $requestType,
    'transaction_type' => $transactionType,
    'full_name' => $fullName,
    'purok' => $purok,
    'birth_date' => $birthdate,
    'birth_place' => $birthplace,
    'civil_status' => $civilstatus,
    'religion' => $religion,
    'height' => $height,
    'weight' => $weight,
    // NEW: valid id number (optional)
    'valid_id_number' => $validIdNumber !== '' ? mb_substr($validIdNumber, 0, 100) : null,
    'emergency_contact_person' => $contactperson,
    'emergency_contact_address' => $contactaddress,
    'formal_picture' => $formalPicName,
    'claim_date' => $claimDate,
    'payment_method' => $paymentMethod,
    'amount' => $amount,
    'payment_status' => $paymentStatus,
    'document_status' => $documentStatus,
    'created_at' => $createdAt,
    'updated_at' => $updatedAt,
    'request_source' => $requestSource,
    'valid_until' => $validUntil  // ADD THIS LINE
];

// Add claim_time/claim_part value if DB supports it
if ($claimTimeColumn) {
    $colValues[$claimTimeColumn] = $claimPart;
}

// Build placeholders
$placeholders = array_map(function($c){ return '?'; }, $columns);

// Build SQL
$sql = "INSERT INTO barangay_id_requests (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed (barangay_id insert): " . $conn->error . " SQL: " . $sql);
    $_SESSION['svc_error'] = 'Server error. Please try again later.';
    header("Location: ../serviceBarangayID.php");
    exit();
}

// Build bind types and bind values array in the same order
$types = '';
$bindValues = [];
foreach ($columns as $col) {
    $val = $colValues[$col] ?? null;
    // determine type
    if ($col === 'account_id') {
        $types .= 'i';
        $bindValues[] = ($val === null ? null : (int)$val);
    } elseif ($col === 'amount') {
        $types .= 'd';
        $bindValues[] = ($val === null ? null : (float)$val);
    } else {
        $types .= 's';
        // for null strings, pass empty string or null? mysqli bind_param treats null differently;
        // we'll convert null -> null and let DB accept NULL for columns that allow it
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
    error_log("bind_param failed (barangay_id): " . $stmt->error . ' / ' . $conn->error);
    $_SESSION['svc_error'] = 'Server error (binding). Please try again later.';
    $stmt->close();
    header("Location: ../serviceBarangayID.php");
    exit();
}

if (!$stmt->execute()) {
    error_log("Insert failed (barangay_id): " . $stmt->error);
    $_SESSION['svc_error'] = 'Failed to save request. Please try again.';
    $stmt->close();
    header("Location: ../serviceBarangayID.php");
    exit();
}

$stmt->close();

// --- GCash Payment Integration ---
if ($paymentMethod === 'GCash') {
    require_once __DIR__ . '/gcash_handler.php';
    
    try {
        $gcashResult = createGCashSource($transactionId, $amount);
        
        if ($gcashResult['success'] && isset($gcashResult['checkout_url'])) {
            // Store transaction in session for security
            $_SESSION['pending_gcash_transaction'] = $transactionId;
            
            // Redirect to GCash checkout
            header("Location: " . $gcashResult['checkout_url']);
            exit();
        } else {
            // GCash initialization failed
            $errorMsg = $gcashResult['error'] ?? 'Failed to initialize GCash payment.';
            $_SESSION['svc_error'] = $errorMsg . ' Please try another payment method.';
            
            // Update payment status to failed
            $stmtFail = $conn->prepare("UPDATE barangay_id_requests SET payment_status = 'failed' WHERE transaction_id = ?");
            $stmtFail->bind_param("s", $transactionId);
            $stmtFail->execute();
            $stmtFail->close();
            
            header("Location: ../serviceBarangayID.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("GCash Integration Error: " . $e->getMessage());
        $_SESSION['svc_error'] = 'Payment system error. Please try another payment method.';
        header("Location: ../serviceBarangayID.php");
        exit();
    }
}

// 5) Redirect back to the appropriate panel (support admin/superAdmin flows)
if (!empty($_POST['superAdminRedirect'])) {
    header("Location: ../superAdminPanel.php?page=superAdminRequest&transaction_id=" . urlencode($transactionId));
    exit();
}

if (!empty($_POST['adminRedirect'])) {
    header("Location: ../adminPanel.php?page=adminRequest&transaction_id=" . urlencode($transactionId));
    exit();
}

// Default: user panel
header("Location: ../userPanel.php?page=serviceBarangayID&tid=" . urlencode($transactionId));
exit();
