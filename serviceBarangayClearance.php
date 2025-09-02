<?php
require 'functions/dbconn.php';

// User auth assumed (same as your reference):
$userId = $_SESSION['loggedInUserID'] ?? null;
$transactionId = $_GET['tid'] ?? null;
$t = $transactionId ? true : false;

// --- Attempt to prefill user info from purok tables (same pattern as reference)
$fullName = '';
$userPurok = '';
$birthdate = '';
$civilstatus = '';

$unionSql = [];
for ($i = 1; $i <= 6; $i++) {
    $unionSql[] = "
        SELECT full_name, birthdate, civil_status, 'Purok $i' AS purok
          FROM purok{$i}_rbi
         WHERE account_ID = ?
    ";
}
$sql = implode(" UNION ALL ", $unionSql) . " LIMIT 1";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $types = str_repeat("i", 6);
    $params = array_fill(0, 6, $userId);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $r = $res->fetch_assoc();
        $fullName    = $r['full_name'] ?? '';
        $birthdate   = $r['birthdate'] ?? '';
        $civilstatus = $r['civil_status'] ?? '';
        $userPurok   = $r['purok'] ?? '';
    }
    $stmt->close();
}

// Try to parse full name into parts (assuming stored as "Surname, First Middle" or plain)
$lastName = $firstName = $middleName = '';
if ($fullName) {
    if (strpos($fullName, ',') !== false) {
        $parts = explode(',', $fullName, 2);
        $lastName = trim($parts[0]);
        $rest = trim($parts[1] ?? '');
        $restParts = preg_split('/\s+/', $rest, -1, PREG_SPLIT_NO_EMPTY);
        if (count($restParts) > 0) {
            $firstName = array_shift($restParts);
            $middleName = implode(' ', $restParts);
        }
    } else {
        // fallback: split by spaces, assume last token is surname
        $parts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) === 1) {
            $firstName = $parts[0];
        } else {
            $lastName = array_pop($parts);
            $firstName = array_shift($parts);
            $middleName = implode(' ', $parts);
        }
    }
}

// compute age if birthdate available
$age = '';
if (!empty($birthdate) && $birthdate !== '0000-00-00') {
    try {
        $dob = new DateTime($birthdate);
        $now = new DateTime();
        $age = $dob->diff($now)->y;
    } catch (Exception $e) {
        $age = '';
    }
}

// If transaction provided, try to fetch existing request (so we can prefill the form & chosen payment)
$existingRequest = [];
$chosenPayment = null;
if ($transactionId) {
    $stmt = $conn->prepare("SELECT * FROM barangay_clearance_requests WHERE transaction_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows) {
            $existingRequest = $res->fetch_assoc();
            $chosenPayment = $existingRequest['payment_method'] ?? null;
        }
        $stmt->close();
    }
}

// Defaults (prefill) you requested
$defaultBarangay = 'Magang';
$defaultMunicipality = 'Daet';
$defaultProvince = 'Camarines Norte';
?>

<link rel="stylesheet" href="serviceBarangayClearance.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="container py-4 px-3">
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
            <div class="progress-fill" id="progressFill" style="<?php echo $t ? 'width: 100%;' : ''; ?>"></div>
        </div>
    </div>

    <div class="card shadow-sm px-5 py-5 mb-5 mt-4">
        <h2 class="mb-1 text-success fw-bold" id="mainHeader"></h2>
        <p id="subHeader" class="mb-2">Provide the necessary details to request a Barangay Clearance.</p>
        <hr id="mainHr" class="mb-4">

        <form id="barangayClearanceForm" action="functions/serviceBarangayClearance_submit.php" method="POST" enctype="multipart/form-data">
            <!-- Step 1: Application Form -->
            <div class="step <?php echo $transactionId ? 'completed' : 'active-step'; ?>">

                <!-- LAST NAME -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Last Name</label>
                <div class="col-md-8">
                    <input type="text" id="lastname" name="lastname"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($lastName); ?>">
                </div>
                </div>

                <!-- FIRST NAME -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">First Name</label>
                <div class="col-md-8">
                    <input type="text" id="firstname" name="firstname"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($firstName); ?>">
                </div>
                </div>

                <!-- MIDDLE NAME -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Middle Name</label>
                <div class="col-md-8">
                    <input type="text" id="middlename" name="middlename"
                        class="form-control custom-input"
                        value="<?php echo htmlspecialchars($middleName); ?>">
                </div>
                </div>

                <!-- STREET (optional) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Street (Optional)</label>
                <div class="col-md-8">
                    <input type="text" id="street" name="street"
                        class="form-control custom-input"
                        placeholder="Street (optional)"
                        value="<?php echo htmlspecialchars($existingRequest['street'] ?? ''); ?>">
                </div>
                </div>

                <!-- PUROK -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Purok</label>
                <div class="col-md-8">
                    <select id="purok" name="purok" class="form-control custom-input" required>
                        <option value="">Select Purok</option>
                        <?php
                        $puroks = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6'];
                        foreach ($puroks as $p) {
                            $selected = ($userPurok === $p || ($existingRequest['purok'] ?? '') === $p) ? 'selected' : '';
                            echo "<option value=\"$p\" $selected>$p</option>";
                        }
                        ?>
                    </select>
                </div>
                </div>

                <!-- BARANGAY (prefilled to Magang but editable) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Barangay</label>
                <div class="col-md-8">
                    <input type="text" id="barangay" name="barangay"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($existingRequest['barangay'] ?? $defaultBarangay); ?>">
                </div>
                </div>

                <!-- MUNICIPALITY (prefilled to Daet but editable) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Municipality</label>
                <div class="col-md-8">
                    <input type="text" id="municipality" name="municipality"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($existingRequest['municipality'] ?? $defaultMunicipality); ?>">
                </div>
                </div>

                <!-- PROVINCE (prefilled to Daet but editable) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Province</label>
                <div class="col-md-8">
                    <input type="text" id="province" name="province"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($existingRequest['province'] ?? $defaultProvince); ?>">
                </div>
                </div>

                <!-- BIRTHDATE -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Birthdate</label>
                <div class="col-md-8">
                    <input type="date" id="birthdate" name="birthdate"
                        class="form-control custom-input"
                        required
                        value="<?php echo (!empty($birthdate) && $birthdate !== '0000-00-00') ? date('Y-m-d', strtotime($birthdate)) : ''; ?>">
                </div>
                </div>

                <!-- AGE -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Age</label>
                <div class="col-md-8">
                    <input type="number" id="age" name="age"
                        class="form-control custom-input"
                        min="0" max="150" required
                        value="<?php echo htmlspecialchars($age); ?>">
                </div>
                </div>

                <!-- BIRTHPLACE -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Birthplace</label>
                <div class="col-md-8">
                    <input type="text" id="birthplace" name="birth_place"
                        class="form-control custom-input"
                        required placeholder="City, Province"
                        value="<?php echo htmlspecialchars($existingRequest['birth_place'] ?? ''); ?>">
                </div>
                </div>

                <!-- MARITAL STATUS -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Marital Status</label>
                <div class="col-md-8">
                    <select id="maritalstatus" name="marital_status" class="form-control custom-input" required>
                        <option value="">Select an option</option>
                        <?php
                        foreach (['Single','Married','Separated','Widowed'] as $opt) {
                            $sel = ($opt === $civilstatus || ($existingRequest['marital_status'] ?? '') === $opt) ? 'selected' : '';
                            echo "<option value=\"$opt\" $sel>$opt</option>";
                        }
                        ?>
                    </select>
                </div>
                </div>

                <!-- CTC NUMBER -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">CTC Number</label>
                <div class="col-md-8">
                    <input type="text" id="ctcnumber" name="ctc_number"
                        class="form-control custom-input"
                        placeholder="Community Tax Certificate No."
                        value="<?php echo htmlspecialchars($existingRequest['ctc_number'] ?? ''); ?>">
                </div>
                </div>

                <!-- PURPOSE (NEW: select + hidden real 'purpose' field) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Purpose</label>
                <div class="col-md-8">
                    <?php
                    // Define fixed purpose options
                    $purposes = ['Employment','ID','School Enrollment','Passport','Travel','Business','Others'];

                    // Existing purpose from DB (if any)
                    $existingPurpose = $existingRequest['purpose'] ?? '';

                    // Determine whether existing purpose matches one of the predefined ones
                    $is_prefilled_in_list = in_array($existingPurpose, $purposes, true);

                    // If existing purpose not in the list, keep its value in hidden input so server still receives it,
                    // but DO NOT pre-select "Others" or show the visible custom input. Visible select shows placeholder.
                    $prefill_other_value = $is_prefilled_in_list ? '' : $existingPurpose;
                    ?>
                    <!-- select used for user UI; note name changed so only hidden field 'purpose' is submitted -->
                    <select id="purposeSelect" name="purpose_select" class="form-control custom-input" required>
                        <!-- Placeholder text shown to the user -->
                        <option value="">Select Purpose</option>
                        <?php
                        foreach ($purposes as $p) {
                            // only pre-select if existing purpose matches one of the predefined ones
                            $sel = ($is_prefilled_in_list && $existingPurpose === $p) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($p) . "\" $sel>" . htmlspecialchars($p) . "</option>";
                        }
                        ?>
                    </select>

                    <!-- visible text input for custom purpose (hidden by default; only shown when user picks "Others") -->
                    <input type="text" id="purposeOther" name="purpose_other"
                        class="form-control custom-input mt-2 d-none"
                        placeholder="Please specify purpose"
                        value="<?php echo htmlspecialchars($prefill_other_value); ?>">

                    <!-- Hidden input that carries the final value for 'purpose' the server expects -->
                    <input type="hidden" id="purposeHidden" name="purpose" value="<?php
                        // initial hidden value: if existing purpose in the predefined list, use it; otherwise use the custom value
                        echo htmlspecialchars($is_prefilled_in_list ? $existingPurpose : $prefill_other_value);
                    ?>">
                </div>
                </div>

                <!-- OPTIONAL: Picture (not required unless you want) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Attach Picture (optional)</label>
                <div class="col-md-8">
                    <input type="file" id="picture" name="picture"
                        class="form-control custom-input"
                        accept="image/*">
                </div>
                </div>

                <!-- CLAIM DATE -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Please select preferred claim date</label>
                <div class="col-md-8">
                    <input type="date" id="claimdate" name="claim_date"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($existingRequest['claim_date'] ?? ''); ?>">
                </div>
                </div>
            </div>

            <!-- Step 2: Payment -->
            <div class="step <?php echo $transactionId ? 'completed' : ''; ?>">
            <div class="payment-container p-4 border rounded shadow-sm bg-green">
                <div class="row g-4">

                <!-- LEFT COLUMN: Fee -->
                <div class="col-md-4">
                    <div class="fee-box p-4 rounded shadow-sm border bg-light text-center">
                        <h5 class="fw-bold text-success mb-2">Barangay Clearance Fee</h5>
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
                          <li>Claim your clearance at the barangay on your selected claim date.</li>
                        </ol>
                    </div>

                    <div class="payment-instruction" data-method="Brgy Payment Device">
                        <ol>
                        <h4 class="fw-bold mb-3 fs-6">HOW TO USE:</h4>
                        <li>Submit your application and download the generated <strong>QR code</strong>.</li>
                        <li>Go to the <strong>Barangay Payment Device</strong> located at the barangay hall.</li>
                        <li>Scan the code and insert <strong>₱130.00</strong>.</li>
                        <li>Wait for the confirmation and printed receipt.</li>
                        <li>Submit the receipt and claim your Barangay Clearance.</li>
                        </ol>
                    </div>

                    <div class="payment-instruction d-none" data-method="Over-the-Counter">
                        <ol>
                        <h4 class="fw-bold mb-3 fs-6">HOW TO USE:</h4>
                            <li>Submit your application and save the given <strong>Transaction Number</strong>.</li>
                            <li>Go to the Barangay Treasurer and present your Transaction Number.</li>
                            <li>Pay <strong>₱130.00</strong> in cash.</li>
                            <li>Receive the official receipt.</li>
                            <li>Claim your clearance at the Barangay Record Keeper.</li>
                        </ol>
                    </div>
                    </div>

                    <!-- Hidden input to capture method -->
                    <input type="hidden" id="paymentMethod" name="payment_method" value="<?php echo htmlspecialchars($existingRequest['payment_method'] ?? 'Brgy Payment Device'); ?>">
                </div>

                </div>
            </div>
            </div>

            <!-- Step 3: Summary / Review -->
            <div class="step <?php echo $transactionId ? 'completed' : ''; ?>">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-8 col-xl-6">
                <div class="summary-container p-4 rounded shadow-sm border">

                    <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Last Name:</span>
                        <span class="text-success" id="summaryLastName"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">First Name:</span>
                        <span class="text-success" id="summaryFirstName"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Middle Name:</span>
                        <span class="text-success" id="summaryMiddleName"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Street:</span>
                        <span class="text-success" id="summaryStreet"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Purok:</span>
                        <span class="text-success" id="summaryPurok"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Barangay / Municipality / Province:</span>
                        <span class="text-success" id="summaryAddress"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Birthdate / Age:</span>
                        <span class="text-success" id="summaryBirthAge"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Birthplace:</span>
                        <span class="text-success" id="summaryBirthplace"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Marital Status:</span>
                        <span class="text-success" id="summaryMaritalStatus"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">CTC Number:</span>
                        <span class="text-success" id="summaryCTC"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Purpose:</span>
                        <span class="text-success" id="summaryPurpose"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Claim Date:</span>
                        <span class="text-success" id="summaryClaimDate"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Payment Method:</span>
                        <span class="text-success" id="summaryPaymentMethod"></span>
                    </li>
                    </ul>
                </div>
                </div>
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
                        $chars = str_split($transactionId);
                        foreach ($chars as $char): ?>
                            <span class="txn-char"><?php echo htmlspecialchars($char); ?></span>
                        <?php endforeach; ?>
                    </div>

                    <!-- QR CODE OR HOURGLASS -->
                    <?php if ($chosenPayment === 'Brgy Payment Device'): ?>
                        <div class="qr-container">
                            <div id="qrcode" style="margin:auto;"></div>
                            <button type="button" id="downloadQRBtn" class="btn download-btn btn-success mt-3">Download QR Code</button>
                            <p>Download or screenshot this QR code in order to use the Barangay Payment Device</p>
                        </div>
                        <script>
                            document.addEventListener("DOMContentLoaded", function(){
                                const container = document.getElementById("qrcode");

                                new QRCode(container, {
                                    text: "<?php echo htmlspecialchars($transactionId); ?>",
                                    width: 300,
                                    height: 300,
                                });

                                document.getElementById("downloadQRBtn").addEventListener("click", function(){
                                    const img = container.querySelector("img");
                                    if (!img) return;
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

                    <p class="submission-footer">
                        To check if your clearance is ready for release,<br>
                        please visit the <strong>My Requests</strong> page and enter your transaction reference number.
                    </p>

                </div>
            <?php endif; ?>
            </div>

            <!-- Navigation Buttons -->
            <div class="d-flex justify-content-between w-100 mt-4">
                <button type="button" class="btn back-btn" id="backBtn">< PREVIOUS</button>
                <button type="button" class="btn next-btn" id="nextBtn">NEXT ></button>
            </div>
        </form>
    </div>

    <!-- Validation Modal -->
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
    window.initialStep = <?php echo $initial; ?>;
</script>

<script>
// small inline helper to show/hide the "other" purpose input and keep summary in sync
document.addEventListener('DOMContentLoaded', function(){
    const purposeSelect = document.getElementById('purposeSelect');
    const purposeOther = document.getElementById('purposeOther');
    const purposeHidden = document.getElementById('purposeHidden'); // final value submitted as 'purpose'

    function togglePurposeOther(){
        if(!purposeSelect) return;
        // Show the free-text field ONLY when user explicitly selects 'Others'
        if(purposeSelect.value === 'Others'){
            purposeOther.classList.remove('d-none');
            purposeOther.required = true;
            // if purposeOther has value use it otherwise keep hidden value 'Others' until typed
            if(purposeOther.value.trim()) {
                purposeHidden.value = purposeOther.value.trim();
            } else {
                // keep hidden as 'Others' while user types
                purposeHidden.value = 'Others';
            }
        } else {
            // hide other input and copy selected value to hidden
            purposeOther.classList.add('d-none');
            purposeOther.required = false;
            // if user selected a predefined option, use it
            if(purposeSelect.value) {
                purposeHidden.value = purposeSelect.value;
            } else {
                // if placeholder is selected and there is already a hidden custom value (from prefill),
                // keep it (so server receives the existing custom purpose). Otherwise clear.
                // (purposeHidden was initialized on server-side with any existing value)
            }
        }
        updateSummary();
    }

    function updateSummary(){
        const byId = id => document.getElementById(id);
        if(byId('summaryLastName')) byId('summaryLastName').textContent = document.getElementById('lastname').value;
        if(byId('summaryFirstName')) byId('summaryFirstName').textContent = document.getElementById('firstname').value;
        if(byId('summaryMiddleName')) byId('summaryMiddleName').textContent = document.getElementById('middlename').value;
        if(byId('summaryStreet')) byId('summaryStreet').textContent = document.getElementById('street').value;
        if(byId('summaryPurok')) byId('summaryPurok').textContent = document.getElementById('purok').value;
        if(byId('summaryAddress')) byId('summaryAddress').textContent = [document.getElementById('barangay').value, document.getElementById('municipality').value, document.getElementById('province').value].filter(Boolean).join(' / ');
        if(byId('summaryBirthAge')) byId('summaryBirthAge').textContent = [document.getElementById('birthdate').value, document.getElementById('age').value ? (' / ' + document.getElementById('age').value) : ''].join('');
        if(byId('summaryBirthplace')) byId('summaryBirthplace').textContent = document.getElementById('birthplace').value;
        if(byId('summaryMaritalStatus')) byId('summaryMaritalStatus').textContent = document.getElementById('maritalstatus').value;
        if(byId('summaryCTC')) byId('summaryCTC').textContent = document.getElementById('ctcnumber').value;
        if(byId('summaryClaimDate')) byId('summaryClaimDate').textContent = document.getElementById('claimdate').value;
        if(byId('summaryPaymentMethod')) byId('summaryPaymentMethod').textContent = document.getElementById('paymentMethod').value;
        if(byId('summaryPurpose')){
            // show the final purpose value from the hidden input
            byId('summaryPurpose').textContent = purposeHidden.value || '';
        }
    }

    if(purposeSelect){
        purposeSelect.addEventListener('change', togglePurposeOther);
    }

    if(purposeOther){
        purposeOther.addEventListener('input', function(){
            const val = purposeOther.value.trim();
            purposeHidden.value = val || 'Others';
            updateSummary();
        });
    }

    // update summary live when fields change (so when user visits review step it's populated)
    ['lastname','firstname','middlename','street','purok','barangay','municipality','province','birthdate','age','birthplace','maritalstatus','ctcnumber','claimdate'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.addEventListener('input', updateSummary);
    });

    // initial toggle based on prefills (we intentionally keep the "other" input hidden unless user picks Others)
    togglePurposeOther();
    updateSummary();

    // Ensure form submission uses the hidden 'purpose' value
    const form = document.getElementById('barangayClearanceForm');
    if(form){
        form.addEventListener('submit', function(e){
            if(purposeSelect && purposeSelect.value === 'Others' && purposeOther && purposeOther.value.trim()){
                purposeHidden.value = purposeOther.value.trim();
            } else if(purposeSelect && purposeSelect.value){
                purposeHidden.value = purposeSelect.value;
            }
            // continue submitting
        });
    }
});
</script>

<script src="js/serviceBarangayClearance.js"></script>
