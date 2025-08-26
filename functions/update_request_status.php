<?php
// functions/update_request_status.php
session_start();
require_once __DIR__ . '/../functions/dbconn.php';
header('Content-Type: application/json; charset=utf-8');

// Server-side role check
$role = $_SESSION['loggedInUserRole'] ?? '';
$allowedRoles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper']; // roles allowed to change statuses

if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: insufficient permissions.']);
    exit;
}

$transaction_id = $_POST['transaction_id'] ?? '';
$new_status     = $_POST['status'] ?? '';

if (!$transaction_id || !$new_status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'transaction_id and status are required.']);
    exit;
}

$allowed = ['Processing', 'Rejected'];
if (!in_array($new_status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status requested.']);
    exit;
}

// 1) Get current status from view_request (avoid guessing underlying table)
$sel = $conn->prepare("SELECT document_status FROM view_request WHERE transaction_id = ? LIMIT 1");
if (!$sel) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error (prepare select).']);
    exit;
}
$sel->bind_param('s', $transaction_id);
$sel->execute();
$res = $sel->get_result();
$row = $res->fetch_assoc();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Record not found.']);
    exit;
}
$current_status = $row['document_status'] ?? '';

// 2) Validate transition rules
if ($new_status === 'Processing' && $current_status !== 'For Verification') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => "Cannot accept: current status is '{$current_status}'. Expected 'For Verification'."]);
    exit;
}
if ($new_status === 'Rejected' && $current_status === 'Rejected') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => "Already rejected."]);
    exit;
}

// 3) Update underlying table(s). If you know which table stores document_status, replace $candidateTables accordingly.
$candidateTables = [
    'barangay_id_requests',
    'business_permit_requests',
    'good_moral_requests',
    'guardianship_requests',
    'indigency_requests',
    'residency_requests',
    'solo_parent_requests',
];

$updated = false;
foreach ($candidateTables as $tbl) {
    // Skip if table doesn't exist: prepare returns false
    $stmt = @$conn->prepare("UPDATE `{$tbl}` SET document_status = ? WHERE transaction_id = ? LIMIT 1");
    if (!$stmt) continue;
    $stmt->bind_param('ss', $new_status, $transaction_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $updated = true;
        break;
    }
}

if ($updated) {
    echo json_encode(['success' => true, 'message' => "Status changed to '{$new_status}'."]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Update failed. Check underlying table name(s). Run SHOW CREATE VIEW view_request; to find base table and update $candidateTables.'
    ]);
}

$sel->close();
$conn->close();
exit;
?>
