<?php
// functions/generateResidencyCertificate.php

session_start();
require_once __DIR__ . '/dbconn.php';

// 1) Validate transaction_id
$tid = $_GET['transaction_id'] ?? '';
if (!$tid) exit('No transaction ID provided.');

// 2) Fetch record
$stmt = $conn->prepare(
    "SELECT full_name, age, civil_status, purok, residing_years, claim_date, purpose
     FROM residency_requests
     WHERE transaction_id = ? LIMIT 1"
);
$stmt->bind_param('s', $tid);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) exit('Record not found.');
$data = $res->fetch_assoc();
$stmt->close();

// 3) Sanitize & assign
$name    = htmlspecialchars($data['full_name']);
$age     = (int) $data['age'];
$civil   = htmlspecialchars($data['civil_status']);
$purok   = htmlspecialchars($data['purok']);
$years   = (int) $data['residing_years'];
$purpose = htmlspecialchars($data['purpose']);
$claimDt = new DateTime($data['claim_date']);

// 4) Format dates
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

// 5) Number to words converter
function numberToWords($num) {
    $ones = [
        '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven',
        'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen',
        'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'
    ];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty'];

    if ($num < 20) return $ones[$num];
    if ($num < 60) {
        $t = (int)($num / 10);
        $o = $num % 10;
        return $tens[$t] . ($o ? '-' . $ones[$o] : '');
    }
    return (string)$num; // fallback for numbers > 59
}
$yearsInWords = numberToWords($years);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    /* 1. Print margins */
    @page {
      margin: .5in;
    }
    /* 2. Centering container on screen */
    html, body {
      height: 100vh;          /* fill viewport */
      margin: 0;              /* no default browser margins */
      display: flex;
      align-items: center;    /* vertical center */
      justify-content: center;/* horizontal center */
    }
    /* 3. Constrain width so text doesn’t run full-bleed */
    .container {
      max-width: 6.5in;       /* about letter-width minus margins */
      width: 100%;
    }

    /* your existing styles… */
    body {
      font-family: "Times New Roman", serif;
    }
    .center  { text-align: center; }
    .start   { text-align: start; }
    .title {
      font-size: 20px;
      font-weight: bold;
      margin: 1em 0;
      text-transform: uppercase;
    }
    .subtitle {
      font-size: 12px;
      margin-bottom: 1em;
      font-weight: bold;
      text-transform: uppercase;
    }
    .content {
      text-align: justify;
      line-height: 1.5;
      margin: 1em 0;
      font-size: 12pt;
      text-indent: 2em; 
    }
    .underline { text-decoration: underline; }
    .text-upper { text-transform: uppercase; }
  </style>
</head>
<body>
  <div class="container">
    <div class="center title">Certificate of Residency</div>
    <div class="start subtitle">To Whom It May Concern:</div>

    <div class="content">
      This is to certify that <strong class="underline"><?= $name ?></strong>, 
      <strong><?= $age ?></strong> years old, <strong><?= $civil ?></strong>, is a bonafide
      resident of <strong><?= $purok ?></strong>, Barangay Magang, Daet Camarines Norte.
    </div>

    <div class="content">
      This is to certify further that the said persons have been residing in this 
      barangay for <strong><?= $yearsInWords ?></strong> (<strong><?= $years ?></strong>) years.
    </div>

    <div class="content">
      This certification is issued this <strong><?= $dayFmt ?></strong> day of 
      <strong><?= $monthFmt ?>, <?= $yearFmt ?></strong> at Barangay Magang, 
      Daet, Camarines Norte upon the request of the interested party for 
      <strong class="text-upper"><?= $purpose ?></strong> purposes.
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      window.print();
    });
  </script>
</body>
</html>