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
$hearingNumber = trim($_POST['hearing_number'] ?? ''); // unang, ikalawang, ikatlong
$scheduleDate = trim($_POST['schedule_date'] ?? '');
$scheduleTime = trim($_POST['schedule_time'] ?? '');
$complainantAffidavit = trim($_POST['complainant_affidavit'] ?? '');
$respondentAffidavit = trim($_POST['respondent_affidavit'] ?? '');

if (!$tid || !$hearingNumber) {
    header("Location: ../adminPanel.php?page=adminComplaints&error=missing_data");
    exit();
}

// Map hearing number to database columns
$scheduleColumn = '';
$complainantColumn = '';
$respondentColumn = '';
$stageValue = '';

switch ($hearingNumber) {
    case 'unang':
        $scheduleColumn = 'schedule_unang_patawag';
        $complainantColumn = 'complainant_affidavit_unang_patawag';
        $respondentColumn = 'respondent_affidavit_unang_patawag';
        $stageValue = 'Unang Patawag';
        break;
    case 'ikalawang':
        $scheduleColumn = 'schedule_ikalawang_patawag';
        $complainantColumn = 'complainant_affidavit_ikalawang_patawag';
        $respondentColumn = 'respondent_affidavit_ikalawang_patawag';
        $stageValue = 'Ikalawang Patawag';
        break;
    case 'ikatlong':
        $scheduleColumn = 'schedule_ikatlong_patawag';
        $complainantColumn = 'complainant_affidavit_ikatlong_patawag';
        $respondentColumn = 'respondent_affidavit_ikatlong_patawag';
        $stageValue = 'Ikatlong Patawag';
        break;
    default:
        header("Location: ../adminPanel.php?page=adminComplaints&error=invalid_hearing");
        exit();
}

// Combine date and time
$scheduleDatetime = null;
if ($scheduleDate && $scheduleTime) {
    $scheduleDatetime = $scheduleDate . ' ' . $scheduleTime . ':00';
}

// BUILD UPDATE QUERY
$updates = [];
$types = '';
$params = [];

if ($scheduleDatetime) {
    $updates[] = "{$scheduleColumn} = ?";
    $types .= 's';
    $params[] = $scheduleDatetime;
}

if ($complainantAffidavit !== '') {
    $updates[] = "{$complainantColumn} = ?";
    $types .= 's';
    $params[] = $complainantAffidavit;
}

if ($respondentAffidavit !== '') {
    $updates[] = "{$respondentColumn} = ?";
    $types .= 's';
    $params[] = $respondentAffidavit;
}

// Update complaint_stage and action_taken
$updates[] = "complaint_stage = ?";
$types .= 's';
$params[] = $stageValue;

$updates[] = "action_taken = 'On-Going'";

// Add transaction_id to params
$types .= 's';
$params[] = $tid;

// EXECUTE UPDATE
if (count($updates) > 0) {
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
}

// ACTIVITY LOG
$logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
$admin_id = $_SESSION['loggedInUserID'];
$role = $_SESSION['loggedInUserRole'];
$action = 'UPDATE';
$table_name = 'barangay_complaints';
$description = "Scheduled Lupon {$stageValue} hearing for {$tid}";

$logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $tid, $description);
$logStmt->execute();
$logStmt->close();

// REDIRECT
header("Location: ../adminPanel.php?page=adminComplaints&updated_complaint_id={$tid}");
exit();
?>