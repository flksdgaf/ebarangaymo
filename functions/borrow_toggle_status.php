<?php
// functions/borrow_toggle_status.php
require '../functions/dbconn.php';
header('Content-Type: application/json');

// 1) Collect and validate inputs
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

if (!$id || !in_array($status, ['Borrowed','Returned'], true)) {
    http_response_code(400);
    echo json_encode(['error'=>'Invalid parameters']);
    exit;
}

// 2) Fetch existing borrow request
$stmt = $conn->prepare("SELECT equipment_sn, qty, status FROM borrow_requests WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($esn, $qty, $oldStatus);
if (!$stmt->fetch()) {
    $stmt->close();
    http_response_code(404);
    echo json_encode(['error'=>'Request not found']);
    exit;
}
$stmt->close();

// 3) If nothing changes, return early
if ($oldStatus === $status) {
    echo json_encode(['status'=>'unchanged']);
    exit;
}

// 4) Look up equipment id
$stmt = $conn->prepare("SELECT id, available_qty FROM equipment_list WHERE equipment_sn = ?");
$stmt->bind_param('s', $esn);
$stmt->execute();
$stmt->bind_result($equipId, $availQty);
if (!$stmt->fetch()) {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['error'=>'Equipment SN not found']);
    exit;
}
$stmt->close();

// 5) Determine delta: if returning, +qty; if borrowing, -qty
$delta = ($status === 'Returned') ? +$qty : -$qty;

// 6) Begin transaction
$conn->begin_transaction();
try {
    // 7) Update borrow_requests.status
    $stmt = $conn->prepare("UPDATE borrow_requests SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    // 8) Update equipment_list.available_qty
    $newAvail = $availQty + $delta;
    if ($newAvail < 0) {
        throw new Exception("Insufficient available_qty");
    }
    $stmt = $conn->prepare("UPDATE equipment_list SET available_qty = ? WHERE id = ?");
    $stmt->bind_param('ii', $newAvail, $equipId);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    // 9) Commit & respond
    $conn->commit();
    echo json_encode([
      'status' => 'ok',
      'newStatus' => $status,
      'availableQty' => $newAvail,
      'equipmentId' => $equipId
    ]);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("borrow_toggle_status failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>'Server error']);
    exit;
}
?>
