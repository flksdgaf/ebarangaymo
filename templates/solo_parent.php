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

// Helper to reformat name from "Last, First, Middle" to "First Middle Last"
function reformatName($name) {
    // Split by comma and trim whitespace
    $parts = array_map('trim', explode(',', $name));
    
    if (count($parts) >= 3) {
        // Format: "Lastname, Firstname, Middlename"
        // Return: "Firstname Middlename Lastname"
        return $parts[1] . ' ' . $parts[2] . ' ' . $parts[0];
    } elseif (count($parts) === 2) {
        // Format: "Lastname, Firstname" (no middle name)
        // Return: "Firstname Lastname"
        return $parts[1] . ' ' . $parts[0];
    }
    
    // Fallback: return as-is
    return $name;
}

// Fetch Barangay Captain name
$captainStmt = $conn->prepare("SELECT account_id FROM user_accounts WHERE role = 'Brgy Captain' LIMIT 1");
$captainStmt->execute();
$captainResult = $captainStmt->get_result();
$captainName = '';

if ($captainResult && $captainResult->num_rows > 0) {
    $captainData = $captainResult->fetch_assoc();
    $captainAccountId = $captainData['account_id'];
    
    $purokTables = ['purok1_rbi', 'purok2_rbi', 'purok3_rbi', 'purok4_rbi', 'purok5_rbi', 'purok6_rbi'];
    
    foreach ($purokTables as $table) {
        $nameStmt = $conn->prepare("SELECT full_name FROM {$table} WHERE account_ID = ? LIMIT 1");
        $nameStmt->bind_param('i', $captainAccountId);
        $nameStmt->execute();
        $nameResult = $nameStmt->get_result();
        
        if ($nameResult && $nameResult->num_rows > 0) {
            $nameData = $nameResult->fetch_assoc();
            $captainName = reformatName($nameData['full_name']);
            $nameStmt->close();
            break;
        }
        $nameStmt->close();
    }
}
$captainStmt->close();

// Fetch data
$transactionId   = $data['transaction_id'] ?? '';
$fullName        = $data['full_name'] ?? '';
$residentAge     = $data['age'] ?? '';
$civilStatusRaw  = strtolower($data['civil_status'] ?? '');
$civilStatus     = $civilStatusRaw;
$purok           = $data['purok'] ?? '';
$yearsSoloParent = $data['years_solo_parent'] ?? '';
$purpose         = $data['purpose'] ?? '';
$parentSex       = strtolower($data['sex'] ?? 'female');
$issuedDate      = date('Y-m-d');

// Reformat the full name
$fullNameFormatted = reformatName($fullName);

$pronoun = ($parentSex === 'male') ? 'his' : 'her';

// Fetch children data from JSON column
$children = [];
$genderCount = ['Male' => 0, 'Female' => 0];
$childrenByGender = ['Male' => [], 'Female' => []];

if (!empty($transactionId)) {
    $stmt = $conn->prepare("SELECT children_data FROM solo_parent_requests WHERE transaction_id = ?");
    $stmt->bind_param("s", $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $childrenJson = $row['children_data'];
        if (!empty($childrenJson)) {
            $children = json_decode($childrenJson, true);
            
            if (is_array($children)) {
                foreach ($children as $child) {
                    $childName = strtoupper(htmlspecialchars($child['name'] ?? ''));
                    // Use age_display directly from the stored data
                    $childAge = htmlspecialchars($child['age_display'] ?? ($child['age'] ?? ''));
                    $childSex = $child['sex'] ?? 'Male';
                    
                    if (isset($genderCount[$childSex])) {
                        $genderCount[$childSex]++;
                        $childrenByGender[$childSex][] = [
                            'name' => $childName,
                            'age' => $childAge
                        ];
                    }
                }
            }
        }
    }
    $stmt->close();
}

$childCount = count($children);

// Format number to words
$numberText = new NumberFormatter("en", NumberFormatter::SPELLOUT);
$yearsWord = $yearsSoloParent ? $numberText->format($yearsSoloParent) . ' (' . $yearsSoloParent . ') years' : '';

// Build gender summary with children grouped by gender
$genderParts = [];

// Determine order: lowest count first
$orderedGenders = [];
if ($genderCount['Female'] > 0 && $genderCount['Male'] > 0) {
    if ($genderCount['Female'] <= $genderCount['Male']) {
        $orderedGenders = ['Female', 'Male'];
    } else {
        $orderedGenders = ['Male', 'Female'];
    }
} elseif ($genderCount['Female'] > 0) {
    $orderedGenders = ['Female'];
} elseif ($genderCount['Male'] > 0) {
    $orderedGenders = ['Male'];
}

// Build parts for each gender
foreach ($orderedGenders as $gender) {
    $count = $genderCount[$gender];
    $label = ($gender === 'Male') ? ($count > 1 ? 'boys' : 'boy') : ($count > 1 ? 'girls' : 'girl');
    
    // Build the gender header: "two (2) girls"
    $genderHeader = "{$numberText->format($count)} ({$count}) {$label}";

    // Build children list for this gender
    $childList = [];
    foreach ($childrenByGender[$gender] as $child) {
        $trimmedName = trim($child['name']); // Remove any trailing/leading spaces
        // Don't add "years old" since age_display already contains it
        $childList[] = "<strong>{$trimmedName}</strong>, {$child['age']}";
    }
    
    // Combine: "two (2) girls CHILD NAME, 22 years old, CHILD NAME, 17 years old"
    $genderParts[] = $genderHeader . ' ' . implode(', ', $childList);
}

// Join with "and": "two (2) girls ... and one (1) boy ..."
$genderSummary = implode(', and ', $genderParts);

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
          padding: 20px;
          font-size: 13pt;
        }
        .header-table { 
          width:100%; 
          border-collapse:collapse; 
          margin-bottom:20px; 
        }
        .header-table td { 
          text-align:center; 
          vertical-align:middle; 
        }
        .header-table img { 
          height:120px; 
          width:auto; 
        }
        .header-title { 
          font-size:13pt; 
          line-height:1.2; 
        }
        .line{
          border-bottom:5px solid #000; 
          margin-bottom:3px;
        }
        .line2{
          border-bottom:2px solid #000; 
          margin-bottom:30px;
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
          line-height: 1.6;
        }
        .no-indent {
          text-indent: 0;
          font-size: 13pt;
          margin-bottom: 40px;
        }
        .signatory {
          margin-top: 60px;
          text-align: right;
          padding-right: 50px;
        }
        .signatory-name {
          font-weight: bold;
          text-transform: uppercase;
          margin-bottom: 5px;
        }
        .signatory-title {
          font-size: 12pt;
        }
      </style>
    </head>
    <body<?php if (!$includeHeader): ?> style="padding-top: 150px;"<?php endif; ?>>
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

        <p class="no-indent"><strong>TO WHOM IT MAY CONCERN:</strong></p>

        <p>
          This is to certify that <strong><?= htmlspecialchars(strtoupper($fullNameFormatted)) ?></strong>, 
          <strong><?= htmlspecialchars($residentAge) ?></strong> years old, 
          <strong><?= htmlspecialchars(strtoupper($civilStatus)) ?></strong>, 
          <!-- <strong><= strtoupper($civilStatus === 'widowed' ? 'WIDOW' : $civilStatus) ?></strong>, -->
          is a resident of <?= htmlspecialchars($purok) ?>, Magang, Daet, Camarines Norte.
        </p>

        <p>
          This is to certify that the said person is a <strong>SOLO PARENT</strong> to <?= $pronoun ?> <?= ($childCount > 1 ? 'children' : 'child') ?>, <?= $genderSummary ?>, 
          <?= $statusTerm ?>
        </p>

        <p>
          Issued this <strong><?= formatWithSuffix($issuedDate) ?></strong> day of <?= date('F, Y', strtotime($issuedDate)) ?> at Barangay Magang, Daet, Camarines Norte
          <?php if (!empty($purpose)): ?>
            for <strong><?= htmlspecialchars(strtoupper($purpose)) ?></strong> purposes.
          <?php else: ?>
            for <strong>WHATEVER PURPOSES IT MAY SERVE</strong>.
          <?php endif; ?>
        </p>
      </div>

      <?php if ($includeHeader): ?>
        <!-- Signatory Section -->
        <div class="signatory">
          <div class="signatory-name"><?= htmlspecialchars($captainName) ?></div>
          <div class="signatory-title">Punong Barangay</div>
        </div>
      <?php endif; ?>
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
    .paper.no-header {
      padding-top: 180px;
    }
    .header-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    .header-table td { text-align:center; vertical-align:middle; }
    .header-table img { height:120px; width:auto; }
    .header-title { font-size:13pt; line-height:1.2; }
    .line{border-bottom:5px solid #000; margin-bottom:3px;}
    .line2{border-bottom:2px solid #000; margin-bottom:30px;}
    .certification-title{
      font-size:20pt; text-align:center;
      letter-spacing:5px;
      font-weight:bold; text-decoration:underline;
      margin-bottom:30px;
    }
    .content { text-align: justify; width:100%; }
    p {
      text-indent:50px; margin-bottom:20px;
      line-height:1.6; font-size:14pt; color:#000;
    }
    .no-indent{ text-indent:0; font-size:14pt; margin-bottom:20px; }
    .signatory {
      margin-top: 60px;
      text-align: right;
      padding-right: 50px;
    }
    .signatory-name {
      font-weight: bold;
      text-transform: uppercase;
      margin-bottom: 5px;
    }
    .signatory-title {
      font-size: 12pt;
    }
  </style>
</head>
<body>
  <div class="paper<?php if (!$includeHeader): ?> no-header<?php endif; ?>">
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

      <p class="no-indent"><strong>TO WHOM IT MAY CONCERN:</strong></p>

      <p>
        This is to certify that <strong><?= htmlspecialchars(strtoupper($fullNameFormatted)) ?></strong>, 
        <strong><?= htmlspecialchars($residentAge) ?></strong> years old, 
        <strong><?= htmlspecialchars(strtoupper($civilStatus)) ?></strong>, 
        <!-- <strong><= strtoupper($civilStatus === 'widowed' ? 'WIDOW' : $civilStatus) ?></strong>, -->
        is a resident of <?= htmlspecialchars($purok) ?>, Magang, Daet, Camarines Norte.
      </p>

      <p>
        This is to certify that the said person is a <strong>SOLO PARENT</strong> to <?= $pronoun ?> <?= ($childCount > 1 ? 'children' : 'child') ?>, <?= $genderSummary ?>, 
        <?= $statusTerm ?>
      </p>

      <p>
        Issued this <strong><?= formatWithSuffix($issuedDate) ?></strong> day of <?= date('F, Y', strtotime($issuedDate)) ?> at Barangay Magang, Daet, Camarines Norte
        <?php if (!empty($purpose)): ?>
          for <strong><?= htmlspecialchars(strtoupper($purpose)) ?></strong> purposes.
        <?php else: ?>
          for <strong>WHATEVER PURPOSES IT MAY SERVE</strong>.
        <?php endif; ?>
      </p>
    </div>

    <?php if ($includeHeader): ?>
      <!-- Signatory Section -->
      <div class="signatory">
        <div class="signatory-name"><?= htmlspecialchars($captainName) ?></div>
        <div class="signatory-title">Punong Barangay</div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>