<?php
// functions/generateSoloParentCertificate.php

session_start();
require_once __DIR__ . '/dbconn.php';

// 1) Validate transaction_id
$tid = $_GET['transaction_id'] ?? '';
if (!$tid) exit('No transaction ID provided.');

// 2) Fetch solo parent record
$stmt = $conn->prepare(
    "SELECT full_name, age, civil_status, purok, child_name, child_age, years_solo_parent, claim_date, purpose
       FROM solo_parent_requests
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
$name             = htmlspecialchars($data['full_name']);
$age              = (int) $data['age'];
$civil            = htmlspecialchars($data['civil_status']);
$purok            = htmlspecialchars($data['purok']);
$childName        = htmlspecialchars($data['child_name']);
$childAge         = (int) $data['child_age'];
$yearsSoloParent   = (int) $data['years_solo_parent'];
$purpose          = htmlspecialchars($data['purpose']);
$claimDt          = new DateTime($data['claim_date']);

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

// 5) Convert number to words (for yearsSoloParent)
function numberToWords($num) {
    $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
             'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
             'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty'];
    if ($num < 20) return $ones[$num];
    if ($num < 60) {
        $t = intdiv($num, 10);
        $o = $num % 10;
        return $tens[$t] . ($o ? '-' . $ones[$o] : '');
    }
    return (string)$num;
}
$yearsWords = numberToWords($yearsSoloParent);

// 6) Render HTML
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
    <div class="center title underline">Certificate of Solo Parent</div>
    <div class="start subtitle text-upper">To Whom It May Concern:</div>

    <div class="content">
      This is to certify that <strong><?= $name ?></strong>, <strong><?= $age ?></strong> years old, <strong><?= $civil ?></strong>, is a 
      resident of <strong><?= $purok ?></strong>, Barangay Magang, Daet, Camarines Norte.
    </div>

    <div class="content">
      This is to certify that the said person is a <strong>SOLO PARENT</strong> to his/her 
      children <strong><?= $childName ?></strong> <?= $childAge ?> years old,
      has been <?= strtolower($civil) ?> for <strong><?= $yearsWords ?> (<?= $yearsSoloParent ?>) <?= $yearsSoloParent === 1 ? 'year' : 'years' ?></strong>.
    </div>

    <div class="content">
      Issued this <strong><?= $dayFmt ?></strong> day of <strong><?= $monthFmt ?>, <?= $yearFmt ?></strong> at Barangay Magang, Daet, 
      Camarines Norte for <strong><?= strtoupper($purpose) ?></strong> purposes.
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => window.print());
  </script>
</body>
</html>
