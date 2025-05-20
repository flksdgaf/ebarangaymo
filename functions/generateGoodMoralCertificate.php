<?php
// functions/generateGoodMoralCertificate.php

session_start();
require_once __DIR__ . '/dbconn.php';

// 1) Validate transaction_id
$tid = $_GET['transaction_id'] ?? '';
if (!$tid) exit('No transaction ID provided.');

// 2) Fetch good moral record
$stmt = $conn->prepare(
    "SELECT full_name, age, civil_status, purok, claim_date, purpose
       FROM good_moral_requests
      WHERE transaction_id = ? LIMIT 1"
);
$stmt->bind_param('s', $tid);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) exit('Record not found.');
$data = $res->fetch_assoc();
$stmt->close();
$conn->close();

// 3) Sanitize & assign
$name    = htmlspecialchars($data['full_name']);
$age     = (int) $data['age'];
$civil   = htmlspecialchars($data['civil_status']);
$purok   = htmlspecialchars($data['purok']);
$purpose = htmlspecialchars($data['purpose']);
$claimDt = new DateTime($data['claim_date']);

// 4) Format date components
$day = (int)$claimDt->format('j');
function ordSuffix($n) {
    if (!in_array($n % 100, [11,12,13])) {
        switch ($n % 10) {
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
        }
    }
    return 'th';
}
$dayFmt   = $day . ordSuffix($day);
$monthFmt = $claimDt->format('F');
$yearFmt  = $claimDt->format('Y');

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    @page { margin: .5in; }
    html, body { height:100vh; margin:0; display:flex; align-items:center; justify-content:center; }
    .container { max-width:6.5in; width:100%; font-family:"Times New Roman", serif; }
    .center { text-align:center; }
    .start  { text-align:start; }
    .title { font-size:20px; font-weight:bold; margin:1em 0; text-transform:uppercase; }
    .subtitle { font-size:12px; margin-bottom:1em; font-weight:bold; text-transform:uppercase; }
    .content { text-align:justify; line-height:1.5; margin:1em 0; font-size:12pt; text-indent:2em; }
    .underline { text-decoration:underline; }
    .text-upper { text-transform:uppercase; }
  </style>
</head>
<body>
  <div class="container">
    <div class="center title">Certification</div>
    <div class="start subtitle">To Whom It May Concern:</div>

    <div class="content">
      This is to certify that <strong class="underline"><?= $name ?></strong>, <strong><?= $age ?></strong> years old, <strong><?= $civil ?></strong>, is a resident of
      <strong><?= $purok ?></strong>, Barangay Magang, Daet, Camarines Norte.
    </div>

    <div class="content">
      This certifies further that the above-named person is known to me 
      of <strong class="text-upper">good moral character</strong> and that <strong> he/she has no derogatory 
      record </strong> on file in this Barangay.
    </div>

    <div class="content">
      This certification is issued upon request of the above-named person 
      for <strong class="text-upper"><?= $purpose ?></strong> purposes.
    </div>

    <div class="content">
      Issued this <strong><?= $dayFmt ?></strong> day of
      <strong><?= $monthFmt ?>, <?= $yearFmt ?></strong> at Barangay Magang, Daet, 
      Camarines Norte.
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => window.print());
  </script>
</body>
</html>