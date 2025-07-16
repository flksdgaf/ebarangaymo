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
$yearsSoloParent = $data['years_solo_parent'] ?? '';
$childName     = $data['child_name'] ?? '';
$childAge      = $data['child_age'] ?? '';
$childSex      = $data['child_sex'] ?? '';
$purpose       = $data['purpose'] ?? '';
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
      <div class="field"><span class="label">Full Name:</span> <span><?= htmlspecialchars($fullName) ?></span></div>
      <div class="field"><span class="label">Civil Status:</span> <span><?= htmlspecialchars($civilStatus) ?></span></div>
      <div class="field"><span class="label">Age:</span> <span><?= htmlspecialchars($age) ?></span></div>
      <div class="field"><span class="label">Purok:</span> <span><?= htmlspecialchars($purok) ?></span></div>
      <div class="field"><span class="label">Years as Solo Parent:</span> <span><?= htmlspecialchars($yearsSoloParent) ?></span></div>
      <div class="field"><span class="label">Child Name:</span> <span><?= htmlspecialchars($childName) ?></span></div>
      <div class="field"><span class="label">Child Age:</span> <span><?= htmlspecialchars($childAge) ?></span></div>
      <div class="field"><span class="label">Child Sex:</span> <span><?= htmlspecialchars($childSex) ?></span></div>
      <div class="field"><span class="label">Purpose:</span> <span><?= htmlspecialchars($purpose) ?></span></div>
      <div class="field"><span class="label">Payment Method:</span> <span><?= htmlspecialchars($paymentMethod) ?></span></div>
      <div class="field"><span class="label">Amount:</span> <span><?= htmlspecialchars($amount) ?></span></div>
      <div class="field"><span class="label">Created At:</span> <span><?= htmlspecialchars($createdAt) ?></span></div>
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
