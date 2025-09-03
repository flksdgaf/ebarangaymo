<?php
// functions/borrow_reject.php
require __DIR__ . '/dbconn.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => 'Invalid request method']);
  exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if ($id <= 0) {
  echo json_encode(['success' => false, 'error' => 'Invalid id']);
  exit;
}
if ($reason === '') {
  echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
  exit;
}

$conn->begin_transaction();

try {
  // Lock the borrow_requests row
  $sel = $conn->prepare("SELECT status, equipment_sn, qty FROM borrow_requests WHERE id = ? FOR UPDATE");
  if (!$sel) throw new Exception('Prepare failed (select borrow_requests): ' . $conn->error);
  $sel->bind_param('i', $id);
  $sel->execute();
  $res = $sel->get_result();
  $br = $res ? $res->fetch_assoc() : null;
  $sel->close();

  if (!$br) throw new Exception('Borrow request not found');
  if (($br['status'] ?? '') !== 'Pending') throw new Exception('Borrow request is not pending');

  // Update borrow_requests: set status to Rejected and save reason + timestamp
  $upd = $conn->prepare("UPDATE borrow_requests SET status = 'Rejected', rejection_reason = ?, rejected_at = NOW() WHERE id = ?");
  if (!$upd) throw new Exception('Prepare failed (update borrow_requests): ' . $conn->error);
  $upd->bind_param('si', $reason, $id);
  if (!$upd->execute()) throw new Exception('Failed to update borrow request: ' . $upd->error);
  $upd->close();

  $conn->commit();
  echo json_encode(['success' => true, 'message' => 'Borrow request rejected']);
  exit;

} catch (Exception $e) {
  $conn->rollback();
  // For production, sanitize message — useful for dev
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  exit;
}

$conn->close();
echo json_encode(['success' => false, 'error' => 'Unknown error']);
exit;
?>
