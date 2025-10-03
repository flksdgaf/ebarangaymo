<?php
require_once 'dbconn.php';
session_start();

if (!($_SESSION['auth'] ?? false)) {
    header("HTTP/1.1 403 Forbidden");
    exit("Not authorized");
}

// allow missing request_for (no-payment flows may hide it client-side)
$requestFor = $_POST['request_for'] ?? 'myself';
if (strtolower($requestFor) === 'myself') {
    $requestFor = 'Myself';
} elseif (strtolower($requestFor) === 'other' || strtolower($requestFor) === 'others') {
    $requestFor = 'Others';
} else {
    // fallback to Myself rather than hard error (helps no-payment flows)
    $requestFor = 'Myself';
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
    // Added First Time Job Seeker config (minimal fields)
    'First Time Job Seeker' => ['full_name','age','civil_status','purok','claim_date'],
];

$map = [
    'Residency'    => ['table'=>'residency_requests',   'prefix'=>'RES-', 'amount'=>130],
    'Indigency'    => ['table'=>'indigency_requests',   'prefix'=>'IND-', 'amount'=>130],
    'Good Moral'   => ['table'=>'good_moral_requests',  'prefix'=>'GM-',  'amount'=>130],
    'Solo Parent'  => ['table'=>'solo_parent_requests', 'prefix'=>'SP-',  'amount'=>130],
    'Guardianship' => ['table'=>'guardianship_requests','prefix'=>'GUA-', 'amount'=>130],
    // Added First Time Job Seeker map
    'First Time Job Seeker' => ['table'=>'job_seeker_requests','prefix'=>'JS-','amount'=>0],
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

// --- Ensure purpose is NOT stored for excluded types (extra server-side safety) ---
$purposeExcluded = ['First Time Job Seeker']; // Guardianship now allowed to have a purpose

if (in_array($type, $purposeExcluded, true)) {
    // Remove 'purpose' from $fields if it slipped in
    $pidx = array_search('purpose', $fields, true);
    if ($pidx !== false) {
        array_splice($fields, $pidx, 1);
    }

    // Ignore any posted purpose — don't allow it to be saved
    if (isset($_POST['purpose'])) {
        unset($_POST['purpose']);
    }
    // Ensure $data has no purpose value
    $data['purpose'] = null;
} else {
    // For types that allow purpose, normalize it (trim) and prepare to validate later
    if (isset($_POST['purpose'])) {
        $data['purpose'] = trim($_POST['purpose']) !== '' ? trim($_POST['purpose']) : null;
    } else {
        // ensure a key exists so later code doesn't fail
        $data['purpose'] = $data['purpose'] ?? null;
    }
}


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
    $data['child_name'] = implode(', ', array_map('trim', $_POST['child_name'] ?? []));
    $data['child_age']  = implode(', ', array_map('trim', $_POST['child_age']  ?? []));
    $data['child_sex']  = implode(', ', array_map('trim', $_POST['child_sex']  ?? []));
    $data['years_solo_parent'] = $_POST['years_solo_parent'] ?? null;
    $fields = array_merge($fields, ['child_name','child_age','child_sex','years_solo_parent']);
}

if ($type === 'Guardianship' || $type === 'Solo Parent') {
    $ps = isset($_POST['parent_sex']) ? trim($_POST['parent_sex']) : null;
    if ($ps === '') $ps = null;
    $data['parent_sex'] = $ps;
    if (!in_array('parent_sex', $fields, true)) $fields[] = 'parent_sex';
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

// treat Indigency and First Time Job Seeker as no-payment
if (strtolower($type) === 'indigency' || strtolower($type) === 'first time job seeker') {
    $postPaymentMethod = null;
    $postPaymentAmount = null;
    $postPaymentStatus = 'Free of Charge';
} else {
    if ($postPaymentMethod === '' || $postPaymentMethod === null) $postPaymentMethod = 'Brgy Payment Device';
    if ($postPaymentAmount === '' || $postPaymentAmount === null) $postPaymentAmount = $defaultAmount;
    if ($postPaymentStatus === '' || $postPaymentStatus === null) $postPaymentStatus = 'Pending';
}

// detect claim_time or claim_part column for the target table (used for non-FTJS tables)
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

// --- generate transaction id (more robust parsing) ---
$res = $conn->query("SELECT transaction_id FROM `$table` ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows) {
    $last = $res->fetch_assoc()['transaction_id'] ?? '';
    $n = 1;
    if ($last) {
        $suffix = substr($last, strlen($prefix));
        if (ctype_digit($suffix)) {
            $n = intval($suffix) + 1;
        }
    }
} else {
    $n = 1;
}
$transactionId = sprintf("%s%07d", $prefix, $n);

// check whether this table supports created_at/updated_at/document_status etc. (we'll include when present)
function table_has_column($conn, $table, $col) {
    $safeTable = $conn->real_escape_string($table);
    $q = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '". $conn->real_escape_string($col) ."'");
    return ($q && $q->num_rows > 0);
}

// --- SPECIAL CASE for First Time Job Seeker: insert into job_seeker_requests with exact columns required ---
if ($type === 'First Time Job Seeker') {
    // Use exact table (already in $table). We'll detect whether named columns exist and insert accordingly.
    // Required core columns we want to set:
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $nowStr = $now->format('Y-m-d H:i:s');

    // ensure required fields exist in $data
    $fn = $data['full_name'] ?? null;
    if ($fn === null || trim($fn) === '') {
        header("HTTP/1.1 400 Bad Request");
        exit("Missing field full_name");
    }
    $ageVal = $data['age'] ?? null;
    if ($ageVal === null || $ageVal === '') {
        header("HTTP/1.1 400 Bad Request");
        exit("Missing field age");
    }
    $ageInt = is_numeric($ageVal) ? (int)$ageVal : 0;

    // choose document_status column if present
    $docCol = table_has_column($conn, $table, 'document_status') ? 'document_status' : (table_has_column($conn, $table, 'status') ? 'status' : null);
    // choose claim_time column name the table actually has (we already attempted to detect above)
    $ctCol = $claimTimeColumn ?? (table_has_column($conn, $table, 'claim_time') ? 'claim_time' : (table_has_column($conn, $table, 'claim_part') ? 'claim_part' : null));
    // created_at / updated_at detection
    $hasCreated = table_has_column($conn, $table, 'created_at');
    $hasUpdated = table_has_column($conn, $table, 'updated_at');

    // Build columns list (only include those that exist in the table)
    $columns = ['account_id','transaction_id','full_name','age','civil_status','purok','claim_date'];
    if ($ctCol) $columns[] = $ctCol;
    if ($docCol) $columns[] = $docCol;
    if ($hasCreated) $columns[] = 'created_at';
    if ($hasUpdated) $columns[] = 'updated_at';
    $columns[] = 'request_source';

    // Build placeholders & params
    $placeholders = [];
    $params = [];
    $paramTypes = [];

    // account_id
    $placeholders[] = '?';
    $params[] = $acct;
    $paramTypes[] = 'i';

    // transaction_id
    $placeholders[] = '?';
    $params[] = $transactionId;
    $paramTypes[] = 's';

    // full_name
    $placeholders[] = '?';
    $params[] = $fn;
    $paramTypes[] = 's';

    // age
    $placeholders[] = '?';
    $params[] = $ageInt;
    $paramTypes[] = 'i';

    // civil_status
    $cs = $data['civil_status'] ?? null;
    $placeholders[] = '?';
    $params[] = $cs;
    $paramTypes[] = 's';

    // purok
    $purok = $data['purok'] ?? null;
    $placeholders[] = '?';
    $params[] = $purok;
    $paramTypes[] = 's';

    // claim_date
    $placeholders[] = '?';
    $params[] = $claimDate;
    $paramTypes[] = 's';

    // claim_time / claim_part if column exists
    if ($ctCol) {
        $placeholders[] = '?';
        $params[] = $claimPart ?? 'Morning';
        $paramTypes[] = 's';
    }

    // document_status column (explicit default)
    if ($docCol) {
        $placeholders[] = '?';
        $params[] = 'For Verification';
        $paramTypes[] = 's';
    }

    // created_at / updated_at
    if ($hasCreated) {
        $placeholders[] = '?';
        $params[] = $nowStr;
        $paramTypes[] = 's';
    }
    if ($hasUpdated) {
        $placeholders[] = '?';
        $params[] = $nowStr;
        $paramTypes[] = 's';
    }

    // request_source
    $placeholders[] = '?';
    $params[] = $requestSource;
    $paramTypes[] = 's';

    // Build SQL
    $sql = sprintf(
        "INSERT INTO `%s` (%s) VALUES (%s)",
        $conn->real_escape_string($table),
        implode(",", array_map(function($c){ return "`$c`"; }, $columns)),
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
    $conn->close();

    header("Location: ../userPanel.php?page=serviceCertification&tid=" . urlencode($transactionId));
    exit;
}

// --- Default insertion path for all other certificate types (unchanged behaviour with improvements) ---

// Build base columns
$columns = array_merge(
    ['account_id','transaction_id'],
    $fields,
    ['payment_method','amount','payment_status','request_for','authorization_letter','request_source']
);

// Extra protection: ensure excluded types do not end up with 'purpose' in $columns
if (in_array($type, $purposeExcluded, true)) {
    $colIdx = array_search('purpose', $columns, true);
    if ($colIdx !== false) array_splice($columns, $colIdx, 1);
}


// If table supports created_at/updated_at and they are not already in $columns, add created_at/updated_at
if (table_has_column($conn, $table, 'created_at') && !in_array('created_at', $columns, true)) $columns[] = 'created_at';
if (table_has_column($conn, $table, 'updated_at') && !in_array('updated_at', $columns, true)) $columns[] = 'updated_at';

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

// always insert payment_status (force default earlier if missing)
$placeholders[] = '?';
$params[]       = $postPaymentStatus;
$paramTypes[]   = 's';

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

$placeholders[] = '?';
$params[] = $requestSource;
$paramTypes[] = 's';

// Append created_at/updated_at values if present
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$nowStr = $now->format('Y-m-d H:i:s');
if (in_array('created_at', $columns, true) && !in_array('created_at', $fields, true)) {
    // Only append if we pushed created_at into $columns earlier
    $placeholders[] = '?';
    $params[] = $nowStr;
    $paramTypes[] = 's';
}
if (in_array('updated_at', $columns, true) && !in_array('updated_at', $fields, true)) {
    $placeholders[] = '?';
    $params[] = $nowStr;
    $paramTypes[] = 's';
}

$sql = sprintf(
    "INSERT INTO `%s` (%s) VALUES (%s)",
    $conn->real_escape_string($table),
    implode(",", array_map(function($c){ return "`$c`"; }, $columns)),
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
$conn->close();

header("Location: ../userPanel.php?page=serviceCertification&tid=" . urlencode($transactionId));
exit;