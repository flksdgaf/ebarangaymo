<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Fetch the record
$tid = $_GET['transaction_id'] ?? die('Missing transaction_id');
$stmt = $conn->prepare("SELECT client_name, client_address, respondent_name, respondent_address, created_at FROM blotter_records WHERE transaction_id = ?");
$stmt->bind_param('s', $tid);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) die('Record not found');

// Helper: Reformat "Lastname, Firstname Middlename" → "Firstname Middlename Lastname"
function reformatName($name) {
    $parts = explode(',', $name);
    if (count($parts) == 2) {
        $last = trim($parts[0]);
        $firstMiddle = trim($parts[1]);
        return $firstMiddle . ' ' . $last;
    }
    return $name; // fallback if not in expected format
}

// Assign & format variables
$clientName = reformatName($data['client_name']);
$clientAddress = $data['client_address'];
$respondentName = reformatName($data['respondent_name']);
$respondentAddress = $data['respondent_address'];
$createdAt = date('Y-m-d', strtotime($data['created_at']));
$formattedDate = date('F j, Y', strtotime($createdAt));

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
      margin-bottom: 30px;
    }
    p {
      text-indent: 50px;
      margin-bottom: 20px;
      line-height: 1.6;
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
    <p class="certification-title">KATUNAYAN</p>

    <p class="no-indent"><strong>SA KINAUUKULAN:</strong></p>

    <p>
      Ito ay nagpapatunay na si <strong><?= htmlspecialchars($clientName) ?></strong>, taga <?= htmlspecialchars($clientAddress) ?> ay nagtungo sa tanggapan ng Punong Barangay noong <?= $formattedDate ?> upang ireklamo si <strong><?= htmlspecialchars($respondentName) ?></strong> na taga <?= htmlspecialchars($respondentAddress) ?>.
    </p>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Render with Dompdf
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream("Blotter_Certificate_{$tid}.pdf", ['Attachment' => false]);
exit;
?>