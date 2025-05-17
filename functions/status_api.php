<?php
require_once __DIR__ . '/dbconn.php';
header('Content-Type: application/json');

$deviceName = 'IOTPS-Magang';
$stmt = $conn->prepare("
  SELECT last_seen, TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS secs_ago
    FROM device_management
   WHERE device_name = ?
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

echo json_encode([
  'statusText'  => $statusText,
  'statusClass' => $statusClass,
  'iconClass'   => $iconClass,
  'timestamp'   => $timestamp
]);
$conn->close();
