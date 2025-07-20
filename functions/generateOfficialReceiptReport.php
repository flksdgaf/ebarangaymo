<?php
require_once __DIR__ . '/dbconn.php';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$type = $_GET['type'] ?? '';

if (!$from || !$to || $type === '') {
    exit('Missing required filters.');
}

if ($type === 'all') {
    $stmt = $conn->prepare("
        SELECT or_number, full_name, request_type, amount_paid, issued_date
        FROM official_receipt_records
        WHERE issued_date BETWEEN ? AND ?
        ORDER BY issued_date ASC, or_number ASC
    ");
    $stmt->bind_param("ss", $from, $to);
} else {
    $stmt = $conn->prepare("
        SELECT or_number, full_name, request_type, amount_paid, issued_date
        FROM official_receipt_records
        WHERE issued_date BETWEEN ? AND ? AND request_type = ?
        ORDER BY issued_date ASC, or_number ASC
    ");
    $stmt->bind_param("sss", $from, $to, $type);
}

$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);

$totalRecords = count($rows);
$totalAmount = array_sum(array_column($rows, 'amount_paid'));
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Official Receipt Report</title>
  <style>
    @page {
      size: A4;
      margin: 1in;
    }

    html, body {
      margin: 0;
      padding: 0;
      background: #ccc;
    }

    .page {
      width: 8.27in;
      height: 11.69in;
      background: white;
      margin: 20px auto;
      padding: 1in;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
      box-sizing: border-box;
    }

    body, .page {
      font-family: "Arial", serif;
      font-size: 10pt;
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

    tfoot td {
      font-weight: bold;
      padding: 6px 8px;
    }

    .left {
      text-align: left;
    }

    .right {
      text-align: right;
    }

    .no-border td {
      border: none;
      padding: 4px 0;
    }

    .report-title {
      font-weight: bold;
      text-align: center;
      text-transform: uppercase;
      font-size: 14pt;
    }

    @media print {
      html, body {
        background: white;
      }

      .page {
        box-shadow: none;
        margin: 0;
      }
    }
  </style>
</head>
<body>
  <div class="page">

    <!-- Header table -->
    <table class="no-border">
      
    </table>

    <!-- Data table -->
    <table>
      <thead>
        <tr>
        <td class="report-title" colspan="5">REPORT OF COLLECTION AND DEPOSITS</td>
      </tr>
      <tr class="second-row">
        <td colspan="3" class="name-treasurer">Name of Barangay Treasurer: <strong>EDITHA B. BORSONG</strong></td>
        <td colspan="2" class="date">Date: ________________</td>
      </tr>
      <tr>
        <td colspan="3" class="barangay">Barangay: <strong>MAGANG</strong></td>
        <td colspan="2" class="rcd">RCD No.: 25-07-007</td>
      </tr>
      <tr>
        <td colspan="5" class="collection"><strong>A. COLLECTIONS</strong></td>
      </tr>
        <tr>
          <th colspan="2">Official Receipt/RCR</th>
          <th rowspan="2">Payor/DBC</th>
          <th rowspan="2">Nature of Collection</th>
          <th rowspan="2">Amounts</th>
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
          <td>₱<?= number_format($totalAmount, 2) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <script>
    window.addEventListener('DOMContentLoaded', () => window.print());
  </script>
</body>
</html>
