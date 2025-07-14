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
      font-size: 14px;
    }

    /* Left info block, nudged further left */
    .info {
      float: left;
      display: table;
      margin-left: -0.25in;      /* ← pull it ¼″ further left */
      margin-bottom: 20px;
      border-collapse: collapse;
      font-size: 14px;
    }
    .info td {
      padding: 2px 4px;
      white-space: nowrap;
    }
    .info .value {
      border-bottom: 1px solid #000;
      padding-left: 4px;
    }

    /* Right meta block, nudged further right */
    .meta {
      display: table;
      margin-left: auto;
      margin-right: -0.25in;     /* ← push it ¼″ further right */
      margin-bottom: 20px;
      border-collapse: collapse;
      font-size: 14px;
    }
    .meta td {
      padding: 2px 4px;
      white-space: nowrap;
    }
    .meta .value {
      border-bottom: 1px solid #000;
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
    .section-text {
      text-indent: 0.5in;
      margin-bottom: 0.1in;
      line-height:1.5;
    }
    .underline-block {
      border-bottom: 1px solid #000;
      margin-bottom: 0.1in;
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

  <div class="content">

    <!-- Left info -->
    <table class="info">
      <tr><td><strong>Name:</strong></td><td class="value">' . htmlspecialchars($rec['complainant_name']) . '</td></tr>
      <tr><td><strong>Add:</strong></td><td class="value">' . htmlspecialchars($rec['complainant_address']) . '</td></tr>
      <tr><td><strong>Name:</strong></td><td class="value">' . htmlspecialchars($rec['respondent_name']) . '</td></tr>
      <tr><td><strong>Add:</strong></td><td class="value">' . htmlspecialchars($rec['respondent_address']) . '</td></tr>
    </table>

    <!-- Right meta -->
    <table class="meta">
      <tr><td><strong>Barangay kaso blg.</strong></td><td class="value">' . htmlspecialchars($tid) . '</td></tr>
      <tr><td><strong>Para:</strong></td><td class="value">' . htmlspecialchars($rec['complaint_type']) . '</td></tr>
    </table>

    <div class="clear"></div>

    <!-- SUMBONG section -->
    <div class="section-title">SUMBONG</div>
    <div class="section-text">
      Ako/kami sa pamamagitan nito ay nagrereklamo laban sa mga pinangalanang isinakdal sa
      itaas, sa pagkakalabag ng aking/naming karapatan at pansariling kapakanan sa sumusunod na
      dahilan:
    </div>
    <div class="underline-block">
      ' . nl2br(htmlspecialchars($rec['complaint_affidavit'])) . '
    </div>

    <!-- Dahil doon section -->
    <div class="section-text">
      Dahil doon, ako/kami ay sumasamo ng sumusunod na kaluwagan/kabayaran ay
      ipinagkaloob sa akin/amin alinsunod sa batas at/o pagkamakatao:
    </div>
    <div class="underline-block">
      ' . nl2br(htmlspecialchars($rec['pleading_statement'])) . '
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
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream("complaint_{$tid}.pdf", ["Attachment" => false]);
exit;
?>
