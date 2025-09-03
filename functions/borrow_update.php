<?php
// functions/borrow_update.php
require __DIR__ . '/dbconn.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Optional: simple auth check (uncomment and adapt)
// session_start();
// if (!in_array($_SESSION['role'] ?? '', ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper'], true)) {
//   http_response_code(403);
//   echo json_encode(['success' => false, 'error' => 'Unauthorized']);
//   exit;
// }

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$resident_name = array_key_exists('resident_name', $_POST) ? trim($_POST['resident_name']) : null;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid id']);
    exit;
}

try {
    $conn->begin_transaction();

    // 1) Lock the borrow_requests row to prevent race conditions
    $sel = $conn->prepare("SELECT id, status, equipment_sn, qty FROM borrow_requests WHERE id = ? FOR UPDATE");
    if (!$sel) throw new Exception('Prepare failed (select borrow_requests): ' . $conn->error);
    $sel->bind_param('i', $id);
    if (!$sel->execute()) throw new Exception('Execute failed (select borrow_requests): ' . $sel->error);

    // Fetch result reliably
    $sel->bind_result($sel_id, $sel_status, $sel_esn, $sel_qty);
    if (!$sel->fetch()) {
        $sel->close();
        throw new Exception('Borrow request not found');
    }
    $sel->close();

    $currentStatus = $sel_status ?? '';
    $equipment_sn = trim($sel_esn ?? ''); // ✅ ensure exact match
    $qty = (int)($sel_qty ?? 0);

    // Optional: update resident_name if provided
    if ($resident_name !== null) {
        $upd_name = $conn->prepare("UPDATE borrow_requests SET resident_name = ? WHERE id = ?");
        if (!$upd_name) throw new Exception('Prepare failed (update resident_name): ' . $conn->error);
        $upd_name->bind_param('si', $resident_name, $id);
        if (!$upd_name->execute()) {
            $upd_name->close();
            throw new Exception('Failed to update resident name: ' . $upd_name->error);
        }
        $upd_name->close();
    }

    if ($action === 'return') {
        // 2) Validate status
        if ($currentStatus !== 'Borrowed') {
            throw new Exception('Only requests with status "Borrowed" can be marked returned');
        }

        // 3) Update borrow_requests status -> Returned
        $u1 = $conn->prepare("UPDATE borrow_requests 
                              SET status = 'Returned', returned_at = NOW() 
                              WHERE id = ?");
        if (!$u1) throw new Exception('Prepare failed (update borrow_requests): ' . $conn->error);
        $u1->bind_param('i', $id);
        if (!$u1->execute()) {
            $u1->close();
            throw new Exception('Failed to update borrow_requests: ' . $u1->error);
        }
        $u1->close();

        // 4) Lock equipment row and increment available_qty
        $s = $conn->prepare("SELECT equipment_sn FROM equipment_list WHERE equipment_sn = ? FOR UPDATE");
        if ($s) {
            $s->bind_param('s', $equipment_sn);
            if (!$s->execute()) {
                $s->close();
                throw new Exception('Failed to lock equipment row: ' . $s->error);
            }
            $found = $s->fetch();
            $s->close();

            if ($found !== null) {
                // ✅ increment but cap at total_qty
                $u2 = $conn->prepare("
                    UPDATE equipment_list 
                    SET available_qty = LEAST(IFNULL(available_qty,0) + ?, total_qty)
                    WHERE equipment_sn = ?
                ");
                if (!$u2) throw new Exception('Prepare failed (increment equipment_list): ' . $conn->error);
                $u2->bind_param('is', $qty, $equipment_sn);
                if (!$u2->execute()) {
                    $u2->close();
                    throw new Exception('Failed to increment equipment available_qty: ' . $u2->error);
                }
                if ($u2->affected_rows === 0) {
                    error_log("borrow_update: Warning — no equipment row was updated for ESN={$equipment_sn}");
                }
                $u2->close();
            } else {
                error_log("borrow_update: equipment row not found for ESN={$equipment_sn}. Borrow marked returned but availability not updated.");
            }
        } else {
            // Fallback without row lock (less safe)
            $u2 = $conn->prepare("
                UPDATE equipment_list 
                SET available_qty = LEAST(IFNULL(available_qty,0) + ?, total_qty)
                WHERE equipment_sn = ?
            ");
            if ($u2) {
                $u2->bind_param('is', $qty, $equipment_sn);
                if (!$u2->execute()) {
                    $u2->close();
                    throw new Exception('Failed to increment equipment available_qty (no-lock fallback): ' . $u2->error);
                }
                $u2->close();
            } else {
                throw new Exception('Failed to prepare equipment update and cannot lock equipment row: ' . $conn->error);
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Marked as returned']);
        exit;
    }

    // Commit for non-return updates
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Updated']);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    error_log('borrow_update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
} finally {
    if ($conn) $conn->close();
}

echo json_encode(['success' => false, 'error' => 'Unknown error']);
exit;
?>
