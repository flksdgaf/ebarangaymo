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

// Simple YYYY-MM-DD validator (server-side)
function valid_date(string $d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d);
}

// 0) Ensure user is logged in
if (!isset($_SESSION['loggedInUserID'])) {
    redirect(['borrow_error' => 'auth']);
}
$userId = (int) ($_SESSION['loggedInUserID'] ?? 0);

// 1) Collect & sanitize inputs (adjust names if your form uses different keys)
$resident        = trim($_POST['resident_name'] ?? '');
$esn             = trim($_POST['equipment_sn'] ?? '');
$qty             = isset($_POST['qty']) ? (int) $_POST['qty'] : 0;
$location        = trim($_POST['location'] ?? '');
$used_for        = trim($_POST['used_for'] ?? '');
$pudo            = trim($_POST['pudo'] ?? 'Pick Up');

// NEW: borrow_date_from and borrow_date_to (required)
$borrowDateFrom  = trim($_POST['borrow_date_from'] ?? '');
$borrowDateTo    = trim($_POST['borrow_date_to'] ?? '');

// Basic validation
if ($resident === '' || $esn === '' || $qty < 1 || $location === '' || $used_for === '' || $pudo === '' || $borrowDateFrom === '' || $borrowDateTo === '') {
    redirect(['borrow_error' => 'missing']);
}

// Validate date formats
if (! valid_date($borrowDateFrom) || ! valid_date($borrowDateTo)) {
    redirect(['borrow_error' => 'date']);
}

// Ensure from <= to
if ($borrowDateFrom > $borrowDateTo) {
    redirect(['borrow_error' => 'daterange']);
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
// ---------------------------------------------------------------------
// NOTE: previously we stored a single `borrow_date` for compatibility.
// That code is commented below. The active INSERT now only uses
// borrow_date_from / borrow_date_to (and no longer writes borrow_date).
// ---------------------------------------------------------------------

/* Old compatibility version (commented out)
$insertSql = "INSERT INTO borrow_requests
    (account_id, transaction_id, request_type, resident_name, equipment_sn, qty, location, used_for,
     borrow_date, borrow_date_from, borrow_date_to, pudo, status)
    VALUES (?, ?, 'Equipment Borrowing', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

$ins = $conn->prepare($insertSql);
if (!$ins) {
    error_log("Prepare failed (borrow insert): " . $conn->error . " -- SQL: " . $insertSql);
    redirect(['borrow_error' => 'db']);
}

// types: account_id (i), transaction_id (s), resident (s), esn (s), qty (i),
// location (s), used_for (s), borrow_date (s), borrow_date_from (s), borrow_date_to (s), pudo (s)
$types = 'isssissssss'; // 11 types matching 11 bound values

$borrowDate = $borrowDateFrom; // compatibility with existing borrow_date column

if (! $ins->bind_param($types,
    $userId,
    $transactionId,
    $resident,
    $esn,
    $qty,
    $location,
    $used_for,
    $borrowDate,
    $borrowDateFrom,
    $borrowDateTo,
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
*/

// ------------------- Active INSERT (no borrow_date column) -------------------
$insertSql = "INSERT INTO borrow_requests
    (account_id, transaction_id, request_type, resident_name, equipment_sn, qty, location, used_for,
     borrow_date_from, borrow_date_to, pudo, status)
    VALUES (?, ?, 'Equipment Borrowing', ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

$ins = $conn->prepare($insertSql);
if (!$ins) {
    error_log("Prepare failed (borrow insert): " . $conn->error . " -- SQL: " . $insertSql);
    redirect(['borrow_error' => 'db']);
}

// types for the 10 bound values:
// account_id (i), transaction_id (s), resident (s), esn (s), qty (i),
// location (s), used_for (s), borrow_date_from (s), borrow_date_to (s), pudo (s)
$types = 'isssisssss'; // corresponds to: i s s s i s s s s s (10 params)

if (! $ins->bind_param($types,
    $userId,
    $transactionId,
    $resident,
    $esn,
    $qty,
    $location,
    $used_for,
    $borrowDateFrom,
    $borrowDateTo,
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

// success: redirect returning transaction id for display
redirect(['borrowed' => $transactionId]);
?>
