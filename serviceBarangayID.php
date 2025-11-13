<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'functions/dbconn.php';

// Ensure the user is authenticated.
// if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
//     header("Location: index.php");
//     exit();
// }

// -- keep same behavior as before (session assumed started elsewhere)
$userId = $_SESSION['loggedInUserID'];
$transactionId = $_GET['tid'] ?? null;
$t = $transactionId ? true : false;

date_default_timezone_set('Asia/Manila'); // added for claim-date handling

// --- 1) Check for existing Brgy-ID record: Renewal vs. New Application
$stmtID = $conn->prepare("
    SELECT *  
    FROM barangay_id_requests  
    WHERE account_id = ? 
    AND document_status = 'Released'
    ORDER BY created_at DESC
    LIMIT 1
");
$stmtID->bind_param("i", $userId);
$stmtID->execute();
$resID = $stmtID->get_result();

$isRenewal = false;
$currentValidUntil = null;
$isExpiredOrExpiring = false;

if ($resID && $resID->num_rows === 1) {
    $rowID = $resID->fetch_assoc();
    $currentValidUntil = $rowID['valid_until'];
    
    // Check if ID is expired or will expire within 30 days
    if ($currentValidUntil) {
        $validUntilDate = new DateTime($currentValidUntil);
        $today = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $daysUntilExpiry = $today->diff($validUntilDate)->days;
        $isExpired = $validUntilDate < $today;
        
        // Set as renewal if expired or expiring within 30 days
        if ($isExpired || $daysUntilExpiry <= 30) {
            $isExpiredOrExpiring = true;
            $isRenewal = true;
        }
    }
    
    // If it's a renewal, pull existing values
    if ($isRenewal) {
        $fullName       = $rowID['full_name'];
        $userPurok      = $rowID['purok'];
        $height         = $rowID['height'];
        $weight         = $rowID['weight'];
        $birthdate      = $rowID['birth_date'];
        $birthplace     = $rowID['birth_place'];
        $civilstatus    = $rowID['civil_status'];
        $religion       = $rowID['religion'];
        $contactperson  = $rowID['emergency_contact_person'];
        $emergencyAddress = $rowID['emergency_contact_address'];
        $formal_picture = $rowID['formal_picture'];
    }
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

// --- Fetch existing request (if transaction provided) so we can prefill claim_date/claim_time and payment
$existingRequest = [];
$chosenPayment = null;
if ($transactionId) {
    $stmt = $conn->prepare("
      SELECT *  
       FROM barangay_id_requests
       WHERE transaction_id = ?  
       LIMIT 1
    ");
    $stmt->bind_param("s", $transactionId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows) {
        $existingRequest = $res->fetch_assoc();
        $chosenPayment = $existingRequest['payment_method'] ?? null;
    }
    $stmt->close();
}

// --- Prefill optional valid ID number from existing request (NEW: maps to valid_id_number column)
$validIdNumber = $existingRequest['valid_id_number'] ?? '';

/**
 * Business-day generator
 *
 * Behavior:
 *  - If the request is made on Saturday or Sunday, the claim options START from next Monday.
 *  - Otherwise, claim options start from tomorrow, but only business days (Mon-Fri) are included.
 *  - Returns DateTime objects for the next $count business days.
 */
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

// build claim options (3 business days)
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

if (!empty($existingRequest)) {
    // Prefer separate columns if available
    if (!empty($existingRequest['claim_date'])) {
        $raw = $existingRequest['claim_date'];
        if (strpos($raw, '|') !== false) {
            // legacy format: "YYYY-MM-DD|Morning"
            [$d, $p] = explode('|', $raw, 2);
            $existingClaimDate = $d;
            $existingClaimPart = $p;
        } else {
            // date-only stored here — use claim_time column if present
            $existingClaimDate = $raw;
            if (!empty($existingRequest['claim_time'])) {
                $existingClaimPart = $existingRequest['claim_time'];
            } else {
                // default to Morning when only date present
                $existingClaimPart = 'Morning';
            }
        }
    } else {
        // No claim_date value stored; check separate fields (rare)
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
// Payment Status Messages
$paymentSuccess = $_SESSION['payment_success'] ?? '';
$paymentError = $_SESSION['payment_error'] ?? '';
$paymentFailed = $_SESSION['payment_failed'] ?? '';
unset($_SESSION['payment_success'], $_SESSION['payment_error'], $_SESSION['payment_failed']);
?>
<link rel="stylesheet" href="serviceBarangayID.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<title>eBarangay Mo | Barangay ID</title>

<!-- Minimal claim-specific styles (kept small & scoped) -->
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

    /* hover */
    .claim-card:hover {
        box-shadow: 0 6px 18px rgba(0,0,0,.04);
        transform: translateY(-1px);
        border-color: #cfe8d8;
    }

    /* keyboard & internal focus (when input inside gets focus) */
    .claim-card:focus-within {
        outline: 3px solid rgba(25,135,84,0.12); /* subtle green focus ring */
        outline-offset: 2px;
        border-color: #b8e0c9;
    }

    /* active / selected look */
    .claim-card.active {
        border-color: #198754; /* green */
        box-shadow: 0 8px 24px rgba(25,135,84,0.06);
        background: #fbfffb;
        transform: translateY(-2px);
    }

    @media (max-width: 575.98px) {
        .claim-grid .col-sm-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }
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

<div class="container px-3">
    <!-- Payment Status Messages -->
    <?php if ($paymentSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong><span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> Success!</strong>
            <?php echo htmlspecialchars($paymentSuccess); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($paymentError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong><span class="material-symbols-outlined" style="vertical-align: middle;">error</span> Error!</strong>
            <?php echo htmlspecialchars($paymentError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($paymentFailed): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong><span class="material-symbols-outlined" style="vertical-align: middle;">warning</span> Payment Cancelled</strong>
            <?php echo htmlspecialchars($paymentFailed); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

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

    <div class="card shadow-sm px-5 py-5 mb-5">
        <div class="d-flex align-items-center position-relative mb-3">
            <!-- Back Button - Only shown on step 1 -->
            <button type="button" id="backToServicesBtn" class="btn btn-link text-success position-absolute start-0" style="display: none;">
                <span class="material-symbols-outlined">chevron_left</span>
            </button>
            
            <div class="flex-grow-1">
                <h2 class="mb-1 text-success fw-bold" id="mainHeader"></h2>
            </div>
        </div>
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

                <!-- ADD THIS NEW SECTION -->
                <?php if ($isRenewal && $currentValidUntil): ?>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Current ID Validity</label>
                    <div class="col-md-8">
                        <div class="alert alert-<?php echo $isExpiredOrExpiring ? 'warning' : 'info'; ?> mb-0">
                            <strong>Valid Until:</strong> <?php echo date('F j, Y', strtotime($currentValidUntil)); ?>
                            <?php if ($isExpiredOrExpiring): ?>
                                <br><small class="text-danger">Your ID has expired or is expiring soon. Please proceed with renewal.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- FULL NAME (always readonly) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Full Name</label>
                <div class="col-md-8">
                    <input type="text" id="fullname_display" 
                        class="form-control custom-input"
                        disabled
                        value="<?php echo htmlspecialchars($fullName); ?>">
                    <!-- Hidden input to actually submit the value -->
                    <input type="hidden" id="fullname" name="fullname" value="<?php echo htmlspecialchars($fullName); ?>">
                </div>
                </div>

                <!-- PUROK (readonly - fetched from database) -->
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Purok</label>
                    <div class="col-md-8">
                        <input type="text" id="purok_display" 
                            class="form-control custom-input" 
                            disabled 
                            value="<?php echo htmlspecialchars($userPurok); ?>">
                        <!-- Hidden input to actually submit the value -->
                        <input type="hidden" id="purok" name="purok" value="<?php echo htmlspecialchars($userPurok); ?>" required>
                    </div>
                </div>

                <!-- BIRTHDATE (always readonly) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Date of Birth</label>
                <div class="col-md-8">
                    <input type="date" id="birthday_display" 
                        class="form-control custom-input"
                        disabled
                        value="<?php echo date('Y-m-d', strtotime($birthdate)); ?>">
                    <!-- Hidden input to actually submit the value -->
                    <input type="hidden" id="birthday" name="birthday" value="<?php echo date('Y-m-d', strtotime($birthdate)); ?>">
                </div>
                </div>

                <!-- CIVIL STATUS (editable always) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Civil Status</label>
                <div class="col-md-8">
                    <select id="civilstatus" name="civilstatus"
                            class="form-control custom-input"
                            required>
                    <?php
                    foreach (['Single','Married','Separated','Widowed'] as $opt) {
                        $sel = ($opt === $civilstatus) ? 'selected' : '';
                        echo "<option value=\"$opt\" $sel>$opt</option>";
                    }
                    ?>
                    </select>
                </div>
                </div>

                <!-- BIRTHPLACE (editable only on NEW) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Place of Birth</label>
                <div class="col-md-8">
                    <input type="text" id="birthplace" name="birthplace"
                        class="form-control custom-input"
                        required placeholder="City, Province"
                        <?php echo $isRenewal ? 'readonly' : ''; ?>
                        value="<?php echo htmlspecialchars($birthplace); ?>">
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

                <!-- NEW: VALID ID NUMBER (optional) -->
                <div class="row mb-3">
                  <label class="col-md-4 text-start fw-bold">SSS / GSIS / Postal ID Number <span class="small text-muted">(Optional)</span></label>
                  <div class="col-md-8">
                    <input type="text" id="validIDNumber" name="valid_id_number"
                      class="form-control custom-input"
                      placeholder="Enter SSS / GSIS / Postal ID number"
                      value="<?php echo htmlspecialchars($validIdNumber); ?>">
                  </div>
                </div>

                <!-- CONTACT PERSON (optional) -->
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Name of Emergency Contact Person</label>
                    <div class="col-md-8">
                        <input type="text" id="contactperson" name="contactperson"
                            class="form-control custom-input"
                            placeholder="First Name MI. Surname (eg. Juan A. dela Cruz Sr.)"
                            value="<?php echo htmlspecialchars($contactperson); ?>">
                    </div>
                </div>

                <!-- CONTACT PERSON ADDRESS (optional) -->
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">
                        Address of Emergency Contact Person</span>
                    </label>
                    <div class="col-md-8">
                        <input
                            type="text"
                            id="contactAddress"
                            name="emergency_contact_address"
                            class="form-control custom-input"
                            placeholder="City, Province"
                            value="<?php
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
                        <div class="d-flex gap-2 align-items-start flex-wrap">
                            <!-- Hidden file input -->
                            <input type="file" id="brgyIDpicture" name="brgyIDpicture"
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
                            Please ensure the picture is a recent/updated photo — clear, front-facing, with a plain background and no heavy filters.
                        </small>
                    </div>
                </div>

                <!-- CLAIM DATE: replaced the simple date input with the Morning/Afternoon grid -->
                <div class="row mb-1">
                <label class="col-md-4 text-start fw-bold">Please select preferred claim date</label>
                <div class="col-md-8 p-3">
                    <div id="claimOptionsGroup" class="claim-list claim-grid">
                        <?php
                        // Render each business day as a row with two columns: Morning (left) and Afternoon (right)
                        foreach ($claimOptions as $coIndex => $co) {
                            $date = $co['date'];
                            $dateLabel = $co['label'];
                            // morning
                            $idMorning = "claim_{$coIndex}_morning";
                            $valMorning = $date . '|Morning'; // legacy string - still helpful for value attribute but we will not post this name
                            $checkedMorning = ($existingClaimDate === $date && $existingClaimPart === 'Morning') ? 'checked' : '';
                            // afternoon
                            $idAfternoon = "claim_{$coIndex}_afternoon";
                            $valAfternoon = $date . '|Afternoon';
                            $checkedAfternoon = ($existingClaimDate === $date && $existingClaimPart === 'Afternoon') ? 'checked' : '';
                            ?>
                            <div class="date-row">
                                <div class="row gx-2">
                                    <div class="col-sm-6 mb-2">
                                        <label class="list-group-item list-group-item-action p-2 claim-card d-flex align-items-start <?php echo $checkedMorning ? 'active' : ''; ?>" for="<?php echo $idMorning; ?>" role="option" aria-pressed="<?php echo $checkedMorning ? 'true' : 'false'; ?>">
                                            <div class="form-check me-2">
                                                <!-- note: radios named claim_slot; we'll store the actual date & part into hidden inputs -->
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

                    <!-- NEW MESSAGE: Right Thumb Mark & Signature processing note -->
                    <div class="text-center">
                        <em class="small text-muted">Note: Your <b>Right Thumb Mark</b> and <b>Signature</b> will be processed personally onto your printed Barangay ID card during issuance.</em>
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
                    <input type="hidden" id="paymentMethod" name="paymentMethod" value="<?php echo htmlspecialchars($existingRequest['payment_method'] ?? 'Brgy Payment Device'); ?>">
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
                        <span class="fw-bold">Date of Birth:</span>
                        <span class="text-success" id="summaryBirthdate"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Place of Birth:</span>
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
                        <span class="fw-bold">Height:</span>
                        <span class="text-success" id="summaryHeight"></span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Weight:</span>
                        <span class="text-success" id="summaryWeight"></span>
                    </li>

                    <!-- NEW: show valid ID number in summary (if provided) -->
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">SSS / GSIS / Postal ID: </span>
                        <span class="text-success" id="summaryValidID"><?php echo htmlspecialchars($validIdNumber); ?></span>
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
    // make the PHP values available to our claim JS
    window.initialStep = <?php echo $initial; ?>;
    window._claimOptions = <?php echo json_encode($claimOptions, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
    window._existingClaimObj = <?php echo json_encode(['date' => $existingClaimDate, 'part' => $existingClaimPart], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
</script>

<!-- Claim handling script (keeps logic local so external JS can remain unchanged) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const claimGroup = document.getElementById('claimOptionsGroup');
    const hiddenDate = document.getElementById('hiddenClaimDate');
    const hiddenTime = document.getElementById('hiddenClaimTime');
    const summaryEl = document.getElementById('summaryClaimDate');

    function clearActiveCards() {
        claimGroup.querySelectorAll('.claim-card').forEach(function(card){
            card.classList.remove('active');
        });
    }

    function setHiddenValues(date, part) {
        if (hiddenDate) hiddenDate.value = date || '';
        if (hiddenTime) hiddenTime.value = part || '';
    }

    function updateSummary(date, part) {
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
        summaryEl.textContent = (date ? date : '') + (part ? ' - ' + part : '');
    }

    if (claimGroup) {
        claimGroup.addEventListener('change', function (e) {
            // find checked radio and add active to its label parent
            const checked = claimGroup.querySelector('input[name="claim_slot"]:checked');
            clearActiveCards();
            if (checked) {
                const parentLabel = checked.closest('label');
                if (parentLabel) parentLabel.classList.add('active');

                const date = checked.dataset.date || '';
                const part = checked.dataset.part || '';
                setHiddenValues(date, part);
                updateSummary(date, part);
            }
        });

        // If there is an existing selection, apply it
        if (window._existingClaimObj && window._existingClaimObj.date) {
            const targetRadio = claimGroup.querySelector('input[name="claim_slot"][data-date="' + window._existingClaimObj.date + '"][data-part="' + window._existingClaimObj.part + '"]');
            if (targetRadio) {
                targetRadio.checked = true;
                // set hidden values and update UI
                const evt = new Event('change', { bubbles: true });
                targetRadio.dispatchEvent(evt);
            } else {
                // if exact match not found, try to match date only (default to Morning if part not available)
                const fallbackRadio = claimGroup.querySelector('input[name="claim_slot"][data-date="' + window._existingClaimObj.date + '"]');
                if (fallbackRadio) {
                    fallbackRadio.checked = true;
                    const evt = new Event('change', { bubbles: true });
                    fallbackRadio.dispatchEvent(evt);
                }
            }
        } else {
            // ensure first radio is selected by default if none selected
            const firstRadio = claimGroup.querySelector('input[name="claim_slot"]');
            if (firstRadio && !claimGroup.querySelector('input[name="claim_slot"]:checked')) {
                firstRadio.checked = true;
                firstRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        // make card clickable (label already handles input toggling), but add click to label to set active
        claimGroup.querySelectorAll('label').forEach(function(lbl) {
            lbl.addEventListener('click', function(){
                const input = lbl.querySelector('input[type="radio"]');
                if (input && !input.checked) {
                    input.checked = true;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    // still dispatch to ensure styles update
                    input && input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });
    } else {
        console.warn('Claim options group not found in DOM.');
    }

    // populate summary placeholders initially (helpful if transaction already present)
    (function(){
        const txType = document.getElementById('transactiontype')?.value || '';
        const tEl = document.getElementById('summarytransactionType');
        if (tEl && (!tEl.textContent || tEl.textContent.trim()==='')) tEl.textContent = txType;

        const fn = document.getElementById('fullname')?.value || '';
        if (document.getElementById('summaryFullName') && (!document.getElementById('summaryFullName').textContent || document.getElementById('summaryFullName').textContent.trim()==='')) {
            document.getElementById('summaryFullName').textContent = fn;
        }

        if (document.getElementById('summaryPurok')) {
            const pu = document.getElementById('purok');
            document.getElementById('summaryPurok').textContent = pu ? (pu.value || '') : '';
        }
        if (document.getElementById('summaryHeight')) document.getElementById('summaryHeight').textContent = document.getElementById('height')?.value || '';
        if (document.getElementById('summaryWeight')) document.getElementById('summaryWeight').textContent = document.getElementById('weight')?.value || '';
        if (document.getElementById('summaryBirthdate')) document.getElementById('summaryBirthdate').textContent = document.getElementById('birthday')?.value || '';
        if (document.getElementById('summaryBirthplace')) document.getElementById('summaryBirthplace').textContent = document.getElementById('birthplace')?.value || '';
        if (document.getElementById('summaryCivilStatus')) document.getElementById('summaryCivilStatus').textContent = document.getElementById('civilstatus')?.value || '';
        if (document.getElementById('summaryReligion')) document.getElementById('summaryReligion').textContent = document.getElementById('religion')?.value || '';
        if (document.getElementById('summaryContactPerson')) document.getElementById('summaryContactPerson').textContent = document.getElementById('contactperson')?.value || '';
        if (document.getElementById('summaryContactAddress')) document.getElementById('summaryContactAddress').textContent = document.getElementById('contactAddress')?.value || '';

        // NEW: populate valid ID summary if present in input (or prefilled from server)
        const summaryValidID = document.getElementById('summaryValidID');
        if (summaryValidID) {
            const prefilled = "<?php echo htmlspecialchars($validIdNumber); ?>";
            // if there's already server prefilled value, keep it; otherwise populate from input field
            if (!prefilled && document.getElementById('validIDNumber')) {
                summaryValidID.textContent = document.getElementById('validIDNumber')?.value || '';
            } else {
                summaryValidID.textContent = prefilled || (document.getElementById('validIDNumber')?.value || '');
            }
        }

        const pm = document.getElementById('paymentMethod');
        if (pm && document.getElementById('summaryPaymentMethod') && (!document.getElementById('summaryPaymentMethod').textContent || document.getElementById('summaryPaymentMethod').textContent.trim()==='')) {
            document.getElementById('summaryPaymentMethod').textContent = pm.value;
        }
    })();
}); // This closes the previous DOMContentLoaded

// Camera functionality - separate from DOMContentLoaded to ensure proper initialization
document.addEventListener('DOMContentLoaded', function() {
    const openCameraBtn = document.getElementById('openCameraBtn');
    const uploadFileBtn = document.getElementById('uploadFileBtn');
    const fileInput = document.getElementById('brgyIDpicture');
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

    // Initialize modal only when needed
    function initModal() {
        if (!cameraModal && cameraModalElement) {
            cameraModal = new bootstrap.Modal(cameraModalElement);
        }
        return cameraModal;
    }

    // Open camera
    if (openCameraBtn) {
        openCameraBtn.addEventListener('click', async function() {
            const modal = initModal();
            if (!modal) {
                console.error('Camera modal not found');
                return;
            }
            
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

    // Capture photo
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
            
            // Set canvas to square (e.g., 800x800 for good quality)
            const outputSize = 800;
            photoCanvas.width = outputSize;
            photoCanvas.height = outputSize;
            
            // Draw the center-cropped square portion of the video
            context.drawImage(
                cameraStream,
                cropX, cropY,           // Source x, y (start of crop)
                minDimension, minDimension,  // Source width, height (square crop)
                0, 0,                   // Destination x, y
                outputSize, outputSize  // Destination width, height
            );
            
            // Stop camera stream
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
            
            // Show preview
            cameraView.classList.add('d-none');
            previewView.classList.remove('d-none');
        });
    }

    // Retake photo
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

    // Use captured photo
    if (uploadPhotoBtn) {
        uploadPhotoBtn.addEventListener('click', function() {
            photoCanvas.toBlob(function(blob) {
                const file = new File([blob], 'camera-photo.jpg', { type: 'image/jpeg' });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;
                
                // Show preview
                photoPreview.src = URL.createObjectURL(blob);
                photoPreviewContainer.classList.remove('d-none');
                
                if (cameraModal) {
                    cameraModal.hide();
                }
            }, 'image/jpeg', 0.9);
        });
    }

    // Upload file button
    if (uploadFileBtn) {
        uploadFileBtn.addEventListener('click', function() {
            fileInput.click();
        });
    }

    // Handle file input change
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

    // Clean up camera when modal closes
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
// Back button functionality for Barangay ID
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

<script src="js/serviceBarangayID.js"></script>
