<?php
session_start();
require 'dbconn.php';

// AUTH CHECK
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

// GET TRANSACTION ID
$tid = trim($_GET['transaction_id'] ?? '');

if (!$tid) {
    header("Location: ../adminPanel.php?page=adminComplaints&error=missing_data");
    exit();
}

// UPDATE: Skip to Lupon stage (Unang Patawag)
$sql = "UPDATE barangay_complaints 
        SET complaint_stage = 'Unang Patawag', 
            action_taken = 'On-Going' 
        WHERE transaction_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $tid);
$stmt->execute();
$stmt->close();

// ACTIVITY LOG
$logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
$admin_id = $_SESSION['loggedInUserID'];
$role = $_SESSION['loggedInUserRole'];
$action = 'UPDATE';
$table_name = 'barangay_complaints';
$description = "Skipped Punong Barangay meetings, proceeded to Lupon for {$tid}";

$logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $tid, $description);
$logStmt->execute();
$logStmt->close();

// REDIRECT
header("Location: ../adminPanel.php?page=adminComplaints&updated_complaint_id={$tid}");
exit();
?>