<?php
// functions/accountable_add.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/dbconn.php'; // adjust path if needed

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../adminCollections.php?tab=accountable');
    exit;
}

// Helper to interpret quantity input:
// - return null if empty string / not set
// - return int value if numeric (including 0)
function parse_qty_nullable($key) {
    if (!isset($_POST[$key])) return null;
    $raw = trim((string)$_POST[$key]);
    if ($raw === '') return null;            // left blank => NULL
    // if numeric (including "0"), return int
    if (is_numeric($raw)) return (int)$raw;
    // non-numeric => NULL (could also choose validation error)
    return null;
}

// Helper to interpret text input: empty => NULL
function parse_text_nullable($key) {
    if (!isset($_POST[$key])) return null;
    $v = trim((string)$_POST[$key]);
    return $v === '' ? null : $v;
}

// Collect & sanitize inputs (map form field names to DB column names)
$form_name_no = trim($_POST['form_name_no'] ?? '');     // required
$form_type    = trim($_POST['form_type'] ?? '');        // required-ish (radio)

// Quantities: nullable integers
$beginning_qty = parse_qty_nullable('beginning_qty');
$receipt_qty   = parse_qty_nullable('receipt_qty');
$issued_qty    = parse_qty_nullable('issued_qty');
$ending_qty    = parse_qty_nullable('ending_qty');

// Serial/text fields: nullable strings
$beginning_from = parse_text_nullable('beginning_serial_from');
$beginning_to   = parse_text_nullable('beginning_serial_to');
$receipt_from   = parse_text_nullable('receipt_serial_from');
$receipt_to     = parse_text_nullable('receipt_serial_to');
$issued_from    = parse_text_nullable('issued_serial_from');
$issued_to      = parse_text_nullable('issued_serial_to');
$ending_from    = parse_text_nullable('ending_serial_from');
$ending_to      = parse_text_nullable('ending_serial_to');

// Basic validation: require form name
if ($form_name_no === '') {
    header('Location: ../adminPanel.php?page=adminCollections&tab=accountable&success=0&err=validation');
    exit;
}

// Prepare SQL. We'll insert NULL for PHP nulls.
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

// Bind types and variables. Nulls are allowed — mysqli will send SQL NULL for PHP null values.
$bindTypes = 'ssisssisssisss'; // s:form_name, s:form_type, i,balance_qty, s/from, s/to, ...
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
    // success: redirect back to the Accountable tab and show alert
    header('Location: ../adminPanel.php?page=adminCollections&tab=accountable&success=1');
    exit;
} else {
    $stmt->close();
    header('Location: ../adminPanel.php?page=adminCollections&tab=accountable&success=0&err=exec');
    exit;
}
