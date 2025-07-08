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
$stmt = $conn->prepare("
  SELECT complainant_name, complainant_address,
         respondent_name, respondent_address,
         complaint_type, complaint_affidavit, pleading_statement,
         DATE_FORMAT(created_at, '%M %e, %Y %l:%i %p') AS created_fmt
  FROM complaint_records
  WHERE transaction_id = ?
");
$stmt->bind_param('s', $tid);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$rec) {
  exit('Record not found');
}

// 4) Build HTML template
$html = '
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: sans-serif; margin: 40px; }
    h1 { text-align: center; font-size: 18px; margin-bottom: 1em; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 1em; }
    td, th { padding: 6px; border: 1px solid #333; vertical-align: top; }
    .label { font-weight: bold; width: 25%; }
  </style>
</head>
<body>
  <h1>Complaint Affidavit<br/>Transaction # ' . htmlspecialchars($tid) . '</h1>
  <table>
    <tr><td class="label">Date Filed</td><td>' . $rec['created_fmt'] . '</td></tr>
    <tr><td class="label">Complainant</td><td>'
      . nl2br(htmlspecialchars($rec['complainant_name'] . "\n" . $rec['complainant_address'])) .
    '</td></tr>
    <tr><td class="label">Respondent</td><td>'
      . nl2br(htmlspecialchars($rec['respondent_name'] . "\n" . $rec['respondent_address'])) .
    '</td></tr>
    <tr><td class="label">Type</td><td>' . htmlspecialchars($rec['complaint_type']) . '</td></tr>
    <tr><td class="label">Affidavit</td><td>' . nl2br(htmlspecialchars($rec['complaint_affidavit'])) . '</td></tr>
    <tr><td class="label">Pleading Statement</td><td>' . nl2br(htmlspecialchars($rec['pleading_statement'])) . '</td></tr>
  </table>
  <p>__________________________<br/>Signature Over Printed Name</p>
</body>
</html>
';

// 5) Instantiate Dompdf
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('a4', 'portrait');
$dompdf->render();

// 6) Stream the PDF
$dompdf->stream("complaint_{$tid}.pdf", ["Attachment" => false]);
exit;
?>
