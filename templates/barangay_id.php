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
$transactionType = $data['transaction_type'] ?? '';
$fullName      = $data['full_name'] ?? '';
$purok         = $data['purok'] ?? '';
$birthDate     = $data['birth_date'] ?? '';
$birthPlace    = $data['birth_place'] ?? '';
$civilStatus   = $data['civil_status'] ?? '';
$religion      = $data['religion'] ?? '';
$height        = $data['height'] ?? '';
$weight        = $data['weight'] ?? '';
$emergencyName = $data['emergency_contact_person'] ?? '';
$emergencyAddress   = $data['emergency_contact_address'] ?? '';
$formalPic     = $data['formal_picture'] ?? '';
$paymentMethod = $data['payment_method'] ?? '';
$amount        = $data['amount'] ?? '';
$createdAt     = $data['created_at'] ?? '';

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
    .header { text-align: center; margin-bottom: 20px; }
    .header img { height: 80px; vertical-align: middle; }
    .header .title { display: inline-block; margin: 0 15px; font-size: 18pt; font-weight: bold; }
    .content { padding: 0 40px; font-size: 12pt; }
    .field { margin-bottom: 8px; }
    .label { font-weight: bold; width: 180px; display: inline-block; }
    .footer { position: fixed; bottom: 20px; width: 100%; text-align: center; font-size: 10pt; }
  </style>
</head>
<body>
  <div class="header">
    <img src="<?= $srcGov ?>" alt="Gov Logo">
    <span class="title">BARANGAY ID CERTIFICATE</span>
    <img src="<?= $srcBrgy ?>" alt="Barangay Logo">
  </div>
    <div class="content">
      <div class="field"><span class="label">Transaction ID:</span> <span><?= htmlspecialchars($transactionId) ?></span></div>
      <div class="field"><span class="label">Request Type:</span> <span><?= htmlspecialchars($requestType) ?></span></div>
      <div class="field"><span class="label">Transaction Type:</span> <span><?= htmlspecialchars($transactionType) ?></span></div>
      <div class="field"><span class="label">Full Name:</span> <span><?= htmlspecialchars($fullName) ?></span></div>
      <div class="field"><span class="label">Purok:</span> <span><?= htmlspecialchars($purok) ?></span></div>
      <div class="field"><span class="label">Birth Date:</span> <span><?= htmlspecialchars($birthDate) ?></span></div>
      <div class="field"><span class="label">Birth Place:</span> <span><?= htmlspecialchars($birthPlace) ?></span></div>
      <div class="field"><span class="label">Civil Status:</span> <span><?= htmlspecialchars($civilStatus) ?></span></div>
      <div class="field"><span class="label">Religion:</span> <span><?= htmlspecialchars($religion) ?></span></div>
      <div class="field"><span class="label">Height:</span> <span><?= htmlspecialchars($height) ?> ft</span></div>
      <div class="field"><span class="label">Weight:</span> <span><?= htmlspecialchars($weight) ?> kg</span></div>
      <div class="field"><span class="label">Emergency Contact:</span> <span><?= htmlspecialchars($emergencyName) ?> - <?= htmlspecialchars($emergencyAddress) ?></span></div>
      <div class="field"><span class="label">Payment Method:</span> <span><?= htmlspecialchars($paymentMethod) ?></span></div>
      <div class="field"><span class="label">Amount Paid:</span> <span><?= htmlspecialchars(number_format((float)$amount, 2)) ?></span></div>
      <div class="field"><span class="label">Created At:</span> <span><?= htmlspecialchars(date('F j, Y g:i A', strtotime($createdAt))) ?></span></div>
    </div>
  <div class="footer">
    Printed on <?= date('F j, Y \a\t g:i A') ?>
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
