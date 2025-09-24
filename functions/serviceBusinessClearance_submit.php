<?php
// functions/serviceBusinessClearance_submit.php
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
    header("Location: ../serviceBusinessClearance.php");
    exit();
}

// Collect posted fields
// Prefer new single full_name (First Middle Surname). Hidden firstname/middlename/lastname kept for compatibility.
$postedFullName  = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$postedFirstName = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$postedMiddleName= isset($_POST['middlename']) ? trim($_POST['middlename']) : '';
$postedLastName  = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';

$purok          = isset($_POST['purok']) ? trim($_POST['purok']) : '';
$barangay       = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
$municipality   = isset($_POST['municipality']) ? trim($_POST['municipality']) : '';
$province       = isset($_POST['province']) ? trim($_POST['province']) : '';
$age            = (isset($_POST['age']) && $_POST['age'] !== '') ? (int) $_POST['age'] : null;
$maritalStatus  = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : '';
$businessName   = isset($_POST['business_name']) ? trim($_POST['business_name']) : '';
$businessType   = isset($_POST['business_type']) ? trim($_POST['business_type']) : '';
$address        = isset($_POST['address']) ? trim($_POST['address']) : '';
$ctcNumber      = isset($_POST['ctc_number']) ? trim($_POST['ctc_number']) : ''; // <--- keep as '' if empty
// New frontend posts separate hidden fields claim_date (YYYY-MM-DD) and claim_time (Morning|Afternoon).
$postedClaimDate = isset($_POST['claim_date']) ? trim($_POST['claim_date']) : null;
$postedClaimTime = isset($_POST['claim_time']) ? trim($_POST['claim_time']) : null;
// Backwards compatibility: older frontend might post claim_date as "YYYY-MM-DD|Morning" or have claim_slot radio.
$postedClaimSlot = isset($_POST['claim_slot']) ? trim($_POST['claim_slot']) : null;

$paymentMethod  = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
$requestSource  = 'Online';

// Defaults for location fields
if (empty($barangay)) $barangay = 'Magang';
if (empty($municipality)) $municipality = 'Daet';
if (empty($province)) $province = 'Camarines Norte';

// Basic required validation (server-side)
$errors = [];

// Determine name parts and DB full_name format
// If user provided visible full_name (First Middle Surname), parse it to components and then build DB-style "LAST, FIRST MIDDLE".
// Otherwise fallback to posted firstname/lastname/middlename (legacy).
$firstName = $postedFirstName;
$middleName = $postedMiddleName;
$lastName = $postedLastName;
$dbFullName = '';

// If visible full_name present, parse it
if ($postedFullName !== '') {
    // split on whitespace
    $parts = preg_split('/\s+/', $postedFullName, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) === 1) {
        // single token: treat as first name (no last name)
        $firstName = $parts[0];
        $middleName = '';
        $lastName = '';
    } else {
        // last token is surname, first token is first name, middle tokens are middle name(s)
        $firstName = $parts[0];
        $lastName = $parts[count($parts) - 1];
        if (count($parts) > 2) {
            $middleName = implode(' ', array_slice($parts, 1, count($parts) - 2));
        } else {
            $middleName = '';
        }
    }
    // Build DB expected "LAST, FIRST MIDDLE" when possible
    if ($lastName !== '') {
        $dbFullName = $lastName . ', ' . $firstName . ($middleName !== '' ? ' ' . $middleName : '');
    } else {
        // No last name present — keep "FIRST MIDDLE" as fallback
        $dbFullName = $firstName . ($middleName !== '' ? ' ' . $middleName : '');
    }
} else {
    // No visible full_name: attempt legacy firstname/lastname fields
    if ($lastName !== '' || $firstName !== '') {
        $dbFullName = trim($lastName);
        if ($firstName !== '') {
            if ($dbFullName !== '') $dbFullName .= ', ';
            $dbFullName .= $firstName . ($middleName !== '' ? ' ' . $middleName : '');
        } else {
            // only last name provided
            $dbFullName = $lastName ?: $firstName ?: '';
        }
    } else {
        // nothing provided: will be caught by validation below
        $dbFullName = '';
    }
}

// Name validation: require visible full_name or at least legacy name parts
if ($dbFullName === '') {
    $errors[] = 'Full name is required.';
}

if ($businessName === '') $errors[] = 'Business name is required.';
if ($businessType === '') $errors[] = 'Business type is required.';
if ($address === '') $errors[] = 'Business address is required.';

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
        // not provided — error later if still null
    }
}

if ($claimDate === null) {
    $errors[] = 'Preferred claim date is required.';
}

// If any required missing -> redirect back
if (!empty($errors)) {
    $_SESSION['svc_error'] = implode(' ', $errors);
    header("Location: ../serviceBusinessClearance.php");
    exit();
}

// Server-side compute allowed claim dates matching serviceBusinessClearance.php behavior:
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
    header("Location: ../serviceBusinessClearance.php");
    exit();
}

// For DB store we use $dbFullName (constructed earlier). If empty fallback to previously built style from last/first
$fullName = $dbFullName;
if ($fullName === '') {
    // construct fallback similar to previous implementation
    $fullName = trim($lastName);
    if ($firstName !== '') {
        $fullName .= ', ' . $firstName;
        if ($middleName !== '') $fullName .= ' ' . $middleName;
    }
}

// Handle optional picture upload (store only filename in DB)
$pictureFilePath = null;
if (!empty($_FILES['picture']['name']) && isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../businessClearancePictures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $orig = basename($_FILES['picture']['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $ext = $ext ? strtolower($ext) : '';
    $allowedExt = ['jpg','jpeg','png','gif','webp'];
    if (in_array($ext, $allowedExt, true)) {
        try {
            $safeName = 'bc_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
        } catch (Exception $e) {
            $safeName = 'bc_' . time() . '_' . mt_rand(1000,9999) . ($ext ? '.' . $ext : '');
        }
        $target = $uploadDir . $safeName;
        if (move_uploaded_file($_FILES['picture']['tmp_name'], $target)) {
            $pictureFilePath = $safeName;
        } else {
            // upload failed but not fatal: continue without picture
            $pictureFilePath = null;
        }
    } else {
        // unsupported file type: ignore file (you could also add an error)
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

// Detect if DB has 'claim_time' column (preferred) or older 'claim_part'
$claimTimeColumn = null;
$check = $conn->query("SHOW COLUMNS FROM business_clearance_requests LIKE 'claim_time'");
if ($check && $check->num_rows > 0) {
    $claimTimeColumn = 'claim_time';
} else {
    $check2 = $conn->query("SHOW COLUMNS FROM business_clearance_requests LIKE 'claim_part'");
    if ($check2 && $check2->num_rows > 0) {
        $claimTimeColumn = 'claim_part'; // legacy support
    }
}

// Build column list and placeholders dynamically
$columns = [
    'account_id','transaction_id','request_type','full_name','purok','barangay','municipality','province',
    'age','marital_status','business_name','business_type','address','ctc_number',
    'date_issued','place_issued','amount','or_number','picture','claim_date',
    'payment_method','payment_status','document_status','created_at','updated_at','request_source'
];

if ($claimTimeColumn) {
    // insert claim_time/claim_part right after claim_date
    $pos = array_search('claim_date', $columns, true);
    if ($pos === false) $columns[] = $claimTimeColumn;
    else array_splice($columns, $pos + 1, 0, $claimTimeColumn);
}

// ---------------------------
// IMPORTANT: if user didn't provide a CTC number (empty string), remove the ctc_number
// column so the DB uses its DEFAULT (NULL). This prevents inserting '' or '0'.
// ---------------------------
if ($ctcNumber === '') {
    $idx = array_search('ctc_number', $columns, true);
    if ($idx !== false) {
        array_splice($columns, $idx, 1);
    }
}

$placeholders = array_map(function($c){ return '?'; }, $columns);

// Prepare values in the same order as columns
$createdAt = date('Y-m-d H:i:s');
$updatedAt = $createdAt;
$dateIssued = null;
$placeIssued = null;
$orNumber = null;
$paymentStatus = 'Pending';
$documentStatus = isset($_POST['document_status']) && $_POST['document_status'] !== '' ? trim($_POST['document_status']) : 'For Verification';
$amount = (isset($_POST['amount']) && $_POST['amount'] !== '') ? floatval($_POST['amount']) : 130.00;
$requestType = 'Business Clearance';

// Map column -> value
$colValues = [
    'account_id' => $userId,
    'transaction_id' => $transactionId,
    'request_type' => $requestType,
    'full_name' => $fullName,
    'purok' => $purok,
    'barangay' => $barangay,
    'municipality' => $municipality,
    'province' => $province,
    'age' => $age,
    'marital_status' => $maritalStatus,
    'business_name' => $businessName,
    'business_type' => $businessType,
    'address' => $address,
    'ctc_number' => $ctcNumber,
    'date_issued' => $dateIssued,
    'place_issued' => $placeIssued,
    'amount' => $amount,
    'or_number' => $orNumber,
    'picture' => $pictureFilePath,
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

// Remove ctc_number value from colValues if we removed the column above
if ($ctcNumber === '' && array_key_exists('ctc_number', $colValues)) {
    unset($colValues['ctc_number']);
}

// Build SQL
$sql = "INSERT INTO business_clearance_requests (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed (business_clearance insert): " . $conn->error . " SQL: " . $sql);
    $_SESSION['svc_error'] = 'Server error. Please try again later.';
    header("Location: ../serviceBusinessClearance.php");
    exit();
}

// Build bind types and bind values array in the same order
$types = '';
$bindValues = [];
foreach ($columns as $col) {
    $val = $colValues[$col] ?? null;
    // determine type
    if ($col === 'account_id' || $col === 'age') {
        $types .= 'i';
        // ensure integer or null (mysqli requires null to be passed as null variable)
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
    error_log("bind_param failed: " . $stmt->error . ' / ' . $conn->error);
    $_SESSION['svc_error'] = 'Server error (binding). Please try again later.';
    $stmt->close();
    header("Location: ../serviceBusinessClearance.php");
    exit();
}

if (!$stmt->execute()) {
    error_log("Insert failed (business_clearance): " . $stmt->error);
    $_SESSION['svc_error'] = 'Failed to save request. Please try again.';
    $stmt->close();
    header("Location: ../serviceBusinessClearance.php");
    exit();
}

$stmt->close();

// Success -> redirect to submission view
header("Location: ../userPanel.php?page=serviceBusinessClearance&tid=" . urlencode($transactionId));
exit();
