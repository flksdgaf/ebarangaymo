<?php
// functions/equipment_delete.php
require '../functions/dbconn.php';

$equipment_sn = trim($_POST['equipment_sn'] ?? '');
if (!$equipment_sn) {
    header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&deleted=0');
    exit;
}

// 1) Check if equipment exists and get name for logging
$stmt = $conn->prepare("SELECT name FROM equipment_list WHERE equipment_sn = ?");
$stmt->bind_param('s', $equipment_sn);
$stmt->execute();
$stmt->bind_result($equipName);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&deleted=0');
    exit;
}
$stmt->close();

// 2) See if any borrow_requests reference it
$stmt = $conn->prepare("SELECT COUNT(*) FROM borrow_requests WHERE equipment_sn = ?");
$stmt->bind_param('s', $equipment_sn);
$stmt->execute();
$stmt->bind_result($cnt);
$stmt->fetch();
$stmt->close();

if ($cnt > 0) {
    // Abort deletion if it has borrow history
    header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&deleted=0&delete_error=borrowed');
    exit;
}

// Activity logging (before deletion)
session_start();
$admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer'];
if (isset($_SESSION['loggedInUserRole']) && in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
    if ($logStmt) {
        $admin_id = (int)$_SESSION['loggedInUserID'];
        $roleName = $_SESSION['loggedInUserRole'];
        $action = 'DELETE';
        $table_name = 'equipment_list';
        $record_id = $equipment_sn;
        $description = 'Deleted Equipment: ' . $equipName . ' (' . $equipment_sn . ')';
        
        $logStmt->bind_param('isssss', $admin_id, $roleName, $action, $table_name, $record_id, $description);
        $logStmt->execute();
        $logStmt->close();
    }
}

// 3) No borrows → safe to delete
$stmt = $conn->prepare("DELETE FROM equipment_list WHERE equipment_sn = ?");
$stmt->bind_param('s', $equipment_sn);
$stmt->execute();
$stmt->close();

header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&deleted=1');
exit;
?>
