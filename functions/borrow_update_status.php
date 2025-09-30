<?php
// functions/borrow_update_status.php
require __DIR__ . '/dbconn.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$newStatus = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
$oldStatus = isset($_POST['old_status']) ? trim($_POST['old_status']) : '';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid id']);
    exit;
}

if (!in_array($newStatus, ['Pending', 'Borrowed', 'Returned', 'Rejected'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    $conn->begin_transaction();

    // Lock the borrow_requests row
    $sel = $conn->prepare("SELECT id, status, equipment_sn, qty FROM borrow_requests WHERE id = ? FOR UPDATE");
    if (!$sel) throw new Exception('Prepare failed (select borrow_requests): ' . $conn->error);
    $sel->bind_param('i', $id);
    if (!$sel->execute()) throw new Exception('Execute failed (select borrow_requests): ' . $sel->error);

    $sel->bind_result($sel_id, $sel_status, $sel_esn, $sel_qty);
    if (!$sel->fetch()) {
        $sel->close();
        throw new Exception('Borrow request not found');
    }
    $sel->close();

    $currentStatus = $sel_status ?? '';
    $equipment_sn = trim($sel_esn ?? '');
    $qty = (int)($sel_qty ?? 0);

    // Validate status transition
    if ($currentStatus === $newStatus) {
        throw new Exception('Status is already ' . $newStatus);
    }

    // Handle quantity adjustments based on status transitions
    $needsQtyAdjustment = false;
    $qtyChange = 0;

    // Status transitions that affect quantity:
    // Pending/Borrowed -> Returned: restore qty (+qty)
    // Pending/Borrowed -> Rejected: restore qty (+qty)
    // Returned/Rejected -> Pending/Borrowed: decrease qty (-qty)

    if (($currentStatus === 'Pending' || $currentStatus === 'Borrowed') && 
        ($newStatus === 'Returned' || $newStatus === 'Rejected')) {
        // Restore quantity
        $needsQtyAdjustment = true;
        $qtyChange = $qty; // positive = increase
    } elseif (($currentStatus === 'Returned' || $currentStatus === 'Rejected') && 
              ($newStatus === 'Pending' || $newStatus === 'Borrowed')) {
        // Decrease quantity
        $needsQtyAdjustment = true;
        $qtyChange = -$qty; // negative = decrease
    }

    // Apply quantity adjustment if needed
    if ($needsQtyAdjustment) {
        if ($qtyChange > 0) {
            // Restore quantity (cap at total_qty)
            $updEq = $conn->prepare("UPDATE equipment_list SET available_qty = LEAST(IFNULL(available_qty,0) + ?, total_qty) WHERE equipment_sn = ?");
        } else {
            // Decrease quantity
            $updEq = $conn->prepare("UPDATE equipment_list SET available_qty = available_qty - ? WHERE equipment_sn = ?");
            $qtyChange = abs($qtyChange);
        }
        
        if (!$updEq) throw new Exception('Prepare failed (update equipment): ' . $conn->error);
        $updEq->bind_param('is', $qtyChange, $equipment_sn);
        if (!$updEq->execute()) throw new Exception('Failed to update equipment quantity: ' . $updEq->error);
        $updEq->close();
    }

    // Update borrow_requests status
    $timestamp_col = '';
    if ($newStatus === 'Returned') {
        $timestamp_col = ', returned_at = NOW()';
    } elseif ($newStatus === 'Rejected') {
        $timestamp_col = ', rejected_at = NOW()';
    }

    $updBr = $conn->prepare("UPDATE borrow_requests SET status = ? {$timestamp_col} WHERE id = ?");
    if (!$updBr) throw new Exception('Prepare failed (update borrow_requests): ' . $conn->error);
    $updBr->bind_param('si', $newStatus, $id);
    if (!$updBr->execute()) throw new Exception('Failed to update borrow request: ' . $updBr->error);
    $updBr->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    error_log('borrow_update_status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
} finally {
    if ($conn) $conn->close();
}

echo json_encode(['success' => false, 'error' => 'Unknown error']);
exit;
?>