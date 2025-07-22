<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$purok     = $_POST['purok']     ?? 'all';
$exactAge  = $_POST['exact_age'] ?? '';
$format    = $_POST['format']    ?? 'preview';

// 1. Generate SQL
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

$stmt = $conn->prepare("$baseSql ORDER BY purok ASC, full_name ASC");
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 2. Helper to calculate age
function calc_age($bdate) {
    $dob = new DateTime($bdate);
    return $dob->diff(new DateTime())->y;
}

// 3. Filter by age
if (is_numeric($exactAge) && $exactAge >= 1 && $exactAge <= 150) {
    $ageFilter = (int)$exactAge;
    $rows = array_filter($rows, function($r) use ($ageFilter) {
        return calc_age($r['birthdate']) === $ageFilter;
    });
    $ageLabel = "{$ageFilter}";
} else {
    // Get min/max age for display
    $ages = array_map(fn($r) => calc_age($r['birthdate']), $rows);
    $minAge = min($ages);
    $maxAge = max($ages);
    $ageLabel = "{$minAge} - {$maxAge}";
}

$totalRecords = count($rows);
$purokLabel = $purok === 'all' ? '1 - 6' : $purok;

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Resident Report</title>
  <style>
    @page { size: A4; margin: 48px; }
    body { font-family: Arial, sans-serif; font-size: 10pt; background: #fff; margin: 0; padding: 0; }
    .page { width: 100%; max-width: 700px; margin: 0 auto; padding: 10px; }
    .center-text { text-align: center; }
    .title { font-size: 14pt; font-weight: bold; margin-bottom: 10px; }
    .subtitle { font-size: 11pt;}
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td {
      border: 1px solid #000;
      padding: 6px 8px;
      text-align: center;
    }
    th { background: #eee; }
    .left { text-align: left; }
    .right { text-align: right; }
  </style>
</head>
<body>
  <div class="page">
    <div class="center-text">
      <p class="title">BARANGAY MAGANG </br> DAET, CAMARINES NORTE</p>
      <!-- <p class="title"></p> -->
      <p class="subtitle">LIST OF <?= htmlspecialchars($ageLabel) ?> YEARS OLD </br> PUROK <?= htmlspecialchars($purokLabel) ?></p>
      <!-- <p class="subtitle"></p> -->
    </div>
    <table>
      <thead>
        <tr>
          <th>No.</th>
          <th>Full Name</th>
          <th>Sex</th>
          <th>Age</th>
          <th>Birthdate</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($totalRecords > 0): ?>
          <?php $counter = 1; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= $counter++ ?></td>
              <td><?= htmlspecialchars($r['full_name']) ?></td>
              <td><?= htmlspecialchars($r['sex']) ?></td>
              <td><?= calc_age($r['birthdate']) ?></td>
              <td><?= date('F j, Y', strtotime($r['birthdate'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5">No residents found for the selected filters.</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5" class="left">Total Records: <?= $totalRecords ?></td>
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
    $dompdf->stream("Resident_Report.pdf", ['Attachment' => false]);
    exit;
} else {
    echo $html;
}
