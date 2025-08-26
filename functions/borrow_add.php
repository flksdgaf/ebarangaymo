<?php
// functions/borrow_add.php
session_start();

// require DB connection from same folder
require_once __DIR__ . '/dbconn.php';

// Redirect helper (adjust target page if you use a different panel)
function redirect($params = []) {
    $qs = http_build_query($params);
    header("Location: ../adminPanel.php?page=adminEquipmentBorrowing&{$qs}");
    exit;
}

// 0) Ensure user is logged in
if (!isset($_SESSION['loggedInUserID'])) {
    redirect(['borrow_error' => 'auth']);
}
$userId = (int) ($_SESSION['loggedInUserID'] ?? 0);

// 1) Collect & sanitize inputs (adjust names if your form uses different keys)
$resident   = trim($_POST['resident_name'] ?? '');
$esn        = trim($_POST['equipment_sn'] ?? '');
$qty        = isset($_POST['qty']) ? (int) $_POST['qty'] : 0;
$location   = trim($_POST['location'] ?? '');
$used_for   = trim($_POST['used_for'] ?? '');
$pudo       = trim($_POST['pudo'] ?? 'Pick Up');

// Accept either borrow_date or date from the form; fallback to today's date
$borrowDate = trim($_POST['borrow_date'] ?? $_POST['date'] ?? date('Y-m-d'));

// Basic validation
if ($resident === '' || $esn === '' || $qty < 1 || $location === '' || $used_for === '' || $pudo === '') {
    redirect(['borrow_error' => 'missing']);
}

// 2) Check that equipment exists and get available quantity (do NOT decrement here)
$stmt = $conn->prepare("SELECT id, available_qty FROM equipment_list WHERE equipment_sn = ? LIMIT 1");
if (!$stmt) {
    error_log("Prepare failed (equipment lookup): " . $conn->error);
    redirect(['borrow_error' => 'db']);
}
$stmt->bind_param('s', $esn);
if (! $stmt->execute()) {
    error_log("Execute failed (equipment lookup): " . $stmt->error);
    $stmt->close();
    redirect(['borrow_error' => 'db']);
}
$res = $stmt->get_result();
if (! $res || $res->num_rows !== 1) {
    $stmt->close();
    redirect(['borrow_error' => 'notfound']);
}
$row = $res->fetch_assoc();
$equipId = (int)$row['id'];
$availQty = (int)$row['available_qty'];
$stmt->close();

// 3) If requested qty is more than available, block creation
if ($qty > $availQty) {
    redirect(['borrow_error' => 'toomany']);
}

// 4) Generate next transaction_id (BRW-0000001 style)
$prefix = 'BRW-';
$num = 1;
$tidStmt = $conn->prepare("SELECT transaction_id FROM borrow_requests WHERE transaction_id <> '' ORDER BY id DESC LIMIT 1");
if ($tidStmt) {
    if ($tidStmt->execute()) {
        $res2 = $tidStmt->get_result();
        if ($res2 && $res2->num_rows === 1) {
            $lastTid = $res2->fetch_assoc()['transaction_id'];
            if (is_string($lastTid) && preg_match('/(\d+)$/', $lastTid, $m)) {
                $num = intval($m[1]) + 1;
            }
        }
    } else {
        error_log("Failed to execute select last transaction_id (borrow): " . $tidStmt->error);
    }
    $tidStmt->close();
}
$transactionId = sprintf($prefix . '%07d', $num);

// 5) Insert into borrow_requests
$insertSql = "INSERT INTO borrow_requests
    (account_id, transaction_id, request_type, resident_name, equipment_sn, qty, location, used_for, borrow_date, pudo, status)
    VALUES (?, ?, 'Equipment Borrowing', ?, ?, ?, ?, ?, ?, ?, 'Pending')";

$ins = $conn->prepare($insertSql);
if (!$ins) {
    error_log("Prepare failed (borrow insert): " . $conn->error . " -- SQL: " . $insertSql);
    redirect(['borrow_error' => 'db']);
}

// Build correct types string: we are binding 9 variables (after types string)
 // userId (i), transactionId (s), resident (s), esn (s), qty (i), location (s), used_for (s), borrowDate (s), pudo (s)
$types = 'isssissss'; // 9 characters matching 9 values below

if (! $ins->bind_param($types,
    $userId,
    $transactionId,
    $resident,
    $esn,
    $qty,
    $location,
    $used_for,
    $borrowDate,
    $pudo
)) {
    error_log("Bind failed (borrow insert): " . $ins->error . " -- types: {$types}");
    $ins->close();
    redirect(['borrow_error' => 'db']);
}

if (! $ins->execute()) {
    error_log("Execute failed (borrow insert): " . $ins->error);
    $ins->close();
    redirect(['borrow_error' => 'db']);
}

$ins->close();

// success: redirect returning tid for display
redirect(['borrowed' => $transactionId]);
?>
