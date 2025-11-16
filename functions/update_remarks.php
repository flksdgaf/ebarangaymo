<?php
require_once 'dbconn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
  || empty($_POST['full_name'])
  || !isset($_POST['purok'], $_POST['remarks'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Invalid request']);
  exit;
}

$name   = trim($_POST['full_name']);
$purok  = (int) $_POST['purok'];
$newRemarks = trim($_POST['remarks']);

// Validate purok
if ($purok < 1 || $purok > 6) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Invalid purok']);
  exit;
}

$table = "purok{$purok}_rbi";

// Get current remarks
$stmt = $conn->prepare("SELECT remarks FROM `$table` WHERE full_name = ?");
$stmt->bind_param('s', $name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Resident not found']);
    exit;
}

$currentRemarks = $result->fetch_assoc()['remarks'];
$stmt->close();

// Check if resident has an active complaint
$closedStatuses = ['Mediated', 'Conciliated', 'Dismissed', 'Cancelled', 'CFA', 'Withdrawn', 'Arbitrated'];
$placeholders = implode(',', array_fill(0, count($closedStatuses), '?'));

$sql = "SELECT transaction_id, case_no, action_taken 
        FROM barangay_complaints 
        WHERE respondent_name = ? 
        AND action_taken NOT IN ($placeholders)
        LIMIT 1";

$stmt = $conn->prepare($sql);
$types = 's' . str_repeat('s', count($closedStatuses));
$params = array_merge([$name], $closedStatuses);
$refs = [];
foreach ($params as $i => $v) {
    $refs[$i] = &$params[$i];
}
array_unshift($refs, $types);
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$complaintResult = $stmt->get_result();

if ($complaintResult->num_rows > 0) {
    // Resident has an active complaint
    $complaint = $complaintResult->fetch_assoc();
    $caseNo = $complaint['case_no'] ?? $complaint['transaction_id'];
    
    // If currently on hold and trying to change to something else
    if ($currentRemarks === 'On Hold' && $newRemarks !== 'On Hold') {
        $stmt->close();
        echo json_encode([
            'success' => false, 
            'error' => "Cannot change remarks: Respondent has an active complaint case ({$caseNo}) with On Hold status. Use the complaint's 'Clear Hold Status' button to clear this status.",
            'has_active_complaint' => true,
            'case_no' => $caseNo
        ]);
        exit;
    }
    
    // If trying to set to "On Hold" manually (should only be done through complaint system)
    if ($currentRemarks !== 'On Hold' && $newRemarks === 'On Hold') {
        $stmt->close();
        echo json_encode([
            'success' => false,
            'error' => "Cannot manually set to 'On Hold': Respondent has an active complaint case ({$caseNo}). Use the complaint's 'Hold Respondent' button to set this status.",
            'has_active_complaint' => true,
            'case_no' => $caseNo
        ]);
        exit;
    }
}
$stmt->close();

// If we got here, the update is allowed
$updateStmt = $conn->prepare("UPDATE `$table` SET remarks = ? WHERE full_name = ?");
$updateRemarks = $newRemarks === '' ? null : $newRemarks;
$updateStmt->bind_param("ss", $updateRemarks, $name);
$updateStmt->execute();
$updateStmt->close();

echo json_encode(['success' => true]);
exit;
?>