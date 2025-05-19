<?php
// status_api.php
require_once __DIR__ . '/dbconn.php';
header('Content-Type: application/json');

$deviceName = 'IOTPS-Magang-01';
$stmt = $conn->prepare("
  SELECT last_seen, TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS secs_ago
    FROM device_management
   WHERE device_id = ?
   LIMIT 1
");
$stmt->bind_param('s', $deviceName);
$stmt->execute();
$stmt->bind_result($lastSeen, $secsAgo);
$stmt->fetch();
$stmt->close();

$isOnline    = ($secsAgo < 3);
$statusText  = $isOnline ? 'On' : 'Off';
$statusClass = $isOnline ? 'text-success' : 'text-danger';
$iconClass   = $isOnline ? 'text-success' : 'text-danger';
$timestamp   = date('m-d-Y H:i:s', strtotime($lastSeen));

// 2) Coin counts
$stmt = $conn->prepare("
  SELECT one_peso, five_peso, ten_peso, twenty_peso
    FROM device_management
   WHERE device_id = ?
   LIMIT 1
");
$stmt->bind_param('s', $deviceName);
$stmt->execute();
$stmt->bind_result($c1, $c5, $c10, $c20);
$stmt->fetch();
$stmt->close();

// 3) Compute total
$total = ($c1 * 1) + ($c5 * 5) + ($c10 * 10) + ($c20 * 20);

echo json_encode([
  // heartbeat
  'statusText'   => $statusText,
  'statusClass'  => $statusClass,
  'iconClass'    => $iconClass,
  'timestamp'    => $timestamp,
  // coin counts
  'one_peso'     => $c1,
  'five_peso'    => $c5,
  'ten_peso'     => $c10,
  'twenty_peso'  => $c20,
  // total collected
  'total_amount' => number_format($total, 2)
]);
$conn->close();
