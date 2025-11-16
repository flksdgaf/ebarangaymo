<?php
require 'dbconn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$transaction_id = $_POST['transaction_id'] ?? '';
$action = $_POST['action'] ?? ''; // 'hold' or 'release'

if (empty($transaction_id) || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get respondent name from complaint
$stmt = $conn->prepare("SELECT respondent_name FROM barangay_complaints WHERE transaction_id = ?");
$stmt->bind_param('s', $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Complaint not found']);
    exit;
}

$row = $result->fetch_assoc();
$respondent_name = $row['respondent_name'];
$stmt->close();

// Search for respondent in all purok RBI tables
$purok_tables = ['purok1_rbi', 'purok2_rbi', 'purok3_rbi', 'purok4_rbi', 'purok5_rbi', 'purok6_rbi'];
$found_in_purok = null;
$current_remarks = null;

foreach ($purok_tables as $table) {
    $stmt = $conn->prepare("SELECT remarks FROM `$table` WHERE full_name = ?");
    $stmt->bind_param('s', $respondent_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $found_in_purok = $table;
        $current_remarks = $result->fetch_assoc()['remarks'];
        $stmt->close();
        break;
    }
    $stmt->close();
}

if (!$found_in_purok) {
    echo json_encode([
        'success' => false, 
        'message' => 'Respondent not found in any RBI table',
        'respondent_name' => $respondent_name
    ]);
    exit;
}

// Perform the action
if ($action === 'hold') {
    if ($current_remarks === 'On Hold') {
        echo json_encode([
            'success' => false, 
            'message' => 'Respondent is already on hold',
            'purok' => $found_in_purok,
            'current_status' => $current_remarks
        ]);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE `$found_in_purok` SET remarks = 'On Hold' WHERE full_name = ?");
    $stmt->bind_param('s', $respondent_name);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Respondent placed on hold successfully',
            'purok' => $found_in_purok,
            'new_status' => 'On Hold'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update hold status']);
    }
    $stmt->close();
    
} elseif ($action === 'release') {
    if ($current_remarks !== 'On Hold') {
        echo json_encode([
            'success' => false, 
            'message' => 'Respondent is not currently on hold',
            'purok' => $found_in_purok,
            'current_status' => $current_remarks
        ]);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE `$found_in_purok` SET remarks = NULL WHERE full_name = ?");
    $stmt->bind_param('s', $respondent_name);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Hold status released successfully',
            'purok' => $found_in_purok,
            'new_status' => 'None'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to release hold status']);
    }
    $stmt->close();
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>