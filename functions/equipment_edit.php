<?php
// functions/equipment_edit.php
require '../functions/dbconn.php';

$id    = (int)$_POST['id'];
$name  = trim($_POST['name']);
$desc  = trim($_POST['description']);
$total = (int)$_POST['total_qty'];

// 1) Fetch the current available_qty & total_qty
$stmt = $conn->prepare("SELECT available_qty, total_qty FROM equipment_list WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($currAvail, $currTotal);
if (!$stmt->fetch()) {
    // no such record
    $stmt->close();
    header('HTTP/1.1 404 Not Found');
    exit("Equipment #{$id} not found");
}
$stmt->close();

// 2) Decide which columns to update
if ($currAvail === $currTotal) {
    // no one is borrowing right now → it's safe to change both total & available
    $newAvail = $total;  // reset available to the new total
    $sql = "
      UPDATE equipment_list
         SET name = ?, 
             description = ?, 
             total_qty = ?, 
             available_qty = ?
       WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssiii', $name, $desc, $total, $newAvail, $id);
} else {
    // some items are checked out → only allow name & description changes
    $sql = "
      UPDATE equipment_list
         SET name = ?, 
             description = ?
       WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $name, $desc, $id);
}

// 3) Execute & redirect
if (!$stmt->execute()) {
    error_log("Failed to update equipment #{$id}: " . $stmt->error);
    header('HTTP/1.1 500 Internal Server Error');
    exit("Update failed");
}

$stmt->close();
header('Location: ../adminPanel.php?page=adminHistory');
exit;
