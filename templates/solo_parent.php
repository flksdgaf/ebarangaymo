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
$age             = $data['age'] ?? '';
$civilStatus     = strtolower($data['civil_status'] ?? '');
$purok           = $data['purok'] ?? '';
$yearsSoloParent = $data['years_solo_parent'] ?? '';
$purpose         = $data['purpose'] ?? '';
$parentSex       = strtolower($data['parent_sex'] ?? 'female');
$issuedDate = date('Y-m-d');

$pronoun   = ($parentSex === 'male') ? 'his' : 'her';
$statusTerm = ($civilStatus === 'widowed') ? 'widowed' : 'separated';

$children = [];
$childAge = $data['child_age'] ?? '';;
$genderCount = ['Male' => 0, 'Female' => 0];
$childDescriptions = [];

if (!empty($transactionId)) {
    $stmt = $conn->prepare("SELECT child_name, child_age, child_sex FROM solo_parent_requests WHERE transaction_id = ?");
    $stmt->bind_param("s", $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $children[] = $row;
        $name = htmlspecialchars($row['child_name']);
        $age = htmlspecialchars($row['child_age']);
        $childDescriptions[] = "<strong>$name</strong>, $childAge years old";
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
  </style>
</head>
<body>
  <div class="content">
    <p class="certification-title">CERTIFICATE OF SOLO PARENT</p>

    <p class="no-indent">TO WHOM IT MAY CONCERN:</p>

    <p>
      This is to certify that <strong><u><?= htmlspecialchars(strtoupper($fullName)) ?></u></strong>, <?= htmlspecialchars($age) ?> years old, <?= htmlspecialchars(ucfirst($civilStatus)) ?>, 
      is a resident of <?= htmlspecialchars($purok) ?>, Magang, Daet, Camarines Norte.
    </p>

    <p>
      This is to certify that the said person is a <strong>SOLO PARENT</strong> to <?= $pronoun ?> <?= ($childCount > 1 ? 'children' : 'child') ?>, <?= $genderSummary ?>
      <?= implode(', ', $childDescriptions) ?>, and has been <?= $statusTerm ?> for <?= $yearsWord ?>.
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
$dompdf->stream($filename, ['Attachment' => false]);
exit;
?>
