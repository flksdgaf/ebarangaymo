<?php
require 'dbconn.php';

$txn  = $_GET['transaction_id'] ?? '';
$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';

if (!$txn || !$date || !$time) {
  exit('Missing required parameters.');
}

// Fetch relevant details
$stmt = $conn->prepare("
  SELECT c.complainant_name, c.respondent_name, k.complaint_type
  FROM katarungang_pambarangay_records k
  LEFT JOIN complaint_records c ON k.transaction_id = c.transaction_id
  WHERE k.transaction_id = ?
");
$stmt->bind_param('s', $txn);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
  exit('Record not found.');
}

$complainant = htmlspecialchars($data['complainant_name']);
$respondent  = htmlspecialchars($data['respondent_name']);
$subject     = htmlspecialchars($data['complaint_type']);
$summonDate  = date('F j, Y', strtotime($date));
$summonTime  = date('g:i A', strtotime($time));

// Output or render your printable HTML here
?>
<!DOCTYPE html>
<html>
<head>
  <title>Summon Printout</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    h2 { text-align: center; }
    .details { margin-top: 40px; }
  </style>
</head>
<body>
  <h2>Barangay Summon</h2>
  <div class="details">
    <p><strong>Transaction ID:</strong> <?= htmlspecialchars($txn) ?></p>
    <p><strong>Complainant:</strong> <?= $complainant ?></p>
    <p><strong>Respondent:</strong> <?= $respondent ?></p>
    <p><strong>Complaint Subject:</strong> <?= $subject ?></p>
    <p><strong>Scheduled Date:</strong> <?= $summonDate ?></p>
    <p><strong>Scheduled Time:</strong> <?= $summonTime ?></p>
  </div>
</body>
</html>
