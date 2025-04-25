<?php 
$page = 'signinup'; 
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="signinup.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/signinup.js"></script>

<!-- Split Login/Signup Section -->
<div class="container-fluid">
    <div class="row split-container">
        <!-- Sign In Section -->
        <div class="col-md-6 split left d-flex flex-column align-items-center justify-content-center text-white" id="left-side">
            <h1 class="fw-bold">HAVE AN ACCOUNT?</h1>
            <p>Keep connected with us and sign in with your existing account.</p>
            <a href="signin.php" class="btn btn-outline-light">SIGN IN</a>
        </div>

        <!-- Sign Up Section -->
        <div class="col-md-6 split right d-flex flex-column align-items-center justify-content-center text-dark" id="right-side">
            <h1 class="fw-bold">CREATE ACCOUNT</h1>
            <p>Provide your personal details and start your journey with us.</p>
            <a href="signup.php" class="btn btn-primary">SIGN UP</a>
        </div>
    </div>
</div>



