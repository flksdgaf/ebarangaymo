<?php
// functions/update_remarks.php
require_once 'dbconn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
  || empty($_POST['full_name'])
  || !isset($_POST['purok'], $_POST['remarks'])) {
  http_response_code(400);
  exit('Invalid request');
}

$name   = trim($_POST['full_name']);
$purok  = (int) $_POST['purok'];
$remarks= trim($_POST['remarks']);

// Validate purok
if ($purok < 1 || $purok > 6) {
  http_response_code(400);
  exit('Bad purok');
}

$table = "purok{$purok}_rbi";

// Update by full_name
$stmt = $conn->prepare("
  UPDATE `$table`
    SET remarks = ?
  WHERE full_name = ?
");
$stmt->bind_param("ss", $remarks, $name);
$stmt->execute();
$stmt->close();

// Return success
echo json_encode(['success' => true]);
