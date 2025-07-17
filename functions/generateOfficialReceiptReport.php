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
        SELECT request_type, or_number, issued_date, amount_paid
        FROM official_receipt_records
        WHERE issued_date BETWEEN ? AND ?
        ORDER BY issued_date ASC
    ");
    $stmt->bind_param("ss", $from, $to);
} else {
    $stmt = $conn->prepare("
        SELECT request_type, or_number, issued_date, amount_paid
        FROM official_receipt_records
        WHERE issued_date BETWEEN ? AND ? AND request_type = ?
        ORDER BY issued_date ASC
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
      overflow: hidden;
    }

    body, .page {
      font-family: "Arial", serif;
      font-size: 12pt;
    }

    .header {
      text-align: center;
      line-height: 1.5;
    }

    .header strong {
      font-size: 13pt;
    }

    h2 {
      text-align: center;
      margin-top: 30px;
      text-transform: uppercase;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 30px;
    }

    th, td {
      border: 1px solid #000;
      padding: 6px 8px;
      text-align: center;
    }

    tfoot td {
      font-weight: bold;
      padding: 6px 8px;
    }

    tfoot .left {
      text-align: left;
    }

    tfoot .right {
      text-align: right;
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
    <div class="header">
      <div>Republic of the Philippines</div>
      <div>Province of Camarines Norte</div>
      <div>Municipality of Daet</div>
      <div><strong>Barangay Magang</strong></div>
    </div>

    <h2>Official Receipt Report</h2>

    <table>
      <thead>
        <tr>
          <th>Type</th>
          <th>OR Number</th>
          <th>Date Issued</th>
          <th>Amount (₱)</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($totalRecords > 0): ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['request_type']) ?></td>
              <td><?= htmlspecialchars($row['or_number']) ?></td>
              <td><?= date('F j, Y', strtotime($row['issued_date'])) ?></td>
              <td><?= number_format((float)$row['amount_paid'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4">No official receipts found for this range.</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2" class="left">Total Records: <?= $totalRecords ?></td>
          <td colspan="2" class="right">Total Amount: ₱<?= number_format($totalAmount, 2) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <script>
    window.addEventListener('DOMContentLoaded', () => window.print());
  </script>
</body>
</html>
