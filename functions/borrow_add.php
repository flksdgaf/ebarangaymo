<?php
// functions/borrow_add.php
require '../functions/dbconn.php';

// 1) Collect & sanitize inputs
$resident = trim($_POST['resident_name'] ?? '');
$esn = trim($_POST['equipment_sn'] ?? '');
$qty = (int) $_POST['qty'] ?? 0;
$location = trim($_POST['location'] ?? '');
$used_for = trim($_POST['used_for'] ?? '');
$pudo = trim($_POST['pudo'] ?? '');

// Redirect helper
function redirect($params = []) {
    $qs = http_build_query($params);
    header("Location: ../adminPanel.php?page=adminEquipmentBorrowing&{$qs}");
    exit;
}

// 2) Basic validation
if (!$resident || !$esn || $qty < 1 || !$location || !$used_for || !$pudo) {
    redirect(['borrow_error' => 'missing']);
}

// 3) Check that equipment exists and enough available
$stmt = $conn->prepare("SELECT id, available_qty FROM equipment_list WHERE equipment_sn = ?");
$stmt->bind_param('s', $esn);
$stmt->execute();
$stmt->bind_result($equipId, $availQty);
if (!$stmt->fetch()) {
    $stmt->close();
    redirect(['borrow_error' => 'notfound']);
}
$stmt->close();

if ($qty > $availQty) {
    redirect(['borrow_error' => 'toomany']);
}

// 4) Begin transaction
$conn->begin_transaction();

try {
    // 5) Insert into borrow_requests
    $stmt = $conn->prepare("INSERT INTO borrow_requests (resident_name, equipment_sn, qty, location, used_for, date, pudo, status) VALUES (?, ?, ?, ?, ?, NOW(), ?, 'Borrowed')");
    $stmt->bind_param('ssisss', $resident, $esn, $qty, $location, $used_for, $pudo);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    // 6) Update available_qty
    $stmt = $conn->prepare("UPDATE equipment_list SET available_qty = available_qty - ? WHERE id = ?");
    $stmt->bind_param('ii', $qty, $equipId);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    // 7) Commit and redirect success
    $conn->commit();
    redirect(['borrowed' => '1']);

} catch (Exception $e) {
    // Roll back on error
    $conn->rollback();
    error_log("Borrow insert failed: " . $e->getMessage());
    redirect(['borrow_error' => 'db']);
}
?>
