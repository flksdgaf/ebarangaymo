<?php 
$page = 'signinup'; 
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="signup.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/signup.js"></script>

<!-- Registration Form Section -->
<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="form-container">
        <h1 class="text-center fw-bold">CREATE ACCOUNT</h1>
        <h5 id="subHeader" class="text-center">Fill out personal information</h5>

        <!-- Multi-Step Form -->
        <form id="registrationForm" action="functions/new_acc_signup.php" method="POST" enctype="multipart/form-data">
            <!-- Step 1: Personal Information -->
            <div class="step active-step">
                <div class="row mb-3">
                    <label class="col-md-3 text-start fw-bold">First Name</label>
                    <div class="col-md-9"><input type="text" id="firstname" name="firstname" class="form-control custom-input" required></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-3 text-start fw-bold">Middle Name</label>
                    <div class="col-md-9"><input type="text" id="middlename" name="middlename" class="form-control custom-input" placeholder="(Optional)"></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-3 text-start fw-bold">Last Name</label>
                    <div class="col-md-9"><input type="text" id="lastname" name="lastname" class="form-control custom-input" required></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-3 text-start fw-bold">Suffix</label>
                    <div class="col-md-9"><input type="text" id="suffix" name="suffix" class="form-control custom-input" placeholder="(Optional)"></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-3 text-start fw-bold">Birthdate</label>
                    <div class="col-md-9"><input type="date" id="birthdate" name="birthdate" class="form-control custom-input" required></div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-3 text-start fw-bold">Purok</label>
                    <div class="col-md-9">
                        <select id="purok" name="purok" class="form-control custom-input" required>
                            <option value="">Click to select your Purok</option>
                            <option value="Purok 1">Purok 1</option>
                            <option value="Purok 2">Purok 2</option>
                            <option value="Purok 3">Purok 3</option>
                            <option value="Purok 4">Purok 4</option>
                            <option value="Purok 5">Purok 5</option>
                            <option value="Purok 6">Purok 6</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Step 3: Upload Valid ID -->
            <div class="step">
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Select Valid ID</label>
                    <div class="col-md-8">
                        <select id="validID" name="validID" class="form-control custom-input" required>
                            <option value="">Click to select a valid ID</option>
                            <option value="Philippine Passport">Philippine Passport</option>
                            <option value="Driver’s License">Driver’s License</option>
                            <option value="SSS ID">SSS ID</option>
                            <option value="UMID">UMID</option>
                            <option value="Voter’s ID">Voter’s ID</option>
                            <option value="TIN ID">TIN ID</option>
                            <option value="PRC ID">PRC ID</option>
                            <option value="PhilHealth ID">PhilHealth ID</option>
                            <option value="Postal ID">Postal ID</option>
                            <option value="Barangay ID">Barangay ID</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Front Picture of ID</label>
                    <div class="col-md-8">
                        <input type="file" id="frontID" name="frontID" class="form-control custom-input" accept="image/*" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Back Picture of ID</label>
                    <div class="col-md-8">
                        <input type="file" id="backID" name="backID" class="form-control custom-input" accept="image/*" required>
                    </div>
                </div>

            </div>

            <!-- Step 4: Create Username & Password -->
            <div class="step">
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Profile Picture</label>
                    <div class="col-md-8">
                        <input type="file" id="profilePic" name="profilePic" class="form-control custom-input" accept="image/*" required>
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Username</label>
                    <div class="col-md-8">
                        <input type="text" class="form-control custom-input" id="username" name="username" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Password</label>
                    <div class="col-md-8 position-relative">
                        <input type="password" class="form-control custom-input password-field" id="password" name="password" required>
                        <span class="toggle-password" onclick="togglePassword('password')">
                            <i class="fa fa-eye-slash"></i>
                        </span>
                    </div>
                    <div class="password-rules mt-2">
                        <small id="lengthRule" class="rule">At least 8 to 15 characters</small><br>
                        <small id="uppercaseRule" class="rule">Upper & lowercase letters</small><br>
                        <small id="numberRule" class="rule">At least one number</small><br>
                        <small id="specialCharRule" class="rule">At least one special character</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Confirm Password</label>
                    <div class="col-md-8 position-relative">
                        <input type="password" class="form-control custom-input password-field" id="confirmPassword" disabled required>
                        <span class="toggle-password" onclick="togglePassword('confirmPassword')">
                            <i class="fa fa-eye-slash"></i>
                        </span>
                    </div>
                    <small id="confirmMessage" class="text-danger"></small>
                </div>
            </div>
            
            <!-- Step 5: Review Filled-Out Information -->
            <div class="step">
                <!-- Full Name -->
                <div class="row mb-3">
                    <label class="col-md-3 text-start fw-bold">Full Name</label>
                    <div class="col-md-9">
                    <input type="text" id="summaryFullName" class="form-control custom-input" readonly>
                    </div>
                </div>
                <!-- Birthdate -->
                <div class="row mb-3">
                    <label class="col-md-3 text-start fw-bold">Birthdate</label>
                    <div class="col-md-9">
                    <input type="text" id="summaryBirthdate" class="form-control custom-input" readonly>
                    </div>
                </div>
                <!-- Purok -->
                <div class="row mb-3">
                    <label class="col-md-3 text-start fw-bold">Purok</label>
                    <div class="col-md-9">
                    <input type="text" id="summaryPurok" class="form-control custom-input" readonly>
                    </div>
                </div>
            </div>

            <!-- Navigation Dots -->
            <div class="text-center mt-2">
                <span class="dot active-dot"></span>
                <span class="dot"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>

            <!-- Buttons -->
            <div class="d-flex justify-content-center mt-4">
                <button type="button" class="btn cancel-btn">Cancel</button>
                <button type="button" class="btn next-btn">Next</button>
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
        </form>
        <p class="text-center pt-4" style="color: #0D2C15; font-size: 12px;">Already have an account?   
            <a href="signin.php" style="color: #0D2C15; font-size: 13px; font-weight: bold; text-decoration: none;">Sign In</a>
        </p>
    </div>
</div>
