<?php
require_once 'dbconn.php';
header('Content-Type: text/plain');

if (empty($_GET['coin'])) {
  http_response_code(400);
  echo "Missing parameters";
  exit;
}

// $id   = $conn->real_escape_string($_GET['device_id']);
$coin = intval($_GET['coin']);
$col = '';

if ($coin === 1)  $col = 'one_peso';
elseif ($coin === 5)  $col = 'five_peso';
elseif ($coin === 10) $col = 'ten_peso';
elseif ($coin === 20) $col = 'twenty_peso';
else {
  http_response_code(400);
  echo "Invalid coin";
  exit;
}

$sql = "
  UPDATE device_management
     SET {$col} = {$col} + 1
   WHERE device_id = 'IOTPS-Magang-01'
";
if (! $conn->query($sql)) {
  http_response_code(500);
  echo "DB error: " . $conn->error;
  exit;
}
echo "OK";
$conn->close();
