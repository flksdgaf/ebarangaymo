<?php
require 'functions/dbconn.php';

// require session if not already started (safe to ensure)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

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

// Defaults (kept if needed server-side)
$defaultBarangay = 'Magang';
$defaultMunicipality = 'Daet';
$defaultProvince = 'Camarines Norte';

/**
 * Business-day generator (same behavior as your Business Clearance reference)
 */
function getNextBusinessDays($fromDate, $count = 3) {
    $results = [];
    $d = clone $fromDate;

    $weekdayNow = (int)$d->format('N'); // 1=Mon .. 7=Sun
    if ($weekdayNow === 6) {
        $d->modify('+2 days');
    } elseif ($weekdayNow === 7) {
        $d->modify('+1 day');
    } else {
        $d->modify('+1 day');
    }

    while (count($results) < $count) {
        $weekday = (int)$d->format('N'); // 1..7
        if ($weekday <= 5) {
            $results[] = clone $d;
        }
        $d->modify('+1 day');
    }
    return $results;
}

// Build claim options to render
$today = new DateTime('now', new DateTimeZone('Asia/Manila'));
$businessDays = getNextBusinessDays($today, 3);

$claimOptions = [];
foreach ($businessDays as $bd) {
    $dateStr = $bd->format('Y-m-d');
    $label = $bd->format('F j, Y');
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

if (!empty($existingRequest)) {
    if (!empty($existingRequest['claim_date'])) {
        $raw = $existingRequest['claim_date'];
        if (strpos($raw, '|') !== false) {
            [$d, $p] = explode('|', $raw, 2);
            $existingClaimDate = $d;
            $existingClaimPart = $p;
        } else {
            $existingClaimDate = $raw;
            if (!empty($existingRequest['claim_time'])) {
                $existingClaimPart = $existingRequest['claim_time'];
            } else {
                $existingClaimPart = 'Morning';
            }
        }
    } else {
        if (!empty($existingRequest['claim_time'])) {
            $existingClaimPart = $existingRequest['claim_time'];
        }
        if (!empty($existingRequest['claim_date'])) {
            $existingClaimDate = $existingRequest['claim_date'];
        }
    }
}
?>
<?php
// Payment Status Messages (grab before any HTML output)
$paymentSuccess = $_SESSION['payment_success'] ?? '';
$paymentError = $_SESSION['payment_error'] ?? '';
$paymentFailed = $_SESSION['payment_failed'] ?? '';
unset($_SESSION['payment_success'], $_SESSION['payment_error'], $_SESSION['payment_failed']);
?>
<link rel="stylesheet" href="serviceBarangayClearance.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
/* claim styles kept as before (unchanged except scoping) */
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
.claim-date-label { font-weight:600; }
.claim-time { font-size: .95rem; color: #6b7280; }
.claim-grid .date-label { font-weight:700; margin-bottom: .35rem; }

/* rest of claim-card CSS unchanged */
.claim-card {
    border: 1px solid #e9ecef;
    background: #ffffff;
    border-radius: 0.5rem;
    padding: 0.75rem;
    transition: box-shadow .12s ease, transform .08s ease, border-color .12s ease, outline .08s ease;
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    min-height: 64px;
    cursor: pointer;
    outline: none;
}
.claim-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,.04); transform: translateY(-1px); border-color: #cfe8d8; }
.claim-card:focus-within { outline: 3px solid rgba(25,135,84,0.12); outline-offset: 2px; border-color: #b8e0c9; }
.claim-card.outlined { outline: 2px solid rgba(0,0,0,0.08); outline-offset: 2px; }
.claim-card.invalid { border-color: #dc3545; box-shadow: none; background: #fff5f5; outline: 2px solid rgba(220,53,69,0.08); outline-offset: 2px; }

@media (max-width: 575.98px) {
    .claim-grid .col-sm-6 { flex: 0 0 100%; max-width: 100%; }
}

/* Camera Modal Styles */
#cameraStream {
    max-height: 400px;
    object-fit: cover;
    background: #000;
}

#photoCanvas {
    max-height: 400px;
    max-width: 400px;
    border: 2px solid #198754;
    aspect-ratio: 1 / 1;
}

#cameraView button, #previewView button {
    min-width: 140px;
}

.material-symbols-outlined {
    vertical-align: middle;
    font-size: 20px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<title>eBarangay Mo | Barangay Clearance</title>

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
        <!-- Payment Status Messages -->
        <?php if ($paymentSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong><span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> Success!</strong>
                <?php echo htmlspecialchars($paymentSuccess); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif;
        
        if ($paymentError): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><span class="material-symbols-outlined" style="vertical-align: middle;">error</span> Error!</strong>
                <?php echo htmlspecialchars($paymentError); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif;
        
        if ($paymentFailed): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong><span class="material-symbols-outlined" style="vertical-align: middle;">warning</span> Payment Cancelled</strong>
                <?php echo htmlspecialchars($paymentFailed); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex align-items-center position-relative mb-3">
            <!-- Back Button - Only shown on step 1 -->
            <button type="button" id="backToServicesBtn" class="btn btn-link text-success position-absolute start-0" style="display: none;">
                <span class="material-symbols-outlined">chevron_left</span>
            </button>
            
            <div class="flex-grow-1">
                <h2 class="mb-1 text-success fw-bold" id="mainHeader"></h2>
            </div>
        </div>
        <p id="subHeader" class="mb-2">Provide the necessary details to request a Barangay Clearance.</p>
        <hr id="mainHr" class="mb-4">

        <!-- add class needs-claim so CSS validation hook works like the Business file -->
        <form id="barangayClearanceForm" action="functions/serviceBarangayClearance_submit.php" method="POST" enctype="multipart/form-data" class="needs-claim">
            <!-- Step 1: Application Form -->
            <div class="step <?php echo $transactionId ? 'completed' : 'active-step'; ?>">

                <!-- FULL NAME (single field: First Middle Surname) -->
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Full Name</label>
                    <div class="col-md-8">
                        <input type="text" id="fullname" name="fullname" disabled
                            class="form-control custom-input"
                            readonly
                            value="<?php echo htmlspecialchars($fullName); ?>"
                            placeholder="First Middle Surname">
                    </div>
                </div>

                <!-- STREET (optional) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Street <span class="small text-muted">(Optional)</span></label>
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
                    <input type="text" id="purok" name="purok" disabled
                        class="form-control custom-input"
                        readonly
                        required
                        value="<?php echo htmlspecialchars($userPurok); ?>"
                        placeholder="Purok">
                </div>
                </div>

                <!-- BIRTHDATE & AGE -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Birthdate</label>
                <div class="col-md-8">
                    <div class="row g-4">
                        <div class="col-md-7">
                            <input type="date" id="birthdate" name="birthdate"
                                class="form-control custom-input" disabled
                                readonly
                                required
                                value="<?php echo (!empty($birthdate) && $birthdate !== '0000-00-00') ? date('Y-m-d', strtotime($birthdate)) : ($existingRequest['birthdate'] ?? ''); ?>">
                        </div>
                        <label class="col-md-1 text-start fw-bold">Age</label>
                        <div class="col-md-4">
                            <input type="number" id="age" name="age" disabled
                                class="form-control custom-input"
                                readonly
                                placeholder="Age"
                                    value="<?php echo htmlspecialchars($age ?: ($existingRequest['age'] ?? '')); ?>">
                        </div>
                    </div>
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

                <!-- CIVIL STATUS -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Civil Status</label>
                <div class="col-md-8">
                    <select id="maritalstatus" name="marital_status" class="form-control custom-input" required>
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

                <!-- PURPOSE (select + hidden final input) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Purpose</label>
                <div class="col-md-8">
                    <?php
                    $purposes = ['Medical Assistance','Employment','School Enrollment','Passport','Scholarship','4Ps Application','Others'];
                    $existingPurpose = $existingRequest['purpose'] ?? '';
                    $is_prefilled_in_list = in_array($existingPurpose, $purposes, true);
                    $prefill_other_value = $is_prefilled_in_list ? '' : $existingPurpose;
                    ?>
                    <select id="purposeSelect" name="purpose_select" class="form-control custom-input" required>
                        <?php
                        foreach ($purposes as $p) {
                            $sel = ($is_prefilled_in_list && $existingPurpose === $p) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($p) . "\" $sel>" . htmlspecialchars($p) . "</option>";
                        }
                        ?>
                    </select>

                    <input type="text" id="purposeOther" name="purpose_other"
                        class="form-control custom-input mt-2 d-none"
                        placeholder="Please specify purpose"
                        value="<?php echo htmlspecialchars($prefill_other_value); ?>">

                    <input type="hidden" id="purposeHidden" name="purpose" value="<?php echo htmlspecialchars($is_prefilled_in_list ? $existingPurpose : $prefill_other_value); ?>">
                </div>
                </div>

                <!-- OPTIONAL: Picture (not required unless you want) -->
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Formal Picture <span class="small text-muted">(Optional)</span></label>
                    <div class="col-md-8">
                        <div class="d-flex gap-2 align-items-start flex-wrap">
                            <!-- Hidden file input -->
                            <input type="file" id="picture" name="picture"
                                class="form-control custom-input d-none"
                                accept="image/*">
                            
                            <!-- Camera Button -->
                            <button type="button" id="openCameraBtn" class="btn btn-outline-success">
                                <span class="material-symbols-outlined">photo_camera</span> Take Photo
                            </button>
                            
                            <!-- Upload Button -->
                            <!-- <button type="button" id="uploadFileBtn" class="btn btn-outline-primary">
                                <span class="material-symbols-outlined">upload_file</span> Upload File
                            </button> -->
                            
                            <!-- Preview Container -->
                            <div id="photoPreviewContainer" class="w-100 mt-2 d-none">
                                <img id="photoPreview" src="" alt="Photo Preview" class="img-thumbnail" style="max-width: 200px;">
                                <p class="text-success small mt-1 mb-0">
                                    <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">check_circle</span>
                                    Photo ready for upload
                                </p>
                            </div>
                        </div>
                        <small class="form-text text-muted mt-1 d-block">
                            Please ensure the picture is a recent/updated photo – clear, front-facing, with a plain background and no heavy filters.
                        </small>
                    </div>
                </div>

                <!-- CLAIM DATE: two-column layout (morning / afternoon) -->
                <div class="row mb-1">
                <label class="col-md-4 text-start fw-bold">Claim Date</label>
                <div class="col-md-8 p-3">
                    <div id="claimOptionsGroup" class="claim-list claim-grid">
                        <?php
                        foreach ($claimOptions as $coIndex => $co) {
                            $date = $co['date'];
                            $dateLabel = $co['label'];
                            $idMorning = "claim_{$coIndex}_morning";
                            $valMorning = $date . '|Morning';
                            $checkedMorning = ($existingClaimDate === $date && $existingClaimPart === 'Morning') ? 'checked' : '';
                            $idAfternoon = "claim_{$coIndex}_afternoon";
                            $valAfternoon = $date . '|Afternoon';
                            $checkedAfternoon = ($existingClaimDate === $date && $existingClaimPart === 'Afternoon') ? 'checked' : '';
                            ?>
                            <div class="date-row">
                                <div class="row gx-2">
                                    <div class="col-sm-6 mb-2">
                                        <label class="list-group-item list-group-item-action p-2 claim-card d-flex align-items-start <?php echo $checkedMorning ? 'active' : ''; ?>" for="<?php echo $idMorning; ?>" role="option" aria-pressed="<?php echo $checkedMorning ? 'true' : 'false'; ?>">
                                            <div class="form-check me-2">
                                                <input class="form-check-input" type="radio" name="claim_slot" id="<?php echo $idMorning; ?>" value="<?php echo htmlspecialchars($valMorning); ?>" data-date="<?php echo $date; ?>" data-part="Morning" <?php echo $checkedMorning; ?> <?php echo $coIndex === 0 ? 'required' : ''; ?>>
                                            </div>
                                            <div>
                                                <div class="claim-date-label"><?php echo htmlspecialchars($dateLabel); ?></div>
                                                <div class="claim-time"><?php echo htmlspecialchars($co['parts'][0]['label']); ?></div>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="col-sm-6 mb-2">
                                        <label class="list-group-item list-group-item-action p-2 claim-card d-flex align-items-start <?php echo $checkedAfternoon ? 'active' : ''; ?>" for="<?php echo $idAfternoon; ?>" role="option" aria-pressed="<?php echo $checkedAfternoon ? 'true' : 'false'; ?>">
                                            <div class="form-check me-2">
                                                <input class="form-check-input" type="radio" name="claim_slot" id="<?php echo $idAfternoon; ?>" value="<?php echo htmlspecialchars($valAfternoon); ?>" data-date="<?php echo $date; ?>" data-part="Afternoon" <?php echo $checkedAfternoon; ?> <?php echo $coIndex === 0 ? 'required' : ''; ?>>
                                            </div>
                                            <div>
                                                <div class="claim-date-label"><?php echo htmlspecialchars($dateLabel); ?></div>
                                                <div class="claim-time"><?php echo htmlspecialchars($co['parts'][1]['label']); ?></div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                        ?>
                    </div>

                    <!-- Hidden inputs that will actually be submitted -->
                    <input type="hidden" id="hiddenClaimDate" name="claim_date" value="<?php echo htmlspecialchars($existingClaimDate); ?>">
                    <input type="hidden" id="hiddenClaimTime" name="claim_time" value="<?php echo htmlspecialchars($existingClaimPart); ?>">
                </div>
                </div>

                    <!-- NEW MESSAGE: Right Thumb Mark processing note -->
                    <div class="text-center">
                        <em class="small text-muted">Note: Your <b>Right Thumb Mark</b> will be processed personally onto your printed Barangay Clearance during issuance.</em>
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
                    <button type="button" class="btn btn-outline-success payment-btn" data-method="GCash">
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
                        <span class="fw-bold">Full Name:</span>
                        <span class="text-success" id="summaryFullName">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Street:</span>
                        <span class="text-success" id="summaryStreet">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Purok:</span>
                        <span class="text-success" id="summaryPurok">-</span>
                    </li>

                    <!-- Removed Barangay / Municipality / Province summary as requested -->

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Birthdate / Age:</span>
                        <span class="text-success" id="summaryBirthAge">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Birthplace:</span>
                        <span class="text-success" id="summaryBirthplace">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Civil Status:</span>
                        <span class="text-success" id="summaryMaritalStatus">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">CTC Number:</span>
                        <span class="text-success" id="summaryCTC">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Purpose:</span>
                        <span class="text-success" id="summaryPurpose">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Claim Date:</span>
                        <span class="text-success" id="summaryClaimDate">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Payment Method:</span>
                        <span class="text-success" id="summaryPaymentMethod">-</span>
                    </li>
                    </ul>

                    <div class="mt-2 small text-muted">
                        <em>Note: Your Right Thumb Mark will be processed personally at the barangay upon claim.</em>
                    </div>
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

    <!-- Camera Modal -->
    <div class="modal fade" id="cameraModal" tabindex="-1" aria-labelledby="cameraModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cameraModalLabel">Take Your Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <!-- Camera View -->
                    <div id="cameraView" class="position-relative">
                        <video id="cameraStream" autoplay playsinline class="w-100 rounded"></video>
                        <button type="button" id="captureBtn" class="btn btn-success btn-lg mt-3">
                            <span class="material-symbols-outlined">photo_camera</span> Capture Photo
                        </button>
                    </div>
                    
                    <!-- Preview View (hidden initially) -->
                    <div id="previewView" class="d-none">
                        <canvas id="photoCanvas" class="w-100 rounded"></canvas>
                        <div class="mt-3 d-flex gap-2 justify-content-center">
                            <button type="button" id="retakeBtn" class="btn btn-warning">
                                <span class="material-symbols-outlined">refresh</span> Retake
                            </button>
                            <button type="button" id="uploadPhotoBtn" class="btn btn-success">
                                <span class="material-symbols-outlined">check_circle</span> Use This Photo
                            </button>
                        </div>
                    </div>
                    
                    <!-- Error Message -->
                    <div id="cameraError" class="alert alert-danger d-none mt-3" role="alert"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Handle step parameter from URL (for GCash returns)
$urlStep = isset($_GET['step']) ? intval($_GET['step']) : null;
$initial = 1; // Default to step 1

if ($transactionId) {
    // If there's a URL step parameter, use it (for GCash redirect returns)
    if ($urlStep && $urlStep >= 1 && $urlStep <= 4) {
        $initial = $urlStep;
    } else {
        // No step parameter: default to final step (submission complete)
        $initial = 4;
    }
} 
?>
<script>
    window.initialStep = <?php echo $initial; ?>;
    window._claimOptions = <?php echo json_encode($claimOptions, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
    window._existingClaimObj = <?php echo json_encode(['date' => $existingClaimDate, 'part' => $existingClaimPart], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Claim UI wiring (copied/adapted)
    const claimGroup = document.getElementById('claimOptionsGroup');
    const hiddenDate = document.getElementById('hiddenClaimDate');
    const hiddenTime = document.getElementById('hiddenClaimTime');
    const summaryEl = document.getElementById('summaryClaimDate');

    function clearActiveCards() {
        if (!claimGroup) return;
        claimGroup.querySelectorAll('.claim-card').forEach(function(card){
            card.classList.remove('active');
        });
    }

    function setHiddenValues(date, part) {
        if (hiddenDate) hiddenDate.value = date || '';
        if (hiddenTime) hiddenTime.value = part || '';
    }

    function updateSummaryFriendly(date, part) {
        if (!summaryEl) return;

        // friendly label lookup
        let friendlyDate = date;
        if (window._claimOptions) {
            for (const co of window._claimOptions) {
                if (co.date === date) {
                    friendlyDate = co.label;
                    for (const p of co.parts) {
                        if (p.key === part) {
                            summaryEl.textContent = friendlyDate + ' - ' + p.label;
                            return;
                        }
                    }
                }
            }
        }
        summaryEl.textContent = (date ? date : '-') + (part ? ' - ' + part : '');
    }

    if (claimGroup) {
        claimGroup.addEventListener('change', function (e) {
            const checked = claimGroup.querySelector('input[name="claim_slot"]:checked');
            clearActiveCards();
            if (checked) {
                const parentLabel = checked.closest('label');
                if (parentLabel) parentLabel.classList.add('active');

                const date = checked.dataset.date || '';
                const part = checked.dataset.part || '';
                setHiddenValues(date, part);
                updateSummaryFriendly(date, part);
            }
        });

        if (window._existingClaimObj && window._existingClaimObj.date) {
            const targetRadio = claimGroup.querySelector('input[name="claim_slot"][data-date="' + window._existingClaimObj.date + '"][data-part="' + window._existingClaimObj.part + '"]');
            if (targetRadio) {
                targetRadio.checked = true;
                const evt = new Event('change', { bubbles: true });
                targetRadio.dispatchEvent(evt);
            } else {
                const fallbackRadio = claimGroup.querySelector('input[name="claim_slot"][data-date="' + window._existingClaimObj.date + '"]');
                if (fallbackRadio) {
                    fallbackRadio.checked = true;
                    const evt = new Event('change', { bubbles: true });
                    fallbackRadio.dispatchEvent(evt);
                }
            }
        } else {
            const firstRadio = claimGroup.querySelector('input[name="claim_slot"]');
            if (firstRadio && !claimGroup.querySelector('input[name="claim_slot"]:checked')) {
                firstRadio.checked = true;
                firstRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        claimGroup.querySelectorAll('label').forEach(function(lbl) {
            lbl.addEventListener('click', function(){
                const input = lbl.querySelector('input[type="radio"]');
                if (input && !input.checked) {
                    input.checked = true;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    input && input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });
    } else {
        const preD = hiddenDate ? hiddenDate.value : '';
        const preP = hiddenTime ? hiddenTime.value : '';
        if (preD) updateSummaryFriendly(preD, preP);
    }

    if (hiddenDate && hiddenTime) {
        let lastD = hiddenDate.value;
        let lastP = hiddenTime.value;
        setInterval(function(){
            if (hiddenDate.value !== lastD || hiddenTime.value !== lastP) {
                lastD = hiddenDate.value;
                lastP = hiddenTime.value;
                updateSummaryFriendly(lastD, lastP);
            }
        }, 500);
    }
});
</script>

<script>
/* small inline helper to show/hide the "other" purpose input and keep summary in sync,
   and updated summary logic to display '-' when a field is empty.
*/
document.addEventListener('DOMContentLoaded', function(){
    const purposeSelect = document.getElementById('purposeSelect');
    const purposeOther = document.getElementById('purposeOther');
    const purposeHidden = document.getElementById('purposeHidden'); // final value submitted as 'purpose'

    function togglePurposeOther(){
        if(!purposeSelect) return;
        if(purposeSelect.value === 'Others'){
            purposeOther.classList.remove('d-none');
            purposeOther.required = true;
            if(purposeOther.value.trim()) purposeHidden.value = purposeOther.value.trim();
            else purposeHidden.value = 'Others';
        } else {
            purposeOther.classList.add('d-none');
            purposeOther.required = false;
            if(purposeSelect.value) purposeHidden.value = purposeSelect.value;
        }
        updateSummary();
    }

    function displayVal(v) {
        if (typeof v === 'undefined' || v === null) return '-';
        const s = ('' + v).trim();
        return s === '' ? '-' : s;
    }

    function updateSummary(){
        const byId = id => document.getElementById(id);
        // Full name (single field)
        if(byId('summaryFullName')) byId('summaryFullName').textContent = displayVal(document.getElementById('fullname') ? document.getElementById('fullname').value : '');

        if(byId('summaryStreet')) byId('summaryStreet').textContent = displayVal(document.getElementById('street') ? document.getElementById('street').value : '');
        if(byId('summaryPurok')) byId('summaryPurok').textContent = displayVal(document.getElementById('purok') ? document.getElementById('purok').value : '');
        // Birthdate / Age combination
        const bd = document.getElementById('birthdate') ? document.getElementById('birthdate').value : '';
        const ag = document.getElementById('age') ? document.getElementById('age').value : '';
        let birthAgeText = '-';
        if (bd && ag) birthAgeText = bd + ' / ' + ag;
        else if (bd) birthAgeText = bd;
        else if (ag) birthAgeText = ag;
        if(byId('summaryBirthAge')) byId('summaryBirthAge').textContent = displayVal(birthAgeText);

        if(byId('summaryBirthplace')) byId('summaryBirthplace').textContent = displayVal(document.getElementById('birthplace') ? document.getElementById('birthplace').value : '');
        if(byId('summaryMaritalStatus')) byId('summaryMaritalStatus').textContent = displayVal(document.getElementById('maritalstatus') ? document.getElementById('maritalstatus').value : '');
        if(byId('summaryCTC')) byId('summaryCTC').textContent = displayVal(document.getElementById('ctcnumber') ? document.getElementById('ctcnumber').value : '');

        // Claim date summary - prefer friendly label from claim hidden inputs
        const hiddenDate = document.getElementById('hiddenClaimDate');
        const hiddenTime = document.getElementById('hiddenClaimTime');
        if (byId('summaryClaimDate')) {
            if (hiddenDate && hiddenDate.value) {
                let friendlyRendered = false;
                if (window._claimOptions) {
                    for (const co of window._claimOptions) {
                        if (co.date === hiddenDate.value) {
                            const partKey = hiddenTime && hiddenTime.value ? hiddenTime.value : null;
                            if (partKey) {
                                const found = Array.isArray(co.parts) ? co.parts.find(p => p.key === partKey) : null;
                                if (found) {
                                    byId('summaryClaimDate').textContent = co.label + ' - ' + found.label;
                                } else {
                                    byId('summaryClaimDate').textContent = co.label + (partKey ? ' - ' + partKey : '');
                                }
                            } else {
                                byId('summaryClaimDate').textContent = co.label;
                            }
                            friendlyRendered = true;
                            break;
                        }
                    }
                }
                if (!friendlyRendered) {
                    const d = hiddenDate.value;
                    const p = hiddenTime && hiddenTime.value ? hiddenTime.value : '';
                    byId('summaryClaimDate').textContent = displayVal(d + (p ? ' - ' + p : ''));
                }
            } else {
                byId('summaryClaimDate').textContent = '-';
            }
        }

        if(byId('summaryPaymentMethod')) byId('summaryPaymentMethod').textContent = displayVal(document.getElementById('paymentMethod') ? document.getElementById('paymentMethod').value : '');
        if(byId('summaryPurpose')) byId('summaryPurpose').textContent = displayVal(purposeHidden ? purposeHidden.value : '');
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

    // update summary live when fields change
    ['fullname','street','purok','birthdate','age','birthplace','maritalstatus','ctcnumber'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.addEventListener('input', updateSummary);
    });

    // also update when hidden claim fields change (these are updated by claim JS)
    const hiddenClaimDateEl = document.getElementById('hiddenClaimDate');
    const hiddenClaimTimeEl = document.getElementById('hiddenClaimTime');
    if (hiddenClaimDateEl) hiddenClaimDateEl.addEventListener('change', updateSummary);
    if (hiddenClaimTimeEl) hiddenClaimTimeEl.addEventListener('change', updateSummary);

    // initial run
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
        });
    }
});

// Camera functionality
document.addEventListener('DOMContentLoaded', function() {
    const openCameraBtn = document.getElementById('openCameraBtn');
    const uploadFileBtn = document.getElementById('uploadFileBtn');
    const fileInput = document.getElementById('picture');
    const cameraModalElement = document.getElementById('cameraModal');
    const cameraStream = document.getElementById('cameraStream');
    const cameraView = document.getElementById('cameraView');
    const previewView = document.getElementById('previewView');
    const photoCanvas = document.getElementById('photoCanvas');
    const cameraError = document.getElementById('cameraError');
    const captureBtn = document.getElementById('captureBtn');
    const retakeBtn = document.getElementById('retakeBtn');
    const uploadPhotoBtn = document.getElementById('uploadPhotoBtn');
    const photoPreview = document.getElementById('photoPreview');
    const photoPreviewContainer = document.getElementById('photoPreviewContainer');
    
    let stream = null;
    let cameraModal = null;

    function initModal() {
        if (!cameraModal && cameraModalElement) {
            cameraModal = new bootstrap.Modal(cameraModalElement);
        }
        return cameraModal;
    }

    if (openCameraBtn) {
        openCameraBtn.addEventListener('click', async function() {
            const modal = initModal();
            if (!modal) return;
            
            modal.show();
            cameraView.classList.remove('d-none');
            previewView.classList.add('d-none');
            cameraError.classList.add('d-none');
            
            try {
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'user', width: 1280, height: 720 } 
                });
                cameraStream.srcObject = stream;
            } catch (err) {
                cameraError.textContent = 'Unable to access camera. Please check permissions or use the upload option.';
                cameraError.classList.remove('d-none');
                console.error('Camera error:', err);
            }
        });
    }

    if (captureBtn) {
        captureBtn.addEventListener('click', function() {
            const context = photoCanvas.getContext('2d');
            
            // Get video dimensions
            const videoWidth = cameraStream.videoWidth;
            const videoHeight = cameraStream.videoHeight;
            
            // Calculate square crop (use the smaller dimension)
            const minDimension = Math.min(videoWidth, videoHeight);
            
            // Calculate center crop coordinates
            const cropX = (videoWidth - minDimension) / 2;
            const cropY = (videoHeight - minDimension) / 2;
            
            // Set canvas to square (800x800 for good quality)
            const outputSize = 800;
            photoCanvas.width = outputSize;
            photoCanvas.height = outputSize;
            
            // Draw the center-cropped square portion of the video
            context.drawImage(
                cameraStream,
                cropX, cropY,
                minDimension, minDimension,
                0, 0,
                outputSize, outputSize
            );
            
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
            
            cameraView.classList.add('d-none');
            previewView.classList.remove('d-none');
        });
    }

    if (retakeBtn) {
        retakeBtn.addEventListener('click', async function() {
            previewView.classList.add('d-none');
            cameraView.classList.remove('d-none');
            
            try {
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'user', width: 1280, height: 720 } 
                });
                cameraStream.srcObject = stream;
            } catch (err) {
                cameraError.textContent = 'Unable to restart camera.';
                cameraError.classList.remove('d-none');
            }
        });
    }

    if (uploadPhotoBtn) {
        uploadPhotoBtn.addEventListener('click', function() {
            photoCanvas.toBlob(function(blob) {
                const file = new File([blob], 'camera-photo.jpg', { type: 'image/jpeg' });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;
                
                photoPreview.src = URL.createObjectURL(blob);
                photoPreviewContainer.classList.remove('d-none');
                
                if (cameraModal) {
                    cameraModal.hide();
                }
            }, 'image/jpeg', 0.9);
        });
    }

    if (uploadFileBtn) {
        uploadFileBtn.addEventListener('click', function() {
            fileInput.click();
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    photoPreview.src = event.target.result;
                    photoPreviewContainer.classList.remove('d-none');
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    }

    if (cameraModalElement) {
        cameraModalElement.addEventListener('hidden.bs.modal', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            if (cameraStream) {
                cameraStream.srcObject = null;
            }
        });
    }
});
</script>

<script>
// Back button functionality for Barangay Clearance
document.addEventListener('DOMContentLoaded', function() {
    const backToServicesBtn = document.getElementById('backToServicesBtn');
    
    if (backToServicesBtn) {
        // Show back button only on step 1 (Application Form)
        function toggleBackButton() {
            // Get all step divs
            const steps = document.querySelectorAll('.step');
            let currentStepIndex = -1;
            
            // Find which step is currently active
            steps.forEach((step, index) => {
                if (step.classList.contains('active-step')) {
                    currentStepIndex = index;
                }
            });
            
            // Show back button only on first step (index 0) and when not viewing a transaction
            if (currentStepIndex === 0 && !<?php echo $transactionId ? 'true' : 'false'; ?>) {
                backToServicesBtn.style.display = 'block';
            } else {
                backToServicesBtn.style.display = 'none';
            }
        }
        
        // Initial check
        toggleBackButton();
        
        // Listen for step changes to show/hide back button
        const observer = new MutationObserver(toggleBackButton);
        const steps = document.querySelectorAll('.step');
        steps.forEach(step => {
            observer.observe(step, { attributes: true, attributeFilter: ['class'] });
        });
        
        // Handle back button click
        backToServicesBtn.addEventListener('click', function() {
            window.location.href = 'userPanel.php?page=userServices';
        });
    }
});
</script>

<script src="js/serviceBarangayClearance.js"></script>
