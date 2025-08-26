<?php
// functions/serviceCertification_submit.php
require_once 'dbconn.php';
session_start();

// ---------- 1) Auth ----------
if (!($_SESSION['auth'] ?? false)) {
    header("HTTP/1.1 403 Forbidden");
    exit("Not authorized");
}

// ---------- 2) Normalize request_for ----------
$requestFor = $_POST['request_for'] ?? '';
if (strtolower($requestFor) === 'myself') {
    $requestFor = 'Myself';
} elseif (strtolower($requestFor) === 'other') {
    $requestFor = 'Others';
} else {
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid request_for");
}

// ---------- 3) Handle Authorization Letter upload for "Others" ----------
$authFilename = null;
if ($requestFor === 'Others') {
    if (!empty($_FILES['authorization_letter']['name'])) {
        $uploaddir = __DIR__ . '/../authorizations/';
        if (!is_dir($uploaddir)) mkdir($uploaddir, 0755, true);
        $ext = pathinfo($_FILES['authorization_letter']['name'], PATHINFO_EXTENSION);
        $authFilename = session_id() . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES['authorization_letter']['tmp_name'], $uploaddir . $authFilename)) {
            header("HTTP/1.1 500 Internal Server Error");
            exit("Upload failed");
        }
    }
}

// ---------- 4) Server-side field config ----------
$certConfigs = [
    'Residency'    => ['full_name','age','civil_status','purok','residing_years','claim_date','purpose'],
    'Indigency'    => ['full_name','age','civil_status','purok','claim_date','purpose'],
    'Good Moral'   => ['full_name','age','civil_status','purok','claim_date','purpose'],
    'Solo Parent'  => ['full_name','age','civil_status','purok','child_name','child_age','child_sex','years_solo_parent','claim_date','purpose'],
    'Guardianship' => ['full_name','age','civil_status','purok','child_name','claim_date','purpose'],
];

// ---------- 5) Table map (Indigency amount = 0) ----------
$map = [
    'Residency'    => ['table'=>'residency_requests',   'prefix'=>'RES-', 'amount'=>130],
    'Indigency'    => ['table'=>'indigency_requests',   'prefix'=>'IND-', 'amount'=>0],
    'Good Moral'   => ['table'=>'good_moral_requests',  'prefix'=>'GM-',  'amount'=>130],
    'Solo Parent'  => ['table'=>'solo_parent_requests', 'prefix'=>'SP-',  'amount'=>130],
    'Guardianship' => ['table'=>'guardianship_requests','prefix'=>'GUA-', 'amount'=>130],
];

// ---------- 6) Normalize certification_type (case-insensitive) ----------
$providedType = trim($_POST['certification_type'] ?? '');
$foundType = null;
foreach (array_keys($map) as $k) {
    if (strcasecmp($k, $providedType) === 0) {
        $foundType = $k;
        break;
    }
}
if ($foundType === null) {
    header("HTTP/1.1 400 Bad Request");
    exit("Unknown certification type");
}
$type = $foundType;

// ---------- 7) Basic variables ----------
if (!isset($certConfigs[$type])) {
    header("HTTP/1.1 400 Bad Request");
    exit("No configuration for type");
}
$table  = $map[$type]['table'];
$prefix = $map[$type]['prefix'];
$amount = $map[$type]['amount'];
$acct   = (int)($_SESSION['loggedInUserID'] ?? 0);
$fields = $certConfigs[$type];
$data   = [];

// ---------- 8) Collect posted values (do not fail on missing optional fields) ----------
foreach ($fields as $field) {
    if (isset($_POST[$field])) {
        $data[$field] = is_array($_POST[$field]) ? $_POST[$field] : trim($_POST[$field]);
    } else {
        $data[$field] = null;
    }
}

// ---------- 9) Flatten child arrays ----------
if ($type === 'Guardianship') {
    $childs = $_POST['child_name'] ?? [];
    if (is_array($childs)) {
        $childs = array_map('trim', $childs);
        $data['child_name'] = $childs ? implode(', ', array_filter($childs, fn($v) => $v !== '')) : null;
    } else {
        $data['child_name'] = trim((string)$childs) ?: null;
    }
}
if ($type === 'Solo Parent') {
    $childNames = $_POST['child_name'] ?? [];
    $childAges  = $_POST['child_age']  ?? [];
    $childSexes = $_POST['child_sex']  ?? [];

    if (is_array($childNames)) {
        $childNames = array_map('trim', $childNames);
        $data['child_name'] = $childNames ? implode(', ', array_filter($childNames, fn($v) => $v !== '')) : null;
    } else {
        $data['child_name'] = trim((string)$childNames) ?: null;
    }

    if (is_array($childAges)) {
        $childAges = array_map('trim', $childAges);
        $data['child_age'] = $childAges ? implode(', ', array_filter($childAges, fn($v) => $v !== '')) : null;
    } else {
        $data['child_age'] = trim((string)$childAges) ?: null;
    }

    if (is_array($childSexes)) {
        $childSexes = array_map('trim', $childSexes);
        $data['child_sex'] = $childSexes ? implode(', ', array_filter($childSexes, fn($v) => $v !== '')) : null;
    } else {
        $data['child_sex'] = trim((string)$childSexes) ?: null;
    }

    $data['years_solo_parent'] = isset($_POST['years_solo_parent']) ? trim($_POST['years_solo_parent']) : null;
}

// ---------- 10) Desired columns (in preferred order) ----------
$requestSource = 'Online';
$desiredColumns = array_merge(
    ['account_id','transaction_id'],
    $fields,
    ['payment_method','amount','request_for','authorization_letter','request_source','certification_type']
);

// ---------- 11) Get real columns present in the table and keep order ----------
$tableCols = [];
$resShow = $conn->query("SHOW COLUMNS FROM `{$table}`");
if (!$resShow) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Table not found or DB error: " . $conn->error);
}
while ($row = $resShow->fetch_assoc()) {
    $tableCols[] = $row['Field'];
}
$resShow->free();

// Keep only desired columns that actually exist in the table (preserve the desired order)
$columns = array_values(array_filter($desiredColumns, function($c) use ($tableCols) {
    return in_array($c, $tableCols, true);
}));

if (empty($columns)) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("No insertable columns found for target table");
}

// ---------- 12) Generate transaction_id (we still create it even if table doesn't store it) ----------
$res = $conn->query("SELECT transaction_id FROM `{$table}` WHERE transaction_id IS NOT NULL ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows) {
    $last = $res->fetch_assoc()['transaction_id'];
    $numPart = intval(substr($last, strlen($prefix)));
    $n = $numPart + 1;
} else {
    $n = 1;
}
$transactionId = sprintf("%s%07d", $prefix, $n);

// ---------- 13) Build params array in exact order of $columns ----------
$params = [];
foreach ($columns as $col) {
    switch ($col) {
        case 'account_id':
            $params[] = $acct;
            break;
        case 'transaction_id':
            $params[] = $transactionId;
            break;
        case 'payment_method':
            $params[] = $_POST['paymentMethod'] ?? ($amount === 0 ? 'FREE' : null);
            break;
        case 'amount':
            $params[] = $amount;
            break;
        case 'request_for':
            $params[] = $requestFor;
            break;
        case 'authorization_letter':
            $params[] = $authFilename;
            break;
        case 'request_source':
            $params[] = $requestSource;
            break;
        case 'certification_type':
            $params[] = $type;
            break;
        default:
            // other fields that should come from $data (full_name, age, purok, etc.)
            $params[] = $data[$col] ?? null;
            break;
    }
}

// ---------- 14) Build placeholders and prepare SQL ----------
$placeholders = implode(',', array_fill(0, count($columns), '?'));
$sql = sprintf("INSERT INTO `%s` (%s) VALUES (%s)", $table, implode(',', $columns), $placeholders);
$stmt = $conn->prepare($sql);
if (!$stmt) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Prepare failed: " . $conn->error);
}

// ---------- 15) Build types string dynamically ----------
$types = '';
foreach ($columns as $col) {
    // integer-like columns
    if (in_array($col, ['account_id','age','residing_years','years_solo_parent','amount'], true)) {
        $types .= 'i';
    } else {
        $types .= 's';
    }
}

// ---------- 16) bind_param requires references ----------
$bind_names[] = $types;
for ($i = 0; $i < count($params); $i++) {
    $bind_name = 'bind' . $i;
    $$bind_name = $params[$i];
    $bind_names[] = &$$bind_name;
}

// call_user_func_array to bind
$bindOk = call_user_func_array([$stmt, 'bind_param'], $bind_names);
if ($bindOk === false) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Bind failed: " . $stmt->error);
}

// ---------- 17) Execute ----------
if (!$stmt->execute()) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Insert failed: " . $stmt->error);
}

// close and finish
$stmt->close();
$conn->close();

// ---------- 18) Redirect to page with tid so the Submission UI appears ----------
header("Location: ../userPanel.php?page=serviceCertification&tid=" . urlencode($transactionId));
exit;
