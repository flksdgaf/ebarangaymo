<?php
// functions/serviceEquipmentBorrowing_submit.php
session_start();

// require DB connection from same folder (functions/dbconn.php)
require_once __DIR__ . '/dbconn.php'; // this file MUST set $conn (mysqli)

// small helper: JSON response
function json_res($arr, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit();
}

// helper: check if a column exists in a table
function column_exists($conn, $table, $column) {
    $table = "`" . $conn->real_escape_string($table) . "`";
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// detect AJAX (fetch) vs normal request
$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
);

// auth: require loggedInUserID
if (empty($_SESSION['loggedInUserID'])) {
    if ($isAjax) json_res(['status' => 'error', 'message' => 'Unauthorized'], 401);
    header('Location: ../index.php');
    exit();
}
$userId = (int) $_SESSION['loggedInUserID'];

// Collect fields
$resident_name = trim($_POST['resident_name'] ?? '');
$purok_post    = trim($_POST['purok'] ?? '');
$equipment_sn  = trim($_POST['equipment_sn'] ?? '');
$qty           = isset($_POST['qty']) ? (int) $_POST['qty'] : 0;
$location      = trim($_POST['location'] ?? '');
$used_for      = trim($_POST['used_for'] ?? '');
$borrow_date   = trim($_POST['borrow_date'] ?? '');
$pudo_option   = trim($_POST['pudo_option'] ?? '');

// Validate
$errors = [];
if ($resident_name === '') $errors[] = 'Full name required.';
if ($equipment_sn === '') $errors[] = 'Equipment identifier required.';
if ($qty < 1) $errors[] = 'Quantity must be at least 1.';
if ($location === '') $errors[] = 'Location is required.';
if ($used_for === '') $errors[] = 'Purpose is required.';
if ($borrow_date === '') $errors[] = 'Borrow date is required.';
if ($pudo_option === '') $errors[] = 'Pick-up or Drop-off selection is required.';

if (!empty($errors)) {
    if ($isAjax) json_res(['status' => 'error', 'message' => implode(' ', $errors)], 400);
    $_SESSION['submit_error'] = implode(' ', $errors);
    header("Location: ../userPanel.php?page=serviceEquipmentBorrowing");
    exit();
}

// Check equipment availability
$stmt = $conn->prepare("SELECT available_qty FROM equipment_list WHERE equipment_sn = ? LIMIT 1");
if (!$stmt) {
    if ($isAjax) json_res(['status' => 'error', 'message' => 'DB error: ' . $conn->error], 500);
    $_SESSION['submit_error'] = 'DB error';
    header("Location: ../userPanel.php?page=serviceEquipmentBorrowing");
    exit();
}
$stmt->bind_param('s', $equipment_sn);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows !== 1) {
    $stmt->close();
    if ($isAjax) json_res(['status' => 'error', 'message' => 'Equipment not found.'], 400);
    $_SESSION['submit_error'] = 'Equipment not found';
    header("Location: ../userPanel.php?page=serviceEquipmentBorrowing");
    exit();
}
$row = $res->fetch_assoc();
$available = (int)$row['available_qty'];
$stmt->close();

if ($qty > $available) {
    if ($isAjax) json_res(['status' => 'error', 'message' => "Requested quantity ({$qty}) exceeds available ({$available})."], 400);
    $_SESSION['submit_error'] = "Requested quantity ({$qty}) exceeds available ({$available}).";
    header("Location: ../userPanel.php?page=serviceEquipmentBorrowing");
    exit();
}

// 3) Generate next transaction_id
$stmt = $conn->prepare("
    SELECT transaction_id 
      FROM borrow_requests
     ORDER BY id DESC 
     LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    $num     = intval(substr($lastTid, 4)) + 1;
} else {
    $num = 1;
}
$transactionId = sprintf('BRW-%07d', $num);
$stmt->close();

// Build insert for borrow_requests
$table = 'borrow_requests';
$cols = ['transaction_id', 'resident_name','equipment_sn','qty','location','used_for','date','status'];
$values = [$transactionId, $resident_name, $equipment_sn, $qty, $location, $used_for, $borrow_date, 'Pending'];

// Add purok if column exists
if ($purok_post !== '' && column_exists($conn, $table, 'purok')) {
    $cols[] = 'purok';
    $values[] = $purok_post;
}

// Always insert pudo_option into pudo column if it exists
if (column_exists($conn, $table, 'pudo')) {
    $cols[] = 'pudo';
    $values[] = $pudo_option;
} else {
    if ($isAjax) json_res(['status' => 'error', 'message' => "Database missing 'pudo' column."], 500);
    $_SESSION['submit_error'] = "Database missing 'pudo' column.";
    header("Location: ../userPanel.php?page=serviceEquipmentBorrowing");
    exit();
}

// Prepare and execute insert
$quotedCols = array_map(fn($c) => "`{$c}`", $cols);
$collist = implode(',', $quotedCols);
$placeholders = implode(',', array_fill(0, count($cols), '?'));
$sql = "INSERT INTO `{$table}` ({$collist}) VALUES ({$placeholders})";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    if ($isAjax) json_res(['status' => 'error', 'message' => 'DB prepare failed: ' . $conn->error], 500);
    $_SESSION['submit_error'] = 'DB prepare failed';
    header("Location: ../userPanel.php?page=serviceEquipmentBorrowing");
    exit();
}

// Bind params
$types = '';
$bindParams = [];
foreach ($values as $v) {
    $types .= is_int($v) ? 'i' : 's';
    $bindParams[] = $v;
}
$refs = [];
$refs[] = &$types;
foreach ($bindParams as $k => $v) $refs[] = &$bindParams[$k];

if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
    $err = $stmt->error ?: $conn->error;
    $stmt->close();
    if ($isAjax) json_res(['status' => 'error', 'message' => 'DB bind failed: ' . $err], 500);
    $_SESSION['submit_error'] = 'DB bind failed';
    header("Location: ../userPanel.php?page=serviceEquipmentBorrowing");
    exit();
}

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    if ($isAjax) json_res(['status' => 'error', 'message' => 'DB execute failed: ' . $err], 500);
    $_SESSION['submit_error'] = 'DB execute failed';
    header("Location: ../userPanel.php?page=serviceEquipmentBorrowing");
    exit();
}

$insertId = $stmt->insert_id;
$stmt->close();

// Update available quantity
$new_avail = max(0, $available - $qty);
$u = $conn->prepare("UPDATE equipment_list SET available_qty = ? WHERE equipment_sn = ?");
if ($u) {
    $u->bind_param('is', $new_avail, $equipment_sn);
    $u->execute();
    $u->close();
}

// Response
if ($isAjax) json_res(['status' => 'success', 'id' => $insertId, 'message' => 'Request submitted successfully.'], 200);

header("Location: ../userPanel.php?page=serviceEquipmentBorrowing&rid=" . urlencode($insertId));
exit();
