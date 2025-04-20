<?php
require 'functions/dbconn.php';

// Ensure the user is authenticated.
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['loggedInUserID'];

// --- 1) Check for existing Brgy-ID record: Renewal vs. New Application
$stmtID = $conn->prepare("
    SELECT * 
    FROM barangayID_accounts 
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
    $stmt = $conn->prepare("
        SELECT full_name, full_address, birthdate 
        FROM user_profiles 
        WHERE account_id = ? 
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $fullName    = $row['full_name'];
        $fullAddress = $row['full_address'];
        $birthdate   = $row['birthdate'];
    }
    $stmt->close();

    // initialize blank/new fields
    $height = $weight = $birthplace = $civilstatus = $religion = $contactperson = '';
    $formal_picture = '';
}

// Transaction label
$transactionType = $isRenewal ? 'Renewal' : 'New Application';
?>

<link rel="stylesheet" href="serviceBarangayID.css">

<div class="container pb-2">
    <div class="progress-container"><div class="stepss">
        <div class="steps">
            <!-- Note: the first circle is preset with class "active" -->
            <div class="circle active" data-step="1">1</div>
            <div class="step-label active">APPLICATION FORM</div>
        </div>
        <div class="steps">
            <div class="circle" data-step="2">2</div>
            <div class="step-label">PAYMENT</div>
        </div>
        <div class="steps">
            <div class="circle" data-step="3">3</div>
            <div class="step-label">REVIEW &amp; CONFIRMATION</div>
        </div>
        <div class="steps">
            <div class="circle" data-step="4">4</div>
            <div class="step-label">SUBMISSION</div>
        </div>
        <div class="progress-line"></div>
        <div class="progress-fill" id="progressFill"></div>
        </div>
    </div>

    <div class="card shadow-sm p-5 mb-5 mt-5">
        <h4 class="mb-3 text-success display-6 fw-bold" id="mainHeader">APPLICATION FORM</h4>
        <p id="subHeader">Provide the necessary details to apply for your Barangay ID.</p>
        <hr>

        <form action="submit.php" method="POST" enctype="multipart/form-data">
            <div class="step active-step">
                <!-- TYPE OF TRANSACTION -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Type of Transaction</label>
                <div class="col-md-8 d-flex align-items-center">
                    <!-- show readonly select -->
                    <select class="form-control custom-input" disabled>
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
                        disabled
                        value="<?php echo htmlspecialchars($fullName); ?>">
                </div>
                </div>

                <!-- FULL ADDRESS (always readonly) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Full Address</label>
                <div class="col-md-8">
                    <input type="text" id="address" name="address"
                        class="form-control custom-input"
                        disabled
                        value="<?php echo htmlspecialchars($fullAddress); ?>">
                </div>
                </div>

                <!-- HEIGHT (editable always) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Height (in cm)</label>
                <div class="col-md-8">
                    <input type="number" id="height" name="height"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($height); ?>">
                </div>
                </div>

                <!-- WEIGHT (editable always) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Weight (in kg)</label>
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
                        disabled
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
                        <?php echo $isRenewal ? 'disabled' : ''; ?>
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
                    <?php if ($isRenewal && $formal_picture): ?>
                    <p>Current file: <?php echo htmlspecialchars($formal_picture); ?></p>
                    <?php endif; ?>
                    <input type="file" id="brgyIDpicture" name="brgyIDpicture"
                        class="form-control custom-input"
                        accept="image/*"
                        <?php echo $isRenewal ? '' : 'required'; ?>>
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
            <div class="step">
                <div class="payment-container p-3">
                    <h5 class="fw-bold">Select preferred Payment Method</h5>

                    <!-- payment method buttons -->
                    <div class="btn-group mb-4" role="group" aria-label="Payment Methods">
                    <button type="button" class="btn btn-outline-success active" data-method="GCash">
                        <span class="material-symbols-outlined me-1">payments</span> GCash
                    </button>
                    <button type="button" class="btn btn-outline-success" data-method="Brgy Payment Device">
                        <span class="material-symbols-outlined me-1">payments</span> Brgy. Payment Device
                    </button>
                    <button type="button" class="btn btn-outline-success" data-method="Over-the-Counter">
                        <span class="material-symbols-outlined me-1">payments</span> Over‑the‑Counter
                    </button>
                    </div>

                    <!-- instructions panels -->
                    <div id="payment-instructions">
                    <div class="payment-instruction" data-method="GCash">
                        <ol>
                        <li>Open your GCash app and scan the QR code below to pay.</li>
                        <li>Enter the exact amount: <strong>₱XX.XX</strong>.</li>
                        <li>Confirm the transaction.</li>
                        <li>Download or screenshot the confirmation receipt.</li>
                        <li>Upload the receipt in the next step.</li>
                        </ol>
                        <!-- optionally: <img src="images/gcash_qr.png" alt="GCash QR"> -->
                    </div>
                    <div class="payment-instruction d-none" data-method="Brgy Payment Device">
                        <ol>
                        <li>Go to the designated Barangay Payment Device located at the barangay hall.</li>
                        <li>Scan the generated QR code to begin your transaction.</li>
                        <li>Insert the exact coins or paper bills until the required amount is reached.</li>
                        <li>Wait for the confirmation screen and printed receipt.</li>
                        <li>Submit the receipt to the Clerk and claim your Barangay ID.</li>
                        </ol>
                        <p><small>Note: Download this QR code to scan at the Barangay Payment Device.</small></p>
                    </div>
                    <div class="payment-instruction d-none" data-method="Over-the-Counter">
                        <ol>
                        <li>Visit the Barangay cashier at the municipal hall.</li>
                        <li>Present your application reference number.</li>
                        <li>Pay the exact amount in cash.</li>
                        <li>Obtain the official receipt from the cashier.</li>
                        <li>Keep the receipt for claim on your Barangay ID.</li>
                        </ol>
                    </div>
                </div>

                <!-- hidden field so PHP can see it on submit -->
                <input type="hidden" id="paymentMethod" name="paymentMethod" value="GCash">
            </div>
            </div>

            <!-- Step 3: Summary / Review -->
            <div class="step">
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


            <!-- Navigation Buttons (these remain centered as needed) -->
            <div class="d-flex justify-content-between w-100 mt-4">
                <button type="button" class="btn back-btn" id="backBtn">< GO BACK</button>
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

<script src="js/serviceBarangayID.js"></script>
