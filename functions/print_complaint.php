<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1) Auth check
session_start();
if (!($_SESSION['auth'] ?? false)) {
  header('HTTP/1.1 403 Forbidden');
  exit('Not authorized');
}

// 2) Fetch transaction_id
$tid = $_GET['transaction_id'] ?? '';
if (!$tid) {
  exit('Missing transaction_id');
}

// 3) Load complaint record
$stmt = $conn->prepare("SELECT complainant_name, complainant_address, respondent_name, respondent_address, complaint_type, complaint_affidavit, pleading_statement, DATE_FORMAT(created_at, '%M %e, %Y %l:%i %p') AS created_fmt FROM complaint_records WHERE transaction_id = ?");
$stmt->bind_param('s', $tid);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$rec) {
  exit('Record not found');
}

// 4) Prepare logo images
$goodGovernanceLogo = realpath(__DIR__ . '/../images/good_governance_logo.png');
$magangLogo = realpath(__DIR__ . '/../images/magang_logo.png');

if (!$goodGovernanceLogo || !$magangLogo) {
  exit("Missing logo image(s).");
}

$logo1 = base64_encode(file_get_contents($magangLogo));
$logo2 = base64_encode(file_get_contents($goodGovernanceLogo));

$src1  = 'data:image/png;base64,' . $logo1;
$src2 = 'data:image/png;base64,' . $logo2;

// 5) Build HTML template
$html = '
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body {
      font-family: "Times New Roman", serif;
      margin: 0;
      padding: 0;
    }

    /* Full-width header */
    .header-table {
      width: 100%;
      margin-bottom: 5px;
      border-collapse: collapse;
    }
    .header-table td {
      vertical-align: middle;
      text-align: center;
    }
    .logo {
      height: 125px;
    }
    .header-text {
      font-size: 17px;
      font-weight: bold;
      line-height: 1.4;
    }
    .header-text .agency {
      margin-top: 10px;
      display: block;
    }
    hr.header-line {
      border: none;
      border-top: 3px solid black;
      width: 100%;
      margin: 0;
    }

    /* Content area with page margins */
    .content {
      margin-left: 0.75in;
      margin-right: 0.75in;
      margin-top: 0.25in;
      font-size: 16px;
      text-alignment: center;
    }

    /* Left info block, nudged further left */
    .info {
      float: left;
      display: table;
      margin-left: -0.25in;      /* ← pull it ¼″ further left */
      margin-bottom: 20px;
      margin-top: 60px;
      border-collapse: collapse;
      font-size: 16px;
    }
    .info td {
      padding: 2px 4px;
      white-space: nowrap;
    }
    .info .name1,
    .info .name2 {
      padding-left: 4px;
      padding-top: 15px;
      margin-bottom: 13px; 
      display: inline-block; 
    }
    .info .add1,
    .info .add2 {
      padding-left: 4px;
      padding-bottom: 1px;
      display: inline-block; 
    }  
  

    /* Right meta block, nudged further right */
    .meta {
      display: table;
      margin-left: auto;
      margin-right: -0.25in;     /* ← push it ¼″ further right */
      margin-bottom: 20px;
      margin-top: 20px;
      border-collapse: collapse;
      font-size: 16px;
    }
    .meta td {
      padding: 2px 4px;
      white-space: nowrap;
    }
    .meta .value {
      padding-left: 4px;
    }

    .clear { clear: both; }

    .body-text {
      line-height: 1.6;
      text-align: justify;
      margin-top: 0.5in;
    }

    /* New body section */
    .section-title {
      text-align: center;
      font-weight: bold;
      margin-bottom: 0.1in;
    }
    .sumbong-content {
      margin-left: -0.20in; 
      margin-right: -32px; 
    }
    .section-text {
      text-indent: 0.5in;
      margin-top: 10px;
      margin-bottom: 3px;
      line-height: 1.2;
      text-align; justify;
    }
    .section-text2 {
      text-indent: 0.5in;
      margin-top: 20px;
      margin-bottom: 3px;
      line-height: 1.2;
      text-align; justify;
    }
    .underline-block {
      text-align; justify;
    }

    /* Signatories */
    .signatories-section {
      margin-top: 50px;
      margin-left: -0.20in; 
      margin-right: -32px; 
    }
    .signatory1 {
      text-align: right;
      margin-bottom: 20px;
    }
    .signatory1 .line {
      display: inline-block;
      border-top: 1px solid black;
      width: 200px;
    }
    .signatory1 p {
      display: block;
      font-size: 16px;
      margin-top: -10px;
    }
    .signatories-section .date-file {
      text-align: center; 
      margin-bottom: 40px;
    }
    .signatory2 {
      margin-left: 300px;
      text-align: center; 
      margin-top: 80px;
    }
    .signatory2 p {
      display: block;
      font-size: 16px;
      margin-top: 3px;
    }
      

  </style>
</head>
<body>

  <!-- HEADER -->
  <table class="header-table">
    <tr>
      <td style="width:20%">
        <img src="' . $src1 . '" class="logo" alt="Barangay Logo">
      </td>
      <td style="width:60%" class="header-text">
        Republic of the Philippines<br>
        Province of Camarines Norte<br>
        Municipality of Daet<br>
        <strong>BARANGAY MAGANG</strong><br>
        <span class="agency">TANGGAPAN NG LUPONG TAGAPAMAYAPA</span>
      </td>
      <td style="width:20%">
        <img src="' . $src2 . '" class="logo" alt="Lupong Tagapamayapa Logo">
      </td>
    </tr>
  </table>

  <hr class="header-line">

  <div>
  <div class="content">

    <!-- Left info -->
    <table class="info">
      <tr><td>Name:</td><td class="name1"><u>' . htmlspecialchars($rec['complainant_name']) . '</u><br></td></tr>
      <tr><td>Add:</td><td class="add1"><u>' . htmlspecialchars($rec['complainant_address']) . '</u></td></tr>
      <tr><td></td><td>Nagrereklamo</td></tr>
      <tr><td>Name:</td><td class="name2"><u>' . htmlspecialchars($rec['respondent_name']) . '</u></td></tr>
      <tr><td>Add:</td><td class="add2"><u>' . htmlspecialchars($rec['respondent_address']) . '</u></td></tr>
      <tr><td></td><td>Inirereklamo</td></tr>
    </table>

    <!-- Right meta -->
    <table class="meta">
      <tr><td>Barangay kaso blg.</td><td class="value"><u>' . htmlspecialchars($tid) . '</u></td></tr>
      <tr><td>Para:</td><td class="value"><u>' . htmlspecialchars($rec['complaint_type']) . '</u></td></tr>
    </table>

    <div class="clear"></div>

    <!-- SUMBONG section -->
    <div class="section-title">SUMBONG</div>
    <div class="sumbong-content">
      <div class="section-text">
        Ako/kami sa pamamagitan nito ay nagrereklamo laban sa mga pinangalanang isinakdal sa
        itaas, sa pagkakalabag ng aking/naming karapatan at pansariling kapakanan sa sumusunod na
        dahilan:
      </div>
      <div class="underline-block">
        <u>' . nl2br(htmlspecialchars($rec['complaint_affidavit'])) . '</u>
      </div>

      <!-- Dahil Doon section -->
      <div class="section-text2">
        Dahil doon, ako/kami ay sumasamo ng sumusunod na kaluwagan/kabayaran ay
        ipinagkaloob sa akin/amin alinsunod sa batas at/o pagkamakatao:
      </div>
      <div class="underline-block">
        <u>' . nl2br(htmlspecialchars($rec['pleading_statement'])) . '</u>
      </div>
    </div>

    <!-- Signature and acknowledgment block -->
    <div class="signatories-section">
      <div class="signatory1">
        <div class="line"></div>
        <p>Nagrereklamo</p>
      </div>

      <div class="date-file">
        Tinanggap at isinasampa ngayon ika- _____________________, 2025.
      </div>

      <div class="signatory2">
        <div class="punong-barangay"><strong>EDUARDO C. ASIAO</strong></div>
        <p>Punong Barangay/Lupon Chairman</p>
      </div>
    </div>



  </div><!-- /.content -->
</body>
</html>
';

// 6) Generate PDF
$options = new Options();
$options->set('isRemoteEnabled', false); // base64 doesn't need remote access
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('a4', 'portrait');
$dompdf->render();
$dompdf->stream("complaint_{$tid}.pdf", ["Attachment" => false]);
exit;
?>
