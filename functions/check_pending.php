<?php
require 'dbconn.php';

$name = $_POST['full_name'] ?? '';
$response = [
    'has_pending' => false,
    'is_on_hold' => false,
    'case_details' => null
];

if ($name !== '') {
    // Check if resident has an active complaint (not closed statuses)
    $closedStatuses = ['Mediated', 'Conciliated', 'Dismissed', 'Cancelled', 'CFA', 'Withdrawn', 'Arbitrated'];
    $placeholders = implode(',', array_fill(0, count($closedStatuses), '?'));
    
    $sql = "SELECT transaction_id, case_no, action_taken, complaint_stage 
            FROM barangay_complaints 
            WHERE respondent_name = ? 
            AND action_taken NOT IN ($placeholders)
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameters: name + all closed statuses
    $types = 's' . str_repeat('s', count($closedStatuses));
    $params = array_merge([$name], $closedStatuses);
    $refs = [];
    foreach ($params as $i => $v) {
        $refs[$i] = &$params[$i];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response['has_pending'] = true;
        $response['case_details'] = [
            'transaction_id' => $row['transaction_id'],
            'case_no' => $row['case_no'] ?? $row['transaction_id'],
            'action_taken' => $row['action_taken'],
            'complaint_stage' => $row['complaint_stage']
        ];
        
        // Check if respondent is currently on hold in RBI
        $purok_tables = ['purok1_rbi', 'purok2_rbi', 'purok3_rbi', 'purok4_rbi', 'purok5_rbi', 'purok6_rbi'];
        foreach ($purok_tables as $table) {
            $checkStmt = $conn->prepare("SELECT remarks FROM `$table` WHERE full_name = ?");
            $checkStmt->bind_param('s', $name);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $remarkRow = $checkResult->fetch_assoc();
                if ($remarkRow['remarks'] === 'On Hold') {
                    $response['is_on_hold'] = true;
                }
                $checkStmt->close();
                break;
            }
            $checkStmt->close();
        }
    }
    
    $stmt->close();
}

echo json_encode($response);
exit;
?>