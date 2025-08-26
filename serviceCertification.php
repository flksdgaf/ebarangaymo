<?php
// serviceCertification.php
require 'functions/dbconn.php';

// Ensure the user is authenticated.
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['loggedInUserID'];
$transactionId = $_GET['tid'] ?? null;
$t = $transactionId ? true : false;

// --- fetch user from whichever purok table they’re in ---
$sql = "
  SELECT full_name, birthdate, civil_status, 'Purok 1' AS purok
    FROM purok1_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, birthdate, civil_status, 'Purok 2' AS purok
    FROM purok2_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, birthdate, civil_status, 'Purok 3' AS purok
    FROM purok3_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, birthdate, civil_status, 'Purok 4' AS purok
    FROM purok4_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, birthdate, civil_status, 'Purok 5' AS purok
    FROM purok5_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, birthdate, civil_status, 'Purok 6' AS purok
    FROM purok6_rbi WHERE account_ID = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiiii", $userId,$userId,$userId,$userId,$userId,$userId);
$stmt->execute();
$userRec = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

// A simple map of types -> tables (used later to lookup chosenPayment if tid exists)
$map = [
  'Residency'    => ['table'=>'residency_requests',    'prefix'=>'RES-'],
  'Indigency'    => ['table'=>'indigency_requests',    'prefix'=>'IND-'],
  'Good Moral'   => ['table'=>'good_moral_requests',   'prefix'=>'GM-' ],
  'Solo Parent'  => ['table'=>'solo_parent_requests',  'prefix'=>'SP-' ],
  'Guardianship' => ['table'=>'guardianship_requests', 'prefix'=>'GUA-'],
];

$chosenPayment = '';
$serverCertType = null; // if tid is present we'll attempt to discover the cert type for UI decisions
if ($transactionId) {
    foreach ($map as $typeName => $m) {
        $tbl = $m['table'];
        // Try to fetch payment_method and optionally certification_type (if your tables store it)
        $q = $conn->prepare("SELECT payment_method, certification_type FROM `$tbl` WHERE transaction_id = ? LIMIT 1");
        if (!$q) continue;
        $q->bind_param('s', $transactionId);
        $q->execute();
        $r = $q->get_result();
        if ($r && $r->num_rows) {
            $row = $r->fetch_assoc();
            $chosenPayment = $row['payment_method'] ?? $chosenPayment;
            // if your table has certification_type column (optional), capture it
            if (!empty($row['certification_type'])) $serverCertType = $row['certification_type'];
            $q->close();
            break;
        }
        $q->close();
    }
}

?>

<title> eBarangay Mo | Certification Services</title>
<link rel="stylesheet" href="serviceCertification.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="container py-4 px-3">
    <div class="progress-container">
        <div class="stepss">

            <!-- STEP MARKERS
                 Each .steps wrapper has data-step (original step number) and an inner .circle with a numeric
                 span (.circle-num). The JS will re-number visible markers when step 2 (Payment) is hidden (Indigency).
            -->

            <!-- STEP 1 -->
            <div id="stepMarker1" class="steps" data-step="1">
                <div class="circle" data-step="1"><span class="circle-num">1</span></div>
                <div class="step-label">
                APPLICATION FORM
                </div>
            </div>

            <!-- STEP 2 -->
            <div id="stepMarker2" class="steps" data-step="2">
                <div class="circle" data-step="2"><span class="circle-num">2</span></div>
                <div class="step-label">
                PAYMENT
                </div>
            </div>

            <!-- STEP 3 -->
            <div id="stepMarker3" class="steps" data-step="3">
                <div class="circle" data-step="3"><span class="circle-num">3</span></div>
                <div class="step-label">
                REVIEW &amp; CONFIRMATION
                </div>
            </div>

            <!-- STEP 4 -->
            <div id="stepMarker4" class="steps" data-step="4">
                <div class="circle" data-step="4"><span class="circle-num">4</span></div>
                <div class="step-label">
                SUBMISSION
                </div>
            </div>

            <div class="progress-line"></div>
            <div class="progress-fill" id="progressFill" style="<?php echo $t ? 'width: 100%;' : ''; ?>"></div>
        </div>
    </div>

    <div class="card shadow-sm px-5 py-5 mb-5 mt-4">
        <h2 class="mb-1 text-success fw-bold" id="mainHeader"></h2>
        <p id="subHeader" class="mb-2">Select a type of certification and provide the necessary details to apply.</p>
        <hr id="mainHr" class="mb-4">

        <form id="certForm" action="functions/serviceCertification_submit.php" method="POST" enctype="multipart/form-data">
            <!-- Step 1: Application Form -->
            <div id="stepContent1" data-step="1" class="step <?php echo $transactionId ? 'completed' : 'active-step'; ?>">
                <!-- TYPE OF CERTIFICATION -->
                <div class="row mb-3 mt-3">
                    <div class="col-md-6">
                        <div class="row">
                            <label for="certType" class="col-sm-5 text-start fw-bold">Type of Certification</label>
                            <div class="col-sm-7 position-relative"> <!-- position-relative to contain the dropdown -->
                                <input type="text" id="certType" name="certification_type" class="form-control" placeholder="Click to select or type" autocomplete="off" required>
                                <ul id="certTypeList" class="list-group position-absolute w-100 shadow-sm bg-white" style="max-height: 150px; overflow-y: auto; display: none; z-index: 1000;">
                                <!-- JS will populate these <li> items -->
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <label for="forSelect" class="col-sm-3 text-start fw-bold">Request For</label>
                            <div class="col-sm-9">
                                <select id="forSelect" name="request_for" class="form-select">
                                <option value="myself" selected>Myself</option>
                                <option value="other">Other Individual</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- break line for your future instructions -->
                <div class="row mb-3">
                    <div class="col-12"><hr></div>
                </div>

                <div id="certFields"></div>

                <!-- … then the rest of your Step 1 fields (Full Name, etc.) … -->
            </div>
            
            <!-- Step 2: Payment -->
            <div id="stepContent2" data-step="2" class="step <?php echo $transactionId ? 'completed' : ''; ?>">
            <div class="payment-container p-4 border rounded shadow-sm bg-green">
                <div class="row g-4">

                    <!-- LEFT COLUMN: Fee -->
                    <div class="col-md-4">
                        <div class="fee-box p-4 rounded shadow-sm border bg-light text-center">
                            <h5 class="fw-bold text-success mb-2">Barangay ID Fee</h5>
                            <div class="display-6 fw-bold text-dark mb-2">₱130.00</div>
                            <p class="text-muted small mb-0">
                                Settle the fee using your preferred<br>payment method on the right.
                            </p>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Payment Methods + Instructions -->
                    <div class="col-md-8 text-center">
                        <h6 class="fw-bold mb-3">Select Preferred Payment Method</h6>

                        <div class="btn-group btn-group-lg mb-4 flex-wrap justify-content-center" role="group" aria-label="Payment Methods">
                        <!-- GCash -->
                        <button type="button" class="btn btn-outline-success disabled payment-btn" data-method="GCash">
                            <img src="images/gcash_logo.png" alt="GCash" class="mb-2 payment-icon">
                            <span class="label fw-bold">GCash</span>
                        </button>

                        <!-- Barangay Device -->
                        <button type="button" class="btn btn-outline-success payment-btn active" data-method="Brgy Payment Device">
                            <span class="material-symbols-outlined mb-2 payment-icon">payments</span>
                            <span class="label fw-bold">Brgy. Payment Device</span>
                        </button>

                        <!-- Over-the-Counter -->
                        <button type="button" class="btn btn-outline-success payment-btn" data-method="Over-the-Counter">
                            <span class="material-symbols-outlined mb-2 payment-icon">paid</span>
                            <span class="label fw-bold">Over-the-Counter</span>
                        </button>
                        </div>

                        <!-- Instructions -->
                        <div id="payment-instructions">
                        <div class="payment-instruction d-none" data-method="GCash">
                            <ol>
                            <h4 class="fw-bold mb-3 fs-6">HOW TO USE:</h4>
                            <li>Once redirected to GCash, send exactly <strong>₱130.00</strong>.</li>
                            <li>Confirm your payment.</li>
                            <li>Download or screenshot the confirmation receipt.</li>
                            <li>After being redirected back to the website, upload the receipt.</li>
                            <li>Claim your ID at the barangay on your selected claim date.</li>
                            </ol>
                        </div>

                        <div class="payment-instruction" data-method="Brgy Payment Device">
                            <ol>
                            <h4 class="fw-bold mb-3 fs-6">HOW TO USE:</h4>
                            <li>Submit your application and download the generated <strong>QR code</strong>.</li>
                            <li>Go to the <strong>Barangay Payment Device</strong> located at the barangay hall.</li>
                            <li>Scan the code and insert <strong>₱130.00</strong>.</li>
                            <li>Wait for the confirmation and printed receipt.</li>
                            <li>Submit the receipt and claim your Barangay ID.</li>
                            </ol>
                        </div>

                        <div class="payment-instruction d-none" data-method="Over-the-Counter">
                            <ol>
                            <h4 class="fw-bold mb-3 fs-6">HOW TO USE:</h4>
                                <li>Submit your application and save the given <strong>Transaction Number</strong>.</li>
                                <li>Go to the Barangay Treasurer and present your Transaction Number.</li>
                                <li>Pay <strong>₱130.00</strong> in cash.</li>
                                <li>Receive the official receipt.</li>
                                <li>Claim your ID at the Barangay Record Keeper.</li>
                            </ol>
                        </div>
                        </div>

                        <!-- Hidden input to capture method -->
                        <input type="hidden" id="paymentMethod" name="paymentMethod" value="Brgy Payment Device">
                    </div>
                </div>
            </div>
            </div>

            <!-- Step 3: Summary / Review -->
            <div id="stepContent3" data-step="3" class="step <?php echo $transactionId ? 'completed' : ''; ?>">
                <div class="summary-container p-3" id="summaryContainer">
                    <!-- JS will inject:
                        Type of Certification
                        then each of the fields & their values -->
                </div>
            </div>

            <!-- Step 4: Submission -->
            <div id="stepContent4" data-step="4" class="step <?php echo $transactionId ? 'active-step' : ''; ?>">
            <?php if ($transactionId): ?>
                <div class="submission-screen text-center">

                    <!-- Title -->
                    <h2 class="submission-title">REQUEST SUBMITTED</h2>

                    <!-- Explanation -->
                    <p class="submission-text">
                        Your request has been successfully submitted and is now pending assessment by the barangay office.<br>
                        Please keep your transaction number for reference:
                    </p>

                    <!-- Transaction ID boxes -->
                    <div class="txn-display">
                        <?php
                        // Split e.g. "BRGYID-0000003" into chars
                        $chars = str_split($transactionId);
                        foreach ($chars as $char): ?>
                            <span class="txn-char"><?php echo htmlspecialchars($char); ?></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- QR CODE HERE OR THE HOUR GLASS GIF-->
                    <?php if ($chosenPayment === 'Brgy Payment Device'): ?>
                        <!-- QR CODE for the Barangay Payment Device -->
                        <div class="qr-container">
                            <div id="qrcode" style="margin:auto;"></div>
                            <!-- Download button -->
                            <button type="button" id="downloadQRBtn" class="btn download-btn btn-success mt-3">Download QR Code</button>
                            <p>Download or screenshot this QR code in order to use the Barangay Payment Device</p>
                        </div>
                        <script>
                            document.addEventListener("DOMContentLoaded", function(){
                                const container = document.getElementById("qrcode");

                                // 1) Generate the QR
                                new QRCode(container, {
                                    text: "<?php echo htmlspecialchars($transactionId); ?>",
                                    width: 300,
                                    height: 300,
                                });

                                // 2) Hook up Download button
                                document.getElementById("downloadQRBtn").addEventListener("click", function(){
                                    // QRCode.js puts an <img> inside #qrcode
                                    const img = container.querySelector("img");
                                    if (!img) return;

                                    // Create a temporary link to trigger download
                                    const link = document.createElement("a");
                                    link.href = img.src;  
                                    link.download = "<?php echo htmlspecialchars($transactionId); ?>.png";
                                    document.body.appendChild(link);
                                    link.click();
                                    document.body.removeChild(link);
                                });
                            });
                        </script>
                    <?php else: ?>
                        <!-- HOURGLASS LOTTIE for GCash or OTC -->
                        <div class="hourglass-container">
                        <canvas id="canvas" width="300" height="300"></canvas>
                        <script type="module">
                            import { DotLottie } from "https://cdn.jsdelivr.net/npm/@lottiefiles/dotlottie-web/+esm";

                            new DotLottie({
                            autoplay: true,
                            loop: true,
                            canvas: document.getElementById("canvas"),
                            src: "https://lottie.host/d0aee06e-c4f8-41ce-900f-8fc92274c294/3lsI0L5C6d.lottie",
                            });
                        </script>
                        <p>Please wait… your request is being verified.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Footer note -->
                    <p class="submission-footer">
                        To check if your permit is ready for release,<br>
                        please visit the <strong>My Requests</strong> page and enter your transaction reference number.
                    </p>

                </div>
            <?php endif; ?>
            </div>

            <!-- Navigation Buttons (these remain centered as needed) -->
            <div class="d-flex justify-content-between w-100 mt-4">
                <button type="button" class="btn back-btn" id="backBtn">< PREVIOUS</button>
                <button type="button" class="btn next-btn" id="nextBtn">NEXT ></button>
            </div>
        </form>
    </div>

    <!-- Custom Modal for Validation -->
    <div class="modal fade" id="validationModal" tabindex="-1" aria-labelledby="validationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="validationModalLabel">Error</h5>
                </div>
                <div class="modal-body">
                    Please fill in all required fields before proceeding.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirm Submission</h5>
                </div>
                <div class="modal-body">
                    Is all of your information correct?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelConfirmBtn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmSubmitBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $initial = $transactionId ? 4 : 1; ?>
<script>
    // make the PHP value available to our external JS
    window.initialStep = <?php echo (int)$initial; ?>;
    window.currentUser = <?= json_encode($userRec, JSON_HEX_TAG) ?>;
    // If server-side we discovered the cert type for this tid (optional), expose it
    window.serverCertType = <?= json_encode($serverCertType) ?>;
    // Expose chosenPayment (used for submission screen to show QR vs hourglass)
    window.serverChosenPayment = <?= json_encode($chosenPayment) ?>;
</script>

<script src="js/serviceCertification.js"></script>
