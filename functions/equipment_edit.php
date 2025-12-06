<?php
// functions/equipment_edit.php
require '../functions/dbconn.php';

$equipment_sn = trim($_POST['equipment_sn'] ?? '');
$name = trim($_POST['name']);
$desc = trim($_POST['description']);
$total = (int)$_POST['total_qty'];

if (!$equipment_sn) {
    header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&updated=error&error_msg=' . urlencode("Equipment serial number is required."));
    exit;
}

// 1) Fetch the current values
$stmt = $conn->prepare("SELECT name, description, total_qty, available_qty FROM equipment_list WHERE equipment_sn = ?");
$stmt->bind_param('s', $equipment_sn);
$stmt->execute();
$stmt->bind_result($currName, $currDesc, $currTotal, $currAvail);
if (!$stmt->fetch()) {
    $stmt->close();
    header('HTTP/1.1 404 Not Found');
    exit("Equipment {$equipment_sn} not found");
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
$sql = "UPDATE equipment_list SET name = ?, description = ?, total_qty = ?, available_qty = ? WHERE equipment_sn = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssiis', $name, $desc, $total, $newAvail, $equipment_sn);

// 6) Execute & redirect
if (!$stmt->execute()) {
    error_log("Failed to update equipment {$equipment_sn}: " . $stmt->error);
    header('HTTP/1.1 500 Internal Server Error');
    exit("Update failed");
}

$stmt->close();

// Activity logging
session_start();
$admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer'];
if (isset($_SESSION['loggedInUserRole']) && in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
    if ($logStmt) {
        $admin_id = (int)$_SESSION['loggedInUserID'];
        $roleName = $_SESSION['loggedInUserRole'];
        $action = 'UPDATE';
        $table_name = 'equipment_list';
        $record_id = $equipment_sn;
        $description = 'Updated Equipment: ' . $name . ' (' . $equipment_sn . ')';
        
        $logStmt->bind_param('isssss', $admin_id, $roleName, $action, $table_name, $record_id, $description);
        $logStmt->execute();
        $logStmt->close();
    }
}

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
