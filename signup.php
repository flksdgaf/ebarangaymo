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
                    <label class="col-md-4 text-start fw-bold">First Name</label>
                    <div class="col-md-8"><input type="text" id="firstname" name="firstname" class="form-control custom-input" required></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Middle Name</label>
                    <div class="col-md-8"><input type="text" id="middlename" name="middlename" class="form-control custom-input" placeholder="(Optional)"></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Last Name</label>
                    <div class="col-md-8"><input type="text" id="lastname" name="lastname" class="form-control custom-input" required></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Suffix</label>
                    <div class="col-md-8"><input type="text" id="suffix" name="suffix" class="form-control custom-input" placeholder="(Optional)"></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Birthdate</label>
                    <div class="col-md-8"><input type="date" id="birthdate" name="birthdate" class="form-control custom-input" required></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Gender</label>
                    <div class="col-md-8">
                        <select id="gender" name="gender" class="form-control custom-input" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Contact Number</label>
                    <div class="col-md-8"><input type="text" id="contact" name="contact" class="form-control custom-input" placeholder="09xxxxxxxxx" required></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Email</label>
                    <div class="col-md-8">
                        <input type="email" id="email" name="email" class="form-control custom-input" placeholder="@email.com" required>
                    </div>
                </div>
            </div>

            <!-- Step 2: Address Information -->
            <div class="step">
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Province</label>
                    <div class="col-md-8">
                        <select id="province" name="province" class="form-control custom-input" required>
                            <option value="">Select Province</option>
                            <option value="Camarines Norte">Camarines Norte</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Municipality</label>
                    <div class="col-md-8">
                        <select id="municipality" name="municipality" class="form-control custom-input" required disabled>
                            <option value="">Select Municipality</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Barangay</label>
                    <div class="col-md-8">
                        <select id="barangay" name="barangay" class="form-control custom-input" required disabled>
                            <option value="">Select Barangay</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Subdivision/Purok</label>
                    <div class="col-md-8"><input type="text" id="purok" name="purok" class="form-control custom-input" required></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Block/Lot/Street</label>
                    <div class="col-md-8"><input type="text" id="block" name="email" class="form-control custom-input" required></div>
                </div>
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Zip Code</label>
                    <div class="col-md-8"><input type="text" id="zip" name="zip" class="form-control custom-input" required></div>
                </div>
            </div>

            <!-- Step 3: Upload Valid ID -->
            <div class="step">
                <div class="row mb-3">
                    <label class="col-md-4 text-start fw-bold">Select Valid ID</label>
                    <div class="col-md-8">
                        <select id="validID" name="validID" class="form-control custom-input" required>
                            <option value="">Select a valid ID</option>
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

            <!-- Step 5: Review Filled Out Information -->
            <div class="step">
                <div class="summary-container p-3">
                    <p><strong>Full Name:</strong> <span id="summaryFullName"></span></p>
                    <p><strong>Birthdate:</strong> <span id="summaryBirthdate"></span></p>
                    <p><strong>Gender:</strong> <span id="summaryGender"></span></p>
                    <p><strong>Contact Number:</strong> <span id="summaryContact"></span></p>
                    <p><strong>Email:</strong> <span id="summaryEmail"></span></p>
                    <p><strong>Address:</strong> <span id="summaryAddress"></span></p>
                </div>
            </div>

            <!-- Navigation Dots -->
            <div class="text-center mt-3">
                <span class="dot active-dot"></span>
                <span class="dot"></span>
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
    </div>
</div>
