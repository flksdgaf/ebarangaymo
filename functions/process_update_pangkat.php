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
$chosenPangkat = trim($_POST['chosen_pangkat'] ?? '');

if (!$tid) {
    header("Location: ../adminPanel.php?page=adminComplaints&error=missing_data");
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

header("Location: ../adminPanel.php?page=adminComplaints&updated_complaint_id={$tid}");
exit();
?>