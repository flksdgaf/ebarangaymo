<?php
// print_complaint.php

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

// 2) transaction_id
$tid = $_GET['transaction_id'] ?? '';
if (!$tid) {
  exit('Missing transaction_id');
}

// 3) Load complaint + latest summon schedule
$sql = "
  SELECT
    c.complainant_name,
    c.complainant_address,
    c.respondent_name,
    c.respondent_address,
    c.complaint_type,
    c.complaint_affidavit,
    c.pleading_statement,
    DATE_FORMAT(c.created_at, '%M %e, %Y %l:%i %p') AS created_fmt,
    CASE k.complaint_stage
      WHEN 'Punong Barangay' THEN k.schedule_punong_barangay
      WHEN 'Unang Patawag' THEN k.schedule_unang_patawag
      WHEN 'Ikalawang Patawag' THEN k.schedule_ikalawang_patawag
      ELSE k.schedule_ikatlong_patawag
    END AS scheduled_at
  FROM complaint_records c
  LEFT JOIN katarungang_pambarangay_records k
    ON k.transaction_id = c.transaction_id
  WHERE c.transaction_id = ?
  ORDER BY k.id DESC
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

// 4) Prepare logos
$logoA = realpath(__DIR__ . '/../images/magang_logo.png');
$logoB = realpath(__DIR__ . '/../images/good_governance_logo.png');
if (! $logoA || ! $logoB) {
  exit("Missing logo images");
}
$dataA = base64_encode(file_get_contents($logoA));
$dataB = base64_encode(file_get_contents($logoB));
$srcA = 'data:image/png;base64,' . $dataA;
$srcB = 'data:image/png;base64,' . $dataB;

// 5) Format the summon date/time
$dtRaw = $rec['scheduled_at'] ?? null;
if ($dtRaw) {
  $dt = new DateTime($dtRaw);
  $summonDate = $dt->format('F j, Y');
  $summonTime = $dt->format('g:i A');
} else {
  $summonDate = $summonTime = '—';
}

// 6) Build the combined HTML
$html = '
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: "Times New Roman", serif; margin: 0; padding: 0; }
    .header-table { width:100%; border-collapse:collapse; margin-bottom:6px; }
    .header-table td { text-align:center; vertical-align:middle; }
    .logo { height:100px; }
    .header-text { font-size:16px; font-weight:bold; line-height:1.3; }
    .header-text .agency { display:block; margin-top:6px; }
    hr { border:none; border-top:3px solid #000; margin:4px 0; }
    .content { margin:0.5in 0.75in; font-size:14px; }
    table.info, table.meta { border-collapse:collapse; margin-bottom:16px; }
    table.info td, table.meta td { padding:4px 6px; white-space:nowrap; }
    .info .value, .meta .value { border-bottom:1px solid #000; padding-left:4px; }
    .section-title { text-align:center; font-weight:bold; margin:16px 0 8px; }
    .section-text { text-indent:0.5in; margin-bottom:12px; line-height:1.5; text-align:justify; }
    .underline { border-bottom:1px solid #000; padding:4px; margin-bottom:16px; }
    .sign-line { display:inline-block; border-top:1px solid #000; width:200px; margin-top:40px; }
    .page-break { page-break-before:always; }
  </style>
</head>
<body>

  <!-- HEADER -->
  <table class="header-table">
    <tr>
      <td style="width:20%"><img src="'. $srcA .'" class="logo"></td>
      <td style="width:60%" class="header-text">
        Republic of the Philippines<br>
        Province of Camarines Norte<br>
        Municipality of Daet<br>
        <strong>BARANGAY MAGANG</strong><br>
        <span class="agency">TANGGAPAN NG LUPONG TAGAPAMAYAPA</span>
      </td>
      <td style="width:20%"><img src="'. $srcB .'" class="logo"></td>
    </tr>
  </table>
  <hr>

  <!-- COMPLAINT (SUMBONG) -->
  <div class="content">
    <table class="info" style="float:left;">
      <tr><td><strong>Name:</strong></td>
          <td class="value">'. htmlspecialchars($rec['complainant_name']) .'</td></tr>
      <tr><td><strong>Address:</strong></td>
          <td class="value">'. htmlspecialchars($rec['complainant_address']) .'</td></tr>
      <tr><td></td><td>Nagrereklamo</td></tr>
      <tr><td><strong>Name:</strong></td>
          <td class="value">'. htmlspecialchars($rec['respondent_name']) .'</td></tr>
      <tr><td><strong>Address:</strong></td>
          <td class="value">'. htmlspecialchars($rec['respondent_address']) .'</td></tr>
      <tr><td></td><td>Inirereklamo</td></tr>
    </table>

    <table class="meta" style="float:right;">
      <tr><td><strong>Case No.:</strong></td>
          <td class="value">'. htmlspecialchars($tid) .'</td></tr>
      <tr><td><strong>Type:</strong></td>
          <td class="value">'. htmlspecialchars($rec['complaint_type']) .'</td></tr>
    </table>
    <div style="clear:both;"></div>

    <div class="section-title">SUMBONG</div>
    <div class="section-text">
      Ako/kami sa pamamagitan nito ay nagrereklamo laban sa mga pinangalanang isinakdal sa itaas…:
    </div>
    <div class="underline">'. nl2br(htmlspecialchars($rec['complaint_affidavit'])) .'</div>

    <div class="section-text">
      Dahil doon, ako/kami ay sumasamo ng sumusunod na kaluwagan…:
    </div>
    <div class="underline">'. nl2br(htmlspecialchars($rec['pleading_statement'])) .'</div>

    <div style="margin-top:40px; text-align:right;">
      <span class="sign-line"></span><br>
      Nagrereklamo
    </div>
  </div>

  <!-- PAGE BREAK -->
  <div class="page-break"></div>

  <!-- SUMMON (PATAWAG) -->
  <table class="header-table">
    <tr>
      <td style="width:20%"><img src="'. $srcA .'" class="logo"></td>
      <td style="width:60%" class="header-text">
        Republic of the Philippines<br>
        Province of Camarines Norte<br>
        Municipality of Daet<br>
        <strong>BARANGAY MAGANG</strong><br>
        <span class="agency">TANGGAPAN NG LUPONG TAGAPAMAYAPA</span>
      </td>
      <td style="width:20%"><img src="'. $srcB .'" class="logo"></td>
    </tr>
  </table>
  <hr>

  <div class="content">
    <table class="info" style="float:left;">
      <tr><td><strong>Patawag Blg.:</strong></td>
          <td class="value">'. htmlspecialchars($tid) .'</td></tr>
      <tr><td><strong>Para sa usapin:</strong></td>
          <td class="value">'. htmlspecialchars($rec['complaint_type']) .'</td></tr>
    </table>
    <table class="info" style="float:right;">
      <tr><td><strong>Petsa:</strong></td>
          <td class="value">'. $summonDate .'</td></tr>
      <tr><td><strong>Oras:</strong></td>
          <td class="value">'. $summonTime .'</td></tr>
    </table>
    <div style="clear:both;"></div>

    <table class="info">
      <tr><td><strong>Isinumbong:</strong></td>
          <td class="value">'. htmlspecialchars($rec['complainant_name']) .'</td></tr>
      <tr><td><strong>Address:</strong></td>
          <td class="value">'. htmlspecialchars($rec['complainant_address']) .'</td></tr>
      <tr><td><strong>Isinakdal:</strong></td>
          <td class="value">'. htmlspecialchars($rec['respondent_name']) .'</td></tr>
      <tr><td><strong>Address:</strong></td>
          <td class="value">'. htmlspecialchars($rec['respondent_address']) .'</td></tr>
    </table>

    <div class="section-title">PATAWAG (SUMMON)</div>
    <div class="section-text">
      Kay: <strong>'. htmlspecialchars($rec['respondent_name']) .'</strong><br>
      Sa pamamagitan nito ay ipinatawag kayo na humarap sa tanggapan ng Lupong Tagapamayapa,
      Barangay Magang, Daet, Camarines Norte, sa petsa <strong>'. $summonDate .'</strong>
      ng ganap na ika-<strong>'. $summonTime .'</strong>, upang sagutin ang sumbong na isinampa
      laban sa inyo ukol sa <em>'. htmlspecialchars($rec['complaint_type']) .'</em>.
    </div>
    <div class="section-text">
      Kung hindi kayo magpakita, maaari kayong pagmultahin o pagkakulong alinsunod sa batas.
    </div>
  </div>

</body>
</html>
';

// 7) Render PDF
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('a4', 'portrait');
$dompdf->render();
$dompdf->stream("complaint_and_summon_{$tid}.pdf", ["Attachment" => false]);
exit;
?>