<?php
// functions/serviceCertification_submit.php
require_once 'dbconn.php';
session_start();

// 1) Auth check…
if (!($_SESSION['auth'] ?? false)) {
    header("HTTP/1.1 403 Forbidden");
    exit("Not authorized");
}

// 2) Collect the “for” and possible file
$requestFor = $_POST['request_for'] ?? '';
// Normalize to your ENUM values 
if (strtolower($requestFor) === 'myself') { 
  $requestFor = 'Myself'; 
} elseif (strtolower($requestFor) === 'other') { 
  $requestFor = 'Others'; 
} else { 
  // Bad value—reject 
  header("HTTP/1.1 400 Bad Request"); 
  exit("Invalid request_for"); 
}

$authFilename = null;
if ($requestFor === 'Others') {
    $uploaddir = __DIR__ . '/../authorizations/';
    is_dir($uploaddir) || mkdir($uploaddir,0755,true);
    $ext = pathinfo($_FILES['authorization_letter']['name'], PATHINFO_EXTENSION);
    $authFilename = session_id() . '_' . time() . '.' . $ext;
    move_uploaded_file($_FILES['authorization_letter']['tmp_name'], $uploaddir.$authFilename)
      or exit("Upload failed");
}

// 3) Define your server-side “certConfigs” to mirror the JS
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

$table     = $map[$type]['table'];
$prefix    = $map[$type]['prefix'];
$amount    = $map[$type]['amount'];
$acct      = (int)$_SESSION['loggedInUserID'];
$fields    = $certConfigs[$type];
$data      = [];

// 5) Pull values out of $_POST in the order of $fields
foreach ($fields as $field) {
    if (!isset($_POST[$field]) && $field!=='residing_years') {
        header("HTTP/1.1 400 Bad Request");
        exit("Missing field $field");
    }
    $data[$field] = $_POST[$field] ?? null;
}

// if guardianship or solo parent, collapse child arrays into comma-lists
if ($type === 'Guardianship') {
  $childs = array_map('trim', $_POST['child_name'] ?? []);
  $data['child_name'] = $childs ? implode(', ', $childs) : null;
  $fields[] = 'child_name';
}

if ($type === 'Solo Parent') {
    $data['child_name'] = implode(', ', array_map('trim', $_POST['child_name'] ?? []));
    $data['child_age']  = implode(', ', array_map('trim', $_POST['child_age']  ?? []));
    $data['child_sex']  = implode(', ', array_map('trim', $_POST['child_sex']  ?? []));
    $data['years_solo_parent'] = $_POST['years_solo_parent'] ?? null;
    // append those to your $fields so they get inserted:
    $fields = array_merge($fields, ['child_name','child_age','child_sex','years_solo_parent']);
}

// 6) Always-present columns:
$requestSource = 'Online';
$columns = array_merge(
    ['account_id','transaction_id'], 
    $fields,
    ['payment_method','amount','request_for','authorization_letter','request_source']
);
$placeholders = array_fill(0, count($columns), '?');

// 7) Generate transaction_id:
$res = $conn->query("SELECT transaction_id FROM `$table` ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows) {
    $last = $res->fetch_assoc()['transaction_id'];
    $n    = intval(substr($last, strlen($prefix))) + 1;
} else {
    $n = 1;
}
$transactionId = sprintf("%s%07d", $prefix, $n);

// 8) Build the full params array in the same order:
$params = array_merge(
    [$acct, $transactionId],
    array_values($data),
    [ $_POST['paymentMethod'], $amount, $requestFor, $authFilename, $requestSource ]
);

// 9) Prepare & bind
$sql = sprintf(
  "INSERT INTO `%s` (%s) VALUES (%s)",
  $table,
  implode(",", $columns),
  implode(",", $placeholders)
);
$stmt = $conn->prepare($sql);
if (!$stmt) exit("Prepare failed: ".$conn->error);

// Build the types string:
$types = str_repeat('s', count($columns));
$types[0] = 'i'; // account_id is int
if (is_numeric($data['age'] ?? '')) {
  // if age is in your fields, find its position
  $pos = array_search('age', $columns);
  $types[$pos] = 'i';
}

// 10) Bind & execute
$stmt->bind_param($types, ...$params);
$stmt->execute() or exit("Insert failed: ".$stmt->error);
$stmt->close();
$conn->close();

// 11) Finally redirect back to the receipt
header("Location: ../userPanel.php?page=serviceCertification&tid=$transactionId");
exit;
