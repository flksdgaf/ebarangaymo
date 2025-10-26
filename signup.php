<?php 
$page = 'signinup'; 
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="signup.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/signup.js"></script>

<body>
    <div class="container d-flex justify-content-center align-items-center">
        <div class="form-container">
            <h1 class="text-center">CREATE ACCOUNT</h1>
            <h5 id="subHeader" class="text-center">Fill out all needed information</h5>

            <!-- Multi-Step Form -->
            <form id="registrationForm" action="functions/new_acc_signup.php" method="POST" enctype="multipart/form-data">
                
                <!-- Step 1: Personal Information -->
                <div class="step active-step">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name</label>
                            <input type="text" id="firstname" name="firstname" class="form-control custom-input" placeholder="eg. Juan" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" id="middlename" name="middlename" class="form-control custom-input" placeholder="eg. Santos">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name</label>
                            <input type="text" id="lastname" name="lastname" class="form-control custom-input" placeholder="eg. dela Cruz" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Birthdate</label>
                            <input type="date" id="birthdate" name="birthdate" class="form-control custom-input" placeholder="10-10-2010" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Purok</label>
                            <select id="purok" name="purok" class="form-select custom-input" required>
                                <option value="" disabled selected>Select your Purok</option>
                                <option value="1">Purok 1</option>
                                <option value="2">Purok 2</option>
                                <option value="3">Purok 3</option>
                                <option value="4">Purok 4</option>
                                <option value="5">Purok 5</option>
                                <option value="6">Purok 6</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Select Valid ID</label>
                            <select id="validID" name="validID" class="form-select custom-input" required>
                                <option value="" disabled selected>Select Valid ID</option>
                                <option value="Philippine National ID">Philippine National ID</option>
                                <option value="Philippine Passport">Philippine Passport</option>
                                <option value="Driver's License">Driver's License</option>
                                <option value="SSS ID">SSS ID</option>
                                <option value="UMID">UMID</option>
                                <option value="Voter's ID">Voter's ID</option>
                                <option value="TIN ID">TIN ID</option>
                                <option value="PRC ID">PRC ID</option>
                                <option value="PhilHealth ID">PhilHealth ID</option>
                                <option value="Postal ID">Postal ID</option>
                                <option value="Barangay ID">Barangay ID</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Picture of Valid ID (Front)</label>
                            <input type="file" id="frontID" name="frontID" class="form-control custom-input" accept="image/*" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Picture of Valid ID (Back)</label>
                            <input type="file" id="backID" name="backID" class="form-control custom-input" accept="image/*" required>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Credentials -->
                <div class="step">
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control custom-input" placeholder="juandelacruz@gmail.com" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Username</label>
                            <input type="text" id="username" name="username" class="form-control custom-input" placeholder="juandelacruz" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Password</label>
                            <div class="position-relative">
                                <input type="password" class="form-control custom-input password-field" id="password" name="password" placeholder="********" required>
                                <span class="toggle-password" onclick="togglePassword('password')">
                                    <i class="fa fa-eye-slash"></i>
                                </span>
                            </div>
                            <div class="password-rules">
                                <small id="uppercaseRule" class="rule">At least 8 characters long</small>
                                <small id="numberRule" class="rule">Includes uppercase & lowercase letters</small>
                                <small id="specialCharRule" class="rule">Includes at least one number (0-9)</small>
                                <small id="specialCharRule2" class="rule">Has at least one special character (eg:!@#$)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Review -->
                <div class="step">
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Full Name</label>
                            <input type="text" id="summaryFullName" class="form-control custom-input" placeholder="Juan Santos dela Cruz" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Birthdate</label>
                            <input type="text" id="summaryBirthdate" class="form-control custom-input" placeholder="10-10-2010" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Purok</label>
                            <input type="text" id="summaryPurok" class="form-control custom-input" placeholder="Purok 1" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="text" id="summaryEmail" class="form-control custom-input" placeholder="juandelacruz@gmail.com" readonly>
                        </div>
                    </div>

                     <div class="row mb-3">
                        <div class="col-12">
                            <div class="accordion" id="privacyAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingPrivacy">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePrivacy" aria-expanded="false" aria-controls="collapsePrivacy">
                                            Data Privacy Agreement
                                        </button>
                                    </h2>
                                    <div id="collapsePrivacy" class="accordion-collapse collapse" aria-labelledby="headingPrivacy" data-bs-parent="#privacyAccordion">
                                        <div class="accordion-body">
                                            <p>eBarangay Mo is operated by the Barangay Local Government Unit to deliver online barangay services. We collect and process <strong>the minimum personal data</strong> necessary to provide your requested services (applications and certificates), for record-keeping, reports, and payment processing.</p>
                                            <p>Your information will be kept <strong>strictly confidential</strong>, accessible only to authorized personnel with high-level access, and <strong>will not be shared with third parties</strong> except when required by law. We retain data only as long as needed to fulfill these purposes or to meet legal obligations.</p>
                                            <p>By checking this box you consent to the collection, use, and retention of your personal data for the purposes stated above. If you have questions or wish to access, correct, or withdraw your data, please contact the Barangay Office.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="privacyConsent" name="privacyConsent" required>
                                <label class="form-check-label" for="privacyConsent">
                                    I have read and agree to the Data Privacy Agreement
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Dots -->
                <div class="dots-container">
                    <span class="dot active-dot"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                </div>

                <!-- Buttons -->
                <div class="btn-container">
                    <button type="button" class="btn cancel-btn">CANCEL</button>
                    <button type="button" class="btn next-btn">NEXT</button>
                </div>
            </form>

            <p class="signin-link">Already have an account? <a href="signin.php">Sign In</a></p>
        </div>
    </div>

    <!-- Validation Modal -->
    <div class="modal fade" id="validationModal" tabindex="-1" aria-labelledby="validationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content signup-modal">
                <div class="modal-body text-center p-4">
                    <div class="modal-icon-wrapper mb-3">
                        <span class="material-symbols-outlined modal-icon error-icon">error</span>
                    </div>
                    <h4 class="modal-title-custom mb-3">Validation Error</h4>
                    <p class="modal-message">Please fill in all required fields before proceeding.</p>
                    <button type="button" class="btn btn-modal-close mt-3" data-bs-dismiss="modal">Got it</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content signup-modal">
                <div class="modal-body text-center p-4">
                    <div class="modal-icon-wrapper mb-3">
                        <span class="material-symbols-outlined modal-icon confirm-icon">help</span>
                    </div>
                    <h4 class="modal-title-custom mb-3">Confirm Submission</h4>
                    <p class="modal-message">Is all of your information correct?</p>
                    <div class="d-flex justify-content-center gap-3 mt-3">
                        <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-modal-close" id="confirmSubmitBtn">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>