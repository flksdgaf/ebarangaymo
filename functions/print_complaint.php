<?php
// print_complaint.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1) Auth check
session_start();
if (!($_SESSION['auth'] ?? false)) {
  header('HTTP/1.1 403 Forbidden');
  exit('Not authorized');
}

// 2) transaction_id
$tid = $_GET['transaction_id'] ?? '';
$stage = $_GET['stage'] ?? 'Punong Barangay';
$dateOverride = $_GET['date'] ?? null;
$timeOverride = $_GET['time'] ?? null;
if (!$tid) {
  exit('Missing transaction_id');
}

// 3) Load complaint + latest summon schedule
$sql = "
  SELECT
    complainant_name,
    complainant_address,
    respondent_name,
    respondent_address,
    complaint_title AS complaint_type,
    complaint_affidavit,
    pleading_statement,
    DATE_FORMAT(created_at, '%M %e, %Y %l:%i %p') AS created_fmt,
    schedule_pb_first AS scheduled_at
  FROM barangay_complaints
  WHERE transaction_id = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $tid);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rec) {
  exit('Complaint not found.');
}

// 3.1) Fetch Brgy. Captain name using account_id from purok tables
$captainName = '';
$res = $conn->query("SELECT account_id FROM user_accounts WHERE role = 'Brgy Captain' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $captainId = $row['account_id'];
    $purokTables = ['purok1_rbi', 'purok2_rbi', 'purok3_rbi', 'purok4_rbi', 'purok5_rbi','purok6_rbi'];
    foreach ($purokTables as $table) {
        $stmt = $conn->prepare("SELECT full_name FROM $table WHERE account_id = ? LIMIT 1");
        $stmt->bind_param("i", $captainId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($data = $result->fetch_assoc()) {
            // Reformat: Surname, Firstname Middlename -> Firstname M. Surname
            $parts = explode(',', $data['full_name']);
            if (count($parts) === 2) {
                $surname = trim($parts[0]);
                $firstMiddle = trim($parts[1]);
                $fmParts = explode(' ', $firstMiddle);
                $firstname = $fmParts[0] ?? '';
                $middlename = $fmParts[1] ?? '';
                $middleInitial = $middlename ? strtoupper($middlename[0]) . '.' : '';
                $captainName = strtoupper("$firstname $middleInitial $surname");
            }
            $stmt->close();
            break;
        }
        $stmt->close();
    }
}

// 3.2) Split complainant address into parts
$complainantBarangay = '';
$complainantMunicipality = '';
$complainantProvince = '';

$addressParts = explode(',', $rec['complainant_address']);
$complainantBarangay = trim($addressParts[0] ?? '');
$complainantMunicipality = trim($addressParts[1] ?? '');
$complainantProvince = trim($addressParts[2] ?? '');

$complainantMunicipalityProvince = $complainantMunicipality;
if ($complainantProvince) {
    $complainantMunicipalityProvince .= ', ' . $complainantProvince;
}

// 3.3) Split respondent address into parts
$respondentBarangay = '';
$respondentMunicipality = '';
$respondentProvince = '';

$respAddressParts = explode(',', $rec['respondent_address']);
$respondentBarangay = trim($respAddressParts[0] ?? '');
$respondentMunicipality = trim($respAddressParts[1] ?? '');
$respondentProvince = trim($respAddressParts[2] ?? '');

$respondentMunicipalityProvince = $respondentMunicipality;
if ($respondentProvince) {
    $respondentMunicipalityProvince .= ', ' . $respondentProvince;
}

// 4) Prepare logos
$logoA = realpath(__DIR__ . '/../images/magang_logo.png');
$logoB = realpath(__DIR__ . '/../images/good_governance_logo.png');
if (! $logoA || ! $logoB) {
  exit("Missing logo images");
}
$dataA = base64_encode(file_get_contents($logoA));
$dataB = base64_encode(file_get_contents($logoB));
$srcA = 'data:image/png;base64,' . $dataA;
$srcB = 'data:image/png;base64,' . $dataB;

// 5) Format the summon date/time
if ($dateOverride && $timeOverride) {
  // Use passed values
  try {
    $dt = new DateTime("$dateOverride $timeOverride");
    $summonDate = $dt->format('F j, Y');
    $summonTime = $dt->format('g:i A');
  } catch (Exception $e) {
    $summonDate = $summonTime = '—';
  }
} else {
  // Use fallback from database
  $dtRaw = $rec['scheduled_at'] ?? null;
  if ($dtRaw) {
    $dt = new DateTime($dtRaw);
    $summonDate = $dt->format('F j, Y');
    $summonTime = $dt->format('g:i A');
  } else {
    $summonDate = $summonTime = '—';
  }
}

// $dtRaw = $rec['scheduled_at'] ?? null;
// if ($dtRaw) {
//   $dt = new DateTime($dtRaw);
//   $summonDate = $dt->format('F j, Y');
//   $summonTime = $dt->format('g:i A');
// } else {
//   $summonDate = $summonTime = '—';
// }

// 5.1) Format Filipino date
function getFilipinoMonth($monthNumber) {
  $months = [
    1 => 'Enero', 2 => 'Pebrero', 3 => 'Marso',
    4 => 'Abril', 5 => 'Mayo', 6 => 'Hunyo',
    7 => 'Hulyo', 8 => 'Agosto', 9 => 'Setyembre',
    10 => 'Oktubre', 11 => 'Nobyembre', 12 => 'Disyembre'
  ];
  return $months[(int)$monthNumber] ?? '';
}

// $filDateRaw = $rec['scheduled_at'] ?? null;
// if ($filDateRaw) {
//   $filDateObj = new DateTime($filDateRaw);
//   $day = $filDateObj->format('j'); // Day without leading zero
//   $year = $filDateObj->format('Y');
//   $monthNum = $filDateObj->format('n'); // 1-12
//   $filipinoMonth = getFilipinoMonth($monthNum);
//   $formattedFilipinoDate = $day . ' ng ' . $filipinoMonth . '</u>, ' . $year . '</strong>';
// } else {
//   $formattedFilipinoDate = 'ika-<u>— ng —</u>, —';
// }

if ($dateOverride && $timeOverride) {
  try {
    $filDateObj = new DateTime("$dateOverride $timeOverride");
    $day = $filDateObj->format('j');
    $year = $filDateObj->format('Y');
    $monthNum = $filDateObj->format('n');
    $filipinoMonth = getFilipinoMonth($monthNum);
    $formattedFilipinoDate = $day . ' ng ' . $filipinoMonth . '</u>, ' . $year . '</strong>';
  } catch (Exception $e) {
    $formattedFilipinoDate = 'ika-<u>— ng —</u>, —';
  }
} else {
  $filDateRaw = $rec['scheduled_at'] ?? null;
  if ($filDateRaw) {
    $filDateObj = new DateTime($filDateRaw);
    $day = $filDateObj->format('j');
    $year = $filDateObj->format('Y');
    $monthNum = $filDateObj->format('n');
    $filipinoMonth = getFilipinoMonth($monthNum);
    $formattedFilipinoDate = $day . ' ng ' . $filipinoMonth . '</u>, ' . $year . '</strong>';
  } else {
    $formattedFilipinoDate = 'ika-<u>— ng —</u>, —';
  }
}


$filDateCreated = $rec['created_fmt'] ?? null;
if ($filDateCreated) {
  $filDateObj = new DateTime($filDateCreated);
  $day = $filDateObj->format('j'); // Day without leading zero
  $year = $filDateObj->format('Y');
  $monthNum = $filDateObj->format('n'); // 1-12
  $filipinoMonth = getFilipinoMonth($monthNum);
  $formattedFilipinoDateCreated = $day . ' ng ' . $filipinoMonth . '</u>, ' . $year . '</strong>';
} else {
  $formattedFilipinoDateCreated = 'ika-<u>— ng —</u>, —';
}

// 6) Build the combined HTML
$html = '
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: "Times New Roman", serif; margin: 0; padding: 0; }
    .header-table { width:100%; border-collapse:collapse; margin-bottom:6px; }
    .header-table td { text-align:center; vertical-align:middle; }
    .logo { height:100px; }
    .header-text { font-size:16px; line-height:1.3; }
    .header-text .agency { display:block; margin-top:6px; }
    hr { border:none; border-top:3px solid #000; margin:4px 0; }
    .content { margin:0.2in 0.5in; font-size: 16px; }
    table.info, table.meta { border-collapse:collapse; margin-bottom:16px; line-height: 0.8;}
    table.info td, table.meta td { padding:4px 6px; white-space:nowrap; }
    table.meta { text-align: right; }
    .section-title { text-align:center; margin:16px 0 0; }
    .section-text { text-indent:0.5in; line-height:1.2; text-align:justify; }
    .line { border-bottom: 5px solid #000; margin-bottom: 3px;}
    .line2 { border-bottom: 2px solid #000;}
    .underline { text-align: justify;}
    .sign-line { display:inline-block; border-top:1px solid #000; width:160px; margin-top:40px; text-indent:0.5in;}
    .footer-captain {text-align: center; margin-top: 80px; font-size: 16px; line-height: 1.2; margin-left: 300px;}
    .footer-captain strong { text-transform: uppercase; }
    .page-break { page-break-before:always; }
  </style>
</head>
<body>

  <!-- COMPLAINT / SUMBONG -->
  <!-- HEADER -->
  <table class="header-table">
    <tr>
      <td style="width:20%"><img src="'. $srcA .'" class="logo"></td>
      <td style="width:60%" class="header-text">
        Republic of the Philippines<br>
        Province of Camarines Norte<br>
        Municipality of Daet<br>
        <strong>BARANGAY MAGANG<br>
        <span class="agency">TANGGAPAN NG LUPONG TAGAPAMAYAPA</strong></span>
      </td>
      <td style="width:20%"><img src="'. $srcB .'" class="logo"></td>
    </tr>
  </table>
  <div class="line"></div>
  <div class="line2"></div>

  <div class="content">
    <table class="meta" style="float:right;">
      <tr><td>Barangay kaso blg. <u> '. htmlspecialchars($tid) .'</u></td></tr>
      <tr><td>Para: <u> '. htmlspecialchars($rec['complaint_type']) .'</u></td></tr>
    </table>

    <table class="info" style="float:left; margin-top:50px;">
      <tr>
        <td>Name:</td>
        <td class="value"><u>'. htmlspecialchars($rec['complainant_name']) .'</u></td></tr>
      <tr>
        <td>Address:</td>
        <td class="value"><u>'. htmlspecialchars($rec['complainant_address']) .'</u></td></tr>
      <tr><td></td><td>Nagrereklamo</td></tr><br>
      <tr>
        <td>Name:</td>
        <td class="value"><u>'. htmlspecialchars($rec['respondent_name']) .'</u></td></tr>
      <tr>
        <td>Address:</td>
        <td class="value"><u>'. htmlspecialchars($rec['respondent_address']) .'</u></td></tr>
      <tr><td></td><td>Inirereklamo</td></tr>
    </table>
    <div style="clear:both;"></div>

    <div class="section-title"><strong>SUMBONG</strong></div><br>
    <div class="section-text">
      Ako/kami sa pamamagitan nito ay nagrereklamo laban sa mga pinangalanan isinakdal sa
      itaas, sa pagkakalabag ng aking/naming karapatan at pansariling kapakanan sa sumusunod na
      dahilan: 
    </div>
    <div class="underline"><u>'. nl2br(htmlspecialchars($rec['complaint_affidavit'])) .'</u></div><br>

    <div class="section-text">
      Dahil doon, ako/kami ay sumasamo ng sumusunod na kaluwagan/kabayaran ay
      ipinagkaloob sa akin/amin ng alinsunod sa batas at/o pagkamakatao:
    </div>
    <div class="underline"><u>'. nl2br(htmlspecialchars($rec['pleading_statement'])) .'</u></div><br>

    <div style="margin-top:40px; text-align:right; ">
      <span class="sign-line"></span><br>
      <span class="sign-label" style="margin-right:27px;">Nagrereklamo</span>
    </div><br>

    <div class="section-text">
      Tinanggap at isinasampa ngayon ika-<u>'. $formattedFilipinoDateCreated .'.
    </div>

    <div class="footer-captain">
      <strong>'. htmlspecialchars($captainName) .'</strong><br>
      Punong Barangay/Lupon Chairman
    </div>
  </div>

  <!-- PAGE BREAK -->
  <div class="page-break"></div>



  <!-- SUMMON (PATAWAG) -->
  <table class="header-table">
    <tr>
      <td style="width:20%"><img src="'. $srcA .'" class="logo"></td>
      <td style="width:60%" class="header-text">
        <strong>Republic of the Philippines<br>
        Province of Camarines Norte<br>
        Municipality of Daet<br>
        BARANGAY MAGANG<br>
        <span class="agency">OFFICE OF THE PUNONG BARANGAY</strong></span>
      </td>
      <td style="width:20%"><img src="'. $srcB .'" class="logo"></td>
    </tr>
  </table>
  <div class="line"></div>
  <div class="line2"></div>

  <div class="content">
    <table class="meta" style="float:right;">
      <tr><td>Kaso ng Barangay Blg.: <u>'. htmlspecialchars($tid) .'</u></td></tr>
      <tr><td>Para: <strong>'. htmlspecialchars($rec['complaint_type']) .'</strong></td></tr>
    </table>

    <table class="info" style="float:left; margin-top:50px;">
      <tr><td class="value"><strong>'. htmlspecialchars($rec['complainant_name']) .'</strong></td></tr>
      <tr><td class="value">'. htmlspecialchars($complainantBarangay) .',</td></tr>
      <tr><td class="value">'. htmlspecialchars($complainantMunicipalityProvince) .'</td></tr>
      <tr><td class="label">Nagrereklamo</td></tr><br>

      <tr><td style="padding-left: 70px;">- laban sa -</td></tr><br>

      <tr><td class="value"><strong>'. htmlspecialchars($rec['respondent_name']) .'</strong></td></tr>
      <tr><td class="value">'. htmlspecialchars($respondentBarangay) .'</td></tr>
      <tr><td class="value">'. htmlspecialchars($respondentMunicipalityProvince) .'</td></tr>
      <tr><td class="label">Inirereklamo</td></tr>
    </table>
    <div style="clear:both;"></div>

    <div class="section-title"><strong>- PATAWAG -</strong><br>(SUMMON)</div><br>
    <div class="kay">Kay: <u>'. htmlspecialchars($rec['complainant_name']) .' at '. htmlspecialchars($rec['respondent_name']) .'</u></div><br>
    <div class="isinumbong" style=" text-align: center;"><br>Isinumbong</div><br><br>
    <div class="section-text">
      Ikaw/kayo ay sa pamamagitan nito ay ipinatawag para humarap sa akin ng
      personal, kasama ang inyong saksi sa ika-<strong>'. $formattedFilipinoDate .'</strong>
      ng ganap na ika <strong>'. $summonTime .'</strong> ng
      umaga/hapon upang sagutin ang sumbong na isinampa sa akin, kalakip nito ang sipi, para
      ayusin ng mahinahon/pagkasunduin ang inyong sigalutan ng nagsusumbong.
    </div><br>

    <div class="section-text">
      Ikaw/kayo sa pamamagitan nito ay binabalaan na kapag ikaw/kayo ay tumanggi o
      sinadyang hindi magpakita sa pagtalima sa pagtawag, ikaw ay maaaring pagbawalan na
      magsampa ng ganting demanda na nagmula sa nabanggit ng nasasakdal.
    </div><br>

    <div class="section-text">
      Huwag na hindi o di kaya ay harapin ang parusang pagsuway sa utos ng hukuman.
    </div><br>

    <div class="section-text">
      Ngayong ika-'. $formattedFilipinoDateCreated .'.
    </div>

    <div class="footer-captain">
      <strong>'. htmlspecialchars($captainName) .'</strong><br>
      Punong Barangay/Lupon Chairman
    </div>
  </div>

  <!-- PAGE BREAK -->
  <div class="page-break"></div>



  <!-- ULAT NG OPISYAL (Officer’s Return) -->
  <div class="content">
    <div class="section-title"><strong>ULAT NG OPISYAL (Officer’s Return)</strong></div><br>

    <div class="section-text">
      Aking dinala ang patawag na ito sa nasasakdal noong ika ______ ng __________, 20___ at sa nasasakdal 
      noong ika ____ ng ____________, 20____ sa pamamagitan:<br>
      Isinasakdal/mga isinasakdal
    </div><br>

    <table style="width:100%; font-size:16px; border-collapse:collapse;">
      <tr><td style="width:100%;">_______________________ 1. Ibinigay sa kaniya/kanila ang nasabing patawag ng personal,</td></tr>
      <tr><td>_______________________ 2. Ibinigay sa kaniya/kanila ang nasabing patawag at siya/sila ay</td></tr>
      <tr><td style="padding-left: 190px;">Tumangging tanggapin ito,</td></tr>
      <tr><td>_______________________ 3. Iniwan ang nasabing patawag sa kanya/kanilang tahanan kay</td></tr>
      <tr><td style="padding-left: 300px;">_______________________</td></tr>
      <tr><td style="padding-left: 365px;">Pangalan</td></tr>
      <tr><td>_______________________ 4. Iniwan ang nabanggit na patawag sa kaniya/kanilang</td></tr>
      <tr><td style="padding-left: 190px;">Tanggapan/lugar ng pinaglilingkuran kay</td></tr>
      <tr><td style="padding-left: 300px;">_______________________</td></tr>
      <tr><td style="padding-left: 365px;">Pangalan</td></tr>
      <tr><td style="padding-left: 250px;">Isang maykakayahan taong namamahala doon.</td></tr>
    </table>

    <br><br>
    <div class="section-text">
      Tinanggap ng isinasakdal/mga isinasakdal<br>
      Kinatawan/mga kinatawan:
    </div><br><br>

    <table style="width:100%; text-align:center; font-size:16px;">
      <tr>
        <td>________________________________<br>(Lagda)</td>
        <td>________________________________<br>(Petsa)</td>
      </tr>
      <tr><td><br></td><td><br></td></tr>
      <tr>
        <td>________________________________<br>(Lagda)</td>
        <td>________________________________<br>(Petsa)</td>
      </tr>
    </table>
  </div>
</body>
</html>
';

// 7) Render PDF
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('a4', 'portrait');
$dompdf->render();
$dompdf->stream("complaint_and_summon_{$tid}.pdf", ["Attachment" => false]);
exit;
?>