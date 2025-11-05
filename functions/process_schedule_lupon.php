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

// Flag to check if we should update stage
$shouldUpdateStage = false;

if ($scheduleDatetime) {
    $updates[] = "{$scheduleColumn} = ?";
    $types .= 's';
    $params[] = $scheduleDatetime;
    
    // Only update stage if scheduling (not just updating affidavits)
    $shouldUpdateStage = true;
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

// Update complaint_stage and action_taken only if scheduling
if ($shouldUpdateStage) {
    // Get current stage to check if we should update
    $checkStmt = $conn->prepare("SELECT complaint_stage FROM barangay_complaints WHERE transaction_id = ?");
    $checkStmt->bind_param('s', $tid);
    $checkStmt->execute();
    $currentStage = $checkStmt->get_result()->fetch_assoc()['complaint_stage'];
    $checkStmt->close();
    
    // Stage hierarchy
    $stageHierarchy = [
        'Filing' => 0,
        'Punong Barangay - 1st' => 1,
        'Punong Barangay - 2nd' => 2,
        'Punong Barangay - 3rd' => 3,
        'Unang Patawag' => 4,
        'Ikalawang Patawag' => 5,
        'Ikatlong Patawag' => 6,
        'Closed' => 7
    ];
    
    $currentLevel = $stageHierarchy[$currentStage] ?? 0;
    $newLevel = $stageHierarchy[$stageValue] ?? 0;
    
    // Only update stage if new stage is more advanced (higher level)
    if ($newLevel > $currentLevel) {
        $updates[] = "complaint_stage = ?";
        $types .= 's';
        $params[] = $stageValue;
    }

    $updates[] = "action_taken = 'On-Going'";
}

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