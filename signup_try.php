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
            <!-- Step 1: Choose verification method -->
            <div class="step active-step" id="step-contact">
            <div class="row mb-3">
                <label class="col-md-3 text-start fw-bold">Verify via</label>
                <div class="col-md-9">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="verify_by" id="verifyPhone" value="phone" checked>
                    <label class="form-check-label" for="verifyPhone">Mobile Number</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="verify_by" id="verifyEmail" value="email">
                    <label class="form-check-label" for="verifyEmail">Email Address</label>
                </div>
                </div>
            </div>

            <div id="phoneBlock" class="row mb-3">
                <label class="col-md-3 text-start fw-bold">Mobile Number</label>
                <div class="col-md-9">
                <input type="tel" id="phoneInput" class="form-control" placeholder="+639XXXXXXXXX">
                <button type="button" id="sendPhoneOtp" class="btn btn-primary mt-2">Send OTP</button>
                <!-- container required by Firebase reCAPTCHA -->
                <div id="recaptcha-container"></div>
                </div>
            </div>

            <div id="emailBlock" class="row mb-3" style="display:none;">
                <label class="col-md-3 text-start fw-bold">Email</label>
                <div class="col-md-9">
                <input type="email" id="emailInput" class="form-control" placeholder="you@example.com">
                <button type="button" id="sendEmailOtp" class="btn btn-primary mt-2">Send Verification Link</button>
                </div>
            </div>

            <!-- OTP entry -->
            <div id="otpBlock" class="row mb-3" style="display:none;">
                <label class="col-md-3 text-start fw-bold">Enter OTP</label>
                <div class="col-md-9">
                <input id="otpCode" class="form-control" placeholder="Enter OTP">
                <button type="button" id="verifyOtpBtn" class="btn btn-success mt-2">Verify</button>
                </div>
            </div>
            </div>

        </form>
        <p class="text-center pt-4" style="color: #0D2C15; font-size: 12px;">Already have an account?   
            <a href="signin.php" style="color: #0D2C15; font-size: 13px; font-weight: bold; text-decoration: none;">Sign In</a>
        </p>
    </div>
</div>
