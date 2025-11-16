<?php
session_start();
require 'dbconn.php';

// AUTH CHECK
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Check user role - only admins can cancel cases
$currentRole = $_SESSION['loggedInUserRole'] ?? '';
if ($currentRole === 'Brgy Kagawad') {
    header("Location: ../adminPanel.php?page=adminComplaints&error=unauthorized");
    exit();
}

// COLLECT POST DATA
$tid = trim($_POST['transaction_id'] ?? '');

if (!$tid) {
    header("Location: ../adminPanel.php?page=adminComplaints&error=missing_data");
    exit();
}

// UPDATE complaint to Cancelled status
$sql = "UPDATE barangay_complaints 
        SET action_taken = 'Cancelled', 
            complaint_stage = 'Closed' 
        WHERE transaction_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $tid);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // ACTIVITY LOG
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
    $admin_id = $_SESSION['loggedInUserID'];
    $role = $_SESSION['loggedInUserRole'];
    $action = 'UPDATE';
    $table_name = 'barangay_complaints';
    $description = "Cancelled case {$tid}";
    
    $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $tid, $description);
    $logStmt->execute();
    $logStmt->close();
    
    $stmt->close();
    
    // Get case_no for the alert
    $caseStmt = $conn->prepare("SELECT case_no FROM barangay_complaints WHERE transaction_id = ?");
    $caseStmt->bind_param('s', $tid);
    $caseStmt->execute();
    $caseResult = $caseStmt->get_result()->fetch_assoc();
    $caseNo = $caseResult['case_no'] ?? $tid;
    $caseStmt->close();
    
    header("Location: ../adminPanel.php?page=adminComplaints&cancelled_complaint_id={$caseNo}");
} else {
    header("Location: ../adminPanel.php?page=adminComplaints&error=cancel_failed");
}

exit();
?>