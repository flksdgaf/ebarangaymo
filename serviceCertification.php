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
// NOTE: added selection of `sex` so client-side can prefill sex-based fields (e.g. Parent Sex)
$sql = "
  SELECT full_name, birthdate, civil_status, sex, 'Purok 1' AS purok
    FROM purok1_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, birthdate, civil_status, sex, 'Purok 2' AS purok
    FROM purok2_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, birthdate, civil_status, sex, 'Purok 3' AS purok
    FROM purok3_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, birthdate, civil_status, sex, 'Purok 4' AS purok
    FROM purok4_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, birthdate, civil_status, sex, 'Purok 5' AS purok
    FROM purok5_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, birthdate, civil_status, sex, 'Purok 6' AS purok
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
  'First Time Job Seeker' => ['table'=>'job_seeker_requests', 'prefix'=>'FTJS-'],
];

$chosenPayment = '';
$existingCertType = null;
$chosenAmount = null;
$chosenPaymentStatus = null;
$existingRequestRow = []; // will hold the full row if tid is found
$existingParentSex = '';  // NEW: hold parent_sex from existing request if present
$existingParentAddress = ''; // NEW: hold parent_address if present

if ($transactionId) {
    // first, find the cert type for this tid and fetch the full row (so we can read claim_date/claim_time)
    foreach ($map as $typeName => $m) {
        $tbl = $m['table'];
        // Select entire row (we need claim_date and claim_time)
        $q = $conn->prepare("SELECT * FROM `$tbl` WHERE transaction_id = ? LIMIT 1");
        $q->bind_param('s', $transactionId);
        $q->execute();
        $r = $q->get_result();
        if ($r && $r->num_rows) {
            $row = $r->fetch_assoc();
            $chosenPayment = $row['payment_method'] ?? '';
            $chosenAmount = $row['amount'] ?? '';
            $chosenPaymentStatus = $row['payment_status'] ?? '';
            $existingCertType = $typeName; // remember which certificate this tid belongs to
            $existingRequestRow = $row;
            // NEW: capture parent_sex & parent_address if available
            $existingParentSex = $row['parent_sex'] ?? '';
            $existingParentAddress = $row['parent_address'] ?? '';
            $q->close();
            break;
        }
        $q->close();
    }
}

// === NEW: compute the default fee label to display in the fee box ===
if ($existingCertType) {
    // Special case for First Time Job Seeker - use exact name instead of "Certificate of"
    if ($existingCertType === 'First Time Job Seeker') {
        $feeLabelDefault = 'First Time Job Seeker Fee';
    } else {
        $feeLabelDefault = 'Certificate of ' . $existingCertType . ' Fee';
    }
} else {
    $feeLabelDefault = 'Barangay ID Fee';
}

function getNextBusinessDays($fromDate, $count = 3) {
    $results = [];
    $d = clone $fromDate;

    // If request is on Saturday (6) or Sunday (7), start at next Monday
    $weekdayNow = (int)$d->format('N'); // 1=Mon .. 7=Sun
    if ($weekdayNow === 6) { // Saturday
        // move to Monday (+2)
        $d->modify('+2 days');
    } elseif ($weekdayNow === 7) { // Sunday
        // move to Monday (+1)
        $d->modify('+1 day');
    } else {
        // For Mon-Fri, options start from TOMORROW
        $d->modify('+1 day');
    }

    while (count($results) < $count) {
        $weekday = (int)$d->format('N'); // 1..7
        if ($weekday <= 5) { // Monday - Friday
            $results[] = clone $d;
        }
        $d->modify('+1 day');
    }
    return $results;
}

// Build claim options server-side (3 business days)
$today = new DateTime('now', new DateTimeZone('Asia/Manila'));
$businessDays = getNextBusinessDays($today, 3);

$claimOptions = [];
foreach ($businessDays as $bd) {
    $dateStr = $bd->format('Y-m-d'); // machine
    $label = $bd->format('F j, Y'); // human readable
    $claimOptions[] = [
        'date' => $dateStr,
        'label' => $label,
        'parts' => [
            ['key' => 'Morning', 'label' => 'Morning (8:00 AM to 12:00 NN)'],
            ['key' => 'Afternoon', 'label' => 'Afternoon (1:00 PM to 5:00 PM)'],
        ],
    ];
}

// existing claim pref - support both legacy "YYYY-MM-DD|Part" and separate columns claim_date & claim_time
$existingClaimDate = '';
$existingClaimPart = '';

if (!empty($existingRequestRow)) {
    // Prefer separate columns if available
    if (!empty($existingRequestRow['claim_date'])) {
        $raw = $existingRequestRow['claim_date'];
        if (strpos($raw, '|') !== false) {
            // legacy format: "YYYY-MM-DD|Morning"
            [$d, $p] = explode('|', $raw, 2);
            $existingClaimDate = trim($d);
            $existingClaimPart = trim($p);
        } else {
            // date-only stored here — use claim_time column if present
            $existingClaimDate = $raw;
            if (!empty($existingRequestRow['claim_time'])) {
                $existingClaimPart = $existingRequestRow['claim_time'];
            } else {
                // default to Morning when only date present
                $existingClaimPart = 'Morning';
            }
        }
    } else {
        // No claim_date value stored; check separate fields (rare)
        if (!empty($existingRequestRow['claim_time'])) {
            $existingClaimPart = $existingRequestRow['claim_time'];
        }
        if (!empty($existingRequestRow['claim_date'])) {
            $existingClaimDate = $existingRequestRow['claim_date'];
        }
    }
}
?>
<link rel="stylesheet" href="serviceCertification.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<!-- Minimal claim-specific styles (scoped to this page) -->
<style>
    /* claim grid: two columns per date (left: Morning, right: Afternoon) */
    .claim-grid .date-row {
        margin-bottom: .6rem;
    }
    .claim-grid .claim-card {
        transition: box-shadow .12s ease, transform .08s ease;
        border-radius: .5rem;
        padding: .75rem;
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        min-height: 64px;
    }
    .claim-grid .claim-card .form-check {
        margin-top: 4px;
    }
    .claim-grid .claim-card.active {
        box-shadow: 0 6px 18px rgba(0,0,0,.06);
        transform: translateY(-2px);
        border: 1px solid #198754;
        background: #fffefb;
    }
    .claim-date-label {
        font-weight:600;
    }
    .claim-time {
        font-size: .95rem;
        color: #6b7280;
    }
    .claim-grid .date-label {
        font-weight:700;
        margin-bottom: .35rem;
    }
    .claim-card {
        border: 1px solid #e9ecef;        /* neutral outline */
        background: #ffffff;
        border-radius: 0.5rem;
        padding: 0.75rem;
        transition: box-shadow .12s ease, transform .08s ease, border-color .12s ease, outline .08s ease;
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        min-height: 64px;
        cursor: pointer;
        outline: none;                    /* avoid default UA outline */
    }

    /* responsive adjustment */
    @media (max-width: 575.98px) {
        .claim-grid .col-sm-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
</style>

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
                            <div class="col-sm-7 position-relative">
                                <input type="text" id="certType" name="certification_type" class="form-control" placeholder="Click to select or type" autocomplete="off" required>
                                <ul id="certTypeList" class="list-group position-absolute w-100 shadow-sm bg-white" style="max-height: 150px; overflow-y: auto; display: none; z-index: 1000;">
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6" id="requestForContainer" style="display: none;">
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

            </div>
            
            <!-- Step 2: Payment -->
            <div class="step payment-step <?php echo $transactionId ? 'completed' : ''; ?>" data-step="2" id="paymentStep">
            <div class="payment-container p-4 border rounded shadow-sm bg-green">
                <div class="row g-4">

                    <!-- LEFT COLUMN: Fee -->
                    <div class="col-md-4">
                        <div class="fee-box p-4 rounded shadow-sm border bg-light text-center">
                            <!-- UPDATED: dynamic fee title (server default + client-side updater will modify on selection) -->
                            <h5 id="feeTitle" class="fw-bold text-success mb-2"><?php echo htmlspecialchars($feeLabelDefault); ?></h5>
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
                        please visit the <strong>My Requests</strong> page and view your latest submitted request.
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
    // also expose the server default fee label so client-side can use it
    window.feeLabelDefault = <?php echo json_encode($feeLabelDefault); ?>;

    // Expose claim options & existing claim object for client-side use
    window._claimOptions = <?php echo json_encode($claimOptions, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
    window._existingClaimObj = <?php echo json_encode(['date' => $existingClaimDate, 'part' => $existingClaimPart], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;

    // NEW: expose existing parent sex & address so client-side can prefill the parent sex dropdown and show previously-entered address
    window.existingParentSex = <?php echo json_encode($existingParentSex, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
    window.existingParentAddress = <?php echo json_encode($existingParentAddress, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;

    window.existingChildrenData = <?php echo json_encode($childrenDataJson ?? '', JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
</script>

<!-- Claim handling script (keeps logic local so external JS can remain unchanged) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const claimGroup = document.getElementById('claimOptionsGroup');
    const hiddenDate = document.getElementById('hiddenClaimDate');
    const hiddenTime = document.getElementById('hiddenClaimTime');

    if (!claimGroup) return;

    function clearActiveCards() {
        claimGroup.querySelectorAll('.claim-card').forEach(function(card){
            card.classList.remove('active');
        });
    }

    function setHiddenValues(date, part) {
        if (hiddenDate) hiddenDate.value = date || '';
        if (hiddenTime) hiddenTime.value = part || '';
    }

    // on change, update hidden inputs & active styles
    claimGroup.addEventListener('change', function (e) {
        clearActiveCards();
        const checked = claimGroup.querySelector('input[name="claim_slot"]:checked');
        if (!checked) return;
        const parentLabel = checked.closest('label');
        if (parentLabel) parentLabel.classList.add('active');

        const date = checked.dataset.date || '';
        const part = checked.dataset.part || '';

        // set hidden fields (prefer data-* attributes)
        setHiddenValues(date, part);

        // also ensure the value fallback (legacy) is acceptable
        if ((!date || !part) && checked.value && checked.value.indexOf('|') !== -1) {
            const parts = checked.value.split('|');
            if (parts.length === 2) {
                setHiddenValues(parts[0], parts[1]);
            }
        }
    });

    // make labels clickable (ensure change event fires)
    claimGroup.querySelectorAll('label').forEach(function(lbl) {
        lbl.addEventListener('click', function(){
            const input = lbl.querySelector('input[type="radio"]');
            if (!input) return;
            if (!input.checked) {
                input.checked = true;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                // still dispatch to update UI
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    // Prefill selection if there is an existing claim
    if (window._existingClaimObj && window._existingClaimObj.date) {
        const d = window._existingClaimObj.date;
        const p = window._existingClaimObj.part;
        const desiredNew = claimGroup.querySelector(`input[name="claim_slot"][data-date="${d}"][data-part="${p}"]`);
        if (desiredNew) {
            desiredNew.checked = true;
            desiredNew.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            // fallback: match date only (prefer morning)
            const fallback = claimGroup.querySelector(`input[name="claim_slot"][data-date="${d}"]`);
            if (fallback) {
                fallback.checked = true;
                fallback.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    } else {
        // select first radio by default if none checked
        const firstRadio = claimGroup.querySelector('input[name="claim_slot"]');
        if (firstRadio && !claimGroup.querySelector('input[name="claim_slot"]:checked')) {
            firstRadio.checked = true;
            firstRadio.dispatchEvent(new Event('change', { bubbles: true }));
        } else if (firstRadio && claimGroup.querySelector('input[name="claim_slot"]:checked')) {
            // sync hidden inputs with already-checked option
            const already = claimGroup.querySelector('input[name="claim_slot"]:checked');
            if (already) {
                const d = already.dataset.date || '';
                const p = already.dataset.part || '';
                if (d && p) setHiddenValues(d, p);
                else if (already.value && already.value.indexOf('|') !== -1) {
                    const parts = already.value.split('|');
                    if (parts.length === 2) setHiddenValues(parts[0], parts[1]);
                }
            }
        }
    }
});
</script>

<script src="js/serviceCertification.js"></script>

<!--
  Override populateSummary after the main JS file loads so the Review summary shows:
   - Indigency -> Payment Status only
   - Others   -> Amount + Payment Status
   - NEW: Good Moral -> also show Parent Sex & Parent Address (address optional)
-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- DYNAMIC FEE TITLE UPDATER ---
    function normalizeTitleWords(s) {
        return s.replace(/\s+/g, ' ').trim().replace(/\b\w/g, c => c.toUpperCase());
    }

    function updateFeeTitleFromCertValue(certVal) {
        const feeEl = document.getElementById('feeTitle');
        if (!feeEl) return;
        const v = (certVal || '').trim();
        if (!v) {
            feeEl.textContent = window.feeLabelDefault || 'Barangay ID Fee';
            return;
        }

        // Build phrasing: "Certificate of <Name> Fee"
        // If user already typed something starting with "Certificate" just append "Fee"
        const normalized = normalizeTitleWords(v);
        let title;
        if (/^Certificate\b/i.test(normalized)) {
            title = normalized + (/\bFee$/i.test(normalized) ? '' : ' Fee');
        } else {
            title = 'Certificate of ' + normalized + ' Fee';
        }
        feeEl.textContent = title;
    }

    const certInput = document.getElementById('certType');
    if (certInput) {
        // initialize: prefer typed value, otherwise server-provided existingCertType
        if (!certInput.value && window.existingCertType) {
            certInput.value = window.existingCertType;
        }
        updateFeeTitleFromCertValue(certInput.value || window.existingCertType);

        // update when user types/selects
        ['input','change','blur'].forEach(evt =>
            certInput.addEventListener(evt, () => updateFeeTitleFromCertValue(certInput.value))
        );

        // If your code populates the cert type by clicking list items, ensure those actions
        // call certInput.dispatchEvent(new Event('input')) — above handlers will pick it up.
    } else {
        // fallback: if no input element present, use server default
        const feeEl = document.getElementById('feeTitle');
        if (feeEl) feeEl.textContent = window.feeLabelDefault || 'Barangay ID Fee';
    }
    
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

        if (type === 'good moral') {
            const parentSex = document.querySelector('[name="parent_sex"]')?.value || window.existingParentSex || '—';
            const parentAddress = document.querySelector('[name="parent_address"]')?.value || window.existingParentAddress || '—';
            rows.push(['Parent Sex:', parentSex]);
            rows.push(['Parent Address:', parentAddress]);
        }

        // Solo Parent: include Parent Sex, Child details + years
        if (type === 'solo parent') {
            const parentSex = document.querySelector('[name="parent_sex"]')?.value || window.existingParentSex || '—';
            rows.push(['Parent Sex:', parentSex]);
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

            if (!empty($existingRequestRow['children_data'])) {
                $childrenDataJson = $existingRequestRow['children_data'];
            } else {
                $childrenDataJson = '';
            }
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
        // Note: summary reads hidden claim_date field populated by the claim widget above
        const claimDateVal = document.querySelector('[name="claim_date"]')?.value;
        const claimTimeVal = document.querySelector('[name="claim_time"]')?.value;
        let claimDisplay = '—';
        if (claimDateVal && claimTimeVal) {
            // try to find friendly label from window._claimOptions
            if (window._claimOptions && Array.isArray(window._claimOptions)) {
                const found = window._claimOptions.find(c => c.date === claimDateVal);
                const dateLabel = found ? found.label : claimDateVal;
                const part = (found && Array.isArray(found.parts)) ? (found.parts.find(p => p.key === claimTimeVal)?.label || claimTimeVal) : claimTimeVal;
                claimDisplay = `${dateLabel} - ${part}`;
            } else {
                claimDisplay = `${claimDateVal} - ${claimTimeVal}`;
            }
        }
        rows.push(['Claim Date:', claimDisplay]);
        const purposeHiddenEl = document.querySelector('[name="purpose"]');
        const purposeDisplay = purposeHiddenEl?.value || '—';
        rows.push(['Purpose:', purposeDisplay]);

        // Payment details — different rules for Indigency vs others
        const paymentAmountEl = document.getElementById('paymentAmount');
        const paymentStatusEl = document.getElementById('paymentStatus');

        const clientAmount = paymentAmountEl?.value?.trim();
        const clientStatus = paymentStatusEl?.value?.trim();

        // prefer client-side hidden inputs, fallback to server values exposed on window
        const amountVal = clientAmount || window.existingPaymentAmount || '';
        const statusVal = clientStatus || window.existingPaymentStatus || '';

        if (type === 'indigency') {
            // show payment_status for indigency (default to "Free of Charge")
            rows.push(['Payment Status:', statusVal || 'Free of Charge']);
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