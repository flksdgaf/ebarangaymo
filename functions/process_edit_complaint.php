<?php
session_start();
require 'dbconn.php';

header('Content-Type: application/json');

// AUTH CHECK
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
    echo json_encode(['success' => false, 'message' => 'Missing transaction ID']);
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
    echo json_encode(['success' => true, 'message' => 'Complaint details updated successfully']);
} else {
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'No changes made', 'type' => 'info']);
}

exit();
?>
