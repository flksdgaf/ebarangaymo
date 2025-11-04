<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Load logos
$govLogo  = realpath(__DIR__ . '/../images/good_governance_logo.png');
$brgyLogo = realpath(__DIR__ . '/../images/magang_logo.png');
$srcGov   = 'data:image/png;base64,' . base64_encode(file_get_contents($govLogo));
$srcBrgy  = 'data:image/png;base64,' . base64_encode(file_get_contents($brgyLogo));

// Fetch Barangay Captain name
$captainStmt = $conn->prepare("SELECT account_id FROM user_accounts WHERE role = 'Brgy Captain' LIMIT 1");
$captainStmt->execute();
$captainResult = $captainStmt->get_result();
$captainName = '';

if ($captainResult && $captainResult->num_rows > 0) {
    $captainData = $captainResult->fetch_assoc();
    $captainAccountId = $captainData['account_id'];
    
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

// Helper to reformat name from "Last, First Middle" to "First Middle Last"
function reformatName($name) {
    $parts = explode(',', $name);
    if (count($parts) === 2) {
        return trim($parts[1]) . ' ' . trim($parts[0]);
    }
    return $name;
}

// Helper to split full name into parts
function splitFullName($fullName) {
    $formatted = reformatName($fullName);
    $parts = explode(' ', trim($formatted));
    
    $result = [
        'first' => '',
        'middle' => '',
        'last' => ''
    ];
    
    if (count($parts) >= 3) {
        $result['first'] = $parts[0];
        $result['middle'] = $parts[1];
        $result['last'] = implode(' ', array_slice($parts, 2));
    } elseif (count($parts) === 2) {
        $result['first'] = $parts[0];
        $result['last'] = $parts[1];
    } else {
        $result['first'] = $parts[0] ?? '';
    }
    
    return $result;
}

// Fetch data
$transactionId = $data['transaction_id'] ?? '';
$nameParts = splitFullName($data['full_name'] ?? '');
$purok = $data['purok'] ?? '';
$barangay = $data['barangay'] ?? '';
$municipality = $data['municipality'] ?? '';
$province = $data['province'] ?? '';
$age = $data['age'] ?? '';
$maritalStatus = $data['marital_status'] ?? '';
$businessName = $data['business_name'] ?? '';
$businessType = $data['business_type'] ?? '';
$address = $data['address'] ?? '';
$ctcNumber = $data['ctc_number'] ?? '';
$dateIssued = $data['date_issued'] ?? '';
$placeIssued = $data['place_issued'] ?? '';
$amount = $data['amount'] ?? '';
$orNumber = $data['or_number'] ?? '';
$picture = $data['picture'] ?? '';

// Process picture from businessClearancePictures folder
$srcPicture = '';
if (!empty($picture)) {
    // Try multiple possible paths
    $possiblePaths = [
        realpath(__DIR__ . '/../businessClearancePictures/' . basename($picture)),
        realpath(__DIR__ . '/../businessClearancePictures/' . $picture),
        realpath(__DIR__ . '/../' . $picture)
    ];
    
    foreach ($possiblePaths as $picturePath) {
        if ($picturePath && file_exists($picturePath)) {
            $imageData = file_get_contents($picturePath);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $picturePath);
            finfo_close($finfo);
            $srcPicture = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            break;
        }
    }
}

$printingDate = date('F d, Y');

// DOMPDF / preview toggles
$download = isset($_GET['download']) && $_GET['download'] === '1';
$print = isset($_GET['print']) && $_GET['print'] === '1';
$includeHeader = isset($_GET['includeHeader']) && $_GET['includeHeader'] === '1';

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
          font-family: Arial, sans-serif;
          margin: 0;
          padding: 20px;
          font-size: 11pt;
        }
        .header-table { 
          width:100%; 
          border-collapse:collapse; 
          margin-bottom:15px; 
        }
        .header-table td { 
          text-align:center; 
          vertical-align:middle; 
        }
        .header-table img { 
          height:100px; 
          width:auto; 
        }
        .header-title { 
          font-size:12pt; 
          line-height:1.3; 
        }
        .line{
          border-bottom:3px solid #000; 
          margin-bottom:2px;
        }
        .line2{
          border-bottom:1px solid #000; 
          margin-bottom:20px;
        }
        .date-section {
          text-align: right;
          margin-bottom: 15px;
          font-weight: bold;
        }
        .title-section {
          text-align: left;
          margin-bottom: 30px;
        }
        .title-section p {
          margin: 5px 0;
          font-size: 11pt;
        }
        .main-content {
          display: table;
          width: 100%;
        }
        .left-section {
          display: table-cell;
          width: 65%;
          vertical-align: top;
          padding-right: 20px;
        }
        .right-section {
          display: table-cell;
          width: 35%;
          vertical-align: top;
          text-align: center;
        }
        .info-row {
          margin-bottom: 8px;
          line-height: 1.4;
        }
        .label {
          display: inline-block;
          width: 150px;
          font-weight: bold;
        }
        .picture-box {
          border: 2px solid #000;
          width: 150px;
          height: 150px;
          margin: 0 auto 15px;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .picture-box img {
          max-width: 100%;
          max-height: 100%;
          object-fit: cover;
        }
        .thumb-box {
          border: 2px solid #000;
          width: 150px;
          height: 120px;
          margin: 0 auto;
        }
        .box-label {
          font-weight: bold;
          margin-top: 5px;
        }
        .italic-note {
          font-style: italic;
          font-size: 9pt;
          margin-top: 5px;
        }
      </style>
    </head>
    <body<?php if (!$includeHeader): ?> style="padding-top: 120px;"<?php endif; ?>>
      <?php if ($includeHeader): ?>
        <table class="header-table">
          <tr>
            <td style="width:20%; text-align:left;">
              <img src="<?= $srcBrgy ?>" alt="Barangay Logo">
            </td>
            <td style="width:60%;" class="header-title">
              Republic of the Philippines<br>
              Province of Camarines Norte<br>
              Municipality of Daet<br>
              <strong>BARANGAY MAGANG</strong>
            </td>
            <td style="width:20%; text-align:right;">
              <img src="<?= $srcGov ?>" alt="Governance Logo">
            </td>
          </tr>
        </table>
        <div class="line"></div>
        <div class="line2"></div>
      <?php endif; ?>

      <div class="date-section">
        DATE OF PRINTING: <?= strtoupper($printingDate) ?>
      </div>

      <div class="title-section">
        <p><strong>TO WHOM IT MAY CONCERN</strong></p>
        <p style="margin-left: 17px;">This is to certify that the person whose name and right thumb prints appear hereon has requested a</p>
        <p><strong>BUSINESS CLEARANCE</strong> from this office as listed below:</p>
      </div>

      <div class="main-content">
        <div class="left-section">
          <div class="info-row">
            <span class="label">LAST NAME:</span>
            <?= strtoupper(htmlspecialchars($nameParts['last'])) ?>
          </div>
          <div class="info-row">
            <span class="label">FIRST NAME</span>
            <?= strtoupper(htmlspecialchars($nameParts['first'])) ?>
          </div>
          <div class="info-row">
            <span class="label">MIDDLE NAME:</span>
            <?= strtoupper(htmlspecialchars($nameParts['middle'])) ?>
          </div>
          <div class="info-row">
            <span class="label">PUROK:</span>
            <?= htmlspecialchars($purok) ?>
          </div>
          <div class="info-row">
            <span class="label">BARANGAY:</span>
            <?= strtoupper(htmlspecialchars($barangay)) ?>
          </div>
          <div class="info-row">
            <span class="label">MUNICIPALITY:</span>
            <?= strtoupper(htmlspecialchars($municipality)) ?>
          </div>
          <div class="info-row">
            <span class="label">PROVINCE:</span>
            <?= strtoupper(htmlspecialchars($province)) ?>
          </div>
          <div class="info-row">
            <span class="label">AGE:</span>
            <?= htmlspecialchars($age) ?>
          </div>
          <div class="info-row">
            <span class="label">MARITAL STATUS:</span>
            <?= strtoupper(htmlspecialchars($maritalStatus)) ?>
          </div>
          <div class="info-row">
            <span class="label">NAME OF BUSINESS:</span>
            <?= strtoupper(htmlspecialchars($businessName)) ?>
          </div>
          <div class="info-row">
            <span class="label">TYPE OF BUSINESS:</span>
            <?= strtoupper(htmlspecialchars($businessType)) ?>
          </div>
          <div class="info-row">
            <span class="label">ADDRESS:</span>
            <?= strtoupper(htmlspecialchars($address)) ?>
          </div>
          <div class="info-row">
            <span class="label">CTC NUMBER:</span>
            <?= ($ctcNumber && $ctcNumber != '0') ? htmlspecialchars($ctcNumber) : '' ?>
          </div>
          <div class="info-row">
            <span class="label">DATE ISSUED:</span>
            <?= htmlspecialchars($dateIssued) ?>
          </div>
          <div class="info-row">
            <span class="label">PLACE ISSUED:</span>
            <?= htmlspecialchars($placeIssued) ?>
          </div>
          <div class="info-row">
            <span class="label">AMOUNT PAID:</span>
            <?= htmlspecialchars($amount) ?>
          </div>
          <div class="info-row">
            <span class="label">OR NUMBER:</span>
            <?= htmlspecialchars($orNumber) ?>
          </div>
        </div>

        <div class="right-section">
          <div class="picture-box">
            <?php if ($srcPicture): ?>
              <img src="<?= $srcPicture ?>" alt="Picture">
            <?php endif; ?>
          </div>
          <div class="box-label">PICTURE</div>
          <div class="italic-note">Not valid without dry seal</div>
          
          <div class="thumb-box" style="margin-top: 150px;"></div>
          <div class="box-label">RIGHT THUMB MARK</div>
        </div>
      </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $filename = 'business_clearance_' . $transactionId . '.pdf';
    $dompdf->stream($filename, ['Attachment' => $download]);
    exit;
}

// === HTML PREVIEW MODE ===
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Business Clearance Preview</title>
  <style>
    body {
      margin:0; padding:0; background:#ccc;
      font-family: Arial, sans-serif;
      display:flex; justify-content:center; align-items:start;
      min-height:100vh;
    }
    .paper {
      width:794px; min-height:1123px; background:#fff;
      padding:30px 40px; box-shadow:0 0 10px rgba(0,0,0,0.2);
      box-sizing:border-box; margin:20px 0;
    }
    .paper.no-header {
      padding-top: 150px;
    }
    .header-table { width:100%; border-collapse:collapse; margin-bottom:15px; }
    .header-table td { text-align:center; vertical-align:middle; }
    .header-table img { height:100px; width:auto; }
    .header-title { font-size:12pt; line-height:1.3; }
    .line{border-bottom:3px solid #000; margin-bottom:2px;}
    .line2{border-bottom:1px solid #000; margin-bottom:20px;}
    .date-section {
      text-align: right;
      margin-bottom: 15px;
      font-weight: bold;
      font-size: 11pt;
    }
    .title-section {
      text-align: left;
      margin-bottom: 20px;
    }
    .title-section p {
      margin: 5px 0;
      font-size: 11pt;
    }
    .main-content {
      display: table;
      width: 100%;
    }
    .left-section {
      display: table-cell;
      width: 65%;
      vertical-align: top;
      padding-right: 20px;
    }
    .right-section {
      display: table-cell;
      width: 35%;
      vertical-align: top;
      text-align: center;
    }
    .info-row {
      margin-bottom: 8px;
      line-height: 1.4;
      font-size: 11pt;
    }
    .label {
      display: inline-block;
      width: 150px;
      font-weight: bold;
    }
    .picture-box {
      border: 2px solid #000;
      width: 150px;
      height: 150px;
      margin: 0 auto 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f9f9f9;
    }
    .picture-box img {
      max-width: 100%;
      max-height: 100%;
      object-fit: cover;
    }
    .thumb-box {
      border: 2px solid #000;
      width: 150px;
      height: 120px;
      margin: 0 auto;
      background: #f9f9f9;
    }
    .box-label {
      font-weight: bold;
      margin-top: 5px;
      font-size: 11pt;
    }
    .italic-note {
      font-style: italic;
      font-size: 9pt;
      margin-top: 5px;
    }
  </style>
</head>
<body>
  <div class="paper<?php if (!$includeHeader): ?> no-header<?php endif; ?>">
    <?php if ($includeHeader): ?>
      <table class="header-table">
        <tr>
          <td style="width:20%; text-align:left;">
            <img src="<?= $srcBrgy ?>" alt="Barangay Logo">
          </td>
          <td style="width:60%;" class="header-title">
            Republic of the Philippines<br>
            Province of Camarines Norte<br>
            Municipality of Daet<br>
            <strong>BARANGAY MAGANG</strong>
          </td>
          <td style="width:20%; text-align:right;">
            <img src="<?= $srcGov ?>" alt="Governance Logo">
          </td>
        </tr>
      </table>
      <div class="line"></div>
      <div class="line2"></div>
    <?php endif; ?>

    <div class="date-section">
      DATE OF PRINTING: <?= strtoupper($printingDate) ?>
    </div>

    <div class="title-section">
      <p><strong>TO WHOM IT MAY CONCERN</strong></p>
      <p style="margin-left: 30px;">This is to certify that the person whose name and right thumb prints appear hereon has requested a</p>
      <p><strong>BUSINESS CLEARANCE</strong> from this office as listed below:</p>
    </div>

    <div class="main-content">
      <div class="left-section">
        <div class="info-row">
          <span class="label">LAST NAME:</span>
          <?= strtoupper(htmlspecialchars($nameParts['last'])) ?>
        </div>
        <div class="info-row">
          <span class="label">FIRST NAME</span>
          <?= strtoupper(htmlspecialchars($nameParts['first'])) ?>
        </div>
        <div class="info-row">
          <span class="label">MIDDLE NAME:</span>
          <?= strtoupper(htmlspecialchars($nameParts['middle'])) ?>
        </div>
        <div class="info-row">
          <span class="label">PUROK:</span>
          <?= htmlspecialchars($purok) ?>
        </div>
        <div class="info-row">
          <span class="label">BARANGAY:</span>
          <?= strtoupper(htmlspecialchars($barangay)) ?>
        </div>
        <div class="info-row">
          <span class="label">MUNICIPALITY:</span>
          <?= strtoupper(htmlspecialchars($municipality)) ?>
        </div>
        <div class="info-row">
          <span class="label">PROVINCE:</span>
          <?= strtoupper(htmlspecialchars($province)) ?>
        </div>
        <div class="info-row">
          <span class="label">AGE:</span>
          <?= htmlspecialchars($age) ?>
        </div>
        <div class="info-row">
          <span class="label">MARITAL STATUS:</span>
          <?= strtoupper(htmlspecialchars($maritalStatus)) ?>
        </div>
        <div class="info-row">
          <span class="label">NAME OF BUSINESS:</span>
          <?= strtoupper(htmlspecialchars($businessName)) ?>
        </div>
        <div class="info-row">
          <span class="label">TYPE OF BUSINESS:</span>
          <?= strtoupper(htmlspecialchars($businessType)) ?>
        </div>
        <div class="info-row">
          <span class="label">ADDRESS:</span>
          <?= strtoupper(htmlspecialchars($address)) ?>
        </div>
        <div class="info-row">
          <span class="label">CTC NUMBER:</span>
          <?= ($ctcNumber && $ctcNumber != '0') ? htmlspecialchars($ctcNumber) : '' ?>
        </div>
        <div class="info-row">
          <span class="label">DATE ISSUED:</span>
          <?= htmlspecialchars($dateIssued) ?>
        </div>
        <div class="info-row">
          <span class="label">PLACE ISSUED:</span>
          <?= htmlspecialchars($placeIssued) ?>
        </div>
        <div class="info-row">
          <span class="label">AMOUNT PAID:</span>
          <!-- <?= htmlspecialchars($amount) ?> -->
        </div>
        <div class="info-row">
          <span class="label">OR NUMBER:</span>
          <?= htmlspecialchars($orNumber) ?>
        </div>
      </div>

      <div class="right-section">
        <div class="picture-box">
          <?php if ($srcPicture): ?>
            <img src="<?= $srcPicture ?>" alt="Picture">
          <?php endif; ?>
        </div>
        <div class="box-label">PICTURE</div>
        <div class="italic-note">Not valid without dry seal</div>
        
        <div class="thumb-box" style="margin-top: 150px;"></div>
        <div class="box-label">RIGHT THUMB MARK</div>
      </div>
    </div>
  </div>
</body>
</html>