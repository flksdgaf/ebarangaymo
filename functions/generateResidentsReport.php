<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$purok     = $_POST['purok']     ?? 'all';
$exactAge  = trim($_POST['exact_age'] ?? '');
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
    if (empty($bdate) || $bdate === '0000-00-00') {
        return 0;
    }
    try {
        $dob = new DateTime($bdate);
        return $dob->diff(new DateTime())->y;
    } catch (Exception $e) {
        return 0;
    }
}

// 3. Filter by age: support single age (e.g. "18") or range (e.g. "18-20")
$ageLabel = '';
$filteredRows = $rows; // default

$validSingle = preg_match('/^\d{1,3}$/', $exactAge);
$validRange  = preg_match('/^\s*(\d{1,3})\s*-\s*(\d{1,3})\s*$/', $exactAge, $matches);

if ($validSingle) {
    $ageFilter = (int)$exactAge;
    if ($ageFilter >= 1 && $ageFilter <= 150) {
        $filteredRows = array_filter($rows, function($r) use ($ageFilter) {
            return calc_age($r['birthdate']) === $ageFilter;
        });
        $ageLabel = "{$ageFilter}";
    } else {
        // invalid single age -> treat as no filter
        $filteredRows = $rows;
    }
} elseif ($validRange) {
    $low  = (int)$matches[1];
    $high = (int)$matches[2];
    // swap if user wrote "20-18"
    if ($low > $high) {
        [$low, $high] = [$high, $low];
    }
    // validate reasonable bounds
    if ($low < 0) $low = 0;
    if ($high > 150) $high = 150;
    if ($low <= $high) {
        $filteredRows = array_filter($rows, function($r) use ($low, $high) {
            $age = calc_age($r['birthdate']);
            return $age >= $low && $age <= $high;
        });
        $ageLabel = "{$low} - {$high}";
    } else {
        // invalid range -> no filtering
        $filteredRows = $rows;
    }
} else {
    // No age filter specified or invalid input: compute min/max for label (handle empty rows)
    if (count($rows) > 0) {
        $ages = array_map(fn($r) => calc_age($r['birthdate']), $rows);
        $minAge = min($ages);
        $maxAge = max($ages);
        $ageLabel = "{$minAge} - {$maxAge}";
    } else {
        $ageLabel = "N/A";
    }
}

// Re-index filtered rows to have sequential numeric keys (useful for foreach)
$rows = array_values($filteredRows);

$totalRecords = count($rows);
$purokLabel = $purok === 'all' ? '1 - 6' : $purok;

// Build CSS (same pattern as your blotter report to keep layout consistent)
$pdfCss = <<<CSS
@page { size: A4; margin: 48px; }
body { font-family: Arial, sans-serif; font-size: 10pt; background: #fff; margin: 0; padding: 0; color: #000; }
.page { width: 80%; max-width: 700px; margin: 0 auto; padding: 10px; box-sizing: border-box; }
.center-text { text-align: center; }
.title { font-size: 14pt; font-weight: bold; margin-bottom: 10px; }
.subtitle { font-size: 11pt; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td {
  border: 1px solid #000;
  padding: 6px 8px;
  text-align: center;
  vertical-align: middle;
}
th { background: #eee; }
.left { text-align: left; }
.right { text-align: right; }
.table-wrap { overflow: auto; }
CSS;

$previewCss = preg_replace('/@page\s*\{[^}]*\}\s*/', '', $pdfCss);
$previewCss .= "\n/* Preview-specific adjustments */\n.page{ box-shadow: none; background: transparent; max-width: 100%; padding: 0; }\n";

// Build table rows HTML (apply alignment rules)
$rowsHtml = '';
if ($totalRecords > 0) {
    $counter = 1;
    foreach ($rows as $row) {
        $fullName = $row['full_name'] ?? '';
        $sex = $row['sex'] ?? '';
        $birthRaw = $row['birthdate'] ?? '';
        $ageVal = calc_age($birthRaw);
        $formattedBirth = ($birthRaw && strtotime($birthRaw)) ? date('F j, Y', strtotime($birthRaw)) : 'N/A';

        $rowsHtml .= '<tr>'
            . '<td>' . $counter++ . '</td>' // No. (center)
            . '<td class="left">' . htmlspecialchars($fullName) . '</td>' // Full Name (left)
            . '<td>' . htmlspecialchars($sex) . '</td>' // Sex (center)
            . '<td>' . htmlspecialchars((string)$ageVal) . '</td>' // Age (center)
            . '<td class="left">' . htmlspecialchars($formattedBirth) . '</td>' // Birthdate (left)
            . '</tr>';
    }
} else {
    $rowsHtml = '<tr><td colspan="5">No residents found for the selected filters.</td></tr>';
}

// Build core page HTML fragment
$corePageHtml = '
  <div class="page">
    <div class="center-text">
      <p class="title">BARANGAY MAGANG </br> DAET, CAMARINES NORTE</p>
      <p class="subtitle">LIST OF <strong>' . htmlspecialchars($ageLabel) . '</strong> YEARS OLD </br> PUROK <strong>' . htmlspecialchars($purokLabel) . '</strong></p>
    </div>

    <div class="table-wrap">
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
        <tbody>' . $rowsHtml . '</tbody>
        <tfoot>
          <tr>
            <td colspan="5" class="left">Total Residents: ' . $totalRecords . '</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
';

// Output handling: PDF vs preview
if (strtolower($format) === 'pdf') {
    // Full document with @page rules for Dompdf
    $fullHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Resident Report</title>'
              . '<style>' . $pdfCss . '</style>'
              . '</head><body>'
              . $corePageHtml
              . '</body></html>';

    // Render PDF with Dompdf
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($fullHtml);
    $dompdf->setPaper('letter', 'portrait'); // keep same as other reports
    $dompdf->render();

    // Safe filename
    $safePurok = preg_replace('/[^0-9A-Za-z_-]/', '_', $purokLabel);
    $safeAge  = preg_replace('/[^0-9A-Za-z_-]/', '_', $ageLabel);
    $filename = "Resident_Report_Purok_{$safePurok}_Age_{$safeAge}.pdf";

    $dompdf->stream($filename, ['Attachment' => false]);
    exit;
} else {
    // Preview mode — return style + page fragment (so admin JS can paste it)
    $pageHtmlPreview = '<style>' . $previewCss . '</style>' . $corePageHtml;
    echo $pageHtmlPreview;
    exit;
}
