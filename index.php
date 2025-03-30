<?php 
session_start();
include 'functions/dbconn.php'; 
$page = 'index';
include 'includes/header.php'; 
?>

<!-- BANNER SECTION -->
<div class="container-fluid px-0 position-relative">
    <img src="images/indexbanner.png" alt="Banner" class="img-fluid w-100">  

    <div class="card mb-3 position-absolute top-50 start-0 translate-middle-y ms-5" 
         style="max-width: 540px; background: transparent; border: none;">
        <div class="row g-0">
            <div class="col-md-4 d-flex align-items-center">
                <img src="images/ebarangaymologo.png" class="img-fluid" style="max-width: 100px" alt="ebarangaymologo">
            </div>
            <div class="banner-title col-lg-8">
                <div class="card-body">
                    <h1 class="card-title mt-4">eBarangay Mo</h1>
                    <p class="card-text">
                        “Bringing community services closer to you <span>&#45;</span> anytime, anywhere.”
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- INDEX MAIN SECTION -->
<div class="container-fluid px-0 landing-page position-relative">
    <img src="images/indeximage.png" alt="" class="landing-img w-100">

    <div class="index-main position-absolute top-50 start-50 translate-middle text-center" 
         style="max-width: 600px; background: transparent; border: none;">
        <img src="images/ebarangaymologo.png" class="img-fluid mb-3" style="width: 250px;" alt="eBarangay Mo Logo">
        <p class="card-text">
            Welcome to <span class="fw-bold">eBarangay Mo</span>, your one-stop platform for fast, transparent, and convenient access to essential barangay services. From requesting documents to reporting concerns, we make community engagement easier—anytime, anywhere.
        </p>
    </div>
</div>

<!-- INDEX ABOUT SECTION -->
<div class="container-fluid px-0">
    <div class="about-container text-center">
        <h1 class="gradient-text mt-2">
            ABOUT
        </h1>
        <p>
            eBarangay Mo is a digital platform designed to streamline barangay services, providing residents with easy access to requests, applications, and community updates. It enhances efficiency, transparency, and convenience in local governance through secure and user-friendly online transactions.
        </p>
    </div>
    <img src="images/aboutimage.png" alt="" class="w-25">
</div>

<!-- INDEX SIGN UP INSTRUCTION SECTION -->
<div class="container-fluid px-0">
    <div class="sign-up-container text-center">
        <h1 class="gradient-text mt-2">SIGN UP NOW</h1>
        <p class="w-50 mx-auto text-center mt-3">
            Follow these four simple steps:        
        </p>
    </div>

    <div class="container">
        <div>
            <img src="images/step1.png" alt="" class="w-25">
            <h3>Visit the Website</h3>
            <hr>
            <p>
                Go to the eBarangay Mo website and click Sign Up.
            </p>
        </div>

        <div>
            <img src="images/step2.png" alt="" class="w-25">
            <h3>Register an Account</h3>
            <hr>
            <p>
                Fill out the registration form with your required personal information.
            </p>
        </div>

        <div>
            <img src="images/step3.png" alt="" class="w-25">
            <h3>Wait for Verification</h3>
            <hr>
            <p>
                Wait for the notification in verifying the account you created.
            </p>
        </div>

        <div>
            <img src="images/step4.png" alt="" class="w-25">
            <h3>Avail Barangay Services</h3>
            <hr>
            <p>
                Once account is successfully verified, you can now submit request for your desired service.
            </p>
        </div>
    </div>
</div>

<?php 
// include 'includes/footer.php'; 
?> 
