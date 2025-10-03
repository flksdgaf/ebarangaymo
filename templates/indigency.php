<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Prepare logos as Base64
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

// Fetch data
$transactionId = $data['transaction_id'] ?? '';
$fullName      = $data['full_name'] ?? '';
$civilStatus   = $data['civil_status'] ?? '';
$age           = $data['age'] ?? '';
$purok         = $data['purok'] ?? '';
$purpose       = $data['purpose'] ?? '';
$createdAt     = $data['created_at'] ?? '';
$issuedDate    = date('Y-m-d');

// Helper to reformat name from "Last, First Middle" to "First Middle Last"
function reformatName($name) {
    $parts = explode(',', $name);
    if (count($parts) === 2) {
        return trim($parts[1]) . ' ' . trim($parts[0]);
    }
    return $name;
}

// Reformat the full name
$fullNameFormatted = reformatName($fullName);

// Format date with suffix
function formatWithSuffix($dateStr) {
    $day = date('j', strtotime($dateStr));
    $suffix = 'th';
    if (!in_array(($day % 100), [11, 12, 13])) {
        switch ($day % 10) {
            case 1: $suffix = 'st'; break;
            case 2: $suffix = 'nd'; break;
            case 3: $suffix = 'rd'; break;
        }
    }
    return $day . '<sup>' . $suffix . '</sup>';
}

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
          letter-spacing:5px;
          font-weight:bold; 
          text-decoration:underline; 
          margin-bottom:50px;
        }
        
        .content { 
          text-align: justify; 
          width:100%;
        }
        
        p { 
          text-indent:50px; 
          margin-bottom:20px; 
          line-height:1.6;
        }

        .no-indent { 
          text-indent:0;
          font-size:13pt;
          margin-bottom:40px;
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
              <img src="<?= $srcBrgy ?>" alt="Barangay Logo">
            </td>
            <td style="width:60%;" class="header-title">
              Republic of the Philippines<br>
              Province of Camarines Norte<br>
              Municipality of Daet<br>
              <strong>BARANGAY MAGANG</strong><br><br>
              <strong>OFFICE OF THE PUNONG BARANGAY</strong>
            </td>
            <td style="width:20%; text-align:right;">
              <img src="<?= $srcGov ?>" alt="Governance Logo">
            </td>
          </tr>
        </table>
        <div class="line"></div>
        <div class="line2"></div>
      <?php endif; ?>

      <div class="content">
        <p class="cert-title">CERTIFICATE OF INDIGENCY</p>

        <p class="no-indent"><strong>TO WHOM IT MAY CONCERN:</strong></p>

        <p>
          This is to certify that 
          <strong><?= htmlspecialchars(strtoupper($fullNameFormatted)) ?></strong>, 
          <strong><?= htmlspecialchars($age) ?></strong> years old,
          <?= htmlspecialchars(strtoupper($civilStatus)) ?>, 
          is a bonafide resident of <?= htmlspecialchars($purok) ?>, 
          Barangay Magang, Daet, Camarines Norte.
        </p>

        <p>
          This is to certify further that said person is known to me as one of the indigent families of this Barangay Magang.
        </p>

        <p>
          This certification is issued this 
          <strong><?= formatWithSuffix($issuedDate) ?></strong> 
          day of <?= date('F, Y', strtotime($issuedDate)) ?> 
          at Barangay Magang, Daet, Camarines Norte, for 
          <strong><?= htmlspecialchars(strtoupper($purpose)) ?></strong> purposes.
        </p>
      </div>

      <?php if ($includeHeader): ?>
        <!-- Signatory Section -->
        <div class="signatory">
          <div class="signatory-name"><?= htmlspecialchars($captainName) ?></div>
          <div class="signatory-title">Punong Barangay</div>
        </div>
      <?php endif; ?>
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
    $filename = 'certificate_of_indigency_' . $transactionId . '.pdf';
    $dompdf->stream($filename, ['Attachment' => $download]);
    exit;
}

// === HTML PREVIEW MODE ===
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Indigency Certificate Preview</title>
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
      letter-spacing:5px;
      font-weight:bold; text-decoration:underline;
      margin-bottom:30px;
    }
    .content { text-align: justify; width:100%; }
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
            <img src="<?= $srcBrgy ?>" alt="Barangay Logo">
          </td>
          <td style="width:60%;" class="header-title">
            Republic of the Philippines<br>
            Province of Camarines Norte<br>
            Municipality of Daet<br>
            <strong>BARANGAY MAGANG</strong><br><br>
            <strong>OFFICE OF THE PUNONG BARANGAY</strong>
          </td>
          <td style="width:20%; text-align:right;">
            <img src="<?= $srcGov ?>" alt="Governance Logo">
          </td>
        </tr>
      </table>
      <div class="line"></div>
      <div class="line2"></div>
    <?php endif; ?>

    <div class="content">
      <p class="certification-title">CERTIFICATE OF INDIGENCY</p>

      <p class="no-indent"><strong>TO WHOM IT MAY CONCERN:</strong></p>

      <p>
        This is to certify that 
        <strong><?= htmlspecialchars(strtoupper($fullNameFormatted)) ?></strong>, 
        <strong><?= htmlspecialchars($age) ?></strong> years old,
        <?= htmlspecialchars(strtoupper($civilStatus)) ?>, 
        is a bonafide resident of <?= htmlspecialchars($purok) ?>, 
        Barangay Magang, Daet, Camarines Norte.
      </p>

      <p>
        This is to certify further that said person is known to me as one of the indigent families of this Barangay Magang.
      </p>

      <p>
        This certification is issued this 
        <strong><?= formatWithSuffix($issuedDate) ?></strong> 
        day of <?= date('F, Y', strtotime($issuedDate)) ?> 
        at Barangay Magang, Daet, Camarines Norte, for 
        <strong><?= htmlspecialchars(strtoupper($purpose)) ?></strong> purposes.
      </p>
    </div>

    <?php if ($includeHeader): ?>
      <!-- Signatory Section -->
      <div class="signatory">
        <div class="signatory-name"><?= htmlspecialchars($captainName) ?></div>
        <div class="signatory-title">Punong Barangay</div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>