<?php
require_once __DIR__ . '/dbconn.php';

$purok     = $_GET['purok']     ?? '';
$exactAge  = $_GET['exact_age'] ?? '';
$format    = $_GET['format']    ?? '';

if ($purok === '' || $format === '') {
    exit('Missing required filters.');
}

// 1) Build SQL
if ($purok === 'all') {
    $parts = [];
    for ($i = 1; $i <= 6; $i++) {
        $parts[] = "SELECT *, {$i} AS purok FROM purok{$i}_rbi";
    }
    $baseSql = implode(" UNION ALL ", $parts);
} else {
    $n = (int)$purok;
    $baseSql = "SELECT *, {$n} AS purok FROM purok{$n}_rbi";
}

// 2) Fetch all data
$stmt = $conn->prepare("$baseSql ORDER BY purok ASC, full_name ASC");
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);

// 3) Helper for age calculation
function calc_age($bdate) {
    $dob = new DateTime($bdate);
    return $dob->diff(new DateTime())->y;
}

// 4) Validate and apply age filter (if needed)
$validAge = is_numeric($exactAge) && $exactAge >= 1 && $exactAge <= 150;

if ($validAge) {
    $exactAge = (int)$exactAge;
    $rows = array_filter($rows, function($r) use ($exactAge) {
        return calc_age($r['birthdate']) === $exactAge;
    });
}

$totalRecords = count($rows);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Resident Report</title>
  <style>
    @page { size: A4; margin: 1in; }
    html, body { margin:0; padding:0; background:#ccc; }
    .page {
      width: 8.27in; height: 11.69in; background: white;
      margin: 20px auto; padding: 1in;
      box-shadow: 0 0 10px rgba(0,0,0,0.3);
      box-sizing: border-box; overflow: hidden;
    }
    body, .page { font-family: "Arial", serif; font-size: 12pt; }
    .header { text-align: center; line-height: 1.5; }
    .header strong { font-size: 13pt; }
    h2 {
      text-align: center; margin-top: 30px;
      text-transform: uppercase;
    }
    table {
      width: 100%; border-collapse: collapse; margin-top: 30px;
    }
    th, td {
      border: 1px solid #000; padding: 6px 8px;
      text-align: center;
    }
    tfoot td {
      font-weight: bold; padding: 6px 8px;
    }
    tfoot .left  { text-align: left; }
    tfoot .right { text-align: right; }
    @media print {
      html, body { background: white; }
      .page { box-shadow: none; margin: 0; }
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

    <h2>Resident Report</h2>

    <table>
      <thead>
        <tr>
          <th>Purok</th>
          <th>Full Name</th>
          <th>Birthdate</th>
          <th>Age</th>
          <th>Sex</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($totalRecords > 0): ?>
          <?php foreach ($rows as $r): 
            $age = calc_age($r['birthdate']); ?>
            <tr>
              <td><?= htmlspecialchars($r['purok']) ?></td>
              <td><?= htmlspecialchars($r['full_name']) ?></td>
              <td><?= date('F j, Y', strtotime($r['birthdate'])) ?></td>
              <td><?= $age ?></td>
              <td><?= htmlspecialchars($r['sex']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5">No residents found for these filters.</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="left">Total Records: <?= $totalRecords ?></td>
          <td colspan="2" class="right"></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <script>
    window.addEventListener('DOMContentLoaded', () => window.print());
  </script>
</body>
</html>
