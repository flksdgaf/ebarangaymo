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
$goodGovLogo = realpath(__DIR__ . '/../images/good_governance_logo.png');
$srcGoodGov  = 'data:image/png;base64,' . base64_encode(file_get_contents($goodGovLogo));

if (!$month || !$year) {
    die('Missing required filters.');
}

// Create date range for the selected month
$firstDay = "$year-$month-01";
$lastDay = date("Y-m-t", strtotime($firstDay));

// Fetch required columns from complaint_records
$stmt = $conn->prepare("
    SELECT case_no, date_settlement, complaint_title, nature_of_case, complainant_name, respondent_name, action_taken
    FROM barangay_complaints
    WHERE DATE(date_filed) BETWEEN ? AND ?
    ORDER BY date_filed ASC, case_no ASC
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
                          $formattedName = '';

                          // Check if name contains comma (format: Surname, First Name, Middle Name)
                          if (strpos($fullName, ',') !== false) {
                              $parts = array_map('trim', explode(',', $fullName));
                              if (count($parts) >= 2) {
                                  $surname = $parts[0];
                                  $remainingParts = explode(' ', trim($parts[1]));
                                  
                                  if (count($remainingParts) >= 2) {
                                      $firstName = $remainingParts[0];
                                      $middleInitial = strtoupper(substr($remainingParts[1], 0, 1)) . '.';
                                      $formattedName = strtoupper("$firstName $middleInitial $surname");
                                  } else {
                                      $firstName = $remainingParts[0];
                                      $formattedName = strtoupper("$firstName $surname");
                                  }
                              } else {
                                  $formattedName = strtoupper($fullName);
                              }
                          } else {
                              // Fallback for space-separated format
                              $nameParts = explode(' ', trim($fullName));
                              if (count($nameParts) >= 3) {
                                  $firstName = $nameParts[0];
                                  $middleInitial = strtoupper(substr($nameParts[1], 0, 1)) . '.';
                                  $lastName = implode(' ', array_slice($nameParts, 2));
                                  $formattedName = strtoupper("$firstName $middleInitial $lastName");
                              } else {
                                  $formattedName = strtoupper($fullName);
                              }
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
                          $formattedName = '';

                          // Check if name contains comma (format: Surname, First Name, Middle Name)
                          if (strpos($fullName, ',') !== false) {
                              $parts = array_map('trim', explode(',', $fullName));
                              if (count($parts) >= 2) {
                                  $surname = $parts[0];
                                  $remainingParts = explode(' ', trim($parts[1]));
                                  
                                  if (count($remainingParts) >= 2) {
                                      $firstName = $remainingParts[0];
                                      $middleInitial = strtoupper(substr($remainingParts[1], 0, 1)) . '.';
                                      $formattedName = strtoupper("$firstName $middleInitial $surname");
                                  } else {
                                      $firstName = $remainingParts[0];
                                      $formattedName = strtoupper("$firstName $surname");
                                  }
                              } else {
                                  $formattedName = strtoupper($fullName);
                              }
                          } else {
                              // Fallback for space-separated format
                              $nameParts = explode(' ', trim($fullName));
                              if (count($nameParts) >= 3) {
                                  $firstName = $nameParts[0];
                                  $middleInitial = strtoupper(substr($nameParts[1], 0, 1)) . '.';
                                  $lastName = implode(' ', array_slice($nameParts, 2));
                                  $formattedName = strtoupper("$firstName $middleInitial $lastName");
                              } else {
                                  $formattedName = strtoupper($fullName);
                              }
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

// CSS for PDF (Print)
$pdfCss = <<<CSS
@page { size: A4 landscape; margin: 36px; }
body { font-family: Arial, sans-serif; font-size: 10pt; background: #fff; margin: 0; padding: 0; color: #000; }
.page { width: 100%; max-width: 100%; margin: 0 auto; padding: 10px; box-sizing: border-box; }
.header-section { 
  display: table;
  width: 100%;
  table-layout: fixed;
  margin-bottom: 5px;
}
.logo-left, .logo-right, .header-text {
  display: table-cell;
  vertical-align: middle;
}
.logo-left {
  width: 110px;
  text-align: right;
  padding-left: 10px;
}
.logo-right {
  width: 110px;
  text-align: left;
  padding-right: 10px;
}
.logo { 
  width: 90px; 
  height: 90px; 
  display: inline-block;
}
.header-text { 
  text-align: center;
  padding: 0 20px;
}
.horizontal1 {
  border: none;
  border-top: 4px solid #000;
  height: 0;
  margin: 0 0 2px 0;
  padding: 0;
  clear: both;
}
.horizontal2 {
  border: none;
  border-top: 2px solid #000;
  height: 0;
  margin: 0;
  padding: 0;
  clear: both;
}
.header-text h4 { margin: 1px 0; font-size: 11pt; font-family: 'Times New Roman', serif; font-weight: bold;}
.header-text h3 { margin: 2px 0; font-size: 12pt; font-family: 'Times New Roman', serif; font-weight: bold;}
.header-text .sub-header { margin: 8px 0 0 0; font-size: 11pt; font-family: 'Times New Roman', serif; font-weight: bold;}
.title { font-size: 13pt; font-weight: bold; margin: 15px 0 0 0; text-align: center; }
.subtitle { font-size: 11pt; text-align: center; margin-bottom: 15px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td {
  border: 1px solid #000;
  padding: 6px 8px;
  text-align: center;
  vertical-align: middle;
  font-size: 9pt;
  word-wrap: break-word;
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
}
.signature-box p { margin: 3px 0; }
CSS;

// CSS for Preview 
$previewCss = <<<CSS
body { font-family: Arial, sans-serif; font-size: 10pt; background: #f5f5f5; margin: 0; padding: 0; color: #000; }
.page { width: 100%; max-width: 100%; margin: 0 auto; padding: 20px; box-sizing: border-box; background: #fff;}
.header-section { 
  display: flex;
  flex-direction: row;
  align-items: center;
  width: 620px;
  margin: 0 auto 8px auto;
  justify-content: space-between;
  gap: 20px;
  padding: 0 20px;
}
.logo-left, .logo-right {
  flex-shrink: 0;
}
.logo-left .logo{
  width: 95px; 
  height: 95px; 
  text-align: right;
  /* object-fit: contain;
  display: block; */
}
.logo-right .logo{
  width: 95px; 
  height: 95px; 
  text-align: left;
  /* object-fit: contain;
  display: block; */
}
/* .logo { 
  width: 95px; 
  height: 95px; 
  object-fit: contain;
  display: block;
} */
.header-text { 
  flex: 1;
  text-align: center;
  padding: 0 15px;
}
.horizontal1 {
  border: none;
  border-top: 4px solid #000;
  height: 0;
  margin: 0 0 3px 0;
  padding: 0;
}
.horizontal2 {
  border: none;
  border-top: 2px solid #000;
  height: 0;
  margin: 0;
  padding: 0;
}
.header-text h4 { margin: 1px 0; font-size: 11pt; font-family: 'Times New Roman', serif; font-weight: bold;}
.header-text h3 { margin: 2px 0; font-size: 12pt; font-family: 'Times New Roman', serif; font-weight: bold;}
.header-text .sub-header { margin: 8px 0 0 0; font-size: 11pt; font-family: 'Times New Roman', serif; font-weight: bold;}
.title { font-size: 13pt; font-weight: bold; margin: 15px 0 -3px 0; text-align: center; }
.subtitle { font-size: 11pt; text-align: center; margin-bottom: 15px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td {
  border: 1px solid #000;
  padding: 6px 8px;
  text-align: center;
  vertical-align: middle;
  font-size: 9pt;
  word-wrap: break-word;
}
th { background: #eee; font-weight: bold; }
.left { text-align: left; }
.footer-note { margin-top: 15px; font-size: 9pt; }
.signature-section { 
  display: flex;
  justify-content: space-between;
  width: 100%; 
  margin-top: 30px;
}
.signature-box { 
  flex: 1;
  text-align: left;
}
.signature-box p { margin: 3px 0; }
.kp-preview-wrapper {
  background: #ffffff;
  padding: 20px;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  margin: 10px 0;
  overflow-x: auto;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
CSS;

// Build table rows
$rowsHtml = '';
if ($totalRecords > 0) {
    foreach ($rows as $row) {
        // Barangay Case No. - use case_no directly (already in correct format)
        $caseNumber = htmlspecialchars($row['case_no']);
        
        // Date of Amicable Settlement - display blank if NULL
        $dateSettlement = '';
        if (!empty($row['date_settlement'])) {
            $dateSettlement = htmlspecialchars(date('F j, Y', strtotime($row['date_settlement'])));
        }
        
        $kindOfCase = htmlspecialchars($row['complaint_title'] ?? 'N/A');
        $title = htmlspecialchars($row['nature_of_case'] ?? 'N/A');
        $complainant = htmlspecialchars($row['complainant_name']);
        $respondent = htmlspecialchars($row['respondent_name'] ?: 'N/A');
        $remarks = htmlspecialchars($row['action_taken'] ?? 'Pending');
        
        $rowsHtml .= '<tr>'
            . '<td>' . $caseNumber . '</td>'
            . '<td>' . $dateSettlement . '</td>'
            . '<td>' . $kindOfCase . '</td>'
            . '<td>' . $title . '</td>'
            . '<td>' . $complainant . '</td>'
            . '<td>' . $respondent . '</td>'
            . '<td>' . $remarks . '</td>'
            . '</tr>';
    }
} else {
    $rowsHtml = '<tr><td colspan="7">No complaint records found for the selected month.</td></tr>';
}

// Core page HTML
$corePageHtml = '
  <div class="page">
    <div class="header-section">
      <div class="logo-left">
        <img src="' . htmlspecialchars($srcBrgy) . '" alt="Barangay Logo" class="logo">
      </div>
      <div class="header-text">
        <h4>Republic of the Philippines</h4>
        <h4>Province of Camarines Norte</h4>
        <h4>Municipality of Daet</h4>
        <h3>BARANGAY MAGANG</h3>
        <h4 class="sub-header">OFFICE OF THE PUNONG BARANGAY</h4>
      </div>
      <div class="logo-right">
        <img src="' . htmlspecialchars($srcGoodGov) . '" alt="Good Governance Logo" class="logo">
      </div>
    </div>
    <div class="horizontal1"></div>
    <div class="horizontal2"></div>
    
    <div class="title">KATARUNGANG PAMBARANGAY MONTHLY REPORT</div>
    <div class="subtitle">For the month of ' . htmlspecialchars($displayPeriod) . '</div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width: 12%;">Barangay Case No.</th>
            <th style="width: 12%;">Date of Amicable Settlement</th>
            <th style="width: 15%;">KIND OF CASE</th>
            <th style="width: 15%;">TITLE</th>
            <th style="width: 15%;">COMPLAINANT</th>
            <th style="width: 15%;">RESPONDENT</th>
            <th style="width: 16%;">REMARKS</th>
          </tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
      </table>
    </div>

    <div class="footer-note">
    <p><strong>NOTE:</strong> In the remarks, Mediated for cases settled by PB, conciliated settled by the Pangkat Tagapagkasundo, Dismissed certified endorsed to court, On- going cases, Incoming, Arbitrated Cases.</p>
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