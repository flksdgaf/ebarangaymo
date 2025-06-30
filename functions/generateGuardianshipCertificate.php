<?php
// functions/generateGuardianshipCertificate.php

session_start();
require_once __DIR__ . '/dbconn.php';

$tid = $_GET['transaction_id'] ?? '';
if (!$tid) exit('No transaction ID provided.');

$stmt = $conn->prepare(
    "SELECT full_name, age, civil_status, purok, child_name, claim_date, purpose
     FROM guardianship_requests
     WHERE transaction_id = ? LIMIT 1"
);
$stmt->bind_param('s', $tid);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) exit('Record not found.');
$data = $res->fetch_assoc();
$stmt->close();
$conn->close();

$guardian = htmlspecialchars($data['full_name']);
$age      = (int) $data['age'];
$civil    = htmlspecialchars($data['civil_status']);
$purok    = htmlspecialchars($data['purok']);
$child    = htmlspecialchars($data['child_name']);
$purpose  = htmlspecialchars($data['purpose']);
$claimDt  = new DateTime($data['claim_date']);

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
    html, body {
      margin: 0;
      padding: 0;
      font-family: "Times New Roman", serif;
      font-size: 12pt;
      height: 100%;
    }
    .page {
      position: relative;
      width: 8.5in;
      height: 11in;
      margin: 0 auto;
      padding: 0.5in;
      box-sizing: border-box;
    }
    .container {
      max-width: 6.5in;
      margin: 0 auto;
    }
    .header-row {
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 0.5em;
      gap: 10px;
    }
    .header-text {
      flex: 0 0 auto;
      text-align: center;
      font-size: 12px;
      line-height: 1.4;
    }
    .header-text strong {
      font-size: 14px;
    }
    .leftlogo, .rightlogo {
      width: 70px;
      height: 70px;
      object-fit: contain;
      margin: 0 25px;
    }
    .title {
      text-align: center;
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
      text-align: left;
    }
    .content {
      text-align: justify;
      line-height: 1.5;
      margin: 1em 0;
      text-indent: 2em;
    }
    .underline { text-decoration: underline; }
    .text-upper { text-transform: uppercase; }
    hr {
      border: none;
      border-top: 1px solid #000;
      margin: 0.5em 0 1em 0;
      margin-top: 20px;
      margin-bottom: 90px;
    }
    .footer-signatory {
      text-align: right;
      margin-top: 100px;
      margin-right: 3em;
    }
    .footer-signatory .sign-name {
      font-weight: bold;
    }
    .footer-signatory .sign-position {
      font-size: 11pt;
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="container">
      <!-- HEADER START -->
      <div class="header-row">
        <img src="../images/magang_logo.png" class="leftlogo">
        <div class="header-text">
          <div>Republic of the Philippines</div>
          <div>Province of Camarines Norte</div>
          <div>Municipality of Daet</div>
          <div><strong>Barangay Magang</strong></div>
        </div>
        <img src="../images/good_governance_logo.png" class="rightlogo">
      </div>
      <hr>
      <!-- HEADER END -->

      <div class="title">Certificate of Guardianship</div>
      <div class="subtitle">To Whom It May Concern:</div>

      <div class="content">
        This is to certify that <strong><?= $guardian ?></strong>, <strong><?= $age ?></strong> years old, <strong><?= $civil ?></strong>, is a bonafide 
        resident of <strong><?= $purok ?></strong>, Barangay Magang, Daet, Camarines Norte.
      </div>

      <div class="content">
        This is to certify further that the above-named person is the legal guardian of 
        <strong class="underline"><?= $child ?></strong>.
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

    <!-- FOOTER SIGNATORY -->
    <div class="footer-signatory">
      <div class="sign-name">Hon. Eduardo C. Asiao</div>
      <div class="sign-position">Punong Barangay</div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => window.print());
  </script>
</body>
</html>
