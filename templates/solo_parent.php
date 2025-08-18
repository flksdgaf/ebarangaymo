<?php
// templates/print/solo_parent.php
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../functions/dbconn.php';

$govLogo  = realpath(__DIR__ . '/../images/good_governance_logo.png');
$brgyLogo = realpath(__DIR__ . '/../images/magang_logo.png');
$srcGov   = 'data:image/png;base64,' . base64_encode(file_get_contents($govLogo));
$srcBrgy  = 'data:image/png;base64,' . base64_encode(file_get_contents($brgyLogo));

$transactionId   = $data['transaction_id'] ?? '';
$fullName        = $data['full_name'] ?? '';
$residentAge     = $data['age'] ?? ''; // ✅ Renamed to avoid overwrite
$civilStatusRaw  = strtolower($data['civil_status'] ?? '');
$civilStatus     = $civilStatusRaw;
$purok           = $data['purok'] ?? '';
$yearsSoloParent = $data['years_solo_parent'] ?? '';
$purpose         = $data['purpose'] ?? '';
$parentSex       = strtolower($data['parent_sex'] ?? 'female');
$issuedDate      = date('Y-m-d');

$pronoun = ($parentSex === 'male') ? 'his' : 'her';

$children = [];
$genderCount = ['Male' => 0, 'Female' => 0];
$childDescriptions = [];

if (!empty($transactionId)) {
    $stmt = $conn->prepare("SELECT child_name, child_age, child_sex FROM solo_parent_requests WHERE transaction_id = ?");
    $stmt->bind_param("s", $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $children[] = $row;
        $childName = strtoupper(htmlspecialchars($row['child_name']));
        $childAge = htmlspecialchars($row['child_age']); // ✅ Separate childAge variable
        $childDescriptions[] = "<strong>$childName</strong>, $childAge years old";
        $genderCount[$row['child_sex']]++;
    }
    $stmt->close();
}

$childCount = count($children);

// Format number to words like: nine (9)
$numberText = new NumberFormatter("en", NumberFormatter::SPELLOUT);
$yearsWord = $yearsSoloParent ? $numberText->format($yearsSoloParent) . ' (' . $yearsSoloParent . ') year' . ($yearsSoloParent > 1 ? 's' : '') : '';

$genderSummaryParts = [];
if ($genderCount['Female'] > 0) {
    $fem = $genderCount['Female'];
    $genderSummaryParts[] = "{$numberText->format($fem)} (" . number_format($fem) . ") " . ($fem > 1 ? 'girls' : 'girl');
}
if ($genderCount['Male'] > 0) {
    $mal = $genderCount['Male'];
    $genderSummaryParts[] = "{$numberText->format($mal)} (" . number_format($mal) . ") " . ($mal > 1 ? 'boys' : 'boy');
}
$genderSummary = implode(' and ', $genderSummaryParts);

// Determine status term (underlined if separated/widowed)
if ($civilStatusRaw === 'separated') {
    $statusTerm = "has been <u>separated</u> for {$yearsWord}.";
} elseif ($civilStatusRaw === 'widowed') {
    $statusTerm = "has been <u>widowed</u> for {$yearsWord}.";
} else {
    $statusTerm = "has been {$civilStatus} for {$yearsWord}.";
}

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

// Preview / DOMPDF toggles
$download = isset($_GET['download']) && $_GET['download'] === '1';
$print    = isset($_GET['print']) && $_GET['print'] === '1';
$includeHeader = isset($_GET['includeHeader']) && $_GET['includeHeader'] === '1';

// === DOMPDF MODE (download or print) ===
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
          margin-bottom: 20px;
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
        .header-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
        .header-table td { text-align:center; vertical-align:middle; }
        .header-table img { height:120px; width:auto; }
        .header-title { font-size:13pt; line-height:1.2; }
        .line{border-bottom:5px solid #000; margin-bottom:3px;}
        .line2{border-bottom:2px solid #000; margin-bottom:30px;}
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
        <p class="certification-title">CERTIFICATE OF SOLO PARENT</p>

        <p class="no-indent">TO WHOM IT MAY CONCERN:</p>

        <p>
          This is to certify that <strong><u><?= htmlspecialchars(strtoupper($fullName)) ?></u></strong>, 
          <strong><?= htmlspecialchars($residentAge) ?></strong> years old, <?= strtoupper($civilStatus === 'WIDOWED' ? 'WIDOW' : $civilStatus) ?>,
          is a resident of <?= htmlspecialchars($purok) ?>, Magang, Daet, Camarines Norte.
        </p>

        <p>
          This is to certify that the said person is a <strong>SOLO PARENT</strong> to <?= $pronoun ?> <?= ($childCount > 1 ? 'children' : 'child') ?>, <?= $genderSummary ?> 
          <?= implode(', ', $childDescriptions) ?>, and <?= $statusTerm ?>
        </p>

        <p>
          Issued this <strong><?= formatWithSuffix($issuedDate) ?></strong> day of <?= date('F, Y', strtotime($issuedDate)) ?> at Barangay Magang, Daet, Camarines Norte for 
          <strong><?= htmlspecialchars(strtoupper($purpose)) ?></strong> purposes.
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
    $filename = 'solo_parent_certificate_' . $transactionId . '.pdf';
    // If ?download=1 -> Attachment true (download). If print=1 (and download not set) -> inline (Attachment false).
    $dompdf->stream($filename, ['Attachment' => $download]);
    exit;
}

// === HTML PREVIEW MODE ===
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Solo Parent Certificate Preview</title>
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
      font-size:18pt; text-align:center;
      font-weight:bold; text-decoration:underline;
      margin-bottom:20px;
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

    <p class="certification-title">CERTIFICATE OF SOLO PARENT</p>

    <p class="no-indent">TO WHOM IT MAY CONCERN:</p>

    <p>
      This is to certify that <strong><u><?= htmlspecialchars(strtoupper($fullName)) ?></u></strong>, 
      <strong><?= htmlspecialchars($residentAge) ?></strong> years old, <?= strtoupper($civilStatus === 'WIDOWED' ? 'WIDOW' : $civilStatus) ?>,
      is a resident of <?= htmlspecialchars($purok) ?>, Magang, Daet, Camarines Norte.
    </p>

    <p>
      This is to certify that the said person is a <strong>SOLO PARENT</strong> to <?= $pronoun ?> <?= ($childCount > 1 ? 'children' : 'child') ?>, <?= $genderSummary ?> 
      <?= implode(', ', $childDescriptions) ?>, and <?= $statusTerm ?>
    </p>

    <p>
      Issued this <strong><?= formatWithSuffix($issuedDate) ?></strong> day of <?= date('F, Y', strtotime($issuedDate)) ?> at Barangay Magang, Daet, Camarines Norte for 
      <strong><?= htmlspecialchars(strtoupper($purpose)) ?></strong> purposes.
    </p>
  </div>
</body>
</html>
