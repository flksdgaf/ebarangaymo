<?php
// functions/accountable_add.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/dbconn.php'; // adjust if your dbconn is elsewhere

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../adminPanel.php?page=adminCollections&tab=accountable');
    exit;
}

// Collect & sanitize inputs
$form_name_no = trim($_POST['form_name_no'] ?? '');     // maps to form_name
$form_type    = trim($_POST['form_type'] ?? '');        // maps to form_type

$beginning_qty  = isset($_POST['beginning_qty']) ? (int)$_POST['beginning_qty'] : 0;
$beginning_from = trim($_POST['beginning_serial_from'] ?? '');
$beginning_to   = trim($_POST['beginning_serial_to'] ?? '');

$receipt_qty  = isset($_POST['receipt_qty']) ? (int)$_POST['receipt_qty'] : 0;
$receipt_from = trim($_POST['receipt_serial_from'] ?? '');
$receipt_to   = trim($_POST['receipt_serial_to'] ?? '');

$issued_qty  = isset($_POST['issued_qty']) ? (int)$_POST['issued_qty'] : 0;
$issued_from = trim($_POST['issued_serial_from'] ?? '');
$issued_to   = trim($_POST['issued_serial_to'] ?? '');

$ending_qty  = isset($_POST['ending_qty']) ? (int)$_POST['ending_qty'] : 0;
$ending_from = trim($_POST['ending_serial_from'] ?? '');
$ending_to   = trim($_POST['ending_serial_to'] ?? '');

// Basic validation: require at least a form name
if ($form_name_no === '') {
    header('Location: ../adminPanel.php?page=adminCollections&tab=accountable&success=0&err=validation');
    exit;
}

$sql = "INSERT INTO accountable_forms
    (form_name, form_type,
     beginning_balance_quantity, beginning_balance_from, beginning_balance_to,
     receipt_quantity, receipt_from, receipt_to,
     issued_quantity, issued_from, issued_to,
     ending_balance_quantity, ending_balance_from, ending_balance_to,
     created_at, updated_at)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    header('Location: ../adminPanel.php?page=adminCollections&tab=accountable&success=0&err=prepare');
    exit;
}

$bindTypes = 'ssisssisssisss';

$bindResult = $stmt->bind_param(
    $bindTypes,
    $form_name_no,
    $form_type,
    $beginning_qty,
    $beginning_from,
    $beginning_to,
    $receipt_qty,
    $receipt_from,
    $receipt_to,
    $issued_qty,
    $issued_from,
    $issued_to,
    $ending_qty,
    $ending_from,
    $ending_to
);

if ($bindResult === false) {
    $stmt->close();
    header('Location: ../adminPanel.php?page=adminCollections&tab=accountable&success=0&err=bind');
    exit;
}

if ($stmt->execute()) {
    $stmt->close();
    // redirect back to adminCollections and show success alert on the Accountable tab
    header('Location: ../adminPanel.php?page=adminCollections&tab=accountable&success=1');
    exit;
} else {
    $stmt->close();
    header('Location: ../adminPanel.php?page=adminCollections&tab=accountable&success=0&err=exec');
    exit;
}
