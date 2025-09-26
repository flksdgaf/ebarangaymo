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

// 3) Calculate the new available quantity based on borrowed items
$borrowedQty = $currTotal - $currAvail; // How many are currently borrowed
$newAvail = $total - $borrowedQty; // New available = new total - borrowed

// 4) Validate that the new total is not less than borrowed quantity
if ($total < $borrowedQty) {
    // Cannot set total quantity less than the number currently borrowed
    header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&updated=error&error_msg=' . urlencode("Cannot set total quantity to {$total} because {$borrowedQty} items are currently borrowed."));
    exit;
}

// 5) Update all fields including total_qty and recalculated available_qty
$sql = "UPDATE equipment_list SET name = ?, description = ?, total_qty = ?, available_qty = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssiii', $name, $desc, $total, $newAvail, $id);

// 6) Execute & redirect
if (!$stmt->execute()) {
    error_log("Failed to update equipment #{$id}: " . $stmt->error);
    header('HTTP/1.1 500 Internal Server Error');
    exit("Update failed");
}

$stmt->close();

// 7) Determine the result flag for user feedback
if ($borrowedQty > 0 && !$isSameTotal) {
    // Total quantity was changed while items were borrowed
    $resultFlag = 'partial_with_borrowed';
} else {
    // Normal full update
    $resultFlag = 'full';
}

header("Location: ../adminPanel.php?page=adminEquipmentBorrowing&updated={$resultFlag}");
exit;
?>
