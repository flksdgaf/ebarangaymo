<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$month = $_POST['month'] ?? '';
$year = $_POST['year'] ?? '';
$format = $_POST['format'] ?? 'preview';
$brgyLogo = realpath(__DIR__ . '/../images/magang_logo.png');
$srcBrgy  = 'data:image/png;base64,' . base64_encode(file_get_contents($brgyLogo));

if (!$month || !$year) {
    die('Missing required filters.');
}

// Create date range for the selected month
$firstDay = "$year-$month-01";
$lastDay = date("Y-m-t", strtotime($firstDay));

// Fetch required columns from complaint_records
$stmt = $conn->prepare("
    SELECT transaction_id, complaint_type, complainant_name, respondent_name, complaint_status
    FROM complaint_records
    WHERE DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at ASC, transaction_id ASC
");
$stmt->bind_param("ss", $firstDay, $lastDay);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalRecords = count($rows);

// Format month name for display
$monthName = date('F', strtotime($firstDay));
$displayPeriod = "$monthName, $year";

// Fetch signatories from rbi table
$secretaryName = 'NAME OF BRGY. SECRETARY'; // default fallback
$searchRoleSecretary = '%secretary%';

if ($stmt_sec = $conn->prepare("SELECT account_id FROM user_accounts WHERE LOWER(role) LIKE ? LIMIT 1")) {
    $lowerSearch = strtolower($searchRoleSecretary);
    $stmt_sec->bind_param('s', $lowerSearch);
    $stmt_sec->execute();
    $res_sec = $stmt_sec->get_result();
    if ($res_sec && $res_sec->num_rows > 0) {
        $r = $res_sec->fetch_assoc();
        $accountId = $r['account_id'] ?? null;

        if ($accountId) {
            // Search purok tables
            $purokTables = ['purok1_rbi', 'purok2_rbi', 'purok3_rbi', 'purok4_rbi', 'purok5_rbi', 'purok6_rbi'];
            $found = false;
            
            foreach ($purokTables as $tbl) {
                $sql = "SELECT full_name FROM {$tbl} WHERE account_ID = ? LIMIT 1";
                if ($st = $conn->prepare($sql)) {
                    $st->bind_param('s', $accountId);
                    $st->execute();
                    $r2 = $st->get_result();
                    if ($r2 && $r2->num_rows > 0) {
                        $rowName = $r2->fetch_assoc();
                        if (!empty($rowName['full_name'])) {
                            $fullName = $rowName['full_name'];
                            $nameParts = explode(' ', trim($fullName));
                            $formattedName = '';

                            if (count($nameParts) >= 3) {
                                $firstName = $nameParts[0];
                                $middleInitial = strtoupper(substr($nameParts[1], 0, 1)) . '.';
                                $lastName = implode(' ', array_slice($nameParts, 2));
                                $formattedName = strtoupper("$firstName $middleInitial $lastName");
                            } else {
                                $formattedName = strtoupper($fullName);
                            }
                            $secretaryName = $formattedName;
                            $found = true;
                            $st->close();
                            break;
                        }
                    }
                    $st->close();
                }
            }

            // Try fallback tables if not found
            if (!$found) {
                $fallbacks = ['pending_accounts', 'declined_accounts'];
                foreach ($fallbacks as $tbl) {
                    $sql = "SELECT full_name FROM {$tbl} WHERE account_ID = ? LIMIT 1";
                    if ($st = $conn->prepare($sql)) {
                        $st->bind_param('s', $accountId);
                        $st->execute();
                        $r2 = $st->get_result();
                        if ($r2 && $r2->num_rows > 0) {
                            $rowName = $r2->fetch_assoc();
                            if (!empty($rowName['full_name'])) {
                                $secretaryName = strtoupper($rowName['full_name']);
                                $found = true;
                                $st->close();
                                break;
                            }
                        }
                        $st->close();
                    }
                }
            }
        }
    }
    $stmt_sec->close();
}

// === Fetch Brgy Captain ===
$captainName = 'NAME OF BRGY. CAPTAIN'; // default fallback
$searchRoleCaptain = '%captain%';

if ($stmt_cap = $conn->prepare("SELECT account_id FROM user_accounts WHERE LOWER(role) LIKE ? LIMIT 1")) {
    $lowerSearch = strtolower($searchRoleCaptain);
    $stmt_cap->bind_param('s', $lowerSearch);
    $stmt_cap->execute();
    $res_cap = $stmt_cap->get_result();
    if ($res_cap && $res_cap->num_rows > 0) {
        $r = $res_cap->fetch_assoc();
        $accountId = $r['account_id'] ?? null;

        if ($accountId) {
            // Search purok tables
            $purokTables = ['purok1_rbi', 'purok2_rbi', 'purok3_rbi', 'purok4_rbi', 'purok5_rbi', 'purok6_rbi'];
            $found = false;
            
            foreach ($purokTables as $tbl) {
                $sql = "SELECT full_name FROM {$tbl} WHERE account_ID = ? LIMIT 1";
                if ($st = $conn->prepare($sql)) {
                    $st->bind_param('s', $accountId);
                    $st->execute();
                    $r2 = $st->get_result();
                    if ($r2 && $r2->num_rows > 0) {
                        $rowName = $r2->fetch_assoc();
                        if (!empty($rowName['full_name'])) {
                            $fullName = $rowName['full_name'];
                            $nameParts = explode(' ', trim($fullName));
                            $formattedName = '';

                            if (count($nameParts) >= 3) {
                                $firstName = $nameParts[0];
                                $middleInitial = strtoupper(substr($nameParts[1], 0, 1)) . '.';
                                $lastName = implode(' ', array_slice($nameParts, 2));
                                $formattedName = strtoupper("$firstName $middleInitial $lastName");
                            } else {
                                $formattedName = strtoupper($fullName);
                            }
                            $captainName = $formattedName;
                            $found = true;
                            $st->close();
                            break;
                        }
                    }
                    $st->close();
                }
            }

            // Try fallback tables if not found
            if (!$found) {
                $fallbacks = ['pending_accounts', 'declined_accounts'];
                foreach ($fallbacks as $tbl) {
                    $sql = "SELECT full_name FROM {$tbl} WHERE account_ID = ? LIMIT 1";
                    if ($st = $conn->prepare($sql)) {
                        $st->bind_param('s', $accountId);
                        $st->execute();
                        $r2 = $st->get_result();
                        if ($r2 && $r2->num_rows > 0) {
                            $rowName = $r2->fetch_assoc();
                            if (!empty($rowName['full_name'])) {
                                $captainName = strtoupper($rowName['full_name']);
                                $found = true;
                                $st->close();
                                break;
                            }
                        }
                        $st->close();
                    }
                }
            }
        }
    }
    $stmt_cap->close();
}

// CSS for PDF and Preview
$pdfCss = <<<CSS
@page { size: A4 landscape; margin: 36px; }
body { font-family: Arial, sans-serif; font-size: 10pt; background: #fff; margin: 0; padding: 0; color: #000; }
.page { width: 95%; max-width: 100%; margin: 0 auto; padding: 10px; box-sizing: border-box; }
.header-section { 
  display: flex; 
  flex-direction: row;
  align-items: center; 
  justify-content: center;
  margin-bottom: 20px; 
  border-bottom: 2px solid #000; 
  padding-bottom: 10px;
  gap: 20px;
}
.logo { 
  width: 80px; 
  height: 80px; 
  object-fit: contain;
  flex-shrink: 0;
}
.header-text { 
  flex: 0 1 auto;
  text-align: center; 
}
.header-text h4 { margin: 2px 0; font-size: 11pt; font-family: 'Times New Roman', serif; }
.header-text h3 { margin: 5px 0; font-size: 12pt; font-weight: bold; font-family: 'Times New Roman', serif; }
.title { font-size: 13pt; font-weight: bold; margin: 15px 0 5px 0; text-align: center; }
.subtitle { font-size: 11pt; text-align: center; margin-bottom: 15px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td {
  border: 1px solid #000;
  padding: 6px 8px;
  text-align: center;
  vertical-align: middle;
  font-size: 9pt;
}
th { background: #eee; font-weight: bold; }
.left { text-align: left; }
.footer-note { margin-top: 15px; font-size: 9pt; }
.signature-section { 
  display: table; 
  width: 100%; 
  margin-top: 30px; 
  table-layout: fixed;
}
.signature-box { 
  display: table-cell; 
  width: 50%; 
  text-align: left; 
  vertical-align: top;
  padding: 0 20px;
}
.signature-box p { margin: 3px 0; }
CSS;

// Preview CSS (remove @page)
$previewCss = preg_replace('/@page\s*\{[^}]*\}\s*/', '', $pdfCss);
$previewCss .= "
.preview-container { 
  background: #fff !important; 
  padding: 20px !important; 
  border: 1px solid #ddd !important; 
  border-radius: 5px !important; 
  max-width: 100% !important; 
  overflow-x: auto !important;
}
.page { 
  margin: 0 !important; 
  padding: 20px !important; 
}
";

// Build table rows
$rowsHtml = '';
if ($totalRecords > 0) {
    $counter = 1;
    foreach ($rows as $row) {
        $status = htmlspecialchars($row['complaint_status'] ?? 'Pending');
        
        $rowsHtml .= '<tr>'
            . '<td>' . $counter++ . '</td>'
            . '<td class="left">' . htmlspecialchars($row['transaction_id']) . '</td>'
            . '<td class="left">' . htmlspecialchars($row['complaint_type'] ?? 'N/A') . '</td>'
            . '<td class="left">' . htmlspecialchars($row['complainant_name']) . '</td>'
            . '<td class="left">' . htmlspecialchars($row['respondent_name'] ?: 'N/A') . '</td>'
            . '<td>' . $status . '</td>'
            . '</tr>';
    }
} else {
    $rowsHtml = '<tr><td colspan="6">No complaint records found for the selected month.</td></tr>';
}

// Core page HTML
$corePageHtml = '
  <div class="page">
    <div class="header-section">
      <img src='. htmlspecialchars($srcBrgy) . ' alt="Barangay Logo" class="logo">
      <div class="header-text">
        <h4>Republic of the Philippines</h4>
        <h4>Province of Camarines Norte</h4>
        <h4>Municipality of Daet</h4>
        <h3>BARANGAY MAGANG</h3>
        <h4>TANGGAPAN NG LUPON TAGAPAMAYAPA</h4>
      </div>
    </div>

    <div class="title">KATARUNGANG PAMBARANGAY MONTHLY REPORT</div>
    <div class="subtitle">For the month of <strong>' . htmlspecialchars($displayPeriod) . '</strong></div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width: 5%;">No.</th>
            <th style="width: 15%;">Transaction ID</th>
            <th style="width: 20%;">Complaint Type</th>
            <th style="width: 22%;">Complainant</th>
            <th style="width: 22%;">Respondent</th>
            <th style="width: 16%;">Status</th>
          </tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
      </table>
    </div>

    <div class="footer-note">
      <p>This ' . date('jS') . ' day of ' . date('F') . ', ' . date('Y') . '. Barangay Magang, Daet, Camarines Norte.</p>
    </div>

    <div class="signature-section">
      <div class="signature-box">
        <p>Prepared by:</p>
        <p style="margin-top: 40px;"><strong>' . htmlspecialchars($secretaryName) . '</strong></p>
        <p>Barangay Secretary</p>
      </div>
      <div class="signature-box">
        <p>Noted:</p>
        <p style="margin-top: 40px;"><strong>' . htmlspecialchars($captainName) . '</strong></p>
        <p>Punong Barangay</p>
      </div>
    </div>
  </div>
';

// Output handling
if (strtolower($format) === 'pdf') {
    $fullHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Katarungang Pambarangay Report</title>'
              . '<style>' . $pdfCss . '</style>'
              . '</head><body>'
              . $corePageHtml
              . '</body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($fullHtml);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $filename = "KP_Report_{$monthName}_{$year}.pdf";
    $dompdf->stream($filename, ['Attachment' => false]);
    exit;
} else {
    // Preview mode
    header('Content-Type: text/html; charset=utf-8');
    
    // Wrap everything in a container with inline styles for maximum compatibility
    echo '<div class="kp-preview-wrapper" style="background: #ffffff; padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; margin: 10px 0; overflow-x: auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
    echo '<style>' . $previewCss . '</style>';
    echo $corePageHtml;
    echo '</div>';
    exit;
}

// <p><strong>NOTE:</strong> In the remarks, Mediated for cases settled by PB, conciliated settled by the Pangkat Tagapagkasundo, Dismissed certified endorsed to court, On-going cases, Incoming, Arbitrated Cases</p>