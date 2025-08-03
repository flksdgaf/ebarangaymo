<?php
require 'functions/dbconn.php';

// Ensure the user is authenticated.
// if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
//     header("Location: index.php");
//     exit();
// }

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
    $rowID = $resID->fetch_assoc();
    // *** ADDED: pull in existing values
    $fullName       = $rowID['full_name'];
    $userPurok      = $rowID['purok'];          // now fetch purok instead of full address
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

// --- 2) If NEW, get basic info from purok tables (and leave editable fields blank)
if (!$isRenewal) {
    // build the UNION of all purok tables (no per-SELECT LIMIT), now also selecting purok label
    $unionSql = [];
    for ($i = 1; $i <= 6; $i++) {
        $unionSql[] = "
            SELECT full_name, birthdate, civil_status, 'Purok $i' AS purok
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
        $userPurok   = $r['purok'];             // capture purok from the union result
    } else {
        // not found in any purok table
        $fullName    = '';
        $birthdate   = '';
        $civilstatus = '';
        $userPurok   = '';                     // default to empty
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
        <p id="subHeader" class="mb-2">Provide the necessary details to apply for your Barangay ID.</p>
        <hr id="mainHr" class="mb-4">

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

                <!-- PUROK DROPDOWN (prefilled and required) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Purok</label>
                <div class="col-md-8">
                    <select id="purok" name="purok" class="form-control custom-input" required>
                        <option value="">Select Purok</option>
                        <?php
                        $puroks = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6'];
                        foreach ($puroks as $p) {
                            $selected = ($userPurok === $p) ? 'selected' : '';
                            echo "<option value=\"$p\" $selected>$p</option>";
                        }
                        ?>
                    </select>
                </div>
                </div>

                <!-- HEIGHT AND WEIGHT (editable always) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Height & Weight</label>
                <div class="col-md-4">
                    <input type="text" id="height" name="height"
                    class="form-control custom-input" 
                    required placeholder="Height (in feet)" 
                    value="<?= htmlspecialchars($height) ?>">
                </div>
                <div class="col-md-4">
                    <input type="text" id="weight" name="weight" 
                    class="form-control custom-input" required 
                    placeholder="Weight (in kilograms)" 
                    value="<?= htmlspecialchars($weight) ?>">
                </div>
                </div>

                <!-- BIRTHDATE (always readonly) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Birthdate</label>
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
                        required placeholder="City, Province"
                        <?php echo $isRenewal ? 'readonly' : ''; ?>
                        value="<?php echo htmlspecialchars($birthplace); ?>"
                    >
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
                        required placeholder="Enter Religion"
                        value="<?php echo htmlspecialchars($religion); ?>">
                </div>
                </div>

                <!-- CONTACT PERSON (editable always) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Contact Person (in case of emergency)</label>
                <div class="col-md-8">
                    <input type="text" id="contactperson" name="contactperson"
                        class="form-control custom-input"
                        required placeholder="First Name | MI. | Surname"
                        value="<?php echo htmlspecialchars($contactperson); ?>">
                </div>
                </div>

                <!-- CONTACT PERSON ADDRESS (editable) -->
                <div class="row mb-3">
                  <label class="col-md-4 text-start fw-bold">
                    Contact Person Address
                  </label>
                  <div class="col-md-8">
                    <input
                      type="text"
                      id="contactAddress"
                      name="emergency_contact_address"
                      class="form-control custom-input"
                      required
                      placeholder="City, Province"
                      value="<?php
                        // if editing existing request, pre-fill:
                        echo isset($existingRequest['emergency_contact_address'])
                             ? htmlspecialchars($existingRequest['emergency_contact_address'])
                             : '';
                      ?>"
                    >
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
                <label class="col-md-4 text-start fw-bold">Please select preferred claim date</label>
                <div class="col-md-8">
                    <input type="date" id="claimdate" name="claimdate"
                        class="form-control custom-input"
                        required>
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
                        <h5 class="fw-bold text-success mb-2">Barangay ID Fee</h5>
                        <div class="display-6 fw-bold text-dark mb-2">₱100.00</div>
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

                    <!-- Over‑the‑Counter -->
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
                          <li>Once redirected to GCash, send exactly <strong>₱100.00</strong>.</li>
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
                        <li>Scan the code and insert <strong>₱100.00</strong>.</li>
                        <li>Wait for the confirmation and printed receipt.</li>
                        <li>Submit the receipt and claim your Barangay ID.</li>
                        </ol>
                    </div>

                    <div class="payment-instruction d-none" data-method="Over-the-Counter">
                        <ol>
                        <h4 class="fw-bold mb-3 fs-6">HOW TO USE:</h4>
                            <li>Submit your application and save the given <strong>Transaction Number</strong>.</li>
                            <li>Go to the Barangay Treasurer and present your Transaction Number.</li>
                            <li>Pay <strong>₱100.00</strong> in cash.</li>
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
            <div class="step <?php echo $transactionId ? 'completed' : ''; ?>">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-8 col-xl-6">
                <div class="summary-container p-4 rounded shadow-sm border">

                    <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Transaction Type:</span>
                        <span class="text-success" id="summarytransactionType"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Full Name:</span>
                        <span class="text-success" id="summaryFullName"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Purok:</span>
                        <span class="text-success" id="summaryPurok"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Height:</span>
                        <span class="text-success" id="summaryHeight"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Weight:</span>
                        <span class="text-success" id="summaryWeight"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Birthdate:</span>
                        <span class="text-success" id="summaryBirthdate"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Birthplace:</span>
                        <span class="text-success" id="summaryBirthplace"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Civil Status:</span>
                        <span class="text-success" id="summaryCivilStatus"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Religion:</span>
                        <span class="text-success" id="summaryReligion"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Contact Person:</span>
                        <span class="text-success" id="summaryContactPerson"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Contact Person Address:</span>
                        <span class="text-success" id="summaryContactAddress"></span>
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

