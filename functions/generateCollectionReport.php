<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$from = $_POST['date_from'] ?? '';
$to = $_POST['date_to'] ?? '';
$type = $_POST['report_type'] ?? '';
$format = $_POST['format'] ?? 'preview';

if (!$from || !$to || $type === '') {
    die('Missing required filters.');
}

// === Prepare SQL ===
if (strtolower($type) === 'all') {
    $stmt = $conn->prepare("
        SELECT or_number, full_name, request_type, amount_paid, issued_date
        FROM view_transaction_history
        WHERE issued_date BETWEEN ? AND ?
        ORDER BY issued_date ASC, or_number ASC
    ");
    $stmt->bind_param("ss", $from, $to);
} else {
    $stmt = $conn->prepare("
        SELECT or_number, full_name, request_type, amount_paid, issued_date
        FROM view_transaction_history
        WHERE issued_date BETWEEN ? AND ? AND request_type = ?
        ORDER BY issued_date ASC, or_number ASC
    ");
    $stmt->bind_param("sss", $from, $to, $type);
}

$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalRecords = count($rows);
$totalAmount = array_sum(array_column($rows, 'amount_paid'));

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Collection Report</title>
  <style>
    @page {
      size: A4;
      margin: 48px;
    }
    body {
      font-family: Arial, sans-serif;
      font-size: 10pt;
      margin: 0;
      padding: 0;
      background: #fff;
    }
    .page {
      width: 100%;
      max-width: 700px;
      margin: 0 auto;
      padding: 10px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
        .second-row {
      text-align: left;
    }

    .name-treasurer, .date,
    .barangay, .rcd, .collection {
      text-align: left;
    }

    td.name-treasurer {
      border-right: none;
    }
    td.date {
      border-left: none;
    }
    td.barangay {
      border-right: none;
    }
    td.rcd {
      border-left: none;
    }

    td.date, td.rcd {
      border-left: none;
    }
    th, td {
      border: 1px solid #000;
      padding: 6px 8px;
      text-align: center;
    }
    th {
      background: #eee;
    }
    .left { text-align: left; }
    .right { text-align: right; }
    .no-border td {
      border: none;
      padding: 4px 0;
    }
    .report-title {
      font-weight: bold;
      text-align: center;
      text-transform: uppercase;
      font-size: 14pt;
      padding-bottom: 10px;
    }
  </style>
</head>
<body>
  <div class="page">
    <table class="no-border">

    </table>

    <table>
      <thead>
        <tr><td class="report-title" colspan="5">REPORT OF COLLECTION AND DEPOSITS</td></tr>
        <tr class="second-row">
            <td colspan="3" class="name-treasurer">Name of Barangay Treasurer: <strong>EDITHA B. BORSONG</strong></td>
            <td colspan="2" class="date">Date: ________________</td>
        </tr>
        <tr>
            <td colspan="3" class="barangay">Barangay: <strong>MAGANG</strong></td>
            <td colspan="2" class="rcd">RCD No.: 25-07-007</td>
        </tr>
        <tr><td colspan="5" class="collection"><strong>A. COLLECTIONS</strong></td></tr>
            <tr>
            <th colspan="2">Official Receipt/RCR</th>
            <th rowspan="2">Payor/DBC</th>
            <th rowspan="2">Nature of Collection</th>
            <th rowspan="2">Amount</th>
        </tr>
        <tr>
          <th>Date</th>
          <th>Number</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($totalRecords > 0): ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= date('m/d/Y', strtotime($row['issued_date'])) ?></td>
              <td><?= htmlspecialchars($row['or_number']) ?></td>
              <td><?= htmlspecialchars($row['full_name']) ?></td>
              <td><?= htmlspecialchars($row['request_type']) ?></td>
              <td><?= number_format((float)$row['amount_paid'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5">No official receipts found for this range.</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="left">Total Records: <?= $totalRecords ?></td>
          <td class="right">Total Amount:</td>
          <td><?= number_format($totalAmount, 2) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

if ($format === 'pdf') {
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $dompdf->stream("Collection_Report_{$from}_to_{$to}.pdf", ['Attachment' => false]);
    exit;
} else {
    echo $html;
}
?>
