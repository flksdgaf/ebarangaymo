<?php
// functions/borrow_reject.php
session_start();
require '../functions/dbconn.php';
header('Content-Type: application/json; charset=utf-8');

$role = $_SESSION['loggedInUserRole'] ?? '';
$allowed = ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper'];
if (!in_array($role, $allowed, true)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$remarks = trim($_POST['remarks'] ?? '');

if (!$id) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing id']);
  exit;
}

// ensure it is pending
$stmt = $conn->prepare("SELECT status FROM borrow_requests WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['success' => false, 'message' => 'Request not found']);
  exit;
}
if ($row['status'] !== 'Pending') {
  http_response_code(409);
  echo json_encode(['success' => false, 'message' => 'Request is not pending']);
  exit;
}

// Update to Rejected (store remarks if column exists)
$hasRemarksCol = false;
$check = $conn->query("SHOW COLUMNS FROM borrow_requests LIKE 'rejected_remarks'");
if ($check && $check->num_rows) $hasRemarksCol = true;

if ($hasRemarksCol) {
  $u = $conn->prepare("UPDATE borrow_requests SET status = 'Rejected', rejected_remarks = ? WHERE id = ?");
  $u->bind_param('si', $remarks, $id);
} else {
  $u = $conn->prepare("UPDATE borrow_requests SET status = 'Rejected' WHERE id = ?");
  $u->bind_param('i', $id);
}
if ($u->execute()) {
  echo json_encode(['success' => true, 'message' => 'Rejected']);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Update failed']);
}
$u->close();
exit;
?>
