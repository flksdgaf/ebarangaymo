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

$map = [
  'Residency'    => ['table'=>'residency_requests',    'prefix'=>'RES-'],
  'Indigency'    => ['table'=>'indigency_requests',    'prefix'=>'IND-'],
  'Good Moral'   => ['table'=>'good_moral_requests',   'prefix'=>'GM-' ],
  'Solo Parent'  => ['table'=>'solo_parent_requests',  'prefix'=>'SP-' ],
  'Guardianship' => ['table'=>'guardianship_requests', 'prefix'=>'GUA-'],
];

$chosenPayment = '';
$existingCertType = null;
$chosenAmount = null;
$chosenPaymentStatus = null;

if ($transactionId) {
    // first, find the cert type for this tid and fetch payment columns too
    foreach ($map as $typeName => $m) {
        $tbl = $m['table'];
        $q = $conn->prepare("SELECT payment_method, amount, payment_status FROM `$tbl` WHERE transaction_id = ? LIMIT 1");
        $q->bind_param('s', $transactionId);
        $q->execute();
        $r = $q->get_result();
        if ($r->num_rows) {
            $row = $r->fetch_assoc();
            $chosenPayment = $row['payment_method'];
            $chosenAmount = $row['amount'];
            $chosenPaymentStatus = $row['payment_status'];
            $existingCertType = $typeName; // remember which certificate this tid belongs to
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

            <!-- STEP 1 -->
            <div class="steps" data-step="1">
                <div class="circle <?php echo $t ? 'completed' : 'active'; ?>">1</div>
                <div class="step-label <?php echo $t ? 'completed' : 'active'; ?>">
                APPLICATION FORM
                </div>
            </div>

            <!-- STEP 2 (Payment - will be removed dynamically for Indigency) -->
            <div class="steps payment-progress-step" data-step="2">
                <div class="circle <?php echo $t ? 'completed' : ''; ?>">2</div>
                <div class="step-label <?php echo $t ? 'completed' : ''; ?>">
                PAYMENT
                </div>
            </div>

            <!-- STEP 3 -->
            <div class="steps" data-step="3">
                <div class="circle <?php echo $t ? 'completed' : ''; ?>">3</div>
                <div class="step-label <?php echo $t ? 'completed' : ''; ?>">
                REVIEW &amp; CONFIRMATION
                </div>
            </div>

            <!-- STEP 4 -->
            <div class="steps" data-step="4">
                <div class="circle <?php echo $t ? 'active' : ''; ?>">4</div>
                <div class="step-label <?php echo $t ? 'active' : ''; ?>">
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
            <div class="step <?php echo $transactionId ? 'completed' : 'active-step'; ?>" data-step="1">
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
            <div class="step payment-step <?php echo $transactionId ? 'completed' : ''; ?>" data-step="2" id="paymentStep">
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
                        <input type="hidden" id="paymentMethod" name="paymentMethod" value="<?php echo htmlspecialchars($chosenPayment ?? 'Brgy Payment Device'); ?>">
                    </div>
                </div>
            </div>
            </div>

            <!-- Step 3: Summary / Review -->
            <div class="step <?php echo $transactionId ? 'completed' : ''; ?>" data-step="3" id="summaryStep">
                <div class="summary-container p-3" id="summaryContainer">
                    <!-- JS will inject:
                        Type of Certification
                        then each of the fields & their values -->
                </div>
            </div>

            <!-- Step 4: Submission -->
            <div class="step <?php echo $transactionId ? 'active-step' : ''; ?>" data-step="4" id="submissionStep">
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

            <!-- Add explicit hidden fields for payment amount/status so submission can receive them even if payment step removed -->
            <input type="hidden" id="paymentAmount" name="paymentAmount" value="<?php echo htmlspecialchars($chosenAmount ?? ''); ?>">
            <input type="hidden" id="paymentStatus" name="paymentStatus" value="<?php echo htmlspecialchars($chosenPaymentStatus ?? ''); ?>">

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
    // make the PHP values available to our external JS
    window.initialStep = <?php echo $initial; ?>;
    window.currentUser = <?= json_encode($userRec, JSON_HEX_TAG) ?>;
    window.existingCertType = <?php echo json_encode($existingCertType); ?>; // e.g. "Indigency" if tid belongs to indigency_requests
    window.existingPaymentMethod = <?php echo json_encode($chosenPayment); ?>;
    window.existingPaymentAmount = <?php echo json_encode($chosenAmount); ?>;
    window.existingPaymentStatus = <?php echo json_encode($chosenPaymentStatus); ?>;
</script>

<script>
/*
  Inline behavior adjustments specific to "Indigency" certificate:
  - Remove/hide the Payment step from the UI when Type is "Indigency"
  - Renumber the progress circles and labels so we get 3 steps:
      1. APPLICATION FORM
      2. REVIEW & CONFIRMATION
      3. SUBMISSION
  - Ensure hidden payment inputs are cleared so backend can treat them as empty/NULL
  - Do NOT remove payment_status from the summary for Indigency — we want to show it.
*/
(function(){
    const certInput = document.getElementById('certType');
    const paymentStepEl = document.getElementById('paymentStep'); // outer .step for payment
    const paymentProgressStep = document.querySelector('.payment-progress-step');
    const summaryContainer = document.getElementById('summaryContainer');
    const paymentMethodInput = document.getElementById('paymentMethod');
    const paymentAmountInput = document.getElementById('paymentAmount');
    const paymentStatusInput = document.getElementById('paymentStatus');

    function isIndigencyValue(val){
        if (!val) return false;
        return val.trim().toLowerCase() === 'indigency';
    }

    function applyIndigencyMode(){
        // 1) Remove the payment step from the flow (both the step content and progress)
        if (paymentStepEl && paymentStepEl.parentNode) {
            paymentStepEl.parentNode.removeChild(paymentStepEl);
        }
        if (paymentProgressStep && paymentProgressStep.parentNode) {
            paymentProgressStep.parentNode.removeChild(paymentProgressStep);
        }

        // 2) Renumber remaining progress circles and update labels
        const progressSteps = document.querySelectorAll('.stepss .steps');
        const newLabels = ['APPLICATION FORM', 'REVIEW & CONFIRMATION', 'SUBMISSION'];
        progressSteps.forEach((s, idx) => {
            const circle = s.querySelector('.circle');
            const label = s.querySelector('.step-label');
            if (circle) circle.textContent = idx + 1;
            if (label) label.textContent = newLabels[idx] || label.textContent;
            s.setAttribute('data-step', idx+1);
        });

        // 3) clear hidden payment fields so submit receives empty values
        if (paymentMethodInput) paymentMethodInput.value = '';
        if (paymentAmountInput) paymentAmountInput.value = '';
        // keep paymentStatusInput untouched here because we want to display/show payment_status for Indigency
        // if you want it cleared for brand-new indigency creation, you may set it to '' here. We'll keep it as-is.

        // 4) ensure any payment UI is not accidentally visible: hide elements that may reference fee/instructions
        const feeBoxes = document.querySelectorAll('.payment-container, .fee-box, #payment-instructions, .payment-instruction, .payment-btn');
        feeBoxes.forEach(el => {
            if (el && el.style) el.style.display = 'none';
        });

        // 5) remove payment_method and amount from the summary, but KEEP payment_status for indigency
        // we will remove nodes containing "Payment Method" and "Amount" but keep "Payment Status"
        if (!summaryContainer) return;
        const nodes = summaryContainer.querySelectorAll('*');
        nodes.forEach(node => {
            if (node.children.length === 0) {
                const txt = (node.textContent || '').trim().toLowerCase();
                if (txt.includes('payment method') || txt === 'amount:' || txt.includes('amount')) {
                    node.remove();
                }
            }
        });

        // 6) observe summary for future injection and remove payment method/amount nodes if they appear
        observeSummaryForPaymentRemoval();
    }

    function observeSummaryForPaymentRemoval(){
        if (!summaryContainer) return;
        const mo = new MutationObserver(mutations => {
            // remove payment method/amount nodes if they reappear
            const nodes = summaryContainer.querySelectorAll('*');
            nodes.forEach(node => {
                if (node.children.length === 0) {
                    const txt = (node.textContent || '').trim().toLowerCase();
                    if (txt.includes('payment method') || txt === 'amount:' || txt.includes('amount')) {
                        node.remove();
                    }
                }
            });
        });
        mo.observe(summaryContainer, { childList: true, subtree: true, characterData: true });
    }

    // react when user changes the certificate type
    function onCertTypeChanged(){
        const val = certInput.value || '';
        if (isIndigencyValue(val)) {
            applyIndigencyMode();
        } else {
            // if user switches back to a paid cert, reload page or restore original UI.
            // We intentionally keep this conservative: simplest behaviour is to reload so the full payment step appears correctly.
            // But only reload if payment step was removed (to avoid infinite reload loops)
            if (!document.getElementById('paymentStep')) {
                location.reload();
            }
        }
    }

    // initial application: if existingCertType was passed from PHP as "Indigency" or the current input has Indigency
    document.addEventListener('DOMContentLoaded', function(){
        // pre-populate certType if viewing an existing transaction
        if (window.existingCertType) {
            document.getElementById('certType').value = window.existingCertType;
        }

        // If this page was loaded for an existing transaction that belongs to Indigency, apply indigency mode.
        if (window.existingCertType && window.existingCertType.toLowerCase() === 'indigency') {
            applyIndigencyMode();
        } else {
            // If the user typed/selected Indigency before JS runs
            if (isIndigencyValue(certInput.value)) {
                applyIndigencyMode();
            }
        }

        // Monitor user changes (typing/selecting) on the certificate type field
        certInput.addEventListener('change', onCertTypeChanged);
        certInput.addEventListener('blur', onCertTypeChanged);
        certInput.addEventListener('input', function(){ /* optional - don't aggressively act on typing */ });
    });
})();
</script>

<script src="js/serviceCertification.js"></script>

<!--
  Override populateSummary after the main JS file loads so the Review summary shows:
   - Indigency -> Payment Status only
   - Others   -> Amount + Payment Status
-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // safe-guard: only override if serviceCertification.js defined a populateSummary; if not, we'll define it.
    function newPopulateSummary() {
        const certInput = document.getElementById('certType');
        const forSelect = document.getElementById('forSelect');
        const container = document.getElementById('summaryContainer');

        const type = (certInput?.value || '').trim().toLowerCase();
        const rows = [
            ['Type of Certification:', certInput?.value || '—'],
            ['Requesting For:', forSelect?.value === 'myself' ? 'Myself' : 'Others'],
            ['Full Name:', (document.querySelector('[name="full_name"]')?.value) || '—'],
            ['Age:', (document.querySelector('[name="age"]')?.value) || '—'],
            ['Civil Status:', (document.querySelector('[name="civil_status"]')?.value) || '—'],
            ['Purok:', (document.querySelector('[name="purok"]')?.value) || '—']
        ];

        // Solo Parent: Child details + years
        if (type === 'solo parent') {
            const childNames = Array.from(document.querySelectorAll('[name="child_name[]"]')).map(el => el.value.trim()).filter(Boolean);
            const childAges  = Array.from(document.querySelectorAll('[name="child_age[]"]')).map(el => el.value.trim()).filter(Boolean);
            const childSexes = Array.from(document.querySelectorAll('[name="child_sex[]"]')).map(el => el.value.trim()).filter(Boolean);

            childNames.forEach((name, i) => {
                rows.push([`Child ${i + 1} Name:`, name || '—']);
                rows.push([`Child ${i + 1} Age:`, childAges[i] || '—']);
                rows.push([`Child ${i + 1} Sex:`, childSexes[i] || '—']);
            });

            const years = document.querySelector('[name="years_solo_parent"]')?.value || '—';
            rows.push(['Years as Solo Parent:', years]);
        }

        // Guardianship: Child names only
        if (type === 'guardianship') {
            const childNames = Array.from(document.querySelectorAll('[name="child_name[]"]')).map(el => el.value.trim()).filter(Boolean);
            childNames.forEach((name, i) => {
                rows.push([`Child ${i + 1} Name:`, name || '—']);
            });
        }

        // Residency specific field
        if (type === 'residency') {
            rows.push([
                'Years Residing:',
                document.querySelector('[name="residing_years"]')?.value || '—'
            ]);
        }

        // Common fields for all types
        rows.push(
            ['Claim Date:', document.querySelector('[name="claim_date"]')?.value || '—'],
            ['Purpose:', document.querySelector('[name="purpose"]')?.value || '—']
        );

        // Payment details — different rules for Indigency vs others
        const paymentAmountEl = document.getElementById('paymentAmount');
        const paymentStatusEl = document.getElementById('paymentStatus');

        const clientAmount = paymentAmountEl?.value?.trim();
        const clientStatus = paymentStatusEl?.value?.trim();

        // prefer client-side hidden inputs, fallback to server values exposed on window
        const amountVal = clientAmount || window.existingPaymentAmount || '';
        const statusVal = clientStatus || window.existingPaymentStatus || '';

        if (type === 'indigency') {
            // show payment_status for indigency (if empty show '—' so user sees something)
            rows.push(['Payment Status:', statusVal || '—']);
        } else {
            // for other certificates, include amount and payment_status.
            // Format amount nicely if numeric
            let amtDisplay = '—';
            if (amountVal !== null && String(amountVal).trim() !== '') {
                // if numeric, prefix peso sign
                if (!isNaN(Number(String(amountVal).replace(/[^0-9.-]+/g, '')))) {
                    amtDisplay = '₱' + Number(String(amountVal).replace(/[^0-9.-]+/g, '')).toFixed(2);
                } else {
                    amtDisplay = String(amountVal);
                }
            } else {
                // fallback default amount (use 130 as your standard fee)
                amtDisplay = '₱130.00';
            }
            rows.push(['Amount:', amtDisplay]);

            // payment status - prefer server-existing, otherwise show 'Pending' as friendly default
            rows.push(['Payment Status:', statusVal || 'Pending']);
        }

        // Build HTML
        let html = `
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-8 col-xl-6">
                    <div class="summary-container p-4 rounded shadow-sm border">
                        <ul class="list-group list-group-flush">
        `;

        rows.forEach(([label, value]) => {
            html += `
                <li class="list-group-item d-flex justify-content-between">
                    <span class="fw-bold">${label}</span>
                    <span class="text-success">${value}</span>
                </li>
            `;
        });

        html += `
                        </ul>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    // If serviceCertification.js already defined populateSummary, override it
    window.populateSummary = newPopulateSummary;

    // Also, if user views an existing transaction, populate now so the summary shows immediately
    if (window.initialStep && Number(window.initialStep) >= 3) {
        // slight timeout to ensure DOM elements are present
        setTimeout(() => {
            if (typeof window.populateSummary === 'function') window.populateSummary();
        }, 120);
    }
});
</script>
