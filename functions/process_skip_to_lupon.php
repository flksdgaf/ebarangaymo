<?php
session_start();
require 'dbconn.php';

// Check if AJAX request
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if ($isAjax) {
    header('Content-Type: application/json');
}

// AUTH CHECK
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    } else {
        header("Location: ../index.php");
    }
    exit();
}

// GET TRANSACTION ID
$tid = trim($_GET['transaction_id'] ?? '');

if (!$tid) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction ID']);
    } else {
        header("Location: ../adminPanel.php?page=adminComplaints&error=missing_data");
    }
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

if ($isAjax) {
    echo json_encode(['success' => true, 'message' => 'Lupon hearings enabled']);
} else {
    header("Location: ../adminPanel.php?page=adminComplaints&updated_complaint_id={$tid}");
}
exit();
?>