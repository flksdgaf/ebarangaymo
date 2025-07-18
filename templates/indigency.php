<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Prepare logos as Base64
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
$purpose       = $data['purpose'] ?? '';
$createdAt     = $data['created_at'] ?? '';
$issuedDate    = $createdAt ?: date('Y-m-d');

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
      font-size: 18pt;
      text-align: center;
      font-weight: bold;
      text-decoration: underline;
      margin-bottom: 50px;
    }
    p {
      text-indent: 50px;
      margin-bottom: 20px;
    }
    .no-indent {
      text-indent: 0;
      font-size: 13pt;
      margin-bottom: 40px;
    }
  </style>
</head>
<body>
  <div class="content">
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
      This certification is issued this <strong><?= formatWithSuffix($issuedDate) ?></strong> day of <?= date('F, Y', strtotime($issuedDate)) ?> at Barangay Magang, Daet, Camarines Norte, for <strong><?= htmlspecialchars($purpose) ?></strong> purposes.
    </p>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Render with Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$filename = 'certificate_of_indigency_' . $transactionId . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
?>
