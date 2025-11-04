<?php
require_once 'dbconn.php';
require_once 'gcash_handler.php';

session_start();

if (!($_SESSION['auth'] ?? false)) {
    header("HTTP/1.1 403 Forbidden");
    exit("Not authorized");
}

$requestFor = $_POST['request_for'] ?? '';
if (strtolower($_POST['certification_type'] ?? '') === 'first time job seeker') {
    $requestFor = 'Myself';
} elseif (strtolower($requestFor) === 'myself') {
    $requestFor = 'Myself';
} elseif (strtolower($requestFor) === 'other') {
    $requestFor = 'Others';
} else {
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid request_for");
}

$authFilename = null;
if ($requestFor === 'Others') {
    $uploaddir = __DIR__ . '/../authorizations/';
    if (!is_dir($uploaddir)) mkdir($uploaddir, 0755, true);
    if (!empty($_FILES['authorization_letter']['name'])) {
        $ext = pathinfo($_FILES['authorization_letter']['name'], PATHINFO_EXTENSION);
        $authFilename = session_id() . '_' . time() . ($ext ? '.' . $ext : '');
        if (!move_uploaded_file($_FILES['authorization_letter']['tmp_name'], $uploaddir . $authFilename)) {
            exit("Upload failed");
        }
    }
}

$certConfigs = [
    'Residency'    => ['full_name','age','civil_status','purok','residing_years','claim_date','purpose'],
    'Indigency'    => ['full_name','age','civil_status','purok','claim_date','purpose'],
    'Good Moral'   => ['full_name','age','civil_status','purok','claim_date','purpose'],
    'Solo Parent'  => ['full_name','age','civil_status','purok','claim_date','purpose'],
    'Guardianship' => ['full_name','age','civil_status','purok','claim_date','purpose'],
    'First Time Job Seeker' => ['full_name','age','civil_status','purok','claim_date'],  
];

$map = [
    'Residency'    => ['table'=>'residency_requests',   'prefix'=>'RES-', 'amount'=>130],
    'Indigency'    => ['table'=>'indigency_requests',   'prefix'=>'IND-', 'amount'=>130],
    'Good Moral'   => ['table'=>'good_moral_requests',  'prefix'=>'CGM-',  'amount'=>130], // GM
    'Solo Parent'  => ['table'=>'solo_parent_requests', 'prefix'=>'CSP-',  'amount'=>130], // SP
    'Guardianship' => ['table'=>'guardianship_requests','prefix'=>'GUA-', 'amount'=>130],
    'First Time Job Seeker' => ['table'=>'job_seeker_requests', 'prefix'=>'FJS-', 'amount'=>130],  // FTJS
];

$type = $_POST['certification_type'] ?? '';
if (!isset($map[$type], $certConfigs[$type])) {
    header("HTTP/1.1 400 Bad Request");
    exit("Unknown type");
}

$table  = $map[$type]['table'];
$prefix = $map[$type]['prefix'];
$defaultAmount = $map[$type]['amount'];

$acct   = (int) ($_SESSION['loggedInUserID'] ?? 0);
$fields = $certConfigs[$type];
$data   = [];

$postedClaimDate = isset($_POST['claim_date']) ? trim($_POST['claim_date']) : null;
$postedClaimTime = isset($_POST['claim_time']) ? trim($_POST['claim_time']) : null;
$postedClaimSlot = isset($_POST['claim_slot']) ? trim($_POST['claim_slot']) : null;

function is_valid_date($d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}
$allowedParts = ['Morning', 'Afternoon'];

$claimDate = null;
$claimPart = null;

if (!empty($postedClaimDate) && !empty($postedClaimTime)) {
    if (is_valid_date($postedClaimDate) && in_array($postedClaimTime, $allowedParts, true)) {
        $claimDate = $postedClaimDate;
        $claimPart = $postedClaimTime;
    } else {
        header("HTTP/1.1 400 Bad Request");
        exit("Invalid preferred claim date or time part.");
    }
} else {
    if (!empty($postedClaimSlot) && strpos($postedClaimSlot, '|') !== false) {
        [$d, $p] = explode('|', $postedClaimSlot, 2);
        $d = trim($d); $p = trim($p);
        if (is_valid_date($d) && in_array($p, $allowedParts, true)) {
            $claimDate = $d;
            $claimPart = $p;
        } else {
            header("HTTP/1.1 400 Bad Request");
            exit("Invalid preferred claim format.");
        }
    } elseif (!empty($postedClaimDate) && strpos($postedClaimDate, '|') !== false) {
        [$d, $p] = explode('|', $postedClaimDate, 2);
        $d = trim($d); $p = trim($p);
        if (is_valid_date($d) && in_array($p, $allowedParts, true)) {
            $claimDate = $d;
            $claimPart = $p;
        } else {
            header("HTTP/1.1 400 Bad Request");
            exit("Invalid preferred claim format.");
        }
    } elseif (!empty($postedClaimDate) && is_valid_date($postedClaimDate)) {
        $claimDate = $postedClaimDate;
        $claimPart = 'Morning';
    }
}

if ($claimDate === null) {
    header("HTTP/1.1 400 Bad Request");
    exit("Preferred claim date is required.");
}

$today = new DateTime('now', new DateTimeZone('Asia/Manila'));
$weekdayNow = (int)$today->format('N');

if ($weekdayNow === 6) {
    $start = (clone $today)->modify('+2 days');
} elseif ($weekdayNow === 7) {
    $start = (clone $today)->modify('+1 day');
} else {
    $start = (clone $today)->modify('+1 day');
}

$allowedDates = [];
$cursor = clone $start;
while (count($allowedDates) < 3) {
    $dow = (int)$cursor->format('N');
    if ($dow <= 5) $allowedDates[] = $cursor->format('Y-m-d');
    $cursor->modify('+1 day');
}

if (!in_array($claimDate, $allowedDates, true)) {
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid preferred claim date. Available dates: " . implode(', ', $allowedDates));
}

foreach ($fields as $field) {
    if ($field === 'claim_date') {
        $data['claim_date'] = $claimDate;
        continue;
    }
    
    // Special handling for purpose field - check hidden input
    if ($field === 'purpose') {
        $purposeValue = '';
        if (isset($_POST['purpose']) && trim($_POST['purpose']) !== '') {
            $purposeValue = trim($_POST['purpose']);
        } elseif (isset($_POST['purpose_other']) && trim($_POST['purpose_other']) !== '') {
            $purposeValue = trim($_POST['purpose_other']);
        } elseif (isset($_POST['purpose_select']) && trim($_POST['purpose_select']) !== '') {
            $purposeValue = trim($_POST['purpose_select']);
        }
        $data['purpose'] = $purposeValue ?: null;
        continue;
    }
    
    if (!isset($_POST[$field]) && $field !== 'residing_years') {
        header("HTTP/1.1 400 Bad Request");
        exit("Missing field $field");
    }
    $data[$field] = $_POST[$field] ?? null;
}

if ($type === 'Guardianship') {
    $childs = array_map('trim', $_POST['child_name'] ?? []);
    $data['child_name'] = $childs ? implode(', ', $childs) : null;
    if (!in_array('child_name', $fields, true)) $fields[] = 'child_name';
}

if ($type === 'Solo Parent') {
    $childrenArray = [];
    $childNames = $_POST['child_name'] ?? [];
    $childBirthdates = $_POST['child_birthdate'] ?? [];
    $childSexes = $_POST['child_sex'] ?? [];
    
    // Helper function to calculate age from birthdate
    function calculateAgeFromBirthdate($birthdate) {
        if (empty($birthdate)) return 0;
        
        $birth = new DateTime($birthdate);
        $today = new DateTime('now', new DateTimeZone('Asia/Manila'));
        
        $years = $today->diff($birth)->y;
        $months = $today->diff($birth)->m;
        $days = $today->diff($birth)->days;
        
        // Return years if >= 1 year old
        if ($years > 0) {
            return $years;
        }
        
        // Return fractional year for infants (months/12 or weeks/52)
        if ($months > 0) {
            return round($months / 12, 2);
        }
        
        // For newborns less than a month, use weeks
        $weeks = floor($days / 7);
        if ($weeks > 0) {
            return round($weeks / 52, 2);
        }
        
        // For very new babies, use days
        return round($days / 365, 2);
    }
    
    for ($i = 0; $i < count($childNames); $i++) {
        if (!empty(trim($childNames[$i]))) {
            $birthdate = isset($childBirthdates[$i]) ? trim($childBirthdates[$i]) : '';
            $age = $birthdate ? calculateAgeFromBirthdate($birthdate) : 0;
            
            $childrenArray[] = [
                'name' => trim($childNames[$i]),
                'birthdate' => $birthdate,
                'age' => $age,  // Calculated age stored for backward compatibility
                'sex' => isset($childSexes[$i]) ? trim($childSexes[$i]) : ''
            ];
        }
    }
    
    $data['children_data'] = json_encode($childrenArray);
    $data['years_solo_parent'] = $_POST['years_solo_parent'] ?? null;
    $fields = array_merge($fields, ['children_data', 'years_solo_parent']);

    // Remove old field names from fields array
    $fields = array_diff($fields, ['child_name', 'child_age', 'child_sex', 'child_birthdate']);

    $ps = isset($_POST['parent_sex']) ? trim($_POST['parent_sex']) : null;
    if ($ps === '') $ps = null;
    $data['sex'] = $ps;
    if (!in_array('sex', $fields, true)) $fields[] = 'sex';
}

if ($type === 'Good Moral') {
    $ps = isset($_POST['parent_sex']) ? trim($_POST['parent_sex']) : null;
    if ($ps === '') $ps = null;
    $data['sex'] = $ps;
    if (!in_array('sex', $fields, true)) $fields[] = 'sex';

    $pa = isset($_POST['parent_address']) ? trim($_POST['parent_address']) : null;
    if ($pa === '') $pa = null;
    $data['address'] = $pa;
    if (!in_array('address', $fields, true)) $fields[] = 'address';
}

$postPaymentMethod = isset($_POST['paymentMethod']) ? trim($_POST['paymentMethod']) : null;
$postPaymentAmount = isset($_POST['paymentAmount']) ? trim($_POST['paymentAmount']) : null;
$postPaymentStatus = isset($_POST['paymentStatus']) ? trim($_POST['paymentStatus']) : null;

if (strtolower($type) === 'indigency' || strtolower($type) === 'first time job seeker') {
    $postPaymentMethod = null;
    $postPaymentAmount = null;
    $postPaymentStatus = 'Free of Charge';
} else {
    if ($postPaymentMethod === '' || $postPaymentMethod === null) $postPaymentMethod = 'Brgy Payment Device';
    if ($postPaymentAmount === '' || $postPaymentAmount === null) $postPaymentAmount = $defaultAmount;
    if ($postPaymentStatus === '' || $postPaymentStatus === null) $postPaymentStatus = 'Pending';
}

$claimTimeColumn = null;
$check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'claim_time'");
if ($check && $check->num_rows > 0) {
    $claimTimeColumn = 'claim_time';
} else {
    $check2 = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'claim_part'");
    if ($check2 && $check2->num_rows > 0) $claimTimeColumn = 'claim_part';
}
if ($claimTimeColumn) {
    if (!in_array($claimTimeColumn, $fields, true)) {
        $pos = array_search('claim_date', $fields, true);
        if ($pos === false) {
            $fields[] = $claimTimeColumn;
        } else {
            array_splice($fields, $pos + 1, 0, $claimTimeColumn);
        }
    }
    $data[$claimTimeColumn] = $claimPart;
}

$requestSource = 'Online';

// Build columns array based on certificate type
if (strtolower($type) === 'first time job seeker') {
    // First Time Job Seeker: no payment fields, no request_for, no authorization_letter
    $columns = array_merge(
        ['account_id','transaction_id'],
        $fields,
        ['request_source']
    );
} elseif (strtolower($type) === 'indigency') {
    // Indigency: no payment fields, but has request_for and authorization_letter
    $columns = array_merge(
        ['account_id','transaction_id'],
        $fields,
        ['request_for','authorization_letter','request_source']
    );
} else {
    // All other certificates: include payment fields, request_for, and authorization_letter
    $columns = array_merge(
        ['account_id','transaction_id'],
        $fields,
        ['payment_method','amount','payment_status','request_for','authorization_letter','request_source']
    );
}

$res = $conn->query("SELECT transaction_id FROM `$table` ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows) {
    $last = $res->fetch_assoc()['transaction_id'];
    $n    = intval(substr($last, strlen($prefix))) + 1;
} else {
    $n = 1;
}
$transactionId = sprintf("%s%07d", $prefix, $n);

$placeholders = [];
$params = [];
$paramTypes = [];

$placeholders[] = '?';
$params[] = $acct;
$paramTypes[] = 'i';

$placeholders[] = '?';
$params[] = $transactionId;
$paramTypes[] = 's';

foreach ($fields as $f) {
    $val = $data[$f] ?? null;
    if ($val === null || $val === '') {
        $placeholders[] = 'NULL';
    } else {
        $placeholders[] = '?';
        if ($f === 'age' && is_numeric($val)) {
            $params[] = (int)$val;
            $paramTypes[] = 'i';
        } else {
            $params[] = $val;
            $paramTypes[] = 's';
        }
    }
}

// Only add payment fields for non-free certificates
if (!(strtolower($type) === 'indigency' || strtolower($type) === 'first time job seeker')) {
    if ($postPaymentMethod === null || $postPaymentMethod === '') {
        $placeholders[] = 'NULL';
    } else {
        $placeholders[] = '?';
        $params[] = $postPaymentMethod;
        $paramTypes[] = 's';
    }

    if ($postPaymentAmount === null || $postPaymentAmount === '') {
        $placeholders[] = 'NULL';
    } else {
        $placeholders[] = '?';
        if (is_numeric(str_replace(',', '', $postPaymentAmount))) {
            $params[] = (float) str_replace(',', '', $postPaymentAmount);
            $paramTypes[] = 'd';
        } else {
            $params[] = $postPaymentAmount;
            $paramTypes[] = 's';
        }
    }

    $placeholders[] = '?';
    $params[]       = $postPaymentStatus;
    $paramTypes[]   = 's';
}

// request_for field (for all types)
if (strtolower($type) !== 'first time job seeker') {
    $placeholders[] = '?';
    $params[] = $requestFor;
    $paramTypes[] = 's';

    if ($authFilename === null || $authFilename === '') {
        $placeholders[] = 'NULL';
    } else {
        $placeholders[] = '?';
        $params[] = $authFilename;
        $paramTypes[] = 's';
    }
}

$placeholders[] = '?';
$params[] = $requestSource;
$paramTypes[] = 's';

$sql = sprintf(
    "INSERT INTO `%s` (%s) VALUES (%s)",
    $table,
    implode(",", $columns),
    implode(",", $placeholders)
);

$stmt = $conn->prepare($sql);
if (!$stmt) exit("Prepare failed: " . $conn->error);

if (!empty($params)) {
    $types = implode('', $paramTypes);
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

if (!$stmt->execute()) exit("Insert failed: " . $stmt->error);

$stmt->close();

// --- GCash Payment Integration (only for paid services) ---
// Check if this is a paid service AND GCash was selected
$isPaidService = !(strtolower($type) === 'indigency' || strtolower($type) === 'first time job seeker');
$isGCashPayment = ($postPaymentMethod === 'GCash');

if ($isPaidService && $isGCashPayment) {
    try {
        // Determine the amount to charge
        $amountToCharge = floatval($postPaymentAmount);
        if ($amountToCharge < 20) {
            $amountToCharge = 130; // Default certification fee
        }
        
        $gcashResult = createGCashSource($transactionId, $amountToCharge);
        
        if ($gcashResult['success'] && isset($gcashResult['checkout_url'])) {
            // Store transaction info in session for security
            $_SESSION['pending_gcash_transaction'] = $transactionId;
            $_SESSION['gcash_cert_type'] = $type;
            
            // Close connection before redirect
            $conn->close();
            
            // Redirect to GCash checkout
            header("Location: " . $gcashResult['checkout_url']);
            exit();
        } else {
            // GCash initialization failed
            $errorMsg = $gcashResult['error'] ?? 'Failed to initialize GCash payment.';
            $_SESSION['svc_error'] = $errorMsg . ' Please try another payment method.';
            
            // Update payment status to failed
            $stmtFail = $conn->prepare("UPDATE `$table` SET payment_status = 'failed' WHERE transaction_id = ?");
            $stmtFail->bind_param("s", $transactionId);
            $stmtFail->execute();
            $stmtFail->close();
            
            $conn->close();
            header("Location: ../serviceCertification.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("GCash Integration Error for {$type}: " . $e->getMessage());
        $_SESSION['svc_error'] = 'Payment system error. Please try another payment method.';
        $conn->close();
        header("Location: ../serviceCertification.php");
        exit();
    }
}

// For free services or non-GCash payments, proceed normally
$conn->close();

header("Location: ../userPanel.php?page=serviceCertification&tid=" . urlencode($transactionId));
exit;