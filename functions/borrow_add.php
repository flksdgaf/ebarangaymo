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

// Helper function to build full name from components
function buildFullName($first, $middle, $last, $suffix) {
    $first = trim($first);
    $middle = trim($middle);
    $last = trim($last);
    $suffix = trim($suffix);
    
    // Build name: "Last, First Middle Suffix" format
    $name = $last . ', ' . $first;
    
    if ($middle !== '') {
        $name .= ' ' . $middle;
    }
    
    if ($suffix !== '') {
        $name .= ' ' . $suffix;
    }
    
    return $name;
}

// 0) Ensure user is logged in
if (!isset($_SESSION['loggedInUserID'])) {
    redirect(['borrow_error' => 'auth', 'tab' => 'borrows']);
}
$userId = (int) ($_SESSION['loggedInUserID'] ?? 0);

// 1) Collect & sanitize inputs - NEW separate name fields
$firstName       = trim($_POST['first_name'] ?? '');
$middleName      = trim($_POST['middle_name'] ?? '');  // optional
$lastName        = trim($_POST['last_name'] ?? '');
$suffix          = trim($_POST['suffix'] ?? '');      // optional

// Build the full name for storage
$resident = buildFullName($firstName, $middleName, $lastName, $suffix);

// Other existing fields
$esn             = trim($_POST['equipment_sn'] ?? '');
$qty             = isset($_POST['qty']) ? (int) $_POST['qty'] : 0;
$location        = trim($_POST['location'] ?? '');
$used_for        = trim($_POST['used_for'] ?? '');
$pudo            = trim($_POST['pudo'] ?? 'Pick Up');

// NEW: borrow_date_from and borrow_date_to (required)
$borrowDateFrom  = trim($_POST['borrow_date_from'] ?? '');
$borrowDateTo    = trim($_POST['borrow_date_to'] ?? '');

// Basic validation - first name and last name are required
if ($firstName === '' || $lastName === '' || $esn === '' || $qty < 1 || $location === '' || $used_for === '' || $pudo === '' || $borrowDateFrom === '' || $borrowDateTo === '') {
    redirect(['borrow_error' => 'missing', 'tab' => 'borrows']);
}

// Validate date formats
if (! valid_date($borrowDateFrom) || ! valid_date($borrowDateTo)) {
    redirect(['borrow_error' => 'date', 'tab' => 'borrows']);
}

// Ensure from <= to
if ($borrowDateFrom > $borrowDateTo) {
    redirect(['borrow_error' => 'daterange', 'tab' => 'borrows']);
}

// 2) Check that equipment exists and get available quantity (do NOT decrement here)
$stmt = $conn->prepare("SELECT id, available_qty FROM equipment_list WHERE equipment_sn = ? LIMIT 1");
if (!$stmt) {
    error_log("Prepare failed (equipment lookup): " . $conn->error);
    redirect(['borrow_error' => 'db', 'tab' => 'borrows']);
}
$stmt->bind_param('s', $esn);
if (! $stmt->execute()) {
    error_log("Execute failed (equipment lookup): " . $stmt->error);
    $stmt->close();
    redirect(['borrow_error' => 'db', 'tab' => 'borrows']);
}
$res = $stmt->get_result();
if (! $res || $res->num_rows !== 1) {
    $stmt->close();
    redirect(['borrow_error' => 'notfound', 'tab' => 'borrows']);
}
$row = $res->fetch_assoc();
$equipId = (int)$row['id'];
$availQty = (int)$row['available_qty'];
$stmt->close();

// 3) If requested qty is more than available, block creation
if ($qty > $availQty) {
    redirect(['borrow_error' => 'toomany', 'tab' => 'borrows']);
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

// 5) Start transaction to ensure atomicity
$conn->begin_transaction();

try {
    // 5a) Decrease available_qty in equipment_list immediately
    $updateEquip = $conn->prepare("UPDATE equipment_list SET available_qty = available_qty - ? WHERE equipment_sn = ?");
    if (!$updateEquip) {
        throw new Exception("Prepare failed (equipment update): " . $conn->error);
    }
    
    $updateEquip->bind_param('is', $qty, $esn);
    if (!$updateEquip->execute()) {
        throw new Exception("Execute failed (equipment update): " . $updateEquip->error);
    }
    $updateEquip->close();

    // 5b) Insert into borrow_requests with status 'Pending'
    $insertSql = "INSERT INTO borrow_requests
        (account_id, transaction_id, request_type, resident_name, equipment_sn, qty, location, used_for,
         borrow_date_from, borrow_date_to, pudo, status)
        VALUES (?, ?, 'Equipment Borrowing', ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

    $ins = $conn->prepare($insertSql);
    if (!$ins) {
        throw new Exception("Prepare failed (borrow insert): " . $conn->error);
    }

    $types = 'isssisssss';

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
        throw new Exception("Bind failed (borrow insert): " . $ins->error);
    }

    if (! $ins->execute()) {
        throw new Exception("Execute failed (borrow insert): " . $ins->error);
    }

    $ins->close();
    
    // Commit transaction
    $conn->commit();
    
    // Success: redirect returning transaction id for display
    redirect(['borrowed' => $transactionId, 'tab' => 'borrows']);

} catch (Exception $e) {
    // Rollback on any error
    $conn->rollback();
    error_log("Borrow creation failed: " . $e->getMessage());
    redirect(['borrow_error' => 'db', 'tab' => 'borrows']);
}
?>