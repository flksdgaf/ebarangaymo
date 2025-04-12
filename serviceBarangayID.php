<?php 
include 'includes/user_header.php';
require 'functions/dbconn.php';

// Ensure the user is authenticated.
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: index.php");
    exit();
}

// Get the user's account id from session.
$userId = $_SESSION['loggedInUserID'];

// Query user_profiles to retrieve the profile picture.
$query = "SELECT * FROM user_profiles WHERE account_id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$fullName = "User"; // Default name if no record is found.
$fullAddress = "Address"; // Default address if no record is found.
$birthdate = "Birthdate"; // Default birthdate if no record is found.

if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $fullName = $row['full_name'];
    $fullAddress = $row['full_address'];
    $birthdate = $row['birthdate'];
}
$stmt->close();
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="serviceBarangayID.css"> 

<!-- BANNER SECTION -->
<div class="container-fluid px-0">
    <!-- Background image with overlay -->
    <div class="position-relative text-white text-center">
        <img src="images/services_banner.png" alt="Transparency Banner" class="img-fluid w-100">
        <!-- Overlay content -->
        <div class="position-absolute top-50 start-50 translate-middle">
            <h1 class="fw-semibold text-uppercase display-4">Barangay ID</h1>
            <p>Home / Services / Barangay ID</p>
        </div>
    </div>
</div>

<div class="container mt-5">
    <div class="progress-container">
        <div class="stepss">
            <div class="steps">
                <!-- Note: the first circle is preset with class "active" -->
                <div class="circle active" data-step="1">1</div>
                <div class="step-label active">APPLICATION FORM</div>
            </div>
            <div class="steps">
                <div class="circle" data-step="2">2</div>
                <div class="step-label">REVIEW &amp; CONFIRMATION</div>
            </div>
            <div class="steps">
                <div class="circle" data-step="3">3</div>
                <div class="step-label">SUBMISSION</div>
            </div>
            <div class="steps">
                <div class="circle" data-step="4">4</div>
                <div class="step-label">RELEASE OF BRGY. ID</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-fill" id="progressFill"></div>
        </div>
    </div>

    <div class="card shadow-sm p-5 mb-5">
        <h4 class="mb-3 text-success display-6 fw-bold" id="mainHeader">APPLICATION FORM</h4>
        <p id="subHeader">Provide the necessary details to apply for your Barangay ID.</p>
        <hr>

        <form action="submit.php" method="POST" enctype="multipart/form-data">
            <!-- Step 1: Data Entry -->
            <div class="step active-step">
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Type of Transaction</label>
                    <div class="col-md-8">
                        <select id="transactiontype" name="transactiontype" class="form-control custom-input" required>
                            <option value="">Select an option</option>
                            <option value="New Application">New Application</option>
                            <option value="Renewal">Renewal</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Full Name</label>
                    <div class="col-md-8">
                        <input type="text" id="fullname" name="fullname" class="form-control custom-input" disabled 
                               value="<?php echo $fullName; ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Full Address</label>
                    <div class="col-md-8">
                        <input type="text" id="address" name="address" class="form-control custom-input" disabled 
                               value="<?php echo $fullAddress; ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Height (in cm)</label>
                    <div class="col-md-8">
                        <input type="text" id="height" name="height" class="form-control custom-input" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Weight (in kg)</label>
                    <div class="col-md-8">
                        <input type="text" id="weight" name="weight" class="form-control custom-input" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Birthday</label>
                    <div class="col-md-8">
                        <input type="date" id="birthday" name="birthday" class="form-control custom-input" disabled 
                               value="<?php echo date('Y-m-d', strtotime($birthdate)); ?>">
                    </div>
                </div>

                <div class="row mb-3"> 
                    <label class="col-md-4 text-start fw-bold">Birthplace</label>
                    <div class="col-md-8">
                        <input type="text" id="birthplace" name="birthplace" class="form-control custom-input" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Civil Status</label>
                    <div class="col-md-8">
                        <select id="civilstatus" name="civilstatus" class="form-control custom-input" required>
                            <option value="">Select an option</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Separated">Separated</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                    </div>
                </div>           

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Religion</label>
                    <div class="col-md-8">
                        <input type="text" id="religion" name="religion" class="form-control custom-input" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Contact Person (in case of emergency)</label>
                    <div class="col-md-8">
                        <input type="text" id="contactperson" name="contactperson" class="form-control custom-input" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">1x1 Formal Picture</label>
                    <div class="col-md-8">
                        <input type="file" id="brgyIDpicture" name="brgyIDpicture" class="form-control custom-input" accept="image/*" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">When would you prefer to claim your Brgy. ID?</label>
                    <div class="col-md-8">
                        <input type="date" id="claimdate" name="claimdate" class="form-control custom-input" required>
                    </div>
                </div>
            </div>

            <!-- Step 2: Summary / Review -->
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

<!-- Bootstrap 5.3.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/serviceBarangayID.js"></script>
</body>
