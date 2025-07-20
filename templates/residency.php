<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Load logos
$govLogo  = realpath(__DIR__ . '/../images/good_governance_logo.png');
$brgyLogo = realpath(__DIR__ . '/../images/magang_logo.png');
$srcGov   = 'data:image/png;base64,' . base64_encode(file_get_contents($govLogo));
$srcBrgy  = 'data:image/png;base64,' . base64_encode(file_get_contents($brgyLogo));

// Fetch data
$transactionId = $data['transaction_id'] ?? '';
$fullName      = $data['full_name'] ?? '';
$civilStatus   = $data['civil_status'] ?? '';
$age           = $data['age'] ?? '';
$purok         = $data['purok'] ?? '';
$residingYears = $data['residing_years'] ?? '';
$purpose       = $data['purpose'] ?? '';
$issuedDate    = date('Y-m-d');

// Convert years to words + number format
$numberText = new NumberFormatter("en", NumberFormatter::SPELLOUT);
$yearsWord = $residingYears ? $numberText->format($residingYears) . ' (' . $residingYears . ') years' : '';

// Day formatting
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

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    body {
      font-family: 'Times New Roman', Times, serif;
      margin: 0;
      padding: 50px 60px;
      font-size: 13pt;
    }
    .content {
      text-align: justify;
      width: 100%;
    }
    .certification-title {
      font-size: 20pt;
      text-align: center;
      font-weight: bold;
      margin-bottom: 30px;
    }
    p {
      text-indent: 50px;
      margin-bottom: 20px;
    }
    .no-indent {
      text-indent: 0;
      font-size: 13pt;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
  <div class="content">
    <p class="certification-title">CERTIFICATE OF RESIDENCY</p>

    <p class="no-indent"><strong>TO WHOM IT MAY CONCERN:</strong></p>

    <p>
      This is to certify that <strong><u><?= htmlspecialchars(strtoupper($fullName)) ?></u></strong>, <?= htmlspecialchars($age) ?> years old, 
      <span style="text-transform: uppercase;"><?= htmlspecialchars($civilStatus) ?></span>, is a bonafide resident of <?= htmlspecialchars(strtoupper($purok)) ?>, 
      Barangay Magang, Daet, Camarines Norte.
    </p>

    <p>
      This is to certify further that the said person has been residing in this barangay for <?= $yearsWord ?>.
    </p>

    <p>
      This certification is issued this <strong><?= formatWithSuffix($issuedDate) ?></strong> day of <?= date('F, Y', strtotime($issuedDate)) ?> at Barangay Magang, Daet, Camarines Norte, upon the request of the interested party for 
      <strong><?= htmlspecialchars($purpose) ?></strong> purposes.
    </p>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Render PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$filename = 'certificate_of_residency_' . $transactionId . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
