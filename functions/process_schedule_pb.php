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
$meetingNumber = trim($_POST['meeting_number'] ?? ''); // first, second, third
$scheduleDate = trim($_POST['schedule_date'] ?? '');
$scheduleTime = trim($_POST['schedule_time'] ?? '');
$complainantAffidavit = trim($_POST['complainant_affidavit'] ?? '');
$respondentAffidavit = trim($_POST['respondent_affidavit'] ?? '');

if (!$tid || !$meetingNumber) {
    header("Location: ../adminPanel.php?page=adminComplaints&error=missing_data");
    exit();
}

// Map meeting number to database columns
$scheduleColumn = '';
$complainantColumn = '';
$respondentColumn = '';
$stageValue = '';

switch ($meetingNumber) {
    case 'first':
        $scheduleColumn = 'schedule_pb_first';
        $complainantColumn = 'complainant_affidavit_pb_first';
        $respondentColumn = 'respondent_affidavit_pb_first';
        $stageValue = 'Punong Barangay - 1st';
        break;
    case 'second':
        $scheduleColumn = 'schedule_pb_second';
        $complainantColumn = 'complainant_affidavit_pb_second';
        $respondentColumn = 'respondent_affidavit_pb_second';
        $stageValue = 'Punong Barangay - 2nd';
        break;
    case 'third':
        $scheduleColumn = 'schedule_pb_third';
        $complainantColumn = 'complainant_affidavit_pb_third';
        $respondentColumn = 'respondent_affidavit_pb_third';
        $stageValue = 'Punong Barangay - 3rd';
        break;
    default:
        header("Location: ../adminPanel.php?page=adminComplaints&error=invalid_meeting");
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
    
    // For first meeting, also update date_initial_hearing
    if ($meetingNumber === 'first') {
        $updates[] = "date_initial_hearing = ?";
        $types .= 's';
        $params[] = $scheduleDatetime;
    }
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

// Only update stage if we're scheduling (not just updating affidavits)
// And only if the new stage is more advanced than current stage
if ($scheduleDatetime) {
    // Get current stage to check if we should update
    $checkStmt = $conn->prepare("SELECT complaint_stage FROM barangay_complaints WHERE transaction_id = ?");
    $checkStmt->bind_param('s', $tid);
    $checkStmt->execute();
    $currentStage = $checkStmt->get_result()->fetch_assoc()['complaint_stage'];
    $checkStmt->close();
    
    // Stage hierarchy for Punong Barangay
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
$description = "Scheduled Punong Barangay {$meetingNumber} meeting for {$tid}";

$logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $tid, $description);
$logStmt->execute();
$logStmt->close();

// Return JSON for AJAX
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
    exit();
}

// REDIRECT (fallback)
header("Location: ../adminPanel.php?page=adminComplaints&updated_complaint_id={$tid}");
exit();
?>