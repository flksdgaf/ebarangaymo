<?php
// functions/print_blotter.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1) fetch the record
$tid = $_GET['transaction_id'] ?? die('Missing transaction_id');
$stmt = $conn->prepare("SELECT client_name, client_address, respondent_name, respondent_address, incident_type, incident_place, DATE_FORMAT(incident_date, '%M %e, %Y') AS date_occurred, DATE_FORMAT(incident_time, '%l:%i %p') AS time_occurred, incident_description FROM blotter_records WHERE transaction_id = ?");
$stmt->bind_param('s', $tid);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$data) die('Record not found');

// 2) build the HTML
$html = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 40px; }
    h2 { text-align:center; margin-bottom:20px; }
    .section { margin-bottom:15px; }
    .label { font-weight:bold; width:120px; display:inline-block; vertical-align:top; }
    .value { display:inline-block; width:380px; }
    .wide { width:500px; }
    .box { border:1px solid #000; padding:8px; margin-top:5px; min-height:80px; }
    .footer { margin-top:40px; text-align:center; font-size:10px; color:#666; }
  </style>
</head>
<body>
  <h2>Blotter Record No. '.htmlspecialchars($tid).'</h2>

  <div class="section">
    <span class="label">Client Name:</span>
    <span class="value">'.htmlspecialchars($data['client_name']).'</span><br>
    <span class="label">Address:</span>
    <span class="value">'.htmlspecialchars($data['client_address']).'</span>
  </div>

  <div class="section">
    <span class="label">Respondent:</span>
    <span class="value">'.htmlspecialchars($data['respondent_name'] ?: '—').'</span><br>
    <span class="label">Address:</span>
    <span class="value">'.htmlspecialchars($data['respondent_address'] ?: '—').'</span>
  </div>

  <div class="section">
    <span class="label">Incident Type:</span>
    <span class="value">'.htmlspecialchars($data['incident_type']).'</span><br>
    <span class="label">Place:</span>
    <span class="value">'.htmlspecialchars($data['incident_place']).'</span><br>
    <span class="label">When:</span>
    <span class="value">'.htmlspecialchars($data['date_occurred'].' @ '.$data['time_occurred']).'</span>
  </div>

  <div class="section">
    <span class="label wide">Description:</span>
    <div class="box">'.nl2br(htmlspecialchars($data['incident_description'])).'</div>
  </div>

  <div class="footer">
    Generated on '.date('F j, Y \a\t g:i A').'
  </div>
</body>
</html>
';

// 3) render with Dompdf
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();

// 4) stream inline
$dompdf->stream("Blotter_{$tid}.pdf", ['Attachment' => false]);
exit;
?>
