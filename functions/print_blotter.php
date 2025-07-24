<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$tid = $_GET['transaction_id'] ?? die('Missing transaction_id');
$download = isset($_GET['download']) && $_GET['download'] == '1';
$print = isset($_GET['print']) && $_GET['print'] == '1';

$stmt = $conn->prepare("SELECT client_name, client_address, respondent_name, respondent_address, created_at FROM blotter_records WHERE transaction_id = ?");
$stmt->bind_param('s', $tid);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) die('Record not found');

function reformatName($name) {
    $parts = explode(',', $name);
    return count($parts) === 2 ? trim($parts[1]) . ' ' . trim($parts[0]) : $name;
}
function extractLastName($name) {
    $parts = explode(',', $name);
    return trim($parts[0] ?? '');
}

$clientName = reformatName($data['client_name']);
$clientLast = extractLastName($data['client_name']);
$clientAddress = $data['client_address'];

$respondentName = reformatName($data['respondent_name']);
$respondentLast = extractLastName($data['respondent_name']);
$respondentAddress = $data['respondent_address'];

$createdAt = date('Y-m-d', strtotime($data['created_at']));
$formattedDate = date('F j, Y', strtotime($createdAt));

// Prepare logos as base64
$govLogoPath  = realpath(__DIR__ . '/../images/good_governance_logo.png');
$brgyLogoPath = realpath(__DIR__ . '/../images/magang_logo.png');
$govLogoSrc   = 'data:image/png;base64,' . base64_encode(file_get_contents($govLogoPath));
$brgyLogoSrc  = 'data:image/png;base64,' . base64_encode(file_get_contents($brgyLogoPath));

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
      font-family: 'Times New Roman', Times, serif;
      margin: 0;
      padding: 20px 20px;
      font-size: 13pt;
    }
    .header-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    .header-table td {
      text-align: center;
      vertical-align: middle;
    }
    .header-table img {
      height: 120px;
      width: auto;
    }
    .header-title {
      font-size: 13pt;
      line-height: 1.2;
    }
    .line {
      border-bottom: 5px solid #000;
      margin-bottom: 3px;
    }
    .line2 {
      border-bottom: 2px solid #000;
      margin-bottom: 30px;
    }
    .content {
      text-align: justify;
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
  <table class="header-table">
    <tr>
      <td style="width: 20%; text-align: left;"><img src="<?= $brgyLogoSrc ?>" alt="Brgy Logo"></td>
      <td style="width: 60%;" class="header-title">
        Republic of the Philippines<br>
        Province of Camarines Norte<br>
        Municipality of Daet<br>
        <strong>BARANGAY MAGANG</strong><br><br>
        <strong>OFFICE OF THE PUNONG BARANGAY</strong>
      </td>
      <td style="width: 20%; text-align: right;"><img src="<?= $govLogoSrc ?>" alt="Governance Logo"></td>
    </tr>
  </table>
  <div class="line"></div>
  <div class="line2"></div>

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

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = "Blotter Certificate ({$clientLast} - {$respondentLast}).pdf";
    $dompdf->stream($filename, ['Attachment' => $download]);
    exit;
}
?>

<!-- === HTML PREVIEW MODE === -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Blotter Certificate Preview</title>
  <style>
    html, body {
      margin: 0;
      padding: 0;
      background-color: #ccc;
      width: 100vw;
      height: 100vh;
      overflow: hidden;
      font-family: 'Times New Roman', Times, serif;
      display: flex;
      justify-content: center;
      align-items: start;
    }
    .paper {
      width: 794px; /* Full A4 width */
      height: 1123px;
      background: white;
      padding: 30px 40px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
      box-sizing: border-box;
    }
    .header-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    .header-table td {
      text-align: center;
      vertical-align: middle;
    }
    .header-table img {
      height: 120px;
      width: auto;
    }
    .header-title {
      font-size: 13pt;
      line-height: 1.2;
    }
    .line {
      border-bottom: 5px solid #000;
      margin-bottom: 3px;
    }
    .line2 {
      border-bottom: 2px solid #000;
      margin-bottom: 30px;
    }
    .certification-title {
      font-size: 20pt;
      text-align: center;
      font-weight: bold;
      text-decoration: underline;
      margin-bottom: 30px;
    }
    p {
      text-indent: 50px;
      margin-bottom: 20px;
      line-height: 1.6;
      font-size: 14pt;
      color: #000;
    }
    .no-indent {
      text-indent: 0;
      font-size: 14pt;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
  <div class="paper">
    <table class="header-table">
      <tr>
        <td style="width: 20%; text-align: left; vertical-align: middle;">
          <img src="<?= $brgyLogoSrc ?>" alt="Brgy Logo">
        </td>
        <td style="width: 60%;" class="header-title">
          Republic of the Philippines<br>
          Province of Camarines Norte<br>
          Municipality of Daet<br>
          <strong>BARANGAY MAGANG</strong><br><br>
          <strong>OFFICE OF THE PUNONG BARANGAY</strong>
        </td>
        <td style="width: 20%; text-align: right; vertical-align: middle;">
          <img src="<?= $govLogoSrc ?>" alt="Governance Logo">
        </td>
      </tr>
    </table>
    <div class="line"></div>
    <div class="line2"></div>

    <p class="certification-title">KATUNAYAN</p>
    <p class="no-indent"><strong>SA KINAUUKULAN:</strong></p>
    <p>
      Ito ay nagpapatunay na si <strong><?= htmlspecialchars($clientName) ?></strong>, taga <?= htmlspecialchars($clientAddress) ?> ay nagtungo sa tanggapan ng Punong Barangay noong <?= $formattedDate ?> upang ireklamo si <strong><?= htmlspecialchars($respondentName) ?></strong> na taga <?= htmlspecialchars($respondentAddress) ?>.
    </p>
  </div>
</body>
</html>
