<?php
// functions/add_equipment.php
require '../functions/dbconn.php';

$name = trim($_POST['name']);
$desc = trim($_POST['description']);
$total = (int)$_POST['total_qty'];

// 1) Figure out next SN in PHP
//    We strip off the "EQ-" prefix, cast to integer, take max, +1
$row = $conn->query("
  SELECT MAX(CAST(SUBSTRING(equipment_sn, 4) AS UNSIGNED)) AS maxnum FROM equipment_list
")->fetch_assoc();
$nextNum = (int)$row['maxnum'] + 1;
$equipmentSn = 'EQ-' . str_pad($nextNum, 7, '0', STR_PAD_LEFT);

// 2) Now insert, explicitly passing equipment_sn
$stmt = $conn->prepare("
  INSERT INTO equipment_list (equipment_sn, name, description, total_qty, available_qty) VALUES (?, ?, ?, ?, ?)
");
$avail = $total; // start available == total
$stmt->bind_param('sssii', $equipmentSn, $name, $desc, $total, $avail);
$stmt->execute();
$stmt->close();

// Activity logging
session_start();
$admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer'];
if (isset($_SESSION['loggedInUserRole']) && in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
    if ($logStmt) {
        $admin_id = (int)$_SESSION['loggedInUserID'];
        $roleName = $_SESSION['loggedInUserRole'];
        $action = 'CREATE';
        $table_name = 'equipment_list';
        $record_id = $equipmentSn;
        $description = 'Created Equipment: ' . $name . ' (' . $equipmentSn . ')';
        
        $logStmt->bind_param('isssss', $admin_id, $roleName, $action, $table_name, $record_id, $description);
        $logStmt->execute();
        $logStmt->close();
    }
}

// 3) Redirect back
// header('Location: ../adminPanel.php?page=adminEquipmentBorrowing');
header('Location: ../adminPanel.php?page=adminEquipmentBorrowing&added=' . urlencode($equipmentSn));
exit;
?>
