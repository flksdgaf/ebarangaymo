<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Prepare logos as Base64
$govLogo  = realpath(__DIR__ . '/../images/good_governance_logo.png');
$brgyLogo = realpath(__DIR__ . '/../images/magang_logo.png');
$srcGov   = 'data:image/png;base64,' . base64_encode(file_get_contents($govLogo));
$srcBrgy  = 'data:image/png;base64,' . base64_encode(file_get_contents($brgyLogo));

// Fetch data (assumes $data is provided by caller as in your original file)
$transactionId = $data['transaction_id'] ?? '';
$fullName      = $data['full_name'] ?? '';
$civilStatus   = $data['civil_status'] ?? '';
$age           = $data['age'] ?? '';
$purok         = $data['purok'] ?? '';
$purpose       = $data['purpose'] ?? '';
$createdAt     = $data['created_at'] ?? '';
$issuedDate    = date('Y-m-d');

// Format day with suffix (e.g., 1st, 2nd)
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

// DOMPDF / preview toggles (same approach as print_blotter.php)
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
          font-weight:bold; 
          text-decoration:underline; 
          margin-bottom:30px;
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

      <p class="cert-title">CERTIFICATE OF INDIGENCY</p>

      <p class="no-indent"><strong>TO WHOM IT MAY CONCERN:</strong></p>

      <p>
        This is to certify that <strong><?= htmlspecialchars(strtoupper($fullName)) ?></strong>, <?= htmlspecialchars($age) ?> years old,
         <span style="text-transform: uppercase;"><?= htmlspecialchars($civilStatus) ?></span>, is a bonafide resident of <?= htmlspecialchars($purok) ?>, 
         Barangay Magang, Daet, Camarines Norte.
      </p>

      <p>
        This is to certify further that said person is known to me as one of the indigent families of this Barangay Magang.
      </p>

      <p>
        This certification is issued this <strong><?= formatWithSuffix($issuedDate) ?></strong> day of <?= date('F, Y', strtotime($issuedDate)) ?> at Barangay Magang, Daet, Camarines Norte, for <strong><?= htmlspecialchars(strtoupper($purpose)) ?></strong> purposes.
      </p>
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
    }
    p {
      text-indent:50px; margin-bottom:20px;
      line-height:1.6; font-size:14pt; color:#000;
    }
    .no-indent{ text-indent:0; font-size:14pt; margin-bottom:20px; }
  </style>
</head>
<body>
  <div class="paper">
    <?php if (!empty($includeHeader) && $includeHeader === true): ?>
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

    <p class="certification-title">CERTIFICATE OF INDIGENCY</p>

    <p class="no-indent"><strong>TO WHOM IT MAY CONCERN:</strong></p>

    <p>
      This is to certify that <strong><?= htmlspecialchars(strtoupper($fullName)) ?></strong>, <?= htmlspecialchars($age) ?> years old,
       <span style="text-transform: uppercase;"><?= htmlspecialchars($civilStatus) ?></span>, is a bonafide resident of <?= htmlspecialchars($purok) ?>, 
       Barangay Magang, Daet, Camarines Norte.
    </p>

    <p>
      This is to certify further that said person is known to me as one of the indigent families of this Barangay Magang.
    </p>

    <p>
      This certification is issued this <strong><?= formatWithSuffix($issuedDate) ?></strong> day of <?= date('F, Y', strtotime($issuedDate)) ?> at Barangay Magang, Daet, Camarines Norte, for <strong><?= htmlspecialchars(strtoupper($purpose)) ?></strong> purposes.
    </p>
  </div>
</body>
</html>