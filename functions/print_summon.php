<?php
// functions/print_summon.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
if (!($_SESSION['auth'] ?? false)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Not authorized');
}

$tid = $_GET['transaction_id'] ?? '';
if (!$tid) {
    exit('Missing transaction_id');
}

// 1) Fetch complaint + summon schedule
$sql = "
    SELECT
      c.complainant_name,
      c.complainant_address,
      c.respondent_name,
      c.respondent_address,
      c.complaint_type,
      k.scheduled_at
    FROM complaint_records c
    JOIN katarungang_pambarangay_records k
      ON k.transaction_id = c.transaction_id
    WHERE c.transaction_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $tid);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rec) {
    exit('Record not found');
}

// 2) Logos
$logo1 = base64_encode(file_get_contents(realpath(__DIR__ . '/../images/magang_logo.png')));
$logo2 = base64_encode(file_get_contents(realpath(__DIR__ . '/../images/good_governance_logo.png')));
$src1  = 'data:image/png;base64,' . $logo1;
$src2  = 'data:image/png;base64,' . $logo2;

// 3) Format dates
$dt = new DateTime($rec['scheduled_at']);
$formattedDate = $dt->format('F j, Y');
$formattedTime = $dt->format('g:i A');

// 4) HTML
$html = '
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: "Times New Roman", serif; margin:0; padding:0; }
    .header-table { width:100%; border-collapse:collapse; }
    .header-table td { vertical-align:middle; text-align:center; }
    .logo { height:100px; }
    .header-text { font-size:16px; font-weight:bold; line-height:1.3; }
    .header-text .agency { display:block; margin-top:8px; }
    hr { border:none; border-top:2px solid black; margin:4px 0; }
    .content { margin:0.5in 0.75in; font-size:14px; }
    .meta, .info { width:auto; border-collapse:collapse; margin-bottom:16px; }
    .meta td, .info td { padding:4px 6px; white-space:nowrap; }
    .meta .value, .info .value { border-bottom:1px solid #000; padding-left:4px; }
    .clear { clear:both; }
    .section-title { text-align:center; font-weight:bold; margin:16px 0 8px; }
    .section-text { text-indent:0.5in; margin-bottom:8px; line-height:1.5; }
    .underline { border-bottom:1px solid #000; padding:8px; margin-bottom:16px; }
  </style>
</head>
<body>

  <!-- HEADER -->
  <table class="header-table">
    <tr>
      <td style="width:20%"><img src="' . $src1 . '" class="logo"></td>
      <td style="width:60%" class="header-text">
        Republic of the Philippines<br>
        Province of Camarines Norte<br>
        Municipality of Daet<br>
        <strong>BARANGAY MAGANG</strong><br>
        <span class="agency">TANGGAPAN NG LUPONG TAGAPAMAYAPA</span>
      </td>
      <td style="width:20%"><img src="' . $src2 . '" class="logo"></td>
    </tr>
  </table>
  <hr>

  <div class="content">

    <!-- Info tables -->
    <table class="info" style="float:left;">
      <tr><td><strong>Patawag Blg.:</strong></td><td class="value">' . htmlspecialchars($tid) . '</td></tr>
      <tr><td><strong>Para sa usapin:</strong></td><td class="value">' . htmlspecialchars($rec['complaint_type']) . '</td></tr>
    </table>

    <table class="info" style="float:right;">
      <tr><td><strong>Petsa:</strong></td><td class="value">' . $formattedDate . '</td></tr>
      <tr><td><strong>Oras:</strong></td><td class="value">' . $formattedTime . '</td></tr>
    </table>

    <div class="clear"></div>

    <!-- Names & Addresses -->
    <table class="info">
      <tr><td><strong>Isinumbong:</strong></td>
          <td class="value">' . htmlspecialchars($rec['complainant_name']) . '</td></tr>
      <tr><td><strong>Address:</strong></td>
          <td class="value">' . htmlspecialchars($rec['complainant_address']) . '</td></tr>
      <tr><td><strong>Isinakdal:</strong></td>
          <td class="value">' . htmlspecialchars($rec['respondent_name']) . '</td></tr>
      <tr><td><strong>Address:</strong></td>
          <td class="value">' . htmlspecialchars($rec['respondent_address']) . '</td></tr>
    </table>

    <!-- Summon Text -->
    <div class="section-title">PATAWAG (SUMMON)</div>
    <div class="section-text">
      Kay: <strong>' . htmlspecialchars($rec['complainant_name']) . '</strong><br>At: <strong>' . htmlspecialchars($rec['respondent_name']) . '</strong><br>
      Sa pamamagitan nito ay ipinatawag kayo na humarap sa tanggapan ng Lupong Tagapamayapa,
      Barangay Magang, Daet, Camarines Norte, sa nasabing <strong>' . $formattedDate . '</strong>
      ng ganap na ika-<strong>' . $formattedTime . '</strong>, upang sagutin ang sumbong na isinampa
      laban sa inyo ukol sa <em>' . htmlspecialchars($rec['complaint_type']) . '</em>.
    </div>

    <div class="section-text">
      Ipinapaabot din na kung hindi kayo magpakita o hindi susunod sa utos na ito, maaari kayong
      pagmultahin o pagkakulong alinsunod sa mga umiiral na batas.
    </div>

  </div><!-- /.content -->

</body>
</html>
';

// 5) Render PDF
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream("summon_{$tid}.pdf", ["Attachment" => false]);
exit;
?>
