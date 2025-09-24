<?php
// functions/serviceCertification_submit.php
require_once 'dbconn.php';
session_start();

// 1) Auth check
if (!($_SESSION['auth'] ?? false)) {
    header("HTTP/1.1 403 Forbidden");
    exit("Not authorized");
}

// 2) Collect the “for” and possible file
$requestFor = $_POST['request_for'] ?? '';
if (strtolower($requestFor) === 'myself') {
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
    if (!is_dir($uploaddir)) {
        mkdir($uploaddir, 0755, true);
    }
    if (!empty($_FILES['authorization_letter']['name'])) {
        $ext = pathinfo($_FILES['authorization_letter']['name'], PATHINFO_EXTENSION);
        $authFilename = session_id() . '_' . time() . ($ext ? '.' . $ext : '');
        if (!move_uploaded_file($_FILES['authorization_letter']['tmp_name'], $uploaddir . $authFilename)) {
            exit("Upload failed");
        }
    }
}

// 3) Server-side configuration of fields per certificate type
$certConfigs = [
    'Residency'    => ['full_name','age','civil_status','purok','residing_years','claim_date','purpose'],
    'Indigency'    => ['full_name','age','civil_status','purok','claim_date','purpose'],
    'Good Moral'   => ['full_name','age','civil_status','purok','claim_date','purpose'],
    'Solo Parent'  => ['full_name','age','civil_status','purok','claim_date','purpose'],
    'Guardianship' => ['full_name','age','civil_status','purok','claim_date','purpose'],
];

// 4) Lookup table/prefix/fee
$map = [
    'Residency'    => ['table'=>'residency_requests',   'prefix'=>'RES-', 'amount'=>130],
    'Indigency'    => ['table'=>'indigency_requests',   'prefix'=>'IND-', 'amount'=>130],
    'Good Moral'   => ['table'=>'good_moral_requests',  'prefix'=>'GM-',  'amount'=>130],
    'Solo Parent'  => ['table'=>'solo_parent_requests', 'prefix'=>'SP-',  'amount'=>130],
    'Guardianship' => ['table'=>'guardianship_requests','prefix'=>'GUA-', 'amount'=>130],
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

// --- CLAIM inputs: prefer separate hidden fields; support legacy claim_slot ---
$postedClaimDate = isset($_POST['claim_date']) ? trim($_POST['claim_date']) : null;
$postedClaimTime = isset($_POST['claim_time']) ? trim($_POST['claim_time']) : null;
$postedClaimSlot = isset($_POST['claim_slot']) ? trim($_POST['claim_slot']) : null;

// Helper to validate date & part
function is_valid_date($d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}
$allowedParts = ['Morning', 'Afternoon'];

$claimDate = null;
$claimPart = null;

// 1) If both separate fields present, prefer them
if (!empty($postedClaimDate) && !empty($postedClaimTime)) {
    if (is_valid_date($postedClaimDate) && in_array($postedClaimTime, $allowedParts, true)) {
        $claimDate = $postedClaimDate;
        $claimPart = $postedClaimTime;
    } else {
        header("HTTP/1.1 400 Bad Request");
        exit("Invalid preferred claim date or time part.");
    }
} else {
    // 2) legacy claim_slot "YYYY-MM-DD|Morning"
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
        // 3) older frontend posted combined in claim_date
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
        // 4) date-only -> default to Morning
        $claimDate = $postedClaimDate;
        $claimPart = 'Morning';
    } else {
        // not provided -> will be checked below as required
    }
}

// Ensure claim_date is present
if ($claimDate === null) {
    header("HTTP/1.1 400 Bad Request");
    exit("Preferred claim date is required.");
}

// --- Compute allowed claim dates (server-side) matching serviceCertification.php behaviour ---
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
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid preferred claim date. Available dates: " . implode(', ', $allowedDates));
}

// 5) Pull values out of $_POST in the order of $fields
// Note: we specially handle 'claim_date' (we don't require $_POST['claim_date'] because legacy may have claim_slot)
foreach ($fields as $field) {
    if ($field === 'claim_date') {
        // we'll use the parsed $claimDate (validated) instead of relying on raw $_POST
        $data['claim_date'] = $claimDate;
        continue;
    }
    // residing_years is optional in some flows — accept NULL if missing
    if (!isset($_POST[$field]) && $field !== 'residing_years') {
        header("HTTP/1.1 400 Bad Request");
        exit("Missing field $field");
    }
    $data[$field] = $_POST[$field] ?? null;
}

// 5.a) Special handling for multi-child fields
if ($type === 'Guardianship') {
    $childs = array_map('trim', $_POST['child_name'] ?? []);
    $data['child_name'] = $childs ? implode(', ', $childs) : null;
    if (!in_array('child_name', $fields, true)) {
        $fields[] = 'child_name';
    }
}

if ($type === 'Solo Parent') {
    $data['child_name'] = implode(', ', array_map('trim', $_POST['child_name'] ?? []));
    $data['child_age']  = implode(', ', array_map('trim', $_POST['child_age']  ?? []));
    $data['child_sex']  = implode(', ', array_map('trim', $_POST['child_sex']  ?? []));
    $data['years_solo_parent'] = $_POST['years_solo_parent'] ?? null;
    $fields = array_merge($fields, ['child_name','child_age','child_sex','years_solo_parent']);
}

// --- NEW: collect parent_sex for Solo Parent and Guardianship (optional) ---
// Accept values like 'Male', 'Female', or other strings provided by the dropdown.
// If empty, treat as NULL so DB column can be NULL.
if ($type === 'Guardianship' || $type === 'Solo Parent') {
    $ps = isset($_POST['parent_sex']) ? trim($_POST['parent_sex']) : null;
    if ($ps === '') $ps = null;
    $data['parent_sex'] = $ps;
    if (!in_array('parent_sex', $fields, true)) {
        // add parent_sex so it will be included in the INSERT columns
        $fields[] = 'parent_sex';
    }
}

// --- NEW: For Good Moral: accept parent_sex and parent_address ---
// parent_sex will be stored in the table column named `sex` (as requested).
// parent_address is optional and stored in `parent_address` column (if your table contains it).
if ($type === 'Good Moral') {
    // parent sex (map into the 'sex' column)
    $ps = isset($_POST['parent_sex']) ? trim($_POST['parent_sex']) : null;
    if ($ps === '') $ps = null;
    $data['sex'] = $ps;
    if (!in_array('sex', $fields, true)) {
        $fields[] = 'sex';
    }

    // parent address (optional)
    $pa = isset($_POST['parent_address']) ? trim($_POST['parent_address']) : null;
    if ($pa === '') $pa = null;
    $data['address'] = $pa;
    if (!in_array('address', $fields, true)) {
        $fields[] = 'address';
    }
}

// 6) Payment-related inputs from the form (these may be empty or absent for Indigency)
$postPaymentMethod = isset($_POST['paymentMethod']) ? trim($_POST['paymentMethod']) : null;
$postPaymentAmount = isset($_POST['paymentAmount']) ? trim($_POST['paymentAmount']) : null;
$postPaymentStatus = isset($_POST['paymentStatus']) ? trim($_POST['paymentStatus']) : null;

// Align behavior with serviceCertification.php:
// - For Indigency: payment_method & amount => NULL; payment_status => POSTED value if present, otherwise "Free of Charge"
// - For non-Indigency: ensure non-null defaults for method/amount/status so DB columns that disallow NULL are satisfied
if (strtolower($type) === 'indigency') {
    $postPaymentMethod = null;
    $postPaymentAmount = null;
    // FORCE the payment_status to "Free of Charge" for Indigency so it is never inserted as empty or NULL.
    // This ensures the summary and DB will consistently show "Free of Charge".
    $postPaymentStatus = 'Free of Charge';
} else {
    if ($postPaymentMethod === '' || $postPaymentMethod === null) {
        $postPaymentMethod = 'Brgy Payment Device';
    }
    if ($postPaymentAmount === '' || $postPaymentAmount === null) {
        $postPaymentAmount = $defaultAmount;
    }
    if ($postPaymentStatus === '' || $postPaymentStatus === null) {
        $postPaymentStatus = 'Pending';
    }
}

// --- Detect claim_time vs legacy claim_part column for this certificate table ---
// If present, append to $fields so it is inserted as its own column.
$claimTimeColumn = null;
$check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'claim_time'");
if ($check && $check->num_rows > 0) {
    $claimTimeColumn = 'claim_time';
} else {
    $check2 = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'claim_part'");
    if ($check2 && $check2->num_rows > 0) {
        $claimTimeColumn = 'claim_part'; // legacy
    }
}
if ($claimTimeColumn) {
    // add to fields and set value from parsed claim part
    if (!in_array($claimTimeColumn, $fields, true)) {
        // place it right after claim_date if possible (not strictly necessary)
        $pos = array_search('claim_date', $fields, true);
        if ($pos === false) {
            $fields[] = $claimTimeColumn;
        } else {
            array_splice($fields, $pos + 1, 0, $claimTimeColumn);
        }
    }
    $data[$claimTimeColumn] = $claimPart;
}

// 7) Always-present columns — include payment_status column
$requestSource = 'Online';
$columns = array_merge(
    ['account_id','transaction_id'],
    $fields,
    ['payment_method','amount','payment_status','request_for','authorization_letter','request_source']
);

// 8) Generate transaction_id (increment last for this table)
$res = $conn->query("SELECT transaction_id FROM `$table` ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows) {
    $last = $res->fetch_assoc()['transaction_id'];
    $n    = intval(substr($last, strlen($prefix))) + 1;
} else {
    $n = 1;
}
$transactionId = sprintf("%s%07d", $prefix, $n);

// 9) Build placeholders & params dynamically so we can insert SQL NULL for PHP nulls
$placeholders = [];
$params = [];       // values to bind
$paramTypes = [];   // types for bind_param in same order as $params

// account_id (int)
$placeholders[] = '?';
$params[] = $acct;
$paramTypes[] = 'i';

// transaction_id (string)
$placeholders[] = '?';
$params[] = $transactionId;
$paramTypes[] = 's';

// now the certificate fields (from $fields). For each, if null -> use NULL literal, else add ? and push param
foreach ($fields as $f) {
    $val = $data[$f] ?? null;
    if ($val === null || $val === '') {
        // insert NULL literal
        $placeholders[] = 'NULL';
    } else {
        $placeholders[] = '?';
        // decide type: age -> int if numeric, otherwise string
        if ($f === 'age' && is_numeric($val)) {
            $params[] = (int)$val;
            $paramTypes[] = 'i';
        } else {
            $params[] = $val;
            $paramTypes[] = 's';
        }
    }
}

// payment_method
if ($postPaymentMethod === null || $postPaymentMethod === '') {
    $placeholders[] = 'NULL';
} else {
    $placeholders[] = '?';
    $params[] = $postPaymentMethod;
    $paramTypes[] = 's';
}

// amount
if ($postPaymentAmount === null || $postPaymentAmount === '') {
    $placeholders[] = 'NULL';
} else {
    $placeholders[] = '?';
    // amount could be integer/float
    if (is_numeric(str_replace(',', '', $postPaymentAmount))) {
        $params[] = (float) str_replace(',', '', $postPaymentAmount);
        $paramTypes[] = 'd';
    } else {
        $params[] = $postPaymentAmount;
        $paramTypes[] = 's';
    }
}

// payment_status
if ($postPaymentStatus === null || $postPaymentStatus === '') {
    $placeholders[] = 'NULL';
} else {
    $placeholders[] = '?';
    $params[] = $postPaymentStatus;
    $paramTypes[] = 's';
}

// request_for
$placeholders[] = '?';
$params[] = $requestFor;
$paramTypes[] = 's';

// authorization_letter (filename) or NULL
if ($authFilename === null || $authFilename === '') {
    $placeholders[] = 'NULL';
} else {
    $placeholders[] = '?';
    $params[] = $authFilename;
    $paramTypes[] = 's';
}

// request_source
$placeholders[] = '?';
$params[] = $requestSource;
$paramTypes[] = 's';

// 10) Build SQL
$sql = sprintf(
    "INSERT INTO `%s` (%s) VALUES (%s)",
    $table,
    implode(",", $columns),
    implode(",", $placeholders)
);

$stmt = $conn->prepare($sql);
if (!$stmt) {
    exit("Prepare failed: " . $conn->error);
}

// 11) Bind params if there are any (there should be at least account_id and transaction_id)
if (!empty($params)) {
    $types = implode('', $paramTypes);
    // bind_param requires variables, so convert params to references
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

if (!$stmt->execute()) {
    exit("Insert failed: " . $stmt->error);
}

$stmt->close();
$conn->close();

// 12) Redirect back to the receipt/view page
header("Location: ../userPanel.php?page=serviceCertification&tid=" . urlencode($transactionId));
exit;
