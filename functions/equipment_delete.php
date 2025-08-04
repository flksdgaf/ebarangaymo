<?php
require '../functions/dbconn.php';
$id = (int)$_POST['id'];

$stmt = $conn->prepare("DELETE FROM equipment_list WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&deleted=1');
exit;
?>
