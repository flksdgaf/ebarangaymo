<?php
require_once __DIR__ . '/dbconn.php';

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

if (!$from || !$to) exit('Missing date range.');

$stmt = $conn->prepare("
    SELECT transaction_id, client_name, respondent_name, incident_type, incident_date
    FROM blotter_records
    WHERE incident_date BETWEEN ? AND ?
    ORDER BY transaction_id ASC
");
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$res = $stmt->get_result();

$rows  = $res->fetch_all(MYSQLI_ASSOC);
$total = count($rows);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Blotter Report</title>
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
      text-align: left;
      padding-left: 10px;
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

    <h2>Blotter Report</h2>

    <table>
      <thead>
        <tr>
          <th>Blotter ID</th>
          <th>Client Name</th>
          <th>Respondent Name</th>
          <th>Incident Type</th>
          <th>Incident Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($total > 0): ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['transaction_id']) ?></td>
              <td><?= htmlspecialchars($row['client_name']) ?></td>
              <td><?= htmlspecialchars($row['respondent_name']) ?: 'N/A' ?></td>
              <td><?= htmlspecialchars($row['incident_type']) ?></td>
              <td><?= date('F j, Y', strtotime($row['incident_date'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5">No blotter records found for this range.</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5">Total Blotter Records: <?= $total ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <script>
    window.addEventListener('DOMContentLoaded', () => window.print());
  </script>
</body>
</html>
