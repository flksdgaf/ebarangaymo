<?php
// functions/borrow_add.php
require '../functions/dbconn.php';

// 1) Collect & sanitize inputs
$resident = trim($_POST['resident_name'] ?? '');
$esn = trim($_POST['equipment_sn'] ?? '');
$qty = (int) ($_POST['qty'] ?? 0);
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

// 3) Check that equipment exists and get available (but do NOT decrement here)
$stmt = $conn->prepare("SELECT id, available_qty FROM equipment_list WHERE equipment_sn = ?");
$stmt->bind_param('s', $esn);
$stmt->execute();
$stmt->bind_result($equipId, $availQty);
if (!$stmt->fetch()) {
    $stmt->close();
    redirect(['borrow_error' => 'notfound']);
}
$stmt->close();

// 4) If requested qty is more than currently available, still block at request creation
if ($qty > $availQty) {
    redirect(['borrow_error' => 'toomany']);
}

// 5) Insert as Pending (do not decrement available_qty here)
$stmt = $conn->prepare(
    "INSERT INTO borrow_requests (resident_name, equipment_sn, qty, location, used_for, date, pudo, status)
     VALUES (?, ?, ?, ?, ?, NOW(), ?, 'Pending')"
);
$stmt->bind_param('ssisss', $resident, $esn, $qty, $location, $used_for, $pudo);
$ok = $stmt->execute();
$stmt->close();

if ($ok) redirect(['borrowed' => '1']); 
else redirect(['borrow_error' => 'db']);

?>
