<?php
require 'dbconn.php';

header('Content-Type: application/json');

$transaction_id = $_POST['transaction_id'] ?? '';

if (empty($transaction_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing transaction ID']);
    exit;
}

// Get respondent name
$stmt = $conn->prepare("SELECT respondent_name FROM barangay_complaints WHERE transaction_id = ?");
$stmt->bind_param('s', $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Complaint not found']);
    exit;
}

$respondent_name = $result->fetch_assoc()['respondent_name'];
$stmt->close();

// Search in purok tables
$purok_tables = ['purok1_rbi', 'purok2_rbi', 'purok3_rbi', 'purok4_rbi', 'purok5_rbi', 'purok6_rbi'];
$found = false;
$purok = null;
$current_status = null;

foreach ($purok_tables as $table) {
    $stmt = $conn->prepare("SELECT remarks FROM `$table` WHERE full_name = ?");
    $stmt->bind_param('s', $respondent_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $found = true;
        $purok = $table;
        $current_status = $result->fetch_assoc()['remarks'];
        $stmt->close();
        break;
    }
    $stmt->close();
}

echo json_encode([
    'found' => $found,
    'respondent_name' => $respondent_name,
    'purok' => $purok,
    'current_status' => $current_status
]);

$conn->close();
?>