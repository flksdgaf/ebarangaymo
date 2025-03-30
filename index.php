<?php 
session_start();
include 'functions/dbconn.php'; 
$page = 'index';
include 'includes/header.php'; 
?>

<!-- BANNER SECTION -->
<div class="container-fluid px-0">
    <img src="images/banner2.png" alt="Banner" class="img-fluid w-auto">    
</div>

<!-- LANDING PAGE MAIN SECTION -->
<div class="container-fluid px-0 landing-page">
    <img src="images/landingpage.png" alt="" class="landing-img">
</div>

<!-- SIGN UP NOW SECTION -->
<div class="container-fluid px-0">
    <div class="sign-up-container text-center">
        <h1 class="gradient-text mt-2">SIGN UP NOW</h1>
        <p class="w-50 mx-auto text-center mt-3">
            Follow these four simple steps:        
        </p>
    </div>
</div>

<?php 
// include 'includes/footer.php'; 
?> 
