<?php
// templates/print/guardianship.php
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// 1) Prepare logos as Base64
$govLogo  = realpath(__DIR__ . '/../images/good_governance_logo.png');
$brgyLogo = realpath(__DIR__ . '/../images/magang_logo.png');
$srcGov   = 'data:image/png;base64,' . base64_encode(file_get_contents($govLogo));
$srcBrgy  = 'data:image/png;base64,' . base64_encode(file_get_contents($brgyLogo));

// 2) Get data from the $data array
$transactionId = $data['transaction_id'] ?? '';
$requestType   = $data['request_type'] ?? '';
$fullName      = $data['full_name'] ?? '';
$civilStatus   = $data['civil_status'] ?? '';
$age           = $data['age'] ?? '';
$purok         = $data['purok'] ?? '';
$childName     = $data['child_name'] ?? '';
$purpose       = $data['purpose'] ?? '';
$paymentMethod = $data['payment_method'] ?? '';
$amount        = $data['amount'] ?? '';
$createdAt     = $data['created_at'] ?? '';
$issuedDate    = date('Y-m-d');

// Format date with suffix and superscript
function formatWithSuffix($dateString) {
    $day = date('j', strtotime($dateString));
    if ($day % 10 == 1 && $day != 11) $suffix = 'st';
    elseif ($day % 10 == 2 && $day != 12) $suffix = 'nd';
    elseif ($day % 10 == 3 && $day != 13) $suffix = 'rd';
    else $suffix = 'th';
    return $day . '<sup>' . $suffix . '</sup>';
}

// DOMPDF / preview toggles
$download = isset($_GET['download']) && $_GET['download'] === '1';
$print = isset($_GET['print']) && $_GET['print'] === '1';
$includeHeader = isset($_GET['includeHeader']) && $_GET['includeHeader'] === '1';

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    /* PDF / preview shared styles */
    body {
      font-family: 'Times New Roman', Times, serif;
      margin: 0;
      padding: 20px;
      font-size: 13pt;
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

    .certification-title {
      font-size:18pt;
      text-align:center;
      font-weight:bold;
      text-decoration:underline;
      margin-bottom:50px;
    }
    .content {
      text-align: justify;
      width: 100%;
    }
    p {
      text-indent: 50px;
      margin-bottom: 20px;
      line-height:1.6;
    }
    .no-indent {
      text-indent:0;
      font-size:13pt;
      margin-bottom:40px;
    }
    sup {
      font-size: 10pt;
      vertical-align: super;
    }
  </style>
</head>
<body>
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
    <p class="certification-title">CERTIFICATE OF GUARDIANSHIP</p>

    <p>
      This is to certify that <span style="text-transform: uppercase;"><strong><u><?= htmlspecialchars($fullName) ?></u></strong></span>,
       <!-- legal age--> <strong><?= htmlspecialchars($age) ?></strong> years old, <span style="text-transform: uppercase;"><?= htmlspecialchars($civilStatus) ?></span>,  
       is a bonafide resident of <?= htmlspecialchars($purok) ?>, Barangay Magang, Daet, Camarines Norte.
    </p>
    <p>
      This is to certify further that said person is the legal guardian of <strong><?= strtoupper(htmlspecialchars($childName)) ?></strong>.
    </p>
    <p>
      This certification is issued this <strong><?= formatWithSuffix($issuedDate) ?></strong> day of <?= date('F, Y', strtotime($issuedDate)) ?> 
      at Barangay Magang, Daet, Camarines Norte upon request of interested person for <strong><?= htmlspecialchars($purpose) ?></strong> purposes.
    </p>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Render with Dompdf for print/download requests
if ($download || $print) {
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $filename = 'certificate_of_guardianship_' . $transactionId . '.pdf';
    $dompdf->stream($filename, ['Attachment' => $download]);
    exit;
}

// === HTML PREVIEW MODE ===
// For preview we want the same layout but wrapped in a "paper" mockup like other templates.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Guardianship Certificate Preview</title>
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
    .header-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    .header-table td { text-align:center; vertical-align:middle; }
    .header-table img { height:120px; width:auto; }
    .header-title { font-size:13pt; line-height:1.2; }
    .line{border-bottom:5px solid #000; margin-bottom:3px;}
    .line2{border-bottom:2px solid #000; margin-bottom:30px;}
    .certification-title {
      font-size:20pt; text-align:center;
      font-weight:bold; text-decoration:underline;
      margin-bottom:30px;
    }
    .content { text-align: justify; width:100%; }
    p {
      text-indent:50px; margin-bottom:20px;
      line-height:1.6; font-size:14pt; color:#000;
    }
    .no-indent{ text-indent:0; font-size:14pt; margin-bottom:20px; }
    sup { font-size: 10pt; vertical-align: super; }
  </style>
</head>
<body>
  <div class="paper">
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
      <p class="certification-title">CERTIFICATE OF GUARDIANSHIP</p>

      <p>
        This is to certify that <span style="text-transform: uppercase;"><strong><u><?= htmlspecialchars($fullName) ?></u></strong></span>,
         <!-- legal age --><strong><?= htmlspecialchars($age) ?></strong> years old, <span style="text-transform: uppercase;"><?= htmlspecialchars($civilStatus) ?></span>, 
         is a bonafide resident of <?= htmlspecialchars($purok) ?>, Barangay Magang, Daet, Camarines Norte.
      </p>
      <p>
        This is to certify further that said person is the legal guardian of <strong><?= strtoupper(htmlspecialchars($childName)) ?></strong>.
      </p>
      <p>
        This certification is issued this <strong><?= formatWithSuffix($issuedDate) ?></strong> day of <?= date('F, Y', strtotime($issuedDate)) ?> 
        at Barangay Magang, Daet, Camarines Norte upon request of interested person for <strong><?= htmlspecialchars($purpose) ?></strong> purposes.
      </p>
    </div>
  </div>
</body>
</html>
