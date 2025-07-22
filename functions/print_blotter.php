<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$reportType = $_GET['report_type'] ?? '';
$dateFrom   = $_GET['date_from'] ?? '';
$dateTo     = $_GET['date_to'] ?? '';
$format     = $_GET['format'] ?? 'preview';

if (!$reportType || !$dateFrom || !$dateTo || $format !== 'preview') {
    exit('Invalid or missing parameters.');
}

// Fetch records from official_receipt_records
$stmt = $conn->prepare("SELECT receipt_number, client_name, amount_paid, date_issued FROM official_receipt_records WHERE request_type = ? AND date_issued BETWEEN ? AND ?");
$stmt->bind_param('sss', $reportType, $dateFrom, $dateTo);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$total = 0;
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $total += $row['amount_paid'];
}
$stmt->close();

// Format date range for display
$formattedFrom = date('F j, Y', strtotime($dateFrom));
$formattedTo   = date('F j, Y', strtotime($dateTo));

// Build the PDF HTML
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    body {
      font-family: 'Times New Roman', Times, serif;
      margin: 50px;
      font-size: 13pt;
      text-align: center;
    }
    h2 {
      margin-bottom: 5px;
    }
    h4 {
      margin-top: 0;
      margin-bottom: 30px;
    }
    table {
      margin: 0 auto;
      width: 90%;
      border-collapse: collapse;
      font-size: 12pt;
    }
    th, td {
      border: 1px solid black;
      padding: 8px 10px;
      text-align: center;
    }
    th {
      background-color: #f2f2f2;
    }
    .total-row td {
      font-weight: bold;
      text-align: right;
    }
    .total-label {
      text-align: right;
      padding-right: 10px;
    }
  </style>
</head>
<body>

  <h2>Collection Report</h2>
  <h4><?= htmlspecialchars($reportType) ?> (<?= $formattedFrom ?> to <?= $formattedTo ?>)</h4>

  <table>
    <thead>
      <tr>
        <th>Receipt #</th>
        <th>Client Name</th>
        <th>Date Issued</th>
        <th>Amount Paid (&#8369;)</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($rows) === 0): ?>
        <tr><td colspan="4">No records found.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['receipt_number']) ?></td>
          <td><?= htmlspecialchars($r['client_name']) ?></td>
          <td><?= date('F j, Y', strtotime($r['date_issued'])) ?></td>
          <td><?= number_format($r['amount_paid'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="3" class="total-label">Total Collected</td>
          <td><?= number_format($total, 2) ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

</body>
</html>
<?php
$html = ob_get_clean();

// Render with Dompdf
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream("Collection_Report_{$reportType}_{$dateFrom}_to_{$dateTo}.pdf", ['Attachment' => false]);
exit;
