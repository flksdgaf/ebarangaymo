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
$nameParts = explode(' ', $fullName);
$formattedName = strtoupper($fullName);

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    @page { margin: 0; }
    body { 
      font-family: Arial, sans-serif; 
      margin: 0; 
      padding: 20px;
      background: white;
    }
    .id-container {
      width: 3.375in; /* Standard ID width */
      height: 2.125in; /* Standard ID height */
      border: 3px solid #75b0fc;
      position: relative;
      background: white;
    }
    .header-section {
      background: #75b0fc;
      color: white;
      text-align: left;
      padding: 6px 5px;
    }
    .header-section img {
      height: 45px;
      vertical-align: middle;
      background: white;
      padding: 3px;
    }
    .header-text {
      display: inline-block;
      vertical-align: middle;
      margin: 0 8px;
      line-height: 1.2;
      text-align: center;
      color: black;
    }
    .header-text div:first-child {
      font-size: 7pt;
      font-weight: normal;
    }
    .header-text div:nth-child(2) {
      font-size: 7pt;
      font-weight: normal;
    }
    .header-text div:nth-child(3) {
      font-size: 7pt;
      font-weight: normal;
    }
    .header-text div:last-child {
      font-size: 7pt;
      font-weight: normal;
    }
    .title-bar {
      background: #e4c258;
      border-top: 2px solid #e4c258;
      border-bottom: 2px solid #e4c258;
      padding: 3px;
      text-align: left;
      font-size: 9.5pt;
      font-weight: bold;
      padding-left: 7px;
    }
    .photo-box {
      position: absolute;
      right: -2px;
      top: -3px;
      width: 90px;
      height: 90px;
      border: 2px solid #75b0fc;
      background: #E7E6E6;
    }
    .id-number-box {
      position: absolute;
      right: 5px;
      top: 100px;
      width: 75px;
      text-align: center;
      border: 2px solid #75b0fc;
      background: white;
      padding: 3px 0;
      box-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }
    .id-number {
      font-size: 11pt;
      font-weight: bold;
    }
    .id-label {
      font-size: 8pt;
      border-top: 1px solid #75b0fc;
      margin-top: 2px;
      padding-top: 2px;
    }
    .validity-box {
      position: absolute;
      right: 5px;
      top: 150px;
      width: 75px;
      text-align: center;
      border: 2px solid #75b0fc;
      background: white;
      padding: 3px 0;
      box-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }
    .validity-date {
      font-size: 11pt;
      font-weight: bold;
    }
    .validity-label {
      font-size: 8pt;
      border-top: 1px solid #4472C4;
      margin-top: 2px;
      padding-top: 2px;
    }
    .content-section {
      padding: 5px 15px;
      width: 200px;
    }
    .certify-text {
      text-align: center;
      font-size: 7.5pt;
      margin: 6px 0 3px 0;
    }
    .name-text {
      font-size: 11pt;
      font-weight: bold;
      text-align: center;
      margin: 3px 0;
    }
    .address-text {
      font-size: 7pt;
      text-align: justify;
      line-height: 1.2;
      margin: 5px 0 0 0;
    }
    .address-text .underline {
      text-decoration: underline;
    }
    .purpose-text {
      font-size: 8pt;
      text-align: justify;
      line-height: 1.3;
      margin: 8px 0 0 0;
    }
    .page-container {
      display: inline-block;
      width: 100%;
    }
    .id-container {
      display: inline-block;
      vertical-align: top;
      margin-right: 15px;
    }
    .id-back {
      display: inline-block;
      vertical-align: top;
      width: 3.375in; /* Standard ID width */
      height: 1.95in; /* Standard ID height */
      border: 1px solid #000;
      position: relative;
      background: white;
      padding: 15px;
      box-sizing: border-box;
    }
    /* .back-top-section {
      display: flex;
      gap: 5px;
      margin-bottom: 0px;
    } */
    .back-column-1 {
      width: 70px;
      flex-shrink: 0;
    }
    .back-column-2 {
      flex: 1;
      min-width: 70px;
      max-width: 85px;
    }
    .back-column-3 {
      flex: 1;
      min-width: 70px;
      max-width: 85px;
    }
    .thumb-box {
      width: 75px;
      height: 75px;
      border: 2px solid #000;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      font-size: 6.5pt;
      line-height: 1.2;
    }
    .back-field {
      margin-bottom: 10px;
      text-align: center;
    }
    .back-field-value {
      font-size: 5pt;
      font-weight: bold;
      padding-bottom: 1px;
    }
    .back-field-label {
      font-size: 4pt;
      font-weight: normal;
      padding-top: 1px;
    }
    .back-field-line {
      width: 100%;
      max-width: 60px;
      height: 1px;
      background: #000;
      margin: 0 auto;
    }
    .back-field-row {
      display: flex;
      gap: 10px;
      margin-bottom: 10px;
    }
    .back-field-row .back-field {
      flex: 1;
      margin-bottom: 0;
    }
    .thumb-box {
      width: 75px;
      height: 75px;
      border: 2px solid #000;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      font-size: 6.5pt;
      text-align: center;
      line-height: 1.2;
    }
    .emergency-section {
      margin-bottom: 10px;
      font-size: 6.5pt;
      margin-left: 10px;
    }
    .emergency-title {
      font-weight: bold;
      margin-bottom: 3px;
    }
    .emergency-row {
      margin-bottom: 1px;
      line-height: 1.3;
    }
    .emergency-label {
      font-weight: normal;
      font-size: 5pt;
      margin-right: 3px;
    }
    .emergency-value {
      font-size: 7pt;
      font-weight: bold;
    }
    .signature-section {
      margin-top: 8px;
      position: relative;
    }
    .signature-box {
      display: inline-block;
      width: 45%;
      text-align: center;
      vertical-align: bottom;
    }
    .signature-line {
      margin-top: 15px;
      width: 70%;
      border-top: 1px solid #000;
      margin-bottom: 2px;
      padding-top: 2px;
      font-size: 5pt;
      font-style: italic;
      margin-left: 20px;
    }
    .punong-box {
      margin-top: 15px;
      display: inline-block;
      width: 50%;
      text-align: center;
      vertical-align: bottom;
      float: right;
    }
    .punong-barangay {
      font-size: 6pt;
      font-weight: bold;
      margin-bottom: 0;
    }
    .pb-title {
      font-size: 5pt;
      font-style: normal;
    }
    .logo-watermark {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      opacity: 0.12;
      width: 140px;
      height: 140px;
      z-index: 0;
    }
    .back-content {
      position: relative;
      z-index: 1;
    }
  </style>
</head>
<body>
  <div class="page-container">
    <!-- FRONT OF ID -->
    <div class="id-container">
      <div class="header-section">
        <div class="header-section-left">
          <img src="<?= $srcBrgy ?>" alt="Logo">
          <div class="header-text">
            <div>REPUBLIC OF THE PHILIPPINES</div>
            <div>PROVINCE OF CAMARINES NORTE</div>
            <div>MUNICIPALITY OF DAET</div>
            <div>BARANGAY MAGANG</div>
          </div>
        </div>
        <div class="photo-box">
          <?php if ($formalPic): ?>
            <img src="<?= htmlspecialchars($formalPic) ?>" style="width:100%; height:100%; object-fit:cover;" alt="Photo">
          <?php endif; ?>
        </div>
      </div>
      
      <div class="title-bar">
        BARANGAY IDENTIFICATION CARD
      </div>
      
      <div class="id-number-box">
        <div class="id-number"><?= htmlspecialchars(substr($transactionId, -5)) ?></div>
        <div class="id-label">ID No.</div>
      </div>
      
      <div class="validity-box">
        <div class="validity-date"><?= date('m-d-y', strtotime('+3 years', strtotime($createdAt))) ?></div>
        <div class="validity-label">Valid Until</div>
      </div>
      
      <div class="content-section">
        <div class="certify-text">This is to certify that</div>
        
        <div class="name-text"><?= $formattedName ?></div>
        
        <div class="address-text">
          of Purok <span class="underline"><strong><?= htmlspecialchars(str_replace('Purok ', '', $purok)) ?></strong></span>, 
          Barangay Magang, Daet, Camarines Norte, whose picture, signature and thumb mark 
          appears hereon is a registered member of this Barangay. 
          This identification card is being issued for whatever lawful purpose it may serve.
        </div>
      </div>
    </div>

    <!-- BACK OF ID -->
    <div class="id-back">
      <img src="<?= $srcGov ?>" alt="Watermark" class="logo-watermark">
      
      <div class="back-content">
        <table style="width: 100%; margin-bottom: 2px; border-collapse: collapse;">
          <tr>
            <!-- Column 1: Thumb Box -->
            <td style="width: 75px; vertical-align: center; padding-right: 5px;">
              <div class="thumb-box">
                <div>Right Thumb<br>Mark</div>
              </div>
            </td>
            
            <!-- Column 2: Date of Birth, Status, Height & Weight -->
            <td style="width: 85px; vertical-align: top; padding-right: 5px;">
              <div class="back-field">
                <div class="back-field-value"><?= htmlspecialchars(date('m-d-Y', strtotime($birthDate))) ?></div>
                <div class="back-field-line"></div>
                <div class="back-field-label">Date of Birth</div>
              </div>
              
              <div class="back-field">
                <div class="back-field-value"><?= htmlspecialchars(strtoupper($civilStatus)) ?></div>
                <div class="back-field-line"></div>
                <div class="back-field-label">Status</div>
              </div>
              
              <table style="width: 100%; border-collapse: collapse;">
                <tr>
                  <td style="width: 50%; text-align: center;">
                    <div class="back-field">
                      <div class="back-field-value"><?= htmlspecialchars($height) ?></div>
                      <div class="back-field-line" style="max-width: 30px;"></div>
                      <div class="back-field-label">Height</div>
                    </div>
                  </td>
                  <td style="width: 50%; text-align: center;">
                    <div class="back-field">
                      <div class="back-field-value"><?= htmlspecialchars($weight) ?></div>
                      <div class="back-field-line" style="max-width: 30px;"></div>
                      <div class="back-field-label">Weight</div>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
            
            <!-- Column 3: Place of Birth, Religion, SSS/GSIS -->
            <td style="width: 85px; vertical-align: top;">
              <div class="back-field">
                <div class="back-field-value"><?= htmlspecialchars(strtoupper($birthPlace)) ?></div>
                <div class="back-field-line"></div>
                <div class="back-field-label">Place of Birth</div>
              </div>
              
              <div class="back-field">
                <div class="back-field-value"><?= htmlspecialchars(strtoupper($religion)) ?></div>
                <div class="back-field-line"></div>
                <div class="back-field-label">Religion</div>
              </div>
              
              <div class="back-field">
                <div class="back-field-value">&nbsp;</div>
                <div class="back-field-line"></div>
                <div class="back-field-label">SSS/GSIS/Postal ID No.</div>
              </div>
            </td>
          </tr>
        </table>
        
        <!-- DIV #2: Emergency Contact -->
        <div class="emergency-section">
          <div class="emergency-title">In case of emergency please notify:</div>
          <div class="emergency-row">
            <span class="emergency-label">Name:</span>
            <span class="emergency-value"><u><?= htmlspecialchars(strtoupper($emergencyName)) ?></u></span>
          </div>
          <div class="emergency-row">
            <span class="emergency-label">Address:</span>
            <span class="emergency-value"><u><?= htmlspecialchars(strtoupper($emergencyAddress)) ?></u></span>
          </div>
        </div>
        
        <!-- DIV #3: Signatures -->
        <div class="signature-section">
          <div class="signature-box">
            <div class="signature-line"></div>
            <div style="font-size: 5pt; font-style: italic;">Signature of Holder</div>
          </div>
          <div class="punong-box">
            <div class="punong-barangay">EDUARDO C. ASIAO</div>
            <div class="pb-title">Punong Barangay</div>
          </div>
        </div>
      </div>
    </div>
  </div>
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