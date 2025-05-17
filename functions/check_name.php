<?php
require_once 'dbconn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['name'])) {
  http_response_code(400);
  echo json_encode(['error'=>'Bad request']);
  exit;
}

$name = trim($_POST['name']);
$purokMap = [
  'Purok 1'=>'purok1_rbi',
  'Purok 2'=>'purok2_rbi',
  'Purok 3'=>'purok3_rbi',
  'Purok 4'=>'purok4_rbi',
  'Purok 5'=>'purok5_rbi',
  'Purok 6'=>'purok6_rbi'
];

// look through each table for that exact full_name
foreach ($purokMap as $label => $table) {
    $stmt = $conn->prepare("SELECT account_ID FROM `$table` WHERE full_name = ? LIMIT 1");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 1) {
        $row = $res->fetch_assoc();
        echo json_encode([
          'found'  => true,
          'purok'  => $label,
          'existingAccountId' => $row['account_ID']
        ]);
        exit;
    }
}
echo json_encode(['found'=>false]);