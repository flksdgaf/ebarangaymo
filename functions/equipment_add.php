<?php
// functions/add_equipment.php
require '../functions/dbconn.php';

$name  = trim($_POST['name']);
$desc  = trim($_POST['description']);
$total = (int)$_POST['total_qty'];

// 1) Figure out next SN in PHP
//    We strip off the "EQ-" prefix, cast to integer, take max, +1
$row = $conn->query("
    SELECT MAX(CAST(SUBSTRING(equipment_sn, 4) AS UNSIGNED)) AS maxnum
      FROM equipment_list
")->fetch_assoc();
$nextNum   = (int)$row['maxnum'] + 1;
$equipmentSn = 'EQ-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

// 2) Now insert, explicitly passing equipment_sn
$stmt = $conn->prepare("
  INSERT INTO equipment_list 
    (equipment_sn, name, description, total_qty, available_qty)
  VALUES (?, ?, ?, ?, ?)
");
$avail = $total; // start available == total
$stmt->bind_param('sssii', $equipmentSn, $name, $desc, $total, $avail);
$stmt->execute();
$stmt->close();

// 3) Redirect back
header('Location: ../adminPanel.php?page=adminHistory');
exit;
