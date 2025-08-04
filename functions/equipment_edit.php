<?php
// functions/equipment_edit.php
require '../functions/dbconn.php';

$id = (int)$_POST['id'];
$name = trim($_POST['name']);
$desc = trim($_POST['description']);
$total = (int)$_POST['total_qty'];

// 1) Fetch the current values
$stmt = $conn->prepare("SELECT name, description, total_qty, available_qty FROM equipment_list WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($currName, $currDesc, $currTotal, $currAvail);
if (!$stmt->fetch()) {
    $stmt->close();
    header('HTTP/1.1 404 Not Found');
    exit("Equipment #{$id} not found");
}
$stmt->close();

// 2) Check for no changes
$isSameName  = ($name === $currName);
$isSameDesc  = ($desc === $currDesc);
$isSameTotal = ($total === $currTotal);

if ($isSameName && $isSameDesc && $isSameTotal) {
    header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&updated=none');
    exit;
}

// 3) Decide which columns to update
if ($currAvail === $currTotal) {
    // No one is borrowing → safe to update all
    $newAvail = $total;
    $sql = "UPDATE equipment_list SET name = ?, description = ?, total_qty = ?, available_qty = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssiii', $name, $desc, $total, $newAvail, $id);
    $resultFlag = 'full';
} else {
    // Some are borrowed → only update name & desc
    $sql = "UPDATE equipment_list SET name = ?, description = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $name, $desc, $id);
    $resultFlag = 'partial';
}

// 4) Execute & redirect
if (!$stmt->execute()) {
    error_log("Failed to update equipment #{$id}: " . $stmt->error);
    header('HTTP/1.1 500 Internal Server Error');
    exit("Update failed");
}

$stmt->close();
header("Location: ../adminPanel.php?page=adminEquipmentBorrowing&updated={$resultFlag}");
exit;
?>
