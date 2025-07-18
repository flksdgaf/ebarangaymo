<?php
// templates/print/barangay_id.php
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// 1) Prepare logos as Base64
$govLogo  = realpath(__DIR__ . '/../images/good_governance_logo.png');
$brgyLogo = realpath(__DIR__ . '/../images/magang_logo.png');
$srcGov   = 'data:image/png;base64,' . base64_encode(file_get_contents($govLogo));
$srcBrgy  = 'data:image/png;base64,' . base64_encode(file_get_contents($brgyLogo));

// 2) Capture the HTML with embedded PHP into $html
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
$issuedDate = date('F j, Y g:i A'); // TEMPORARY

// Format date with suffix (e.g., 21st, 22nd)
function formatWithSuffix($dateString) {
    $day = date('j', strtotime($dateString));
    if ($day % 10 == 1 && $day != 11) $suffix = 'st';
    elseif ($day % 10 == 2 && $day != 12) $suffix = 'nd';
    elseif ($day % 10 == 3 && $day != 13) $suffix = 'rd';
    else $suffix = 'th';
    return $day . $suffix;
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
    <p class="certification-title">CERTIFICATE OF GUARDIANSHIP</p>

    <p>
      This is to certify that <span style="text-transform: uppercase;"><strong><u><?= htmlspecialchars($fullName) ?></u></strong></span>,
       <?= htmlspecialchars($age) ?> years old, <span style="text-transform: uppercase;"><?= htmlspecialchars($civilStatus) ?></span>, 
       is a bonafide resident of <?= htmlspecialchars($purok) ?>, Barangay Magang, Daet, Camarines Norte.
    </p>
    <p>
      This is to certify further that said person is the legal guardian of <strong><?= htmlspecialchars($childName) ?></strong>.
    </p>
    <p>
      This certification is issued this <strong><?= formatWithSuffix($issuedDate) ?></strong> day of <?= date('F, Y', strtotime($issuedDate)) ?> 
      at Barangay Magang, Daet, Camarines Norte upon request of interested person for whatever purposes it may serve.
    </p>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

// 3) Render with Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$filename = 'barangay_id_' . $data['transaction_id'] . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
?>
