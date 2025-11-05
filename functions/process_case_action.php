<?php
session_start();
require 'dbconn.php';

// AUTH CHECK
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

// COLLECT POST DATA
$tid = trim($_POST['transaction_id'] ?? '');
$actionType = trim($_POST['action_type'] ?? ''); // Should be 'close_case'
$actionTaken = trim($_POST['action_taken'] ?? '');

if (!$tid || !$actionType || $actionType !== 'close_case') {
    header("Location: ../adminPanel.php?page=adminComplaints&error=invalid_request");
    exit();
}

if (!$actionTaken) {
    header("Location: ../adminPanel.php?page=adminComplaints&error=action_taken_required");
    exit();
}

// Auto-set date_settlement to current date if action is Mediated or Conciliated
$dateSettlement = null;
if ($actionTaken === 'Mediated' || $actionTaken === 'Conciliated') {
    $dateSettlement = date('Y-m-d');
}

// Auto-set date_cfa_issued if action is CFA
$dateCfaIssued = null;
if ($actionTaken === 'CFA') {
    $dateCfaIssued = date('Y-m-d');
}

// Prepare UPDATE query
$updates = [];
$types = '';
$params = [];

$updates[] = "action_taken = ?";
$types .= 's';
$params[] = $actionTaken;

$updates[] = "complaint_stage = 'Closed'";

if ($dateSettlement) {
    $updates[] = "date_settlement = ?";
    $types .= 's';
    $params[] = $dateSettlement;
}

if ($dateCfaIssued) {
    $updates[] = "date_cfa_issued = ?";
    $types .= 's';
    $params[] = $dateCfaIssued;
}

// Add transaction_id to params
$types .= 's';
$params[] = $tid;

// Execute UPDATE
$sql = "UPDATE barangay_complaints SET " . implode(', ', $updates) . " WHERE transaction_id = ?";
$stmt = $conn->prepare($sql);

// Bind parameters dynamically
$refs = [];
foreach ($params as $i => $val) {
    $refs[$i] = &$params[$i];
}
array_unshift($refs, $types);
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$stmt->close();

// ACTIVITY LOG
$logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
$admin_id = $_SESSION['loggedInUserID'];
$role = $_SESSION['loggedInUserRole'];
$action = 'UPDATE';
$table_name = 'barangay_complaints';
$description = "Closed case {$tid} with action: {$actionTaken}";
$logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $tid, $description);
$logStmt->execute();
$logStmt->close();

header("Location: ../adminPanel.php?page=adminComplaints&updated_complaint_id={$tid}");
exit();
?>