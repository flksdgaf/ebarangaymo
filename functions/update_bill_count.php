<?php
require_once 'dbconn.php';
header('Content-Type: text/plain');

if (empty($_GET['bill'])) {
  http_response_code(400);
  echo "Missing parameters";
  exit;
}

// $id   = $conn->real_escape_string($_GET['device_id']);
$bill = intval($_GET['bill']);
$col = '';

if ($bill === 20)  $col = 'twenty_bill';
elseif ($bill === 50)  $col = 'fifty_bill';
elseif ($bill === 100) $col = 'one_hundred_bill';
elseif ($bill === 200) $col = 'two_hundred_bill';
else {
  http_response_code(400);
  echo "Invalid bill";
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
