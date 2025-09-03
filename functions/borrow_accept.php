<?php
// functions/borrow_accept.php
require __DIR__ . '/dbconn.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => 'Invalid request method']);
  exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  echo json_encode(['success' => false, 'error' => 'Invalid id']);
  exit;
}

$conn->begin_transaction();

try {
  // 1) Lock borrow_requests row
  $sel = $conn->prepare("SELECT qty, equipment_sn, status FROM borrow_requests WHERE id = ? FOR UPDATE");
  if (!$sel) throw new Exception('Prepare failed (select borrow_requests): ' . $conn->error);
  $sel->bind_param('i', $id);
  $sel->execute();
  $res = $sel->get_result();
  $br = $res->fetch_assoc();
  $sel->close();

  if (!$br) throw new Exception('Borrow request not found');
  if (($br['status'] ?? '') !== 'Pending') throw new Exception('Borrow request is not pending');

  $qty = (int)$br['qty'];
  $esn = $br['equipment_sn'];

  // 2) Lock equipment row
  $selEq = $conn->prepare("SELECT available_qty FROM equipment_list WHERE equipment_sn = ? FOR UPDATE");
  if (!$selEq) throw new Exception('Prepare failed (select equipment): ' . $conn->error);
  $selEq->bind_param('s', $esn);
  $selEq->execute();
  $resEq = $selEq->get_result();
  $eq = $resEq->fetch_assoc();
  $selEq->close();

  if (!$eq) throw new Exception('Equipment not found for ESN: ' . $esn);

  $avail = (int)$eq['available_qty'];
  if ($avail < $qty) throw new Exception('Insufficient available quantity');

  // 3) Update equipment available quantity
  $updEq = $conn->prepare("UPDATE equipment_list SET available_qty = available_qty - ? WHERE equipment_sn = ?");
  if (!$updEq) throw new Exception('Prepare failed (update equipment): ' . $conn->error);
  $updEq->bind_param('is', $qty, $esn);
  if (!$updEq->execute()) throw new Exception('Failed to update equipment: ' . $updEq->error);
  $updEq->close();

  // 4) Update borrow_requests status
  $updBr = $conn->prepare("UPDATE borrow_requests SET status = 'Borrowed' WHERE id = ?");
  if (!$updBr) throw new Exception('Prepare failed (update borrow_requests): ' . $conn->error);
  $updBr->bind_param('i', $id);
  if (!$updBr->execute()) throw new Exception('Failed to update borrow request: ' . $updBr->error);
  $updBr->close();

  $conn->commit();
  echo json_encode(['success' => true, 'message' => 'Borrow request accepted']);
  exit;

} catch (Exception $e) {
  $conn->rollback();
  // avoid leaking internal info in production; message is helpful in development
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  exit;
}

$conn->close();

echo json_encode(['success' => false, 'error' => 'Unknown error']);
exit;
?>
