<?php
// functions/equipment_delete.php
require '../functions/dbconn.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&deleted=0');
    exit;
}

// 1) Find this equipment's SN
$stmt = $conn->prepare("SELECT equipment_sn FROM equipment_list WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($esn);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&deleted=0');
    exit;
}
$stmt->close();

// 2) See if any borrow_requests reference it
$stmt = $conn->prepare("SELECT COUNT(*) FROM borrow_requests WHERE equipment_sn = ?");
$stmt->bind_param('s', $esn);
$stmt->execute();
$stmt->bind_result($cnt);
$stmt->fetch();
$stmt->close();

if ($cnt > 0) {
    // Abort deletion if it has borrow history
    header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&deleted=0&delete_error=borrowed');
    exit;
}

// 3) No borrows → safe to delete
$stmt = $conn->prepare("DELETE FROM equipment_list WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&deleted=1');
exit;
?>
