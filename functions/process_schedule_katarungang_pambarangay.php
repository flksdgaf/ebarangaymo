<?php
// functions/process_schedule_katarungang_pambarangay.php

require 'dbconn.php';
session_start();
if (!($_SESSION['auth'] ?? false)) {
  header('HTTP/1.1 403 Forbidden');
  exit('Not authorized');
}

$pageNum = $_POST['katarungan_page'] ?? 1;

// 1) Grab POST
$txn = $_POST['transaction_id'] ?? '';
$currStage = $_POST['current_stage'] ?? '';
$nextDate = $_POST['next_date'] ?? '';
$nextTime = $_POST['next_time'] ?? '';

if (!$txn || !$currStage || !$nextDate || !$nextTime) {
  exit('Missing data');
}

// 2) Map to next stage
$map = [
  'Punong Barangay' => 'Unang Patawag',
  'Unang Patawag' => 'Ikalawang Patawag',
  'Ikalawang Patawag' => 'Ikatlong Patawag',
];
if (! isset($map[$currStage])) {
  exit('Invalid current_stage');
}
$nextStage = $map[$currStage];

// 3) Build the column name (`schedule_unang_patawag`, etc.)
$colSuffix = strtolower(str_replace(' ', '_', $nextStage));
$scheduleCol = "schedule_{$colSuffix}";

// 4) Combine date + time
$dt = $nextDate . ' ' . $nextTime;

// 5) UPDATE the KP record’s schedule _and_ complaint_stage
$sql = "UPDATE katarungang_pambarangay_records SET {$scheduleCol} = ?, complaint_stage = ? WHERE transaction_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
  die("Prepare failed: " . $conn->error);
}
$dt = $nextDate . ' ' . $nextTime;
if (!$stmt->bind_param('sss', $dt, $nextStage, $txn)) {
  die("bind_param failed: " . $stmt->error);
}
if (!$stmt->execute()) {
  die("Execute failed: " . $stmt->error);
}
$stmt->close();

// 6) Update complaint_records.status to the new stage
// $sql2 = "UPDATE complaint_records
//          SET complaint_status = ?
//          WHERE transaction_id = ?";
// $stmt2 = $conn->prepare($sql2);
// if ($stmt2 === false) {
//     die("Prepare2 failed: " . $conn->error);
// }
// if (! $stmt2->bind_param('ss', $nextStage, $txn)) {
//     die("bind_param2 failed: " . $stmt2->error);
// }
// if (! $stmt2->execute()) {
//     die("Execute2 failed: " . $stmt2->error);
// }
// $stmt2->close();

// 7) Redirect back
header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&cleared_tid=" . urlencode($txn));
exit;
?>
