<?php
// templates/print/barangay_id.php
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Prepare logos as Base64 (if needed later)
$govLogo  = realpath(__DIR__ . '/../images/good_governance_logo.png');
$brgyLogo = realpath(__DIR__ . '/../images/magang_logo.png');
$srcGov   = 'data:image/png;base64,' . base64_encode(file_get_contents($govLogo));
$srcBrgy  = 'data:image/png;base64,' . base64_encode(file_get_contents($brgyLogo));

// Fetch data
$transactionId = $data['transaction_id'] ?? '';
$requestType   = $data['request_type'] ?? '';
$fullName      = $data['full_name'] ?? '';
$civilStatus   = $data['civil_status'] ?? '';
$sex           = $data['sex'] ?? '';
$age           = $data['age'] ?? '';
$purok         = $data['purok'] ?? '';
$subdivision   = $data['subdivision'] ?? '';
$purpose       = $data['purpose'] ?? '';
$paymentMethod = $data['payment_method'] ?? '';
$amount        = $data['amount'] ?? '';
$createdAt     = $data['created_at'] ?? '';
$issuedDate    = date('Y-m-d');

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
      letter-spacing: 5px;
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
    <p class="certification-title">CERTIFICATION</p>

    <p class="no-indent"><strong>TO WHOM IT MAY CONCERN:</strong></p>

    <p>
      This is to certify that
      <strong><u><?= htmlspecialchars(strtoupper($fullName)) ?></u></strong>,
      <strong><?= htmlspecialchars($age) ?></strong> years old,
      <?= htmlspecialchars(strtoupper($civilStatus)) ?>,
      is a resident of <?= htmlspecialchars($subdivision) ?>,
      <?= htmlspecialchars($purok) ?>,
      Magang, Daet, Camarines Norte.
    </p>

    <p>
      This certifies further that the above-named person is known to me of
      <span><strong>GOOD MORAL CHARACTER</strong></span> and that
      <strong>
        <?= strtolower($sex) === 'male'
            ? 'he'
            : (strtolower($sex) === 'female'
                ? 'she'
                : 'he/she') ?>
        has no derogatory record
      </strong> on file in this Barangay.
    </p>

    <p>
      This certification is issued upon request of the above-named person for
      <span><strong><?= htmlspecialchars($purpose) ?></strong></span> purposes.
    </p>

    <p>
      Issued this
      <strong><?= formatWithSuffix($issuedDate) ?></strong>
      day of <?= date('F, Y', strtotime($issuedDate)) ?>
      at Barangay Magang, Daet, Camarines Norte.
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
$filename = 'barangay_id_' . $transactionId . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
?>
