<?php
session_start();
require 'dbconn.php';

// AUTH CHECK
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = (int)$_SESSION['loggedInUserID'];

// COLLECT POST DATA
$tid = trim($_POST['transaction_id'] ?? '');
$complaintTitle = trim($_POST['complaint_title'] ?? '');
$natureOfCase = trim($_POST['nature_of_case'] ?? '');
$complaintAffidavit = trim($_POST['complaint_affidavit'] ?? '');
$pleadingStatement = trim($_POST['pleading_statement'] ?? '');

if (!$tid) {
    header("Location: ../adminPanel.php?page=adminComplaints&error=missing_data");
    exit();
}

// UPDATE COMPLAINT
$sql = "UPDATE barangay_complaints 
        SET complaint_title = ?, 
            nature_of_case = ?, 
            complaint_affidavit = ?, 
            pleading_statement = ? 
        WHERE transaction_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('sssss', $complaintTitle, $natureOfCase, $complaintAffidavit, $pleadingStatement, $tid);
$stmt->execute();

// CHECK IF UPDATED
if ($stmt->affected_rows > 0) {
    // ACTIVITY LOG
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
    $admin_id = $_SESSION['loggedInUserID'];
    $role = $_SESSION['loggedInUserRole'];
    $action = 'UPDATE';
    $table_name = 'barangay_complaints';
    $description = "Updated complaint details for {$tid}";
    
    $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $tid, $description);
    $logStmt->execute();
    $logStmt->close();
    
    $stmt->close();
    header("Location: ../adminPanel.php?page=adminComplaints&updated_complaint_id={$tid}");
} else {
    $stmt->close();
    header("Location: ../adminPanel.php?page=adminComplaints&complaint_nochange=1");
}

exit();
?>
