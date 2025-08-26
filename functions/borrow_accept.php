<?php
// functions/borrow_accept.php
session_start();
require '../functions/dbconn.php';
header('Content-Type: application/json; charset=utf-8');

// role check: change allowed roles if needed
$role = $_SESSION['loggedInUserRole'] ?? '';
$allowed = ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper'];
if (!in_array($role, $allowed, true)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if (!$id) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing id']);
  exit;
}

$conn->begin_transaction();
try {
  // lock borrow row
  $stmt = $conn->prepare("SELECT equipment_sn, qty, status FROM borrow_requests WHERE id = ? FOR UPDATE");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if (!$row) throw new Exception('Request not found');
  if ($row['status'] !== 'Pending') throw new Exception('Request is not pending');

  $esn = $row['equipment_sn'];
  $qty = (int)$row['qty'];

  // lock equipment row
  $stmt = $conn->prepare("SELECT id, available_qty FROM equipment_list WHERE equipment_sn = ? FOR UPDATE");
  $stmt->bind_param('s', $esn);
  $stmt->execute();
  $res2 = $stmt->get_result();
  $eq = $res2->fetch_assoc();
  $stmt->close();

  if (!$eq) throw new Exception('Equipment not found');
  $avail = (int)$eq['available_qty'];
  $equipId = (int)$eq['id'];

  if ($avail < $qty) throw new Exception("Insufficient available quantity (available: {$avail})");

  // decrement equipment available_qty
  $u = $conn->prepare("UPDATE equipment_list SET available_qty = available_qty - ? WHERE id = ?");
  $u->bind_param('ii', $qty, $equipId);
  if (!$u->execute()) throw new Exception('Failed updating equipment qty');
  $u->close();

  // update borrow_requests.status = 'Borrowed'
  $u2 = $conn->prepare("UPDATE borrow_requests SET status = 'Borrowed' WHERE id = ?");
  $u2->bind_param('i', $id);
  if (!$u2->execute()) throw new Exception('Failed updating borrow request');
  $u2->close();

  $conn->commit();
  echo json_encode(['success' => true, 'message' => 'Accepted']);
  exit;

} catch (Exception $ex) {
  $conn->rollback();
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
  exit;
}
?>
