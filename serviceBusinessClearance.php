<?php
require 'functions/dbconn.php';

// require login (align with submit handler)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['loggedInUserID'])) {
    header('Location: index.php');
    exit();
}

date_default_timezone_set('Asia/Manila');

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

// parse full name into parts (we keep parts for hidden inputs to preserve compatibility)
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

// If there's an existing request, prefer its name fields (so edit/view works)
$existingRequest = [];
$chosenPayment = null;
if ($transactionId) {
    $stmt = $conn->prepare("SELECT * FROM business_clearance_requests WHERE transaction_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows) {
            $existingRequest = $res->fetch_assoc();
            $chosenPayment = $existingRequest['payment_method'] ?? null;

            // if DB has components, override parsed pieces
            if (!empty($existingRequest['first_name'])) {
                $firstName = $existingRequest['first_name'];
            }
            if (!empty($existingRequest['middle_name'])) {
                $middleName = $existingRequest['middle_name'];
            }
            if (!empty($existingRequest['last_name'])) {
                $lastName = $existingRequest['last_name'];
            }
            // If DB already saved full_name, we will display it directly
            if (!empty($existingRequest['full_name'])) {
                $fullName = $existingRequest['full_name'];
            }
        }
        $stmt->close();
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

// build a display-friendly full name (First Middle Last) preferring existingRequest->full_name when present
$displayFullName = '';
if (!empty($existingRequest['full_name'])) {
    $displayFullName = $existingRequest['full_name'];
} elseif (!empty($fullName)) {
    // If we already parsed/prefilled $fullName from purok table, try to construct First Middle Last
    $constructed = trim(implode(' ', array_filter([$firstName, $middleName, $lastName])));
    $displayFullName = $constructed ?: $fullName;
} else {
    $displayFullName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName])));
}

// Defaults (kept but not used as visible inputs anymore)
$defaultBarangay = 'Magang';
$defaultMunicipality = 'Daet';
$defaultProvince = 'Camarines Norte';

// Grab any server-side error from session (set by submit handler) and then clear it
$svcError = null;
if (!empty($_SESSION['svc_error'])) {
    $svcError = $_SESSION['svc_error'];
    unset($_SESSION['svc_error']);
}

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

$today = new DateTime('now', new DateTimeZone('Asia/Manila'));
$businessDays = getNextBusinessDays($today, 3);

// build claim options array (server-side) for rendering: each date has Morning & Afternoon
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
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Business Clearance</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="serviceBusinessClearance.css">

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

    /* explicit outlined class — used for invalid hints or emphasis */
    .claim-card.outlined {
    outline: 2px solid rgba(0,0,0,0.08);
    outline-offset: 2px;
    }

    /* active / selected look */
    .claim-card.active {
    border-color: #198754; /* green */
    box-shadow: 0 8px 24px rgba(25,135,84,0.06);
    background: #fbfffb;
    transform: translateY(-2px);
    }

    /* invalid visual state (used by JS when validation fails) */
    .claim-card.invalid {
    border-color: #dc3545;
    box-shadow: none;
    background: #fff5f5;
    outline: 2px solid rgba(220,53,69,0.08);
    outline-offset: 2px;
    }

    /* small adjustments to the radio position */
    .claim-card .form-check {
    margin-top: 3px;
    }

    /* ensure stacking on very small screens unchanged */
    @media (max-width: 575.98px) {
    .claim-grid .col-sm-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    }
    /* validation outline for invalid radio group */
    .needs-claim .form-check-input:invalid ~ .claim-card,
    .needs-claim .claim-card[aria-invalid="true"] {
        outline: 1px solid #dc3545;
    }
  </style>

  <!-- Optional: flatpickr (kept but not used for claim date) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
<div class="container py-4 px-3">
    <?php if ($svcError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($svcError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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

    <div class="card shadow-sm px-5 py-5 mb-5 mt-4">
        <h2 class="mb-1 text-success fw-bold" id="mainHeader"></h2>
        <p id="subHeader" class="mb-2">Provide the necessary details to request a Business Clearance.</p>
        <hr id="mainHr" class="mb-4">

        <form id="businessClearanceForm" action="functions/serviceBusinessClearance_submit.php" method="POST" enctype="multipart/form-data" class="needs-claim">
            <!-- Step 1: Application Form -->
            <div class="step <?php echo $transactionId ? 'completed' : 'active-step'; ?>">

                <!-- FULL NAME (single field) -->
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Full Name</label>
                    <div class="col-md-8">
                        <input type="text" id="full_name" name="full_name"
                               class="form-control custom-input"
                               required
                               value="<?php echo htmlspecialchars($displayFullName); ?>">
                    </div>
                </div>

                <!-- Hidden name parts to keep backward compatibility with existing submit handler -->
                <input type="hidden" id="firstname" name="firstname" value="<?php echo htmlspecialchars($firstName); ?>">
                <input type="hidden" id="middlename" name="middlename" value="<?php echo htmlspecialchars($middleName); ?>">
                <input type="hidden" id="lastname" name="lastname" value="<?php echo htmlspecialchars($lastName); ?>">

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

                <!-- AGE -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Age</label>
                <div class="col-md-8">
                    <input type="number" id="age" name="age"
                        class="form-control custom-input"
                        min="0" max="150" required
                        value="<?php echo htmlspecialchars($existingRequest['age'] ?? $age); ?>">
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

                <!-- BUSINESS NAME -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Name of Business</label>
                <div class="col-md-8">
                    <input type="text" id="business_name" name="business_name"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($existingRequest['business_name'] ?? ''); ?>">
                </div>
                </div>

                <!-- BUSINESS TYPE -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Type of Business</label>
                <div class="col-md-8">
                    <input type="text" id="business_type" name="business_type"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($existingRequest['business_type'] ?? ''); ?>">
                </div>
                </div>

                <!-- ADDRESS (business address) -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Business Address</label>
                <div class="col-md-8">
                    <input type="text" id="address" name="address"
                        class="form-control custom-input"
                        required
                        value="<?php echo htmlspecialchars($existingRequest['address'] ?? ''); ?>">
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

                <!-- PICTURE -->
                <div class="row mb-3">
                <label class="col-md-4 text-start fw-bold">Formal Picture</label>
                <div class="col-md-8">
                    <input type="file" id="picture" name="picture"
                        class="form-control custom-input"
                        accept="image/*">
                    <!-- NEW NOTE: ensure picture is recent -->
                    <small class="form-text text-muted mt-1">
                        Please ensure the picture is a recent/updated photo — clear, front-facing, with a plain background and no heavy filters.
                    </small>

                </div>
                </div>

                <!-- CLAIM DATE: two-column layout (morning / afternoon) -->
                <div class="row mb-1">
                <label class="col-md-4 text-start fw-bold">Claim Date</label>
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
                        <em class="small text-muted">Note: Your <b>Right Thumb Mark</b> will be processed personally onto your printed Business Clearance during issuance.</em>
                    </div>
            </div>

            <!-- Step 2: Payment -->
            <div class="step <?php echo $transactionId ? 'completed' : ''; ?>">
            <div class="payment-container p-4 border rounded shadow-sm bg-green">
                <div class="row g-4">

                <!-- LEFT COLUMN: Fee -->
                <div class="col-md-4">
                    <div class="fee-box p-4 rounded shadow-sm border bg-light text-center">
                        <h5 class="fw-bold text-success mb-2">Business Clearance Fee</h5>
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
                        <span class="label fw-bold">Brgy Payment Device</span>
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
                        <li>Submit the receipt and claim your Business Clearance.</li>
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
                        <span class="fw-bold">Purok:</span>
                        <span class="text-success" id="summaryPurok">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Age / Marital Status:</span>
                        <span class="text-success" id="summaryAgeMarital">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Business Name / Type:</span>
                        <span class="text-success" id="summaryBusiness">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Business Address:</span>
                        <span class="text-success" id="summaryBusinessAddress">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">CTC Number:</span>
                        <span class="text-success" id="summaryCTC">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Claim Date:</span>
                        <span class="text-success" id="summaryClaimDate">-</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span class="fw-bold">Payment Method:</span>
                        <span class="text-success" id="summaryPaymentMethod">-</span>
                    </li>

                    <li class="list-group-item">
                        <em class="small text-muted">Note: Your <b>Right Thumb Mark</b> will be processed personally onto your printed Business Clearance during issuance.</em>
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
    // claim options data for client-side usage
    window._claimOptions = <?php echo json_encode($claimOptions, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
    // existing claim as object for client-side use
    window._existingClaimObj = <?php echo json_encode(['date' => $existingClaimDate, 'part' => $existingClaimPart], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Use the claimOptionsGroup wrapper we added
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
        summaryEl.textContent = (date ? date : '-') + (part ? ' - ' + part : '');
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

    // helper to show dash if empty
    function asDash(value) {
        if (!value && value !== 0) return '-';
        const s = String(value).trim();
        return s === '' ? '-' : s;
    }

    // populate summary placeholders initially
    const setIfEmpty = function(targetId, sourceSelector, formatter) {
        const target = document.getElementById(targetId);
        if (!target) return;
        const src = document.querySelector(sourceSelector);
        let val = '';
        if (src) {
            if (src.tagName === 'SELECT' || src.tagName === 'INPUT' || src.tagName === 'TEXTAREA') {
                val = src.value;
            } else {
                val = src.textContent || src.value || '';
            }
        }
        if (formatter && typeof formatter === 'function') val = formatter(val);
        target.textContent = asDash(val);
    };

    // Full name (single input)
    setIfEmpty('summaryFullName', '#full_name');

    // Purok
    setIfEmpty('summaryPurok', '#purok');

    // Age / Marital
    const age = document.getElementById('age')?.value || '';
    const marital = document.getElementById('maritalstatus')?.value || '';
    document.getElementById('summaryAgeMarital').textContent = asDash((age ? age : '-') + (marital ? ' / ' + marital : '') === '-' ? '-' : ( (age ? age : '-') + (marital ? ' / ' + marital : '') ));

    // Business Name / Type
    setIfEmpty('summaryBusiness', '#business_name', function(v){
        const t = document.getElementById('business_type')?.value || '';
        if (!v && !t) return '-';
        return (v ? v : '-') + (t ? ' / ' + t : '');
    });

    // Business Address
    setIfEmpty('summaryBusinessAddress', '#address');

    // CTC
    setIfEmpty('summaryCTC', '#ctcnumber');

    // Claim date handled by claim code above (if radio selected updateSummary will run). If none was set, show dash:
    if (!document.getElementById('summaryClaimDate').textContent || document.getElementById('summaryClaimDate').textContent.trim() === '') {
        document.getElementById('summaryClaimDate').textContent = '-';
    }

    // Payment method summary
    const pm = document.getElementById('paymentMethod');
    if (pm) {
        const pmSummary = document.getElementById('summaryPaymentMethod');
        if (pmSummary && (!pmSummary.textContent || pmSummary.textContent.trim()==='')) {
            pmSummary.textContent = asDash(pm.value || '-');
        }
    }

    // When the user edits the visible full_name input, also update hidden parts (naive split: first token = first, last token = last, middle = rest)
    const fullInput = document.getElementById('full_name');
    if (fullInput) {
        fullInput.addEventListener('input', function(){
            const val = fullInput.value.trim();
            // update summary
            document.getElementById('summaryFullName').textContent = asDash(val);

            // split into parts and populate hidden inputs for backward compatibility
            if (val === '') {
                document.getElementById('firstname').value = '';
                document.getElementById('middlename').value = '';
                document.getElementById('lastname').value = '';
                return;
            }
            const parts = val.split(/\s+/);
            if (parts.length === 1) {
                document.getElementById('firstname').value = parts[0];
                document.getElementById('middlename').value = '';
                document.getElementById('lastname').value = '';
            } else {
                document.getElementById('firstname').value = parts[0];
                document.getElementById('lastname').value = parts[parts.length - 1];
                if (parts.length > 2) {
                    document.getElementById('middlename').value = parts.slice(1, parts.length - 1).join(' ');
                } else {
                    document.getElementById('middlename').value = '';
                }
            }
        });
    }

    // update summary when business fields change (so the review page shows edits made prior to hitting Next)
    ['business_name','business_type','address','ctcnumber','age','maritalstatus','purok'].forEach(function(id){
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', function(){
            // refresh the little summaries that depend on them
            setIfEmpty('summaryPurok', '#purok');
            const ageVal = document.getElementById('age')?.value || '';
            const maritalVal = document.getElementById('maritalstatus')?.value || '';
            document.getElementById('summaryAgeMarital').textContent = asDash((ageVal ? ageVal : '-') + (maritalVal ? ' / ' + maritalVal : ''));
            setIfEmpty('summaryBusiness', '#business_name', function(v){
                const t = document.getElementById('business_type')?.value || '';
                if (!v && !t) return '-';
                return (v ? v : '-') + (t ? ' / ' + t : '');
            });
            setIfEmpty('summaryBusinessAddress', '#address');
            setIfEmpty('summaryCTC', '#ctcnumber');
        });
    });
});
</script>

<script src="js/serviceBusinessClearance.js"></script>
</body>
</html>
