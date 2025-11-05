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

// DON'T UPDATE STAGE - just enable Lupon tab
// The stage will be updated when the first Lupon schedule is created
// No database update needed here

// ACTIVITY LOG
$logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
$admin_id = $_SESSION['loggedInUserID'];
$role = $_SESSION['loggedInUserRole'];
$action = 'UPDATE';
$table_name = 'barangay_complaints';
$description = "Enabled Lupon hearings for {$tid}";

$logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $tid, $description);
$logStmt->execute();
$logStmt->close();

// REDIRECT
header("Location: ../adminPanel.php?page=adminComplaints&updated_complaint_id={$tid}");
exit();
?>