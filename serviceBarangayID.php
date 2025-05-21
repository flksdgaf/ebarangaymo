<?php
require 'functions/dbconn.php';

// Ensure the user is authenticated.
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['loggedInUserID'];
$transactionId = $_GET['tid'] ?? null;
$t = $transactionId ? true : false;

// --- 1) Check for existing Brgy-ID record: Renewal vs. New Application
$stmtID = $conn->prepare("
    SELECT * 
    FROM barangay_id_accounts 
    WHERE account_id = ? 
    LIMIT 1
");
$stmtID->bind_param("i", $userId);
$stmtID->execute();
$resID = $stmtID->get_result();

$isRenewal = false;
if ($resID && $resID->num_rows === 1) {
    $isRenewal = true;
    $rowID       = $resID->fetch_assoc();
    // *** ADDED: pull in existing values
    $fullName       = $rowID['full_name'];
    $fullAddress    = $rowID['address'];
    $height         = $rowID['height'];
    $weight         = $rowID['weight'];
    $birthdate      = $rowID['birthdate'];
    $birthplace     = $rowID['birthplace'];
    $civilstatus    = $rowID['civil_status'];
    $religion       = $rowID['religion'];
    $contactperson  = $rowID['contact_person'];
    $formal_picture = $rowID['formal_picture'];
}
$stmtID->close();

// --- 2) If NEW, get basic info from user_profiles (and leave editable fields blank)
if (!$isRenewal) {
    // build the UNION of all purok tables (no per-SELECT LIMIT)
    $unionSql = [];
    for ($i = 1; $i <= 6; $i++) {
        $unionSql[] = "
            SELECT full_name, birthdate, civil_status
              FROM purok{$i}_rbi
             WHERE account_ID = ?
        ";
    }
    // join with UNION ALL, then one global LIMIT 1
    $sql = implode(" UNION ALL ", $unionSql) . " LIMIT 1";

    $stmt = $conn->prepare($sql);
    // bind the same userId six times
    $types = str_repeat("i", 6);
    $params = array_fill(0, 6, $userId);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $r = $res->fetch_assoc();
        $fullName    = $r['full_name'];
        $birthdate   = $r['birthdate'];
        $civilstatus = $r['civil_status'];
    } else {
        // not found in any purok table
        $fullName    = '';
        $birthdate   = '';
        $civilstatus = '';
    }
    $stmt->close();

    // initialize the “new application”–only fields
    $fullAddress   = '';
    $height        = '';
    $weight        = '';
    $birthplace    = '';
    $religion      = '';
    $contactperson = '';
    $formal_picture= '';
}

// Transaction label
$transactionType = $isRenewal ? 'Renewal' : 'New Application';

$chosenPayment = null;
if ($transactionId) {
    $stmt = $conn->prepare("
      SELECT payment_method 
       FROM barangay_id_requests
       WHERE transaction_id = ? 
       LIMIT 1
    ");
    $stmt->bind_param("s", $transactionId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows) {
        $chosenPayment = $res->fetch_assoc()['payment_method'];
    }
    $stmt->close();
}
?>

<link rel="stylesheet" href="serviceBarangayID.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="container pb-2">
    <div class="progress-container">
        <div class="stepss">

            <!-- STEP 1 -->
            <div class="steps">
                <div class="circle <?php echo $t ? 'completed' : 'active'; ?>" data-step="1">1</div>
                <div class="step-label <?php echo $t ? 'completed' : 'active'; ?>">
                APPLICATION FORM
                </div>
            </div>

            <!-- STEP 2 -->
            <div class="steps">
                <div class="circle <?php echo $t ? 'completed' : ''; ?>" data-step="2">2</div>
                <div class="step-label <?php echo $t ? 'completed' : ''; ?>">
                PAYMENT
                </div>
            </div>

            <!-- STEP 3 -->
            <div class="steps">
                <div class="circle <?php echo $t ? 'completed' : ''; ?>" data-step="3">3</div>
                <div class="step-label <?php echo $t ? 'completed' : ''; ?>">
                REVIEW &amp; CONFIRMATION
                </div>
            </div>

            <!-- STEP 4 -->
            <div class="steps">
                <div class="circle <?php echo $t ? 'active' : ''; ?>" data-step="4">4</div>
                <div class="step-label <?php echo $t ? 'active' : ''; ?>">
                SUBMISSION
                </div>
            </div>

            <div class="progress-line"></div>
            <!-- fill to 100% if on step 4 -->
            <div class="progress-fill" id="progressFill" style="<?php echo $t ? 'width: 100%;' : ''; ?>"></div>
        </div>
    </div>

    <div class="card shadow-sm p-5 mb-5 mt-5">
        <h4 class="mb-3 text-success display-6 fw-bold" id="mainHeader">APPLICATION FORM</h4>
        <p id="subHeader">Provide the necessary details to apply for your Barangay ID.</p>
        <hr id="mainHr">

        <form id="barangayIDForm" action="functions/serviceBarangayID_submit.php" method="POST" enctype="multipart/form-data">
            <div class="step <?php echo $transactionId ? 'completed' : 'active-step'; ?>">
                <!-- TYPE OF TRANSACTION -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Type of Transaction</label>
                <div class="col-md-8 d-flex align-items-center">
                    <!-- show readonly select -->
                    <select class="form-control custom-input" readonly>
                    <option><?php echo $transactionType; ?></option>
                    </select>
                    <!-- hidden so it still posts -->
                    <input type="hidden" id="transactiontype" name="transactiontype" value="<?php echo $transactionType; ?>">
                </div>
                </div>

                <!-- FULL NAME (always readonly) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Full Name</label>
                <div class="col-md-8">
                    <input type="text" id="fullname" name="fullname"
                        class="form-control custom-input"
                        readonly
                        value="<?php echo htmlspecialchars($fullName); ?>">
                </div>
                </div>

                <!-- FULL ADDRESS (always readonly) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Full Address</label>
                <div class="col-md-8">
                    <input type="text" id="address" name="address"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($fullAddress); ?>">
                </div>
                </div>

                <!-- HEIGHT (editable always) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Height (in feet)</label>
                <div class="col-md-8">
                    <input type="text" id="height" name="height"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($height); ?>">
                </div>
                </div>

                <!-- WEIGHT (editable always) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Weight (in kilograms)</label>
                <div class="col-md-8">
                    <input type="number" id="weight" name="weight"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($weight); ?>">
                </div>
                </div>

                <!-- BIRTHDAY (always readonly) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Birthday</label>
                <div class="col-md-8">
                    <input type="date" id="birthday" name="birthday"
                        class="form-control custom-input"
                        readonly
                        value="<?php echo date('Y-m-d', strtotime($birthdate)); ?>">
                </div>
                </div>

                <!-- BIRTHPLACE (editable only on NEW) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Birthplace</label>
                <div class="col-md-8">
                    <input type="text" id="birthplace" name="birthplace"
                        class="form-control custom-input"
                        required
                        <?php echo $isRenewal ? 'readonly' : ''; ?>
                        value="<?php echo htmlspecialchars($birthplace); ?>">
                </div>
                </div>

                <!-- CIVIL STATUS (editable always) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Civil Status</label>
                <div class="col-md-8">
                    <select id="civilstatus" name="civilstatus"
                            class="form-control custom-input"
                            required>
                    <option value="">Select an option</option>
                    <?php
                    foreach (['Single','Married','Separated','Widowed'] as $opt) {
                        $sel = ($opt === $civilstatus) ? 'selected' : '';
                        echo "<option value=\"$opt\" $sel>$opt</option>";
                    }
                    ?>
                    </select>
                </div>
                </div>

                <!-- RELIGION (editable always) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Religion</label>
                <div class="col-md-8">
                    <input type="text" id="religion" name="religion"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($religion); ?>">
                </div>
                </div>

                <!-- CONTACT PERSON (editable always) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Contact Person (in case of emergency)</label>
                <div class="col-md-8">
                    <input type="text" id="contactperson" name="contactperson"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($contactperson); ?>">
                </div>
                </div>

                <!-- FORMAL PICTURE (always editable; required only on NEW) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">1x1 Formal Picture</label>
                <div class="col-md-8">
                    <input type="file" id="brgyIDpicture" name="brgyIDpicture"
                        class="form-control custom-input"
                        accept="image/*"
                        required>
                </div>
                </div>

                <!-- CLAIM DATE (always editable) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">When would you prefer to claim your Brgy. ID?</label>
                <div class="col-md-8">
                    <input type="date" id="claimdate" name="claimdate"
                        class="form-control custom-input"
                        required>
                </div>
                </div>
            </div>
            
            <!-- Step 2: Payment -->
            <div class="step <?php echo $transactionId ? 'completed' : ''; ?>">
                <div class="payment-container p-3 text-center">
                    <h5 class="fw-bold mb-4">Select preferred Payment Method</h5>

                    <!-- Use Bootstrap’s btn-group to attach the buttons -->
                    <div class="btn-group btn-group-lg mb-4" role="group" aria-label="Payment Methods">
                        
                        <!-- GCash -->
                        <button type="button" class="btn btn-outline-success disabled payment-btn" data-method="GCash">
                            <img src="images/gcash_logo.png" alt="GCash" class="mb-2 payment-icon">
                            <span class="fw-bold fs-6">GCash</span>
                        </button>

                        <!-- Barangay Device -->
                        <button type="button" class="btn btn-outline-success payment-btn active" data-method="Brgy Payment Device">
                            <span class="material-symbols-outlined mb-2 payment-icon">payments</span>
                            <span class="fw-bold fs-6">Brgy. Payment Device</span>
                        </button>

                        <!-- Over‑the‑Counter (now uses a “paid” icon) -->
                        <button type="button" class="btn btn-outline-success payment-btn" data-method="Over-the-Counter">
                            <span class="material-symbols-outlined mb-2 payment-icon">paid</span>
                            <span class="fw-bold fs-6">Over-the-Counter</span>
                        </button>
                    </div>

                    <!-- instructions panels -->
                    <div id="payment-instructions">
                        <div class="payment-instruction d-none" data-method="GCash">
                            <ol>
                                <li>Open your GCash app and scan the QR code below to pay.</li>
                                <li>Enter the exact amount: <strong>₱XX.XX</strong>.</li>
                                <li>Confirm the transaction.</li>
                                <li>Download or screenshot the confirmation receipt.</li>
                                <li>Upload the receipt in the next step.</li>
                            </ol>
                        </div>
                        <div class="payment-instruction" data-method="Brgy Payment Device">
                            <ol>
                                <h4 class="fw-bold mb-4 fs-6">HOW TO USE:</h4>
                                <li>Submit your application and wait for a generated QR code in the last step.</li>
                                <li>Download or screenshot the generated QR code.</li>
                                <li>Go to the designated Barangay Payment Device located at the barangay hall.</li>
                                <li>Scan the generated QR code to the device to begin your transaction.</li>
                                <li>Insert the coins or paper bills until the required amount is reached.</li>
                                <li>Wait for the confirmation screen and printed receipt.</li>
                                <li>Submit the receipt to the Clerk and claim your Barangay ID.</li>
                            </ol>
                        </div>
                        <div class="payment-instruction d-none" data-method="Over-the-Counter">
                            <ol>
                                <h4 class="fw-bold mb-4 fs-6">HOW TO USE:</h4>
                                <li>Visit the Barangay Treasurer at the barangay hall.</li>
                                <li>Present your application transaction number.</li>
                                <li>Pay the transaction fee in cash.</li>
                                <li>Obtain the official receipt from the treasurer.</li>
                                <li>Keep the receipt for claim on your Barangay ID.</li>
                            </ol>
                        </div>
                    </div>

                    <!-- hidden field so PHP can see it on submit -->
                    <input type="hidden" id="paymentMethod" name="paymentMethod" value="Brgy Payment Device">
                </div>
            </div>

            <!-- Step 3: Summary / Review -->
            <div class="step <?php echo $transactionId ? 'completed' : ''; ?>">
                <div class="summary-container p-3">
                    <p><strong>Transaction Type:</strong> <span id="summarytransactionType"></span></p>
                    <p><strong>Full Name:</strong> <span id="summaryFullName"></span></p>
                    <p><strong>Address:</strong> <span id="summaryAddress"></span></p>
                    <p><strong>Height:</strong> <span id="summaryHeight"></span></p>
                    <p><strong>Weight:</strong> <span id="summaryWeight"></span></p>
                    <p><strong>Birthday:</strong> <span id="summaryBirthdate"></span></p>
                    <p><strong>Birthplace:</strong> <span id="summaryBirthplace"></span></p>
                    <p><strong>Civil Status:</strong> <span id="summaryCivilStatus"></span></p>
                    <p><strong>Religion:</strong> <span id="summaryReligion"></span></p>
                    <p><strong>Contact Person:</strong> <span id="summaryContactPerson"></span></p>
                    <p><strong>Claim Date:</strong> <span id="summaryClaimDate"></span></p>

                    <!-- ADD: payment method summary -->
                    <p><strong>Payment Method:</strong> <span id="summaryPaymentMethod"></span></p>
                </div>
            </div>

            <!-- Step 4: Submission -->
            <div class="step <?php echo $transactionId ? 'active-step' : ''; ?>">
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
    window.initialStep = <?php echo $initial; ?>;
</script>

<script src="js/serviceBarangayID.js"></script>

