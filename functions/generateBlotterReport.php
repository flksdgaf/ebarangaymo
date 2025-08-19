<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$from = $_POST['date_from'] ?? '';
$to   = $_POST['date_to'] ?? '';
$format = $_POST['format'] ?? 'preview';

if (!$from || !$to) {
    die('Missing required filters.');
}

// Fetch required columns from blotter_records
$stmt = $conn->prepare("
    SELECT transaction_id, client_name, respondent_name, incident_type, incident_place, incident_date
    FROM blotter_records
    WHERE incident_date BETWEEN ? AND ?
    ORDER BY incident_date ASC, transaction_id ASC
");
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalRecords = count($rows);

// Prepare safe formatted dates for the page header (define BEFORE using them)
$htmlFrom = htmlspecialchars(date('F j, Y', strtotime($from)));
$htmlTo   = htmlspecialchars(date('F j, Y', strtotime($to)));

// CSS used in PDF (includes @page) and in preview (we'll omit @page for preview)
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

// For preview we remove @page and tweak some visual rules so it fits the admin page
$previewCss = preg_replace('/@page\s*\{[^}]*\}\s*/', '', $pdfCss);
$previewCss .= "\n/* Preview-specific adjustments */\n.page{ box-shadow: none; background: transparent; max-width: 100%; padding: 0; }\n";

// Build table rows HTML
$rowsHtml = '';
if ($totalRecords > 0) {
    $counter = 1;
    foreach ($rows as $row) {
        $rawDate = $row['incident_date'] ?? '';
        $formattedDate = ($rawDate && strtotime($rawDate)) ? date('F j, Y', strtotime($rawDate)) : 'N/A';

        // Alignment rules:
        // - No. (first column) => centered (no .left class)
        // - Incident Type (5th column) => centered (no .left class)
        // - All other value columns => left-aligned (class="left")
        $rowsHtml .= '<tr>'
            . '<td>' . $counter++ . '</td>' // No. (center)
            . '<td class="left">' . htmlspecialchars($row['transaction_id']) . '</td>' // Blotter ID (left)
            . '<td class="left">' . htmlspecialchars($row['client_name']) . '</td>'     // Client Name (left)
            . '<td class="left">' . htmlspecialchars($row['respondent_name'] ?: 'N/A') . '</td>' // Respondent (left)
            . '<td>' . htmlspecialchars($row['incident_type'] ?: 'N/A') . '</td>' // Incident Type (center)
            . '<td class="left">' . htmlspecialchars($row['incident_place'] ?: 'N/A') . '</td>' // Incident Place (left)
            . '<td class="left">' . htmlspecialchars($formattedDate) . '</td>' // Incident Date (left)
            . '</tr>';
    }
} else {
    $rowsHtml = '<tr><td colspan="7">No blotter records found for the selected date range.</td></tr>';
}

// Build the core page fragment (used for both preview and PDF content insertion)
$corePageHtml = '
  <div class="page">
    <div class="center-text">
      <p class="title">BARANGAY MAGANG </br> DAET, CAMARINES NORTE</p>
      <p class="subtitle">BLOTTER REPORTS FROM </br>
        <strong>' . $htmlFrom . '</strong> to <strong>' . $htmlTo . '</strong>
      </p>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>No.</th>
            <th>Blotter ID</th>
            <th>Client Name</th>
            <th>Respondent Name</th>
            <th>Incident Type</th>
            <th>Incident Place</th>
            <th>Incident Date</th>
          </tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
        <tfoot>
          <tr>
            <td colspan="7" class="left">Total Blotter Records: ' . $totalRecords . '</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
';

// Output handling: PDF vs preview
if (strtolower($format) === 'pdf') {
    // Full document with @page rules for Dompdf
    $fullHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Blotter Report</title>'
              . '<style>' . $pdfCss . '</style>'
              . '</head><body>'
              . $corePageHtml
              . '</body></html>';

    // Render PDF with Dompdf (no output should be sent before this)
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($fullHtml);
    $dompdf->setPaper('letter', 'portrait'); // keep letter for consistency; change to 'A4' if desired
    $dompdf->render();

    $safeFrom = preg_replace('/[^0-9A-Za-z_-]/', '_', $from);
    $safeTo = preg_replace('/[^0-9A-Za-z_-]/', '_', $to);
    $filename = "Blotter_Report_{$safeFrom}_to_{$safeTo}.pdf";

    // Stream inline
    $dompdf->stream($filename, ['Attachment' => false]);
    exit;
} else {
    // Preview mode — return a self-contained fragment (includes <style> and .page)
    // This prevents admin JS from losing styles when it injects the preview into the DOM.
    $pageHtmlPreview = '<style>' . $previewCss . '</style>' . $corePageHtml;

    // Return fragment (no extra HTML wrapper)
    echo $pageHtmlPreview;
    exit;
}
