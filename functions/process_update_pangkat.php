<?php
session_start();
require 'dbconn.php';

header('Content-Type: application/json');

// AUTH CHECK
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// COLLECT POST DATA
$tid = trim($_POST['transaction_id'] ?? '');
$chosenPangkat = trim($_POST['chosen_pangkat'] ?? '');

if (!$tid) {
    echo json_encode(['success' => false, 'message' => 'Missing transaction ID']);
    exit();
}

// UPDATE chosen_pangkat
$stmt = $conn->prepare("UPDATE barangay_complaints SET chosen_pangkat = ? WHERE transaction_id = ?");
$stmt->bind_param('ss', $chosenPangkat, $tid);
$stmt->execute();
$stmt->close();

// ACTIVITY LOG
$logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
$admin_id = $_SESSION['loggedInUserID'];
$role = $_SESSION['loggedInUserRole'];
$action = 'UPDATE';
$table_name = 'barangay_complaints';
$description = "Updated Pangkat members for {$tid}";
$logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $tid, $description);
$logStmt->execute();
$logStmt->close();

echo json_encode(['success' => true, 'message' => 'Pangkat members updated successfully']);
exit();
?>