<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Fetch parameters
$tid = $_GET['transaction_id'] ?? die('Missing transaction_id');
$includeHeader = isset($_GET['includeHeader']) && $_GET['includeHeader'] === '1';

$download = isset($_GET['download']) && $_GET['download'] === '1';
$print = isset($_GET['print']) && $_GET['print'] === '1';

// Query
$stmt = $conn->prepare("
  SELECT client_name, client_address, respondent_name, respondent_address, incident_type, 
  incident_description, incident_place, incident_date, incident_time, created_at
    FROM blotter_records
  WHERE transaction_id = ?
");
$stmt->bind_param('s', $tid);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch Barangay Captain name from user_accounts and purok tables
$captainStmt = $conn->prepare("SELECT account_id FROM user_accounts WHERE role = 'Brgy Captain' LIMIT 1");
$captainStmt->execute();
$captainResult = $captainStmt->get_result();
$captainName = '';

if ($captainResult && $captainResult->num_rows > 0) {
    $captainData = $captainResult->fetch_assoc();
    $captainAccountId = $captainData['account_id'];
    
    // Search through all purok tables
    $purokTables = ['purok1_rbi', 'purok2_rbi', 'purok3_rbi', 'purok4_rbi', 'purok5_rbi', 'purok6_rbi'];
    
    foreach ($purokTables as $table) {
        $nameStmt = $conn->prepare("SELECT full_name FROM {$table} WHERE account_ID = ? LIMIT 1");
        $nameStmt->bind_param('i', $captainAccountId);
        $nameStmt->execute();
        $nameResult = $nameStmt->get_result();
        
        if ($nameResult && $nameResult->num_rows > 0) {
            $nameData = $nameResult->fetch_assoc();
            $captainName = reformatName($nameData['full_name']);
            $nameStmt->close();
            break;
        }
        $nameStmt->close();
    }
}
$captainStmt->close();

if (!$data) die('Record not found');

// Helpers
function reformatName($name) {
  // "Last, First Middle" -> "First Middle Last"
  $parts = explode(',', $name);
  return count($parts) === 2 ? trim($parts[1]) . ' ' . trim($parts[0]) : $name;
}
function extractLastName($name) {
  $parts = explode(',', $name);
  return trim($parts[0] ?? '');
}

// Filipino month names
$filipinoMonths = [
  'January' => 'Enero',
  'February' => 'Pebrero',
  'March' => 'Marso',
  'April' => 'Abril',
  'May' => 'Mayo',
  'June' => 'Hunyo',
  'July' => 'Hulyo',
  'August' => 'Agosto',
  'September' => 'Setyembre',
  'October' => 'Oktubre',
  'November' => 'Nobyembre',
  'December' => 'Disyembre',
];

// Prepare data
$clientName = reformatName($data['client_name']);
$clientLast = extractLastName($data['client_name']);
$clientAddress = $data['client_address'];
$respondentName = reformatName($data['respondent_name']);
$respondentLast = extractLastName($data['respondent_name']);
$respondentAddress = $data['respondent_address'];
$incidentDescription = $data['incident_description'];

// Parse multiple clients (separated by "at")
$clientNameArray = explode(' at ', $data['client_name']);
$clientAddressArray = explode('; ', $data['client_address']);

// Format clients with their addresses inline
$clientsFormatted = [];
$clientNamesReformatted = []; // Add this array to store reformatted names
for ($i = 0; $i < count($clientNameArray); $i++) {
    $name = reformatName(trim($clientNameArray[$i]));
    $address = isset($clientAddressArray[$i]) ? trim($clientAddressArray[$i]) : '';
    $clientsFormatted[] = [
        'name' => $name,
        'address' => $address
    ];
    $clientNamesReformatted[] = $name; // Store reformatted name
}

// Build client names string for the last paragraph
$clientNamesForSignature = implode(' at ', $clientNamesReformatted);

// Build the client text
if (count($clientsFormatted) === 1) {
    $clientText = "si <strong>" . htmlspecialchars($clientsFormatted[0]['name']) . "</strong>, nakatira sa " . htmlspecialchars($clientsFormatted[0]['address']);
} else {
    $parts = [];
    foreach ($clientsFormatted as $index => $client) {
        if ($index === 0) {
            $parts[] = "sina <strong>" . htmlspecialchars($client['name']) . "</strong>, nakatira sa " . htmlspecialchars($client['address']);
        } else {
            $parts[] = "<strong>" . htmlspecialchars($client['name']) . "</strong>, nakatira sa " . htmlspecialchars($client['address']);
        }
    }
    $clientText = implode(' at ', $parts);
}

// Build CREATED date/time pieces
$dtCreated = new DateTime($data['created_at']);
$createdDay = $dtCreated->format('j');
$createdMonthEng = $dtCreated->format('F');
$createdMonthFil = $filipinoMonths[$createdMonthEng] ?? $createdMonthEng;
$createdYear = $dtCreated->format('Y');
$createdHour = (int)$dtCreated->format('H');
$createdMinute = $dtCreated->format('i');
$createdTimeOfDay = ($createdHour < 12) ? 'umaga' : 'hapon';

// Format time as "hh:mm" in 12-hour format
$createdHour12 = ($createdHour % 12) ?: 12;
$createdTimeFormatted = sprintf('%d:%s', $createdHour12, $createdMinute);

// Build INCIDENT date/time pieces
$dtIncident = new DateTime($data['incident_date'] . ' ' . $data['incident_time']);
$incidentDay = $dtIncident->format('j');
$incidentMonthEng = $dtIncident->format('F');
$incidentMonthFil = $filipinoMonths[$incidentMonthEng] ?? $incidentMonthEng;
$incidentYear = $dtIncident->format('Y');
$incidentHour = (int)$dtIncident->format('H');
$incidentMinute = $dtIncident->format('i');
$incidentTimeOfDay = ($incidentHour < 12) ? 'umaga' : 'hapon';

// Format incident time
$incidentHour12 = ($incidentHour % 12) ?: 12;
$incidentTimeFormatted = sprintf('%d:%s', $incidentHour12, $incidentMinute);

// Logos
$govLogoSrc = 'data:image/png;base64,'.base64_encode(file_get_contents(realpath(__DIR__ . '/../images/good_governance_logo.png')));
$brgyLogoSrc = 'data:image/png;base64,'.base64_encode(file_get_contents(realpath(__DIR__ . '/../images/magang_logo.png')));

// === DOMPDF MODE ===
if ($download || $print) {
  ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
  <style>
    body { 
      font-family:'Times New Roman', serif; 
      margin:0; 
      padding:20px; 
      font-size:13pt; 
    }

    .header-table { 
      width:100%; 
      border-collapse:collapse; 
      margin-bottom:20px; 
    }

    .header-table td { 
      text-align:center; 
      vertical-align:middle; 
    }

    .header-table img { 
      height:120px; 
      width:auto; 
    }

    .header-title { 
      font-size:13pt; 
      line-height:1.2; 
    }

    .line{
      border-bottom:5px solid #000; 
      margin-bottom:3px;
    }

    .line2{
      border-bottom:2px solid #000; 
      margin-bottom:30px;
    }

    .cert-title{ 
      font-size:18pt; 
      text-align:center; 
      font-weight:bold; 
      text-decoration:underline; 
      margin-bottom:30px;
      margin-left: -50px;  /* Add this line */
      margin-right: -50px; /* Add this line */
    }
    p { 
      text-indent:50px; 
      margin-bottom:20px; 
      line-height:1.6;
    }

    .no-indent { 
      text-indent:0; 
      margin-bottom:20px; 
    }

    .signatory {
      margin-top: 60px;
      text-align: right;
      padding-right: 50px;
    }

    .signatory-name {
      font-weight: bold;
      text-transform: uppercase;
      margin-bottom: 5px;
    }

    .signatory-title {
      font-size: 12pt;
    }

  </style>
</head>
<body<?php if (!$includeHeader): ?> style="padding-top: 150px;"<?php endif; ?>>
  <?php if ($includeHeader): ?>
    <table class="header-table">
      <tr>
        <td style="width:20%; text-align:left;">
          <img src="<?= $brgyLogoSrc ?>" alt="Barangay Logo">
        </td>
        <td style="width:60%;" class="header-title">
          Republic of the Philippines<br>
          Province of Camarines Norte<br>
          Municipality of Daet<br>
          <strong>BARANGAY MAGANG</strong><br><br>
          <strong>OFFICE OF THE PUNONG BARANGAY</strong>
        </td>
        <td style="width:20%; text-align:right;">
          <img src="<?= $govLogoSrc ?>" alt="Governance Logo">
        </td>
      </tr>
    </table>
    <div class="line"></div>
    <div class="line2"></div>
  <?php endif; ?>

  <p class="cert-title">KATUNAYAN</p>
  <p class="no-indent"><strong>SA KINAUUKULAN:</strong></p>

  <!-- Main paragraph -->
  <p>
    Ito ay nagpapatunay na ayon sa talaan ng tanggapang ito na may petsang 
    <strong><?= $createdMonthFil ?> <?= $createdDay ?>, <?= $createdYear ?></strong>, 
    sa ganap na ika-<strong><?= $createdTimeFormatted ?></strong> ng <strong><?= $createdTimeOfDay ?></strong>, 
    <?= $clientText ?> ay dumulog sa tanggapan ng Punong Barangay 
    upang ipatala ang pangyayaring naganap noong 
    <strong><?= $incidentMonthFil ?> <?= $incidentDay ?>, <?= $incidentYear ?></strong> 
    sa ganap na ika-<strong><?= $incidentTimeFormatted ?></strong> ng <strong><?= $incidentTimeOfDay ?></strong>.
  </p>

  <!-- Incident Description -->
  <p>
    <?= nl2br(htmlspecialchars($incidentDescription)) ?>
  </p>

  <!-- Signature section -->
  <p>
    Nilagdaan sa Barangay Magang ngayong ika-<strong><?= $createdDay ?> ng <?= $createdMonthFil ?>, <?= $createdYear ?></strong>
    sa kahilingan <?= count($clientsFormatted) === 1 ? 'ni G.' : 'nila' ?> 
    <strong><?= str_replace(' at ', '</strong> at <strong>', htmlspecialchars($clientNamesForSignature)) ?></strong>.
  </p>

  <!-- Signatory Section -->
  <div class="signatory">
    <div class="signatory-name"><?= htmlspecialchars($captainName) ?></div>
    <div class="signatory-title">Punong Barangay</div>
  </div>
</body>
</html>
  <?php
  $html = ob_get_clean();
  $options = new Options();
  $options->set('isRemoteEnabled', true);
  $dompdf = new Dompdf($options);
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4','portrait');
  $dompdf->render();
  $filename = "Blotter Certificate ({$clientLast} - {$respondentLast}).pdf";
  $dompdf->stream($filename, ['Attachment'=>$download]);
  exit;
}
?>

<!-- === HTML PREVIEW MODE === -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Blotter Certificate Preview</title>
  <style>
    body {
      margin:0; padding:0; background:#ccc;
      font-family:'Times New Roman', serif;
      display:flex; justify-content:center; align-items:start;
      min-height:100vh;
    }
    .paper {
      width:794px; min-height:1123px; background:#fff;
      padding:30px 40px; box-shadow:0 0 10px rgba(0,0,0,0.2);
      box-sizing:border-box; margin:20px 0;
      overflow-x: hidden;  /* Add this line */
    }
    .paper.no-header {
      padding-top: 180px;
    }
    .header-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    .header-table td { text-align:center; vertical-align:middle; }
    .header-table img { height:120px; width:auto; }
    .header-title { font-size:13pt; line-height:1.2; }
    .line{border-bottom:5px solid #000; margin-bottom:3px;}
    .line2{border-bottom:2px solid #000; margin-bottom:30px;}
    .certification-title{
      font-size:20pt; text-align:center;
      font-weight:bold; text-decoration:underline;
      margin-bottom:30px;
      margin-left: -50px;  /* Add this line */
      margin-right: -50px; /* Add this line */
    }
    p {
      text-indent:50px; margin-bottom:20px;
      line-height:1.6; font-size:14pt; color:#000;
    }
    .no-indent{ text-indent:0; font-size:14pt; margin-bottom:20px; }

    .signatory {
      margin-top: 60px;
      text-align: right;
      padding-right: 50px;
    }

    .signatory-name {
      font-weight: bold;
      text-transform: uppercase;
      margin-bottom: 5px;
    }

    .signatory-title {
      font-size: 12pt;
    }
  </style>
</head>
<body>
  <div class="paper<?php if (!$includeHeader): ?> no-header<?php endif; ?>">
    <?php if ($includeHeader): ?>
      <table class="header-table">
        <tr>
          <td style="width:20%; text-align:left;">
            <img src="<?= $brgyLogoSrc ?>" alt="Barangay Logo">
          </td>
          <td style="width:60%;" class="header-title">
            Republic of the Philippines<br>
            Province of Camarines Norte<br>
            Municipality of Daet<br>
            <strong>BARANGAY MAGANG</strong><br><br>
            <strong>OFFICE OF THE PUNONG BARANGAY</strong>
          </td>
          <td style="width:20%; text-align:right;">
            <img src="<?= $govLogoSrc ?>" alt="Governance Logo">
          </td>
        </tr>
      </table>
      <div class="line"></div>
      <div class="line2"></div>
    <?php endif; ?>

    <p class="certification-title">KATUNAYAN</p>
    <p class="no-indent"><strong>SA KINAUUKULAN:</strong></p>
    
    <!-- Main paragraph -->
    <p>
      Ito ay nagpapatunay na ayon sa talaan ng tanggapang ito na may petsang 
      <strong><?= $createdMonthFil ?> <?= $createdDay ?>, <?= $createdYear ?></strong>, 
      sa ganap na ika-<strong><?= $createdTimeFormatted ?></strong> ng <strong><?= $createdTimeOfDay ?></strong>, 
      <?= $clientText ?> ay dumulog sa tanggapan ng Punong Barangay 
      upang ipatala ang pangyayaring naganap noong 
      <strong><?= $incidentMonthFil ?> <?= $incidentDay ?>, <?= $incidentYear ?></strong> 
      sa ganap na ika-<strong><?= $incidentTimeFormatted ?></strong> ng <strong><?= $incidentTimeOfDay ?></strong>.
    </p>

    <p>
      <?= nl2br(htmlspecialchars($incidentDescription)) ?>
    </p>

    <p>
      Nilagdaan sa Barangay Magang ngayong ika-<strong><?= $createdDay ?> ng <?= $createdMonthFil ?>, <?= $createdYear ?></strong>
      sa kahilingan <?= count($clientsFormatted) === 1 ? 'ni' : 'nila' ?> 
      <strong><?= str_replace(' at ', '</strong> at <strong>', htmlspecialchars($clientNamesForSignature)) ?></strong>.
    </p>
  

    <!-- Signatory Section -->
    <div class="signatory">
      <div class="signatory-name"><?= htmlspecialchars($captainName) ?></div>
      <div class="signatory-title">Punong Barangay</div>
    </div>
  </div>
</body>
</html>
