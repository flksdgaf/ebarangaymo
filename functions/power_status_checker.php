<?php
// heartbeat.php
require_once 'dbconn.php';

header('Content-Type: text/plain');

if (empty($_GET['device_name'])) {
  http_response_code(400);
  echo "missing device_name";
  exit;
}

$id = $conn->real_escape_string($_GET['device_name']);

// Upsert last_seen for this device_name
$sql = "
  INSERT INTO device_management (device_name, last_seen)
    VALUES ('{$id}', NOW())
  ON DUPLICATE KEY
    UPDATE last_seen = NOW()
";

if (! $conn->query($sql)) {
  http_response_code(500);
  echo "DB error: " . $conn->error;
  exit;
}

$conn->close();
echo "OK";
