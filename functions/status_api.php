<?php
// status_api.php
require_once __DIR__ . '/dbconn.php';
header('Content-Type: application/json');

$deviceName = 'IOTPS-Magang-01';

// 1) Device status
$stmt = $conn->prepare("
  SELECT last_seen, TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS secs_ago
    FROM collection_table
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

// 2) Fetch coin and bill counts from collection_table
$stmt = $conn->prepare("
  SELECT one_peso, five_peso, ten_peso, twenty_peso,
         twenty_bill, fifty_bill, one_hundred_bill, two_hundred_bill
    FROM collection_table
   WHERE device_id = ?
   LIMIT 1
");
$stmt->bind_param('s', $deviceName);
$stmt->execute();
$stmt->bind_result($c1, $c5, $c10, $c20, $b20, $b50, $b100, $b200);
$stmt->fetch();
$stmt->close();

// 3) Compute total
$total =
    ($c1   * 1) +
    ($c5   * 5) +
    ($c10  * 10) +
    ($c20  * 20) +
    ($b20  * 20) +
    ($b50  * 50) +
    ($b100 * 100) +
    ($b200 * 200);

echo json_encode([
  // Heartbeat
  'statusText'   => $statusText,
  'statusClass'  => $statusClass,
  'iconClass'    => $iconClass,
  'timestamp'    => $timestamp,

  // Coin counts
  'one_peso'     => $c1,
  'five_peso'    => $c5,
  'ten_peso'     => $c10,
  'twenty_peso'  => $c20,

  // Bill counts
  'twenty_bill'      => $b20,
  'fifty_bill'       => $b50,
  'one_hundred_bill' => $b100,
  'two_hundred_bill' => $b200,

  // Correct total
  'total_amount' => number_format($total, 2)
]);

$conn->close();
