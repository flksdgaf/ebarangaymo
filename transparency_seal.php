<?php 
// session_start();
// include 'functions/dbconn.php'; 
$page = 'user_homepage';
include 'includes/header.php'; 
?>

<!-- BANNER SECTION -->
<div class="container-fluid px-0">
    <!-- Background image with overlay -->
    <div class="position-relative text-white text-center">
        <img src="images/about_banner.png" alt="Transparency Banner" class="img-fluid w-100">
        
        <!-- Overlay content -->
        <div class="position-absolute top-50 start-50 translate-middle">
            <h1 class="fw-semibold text-uppercase">Transparency Seal</h1>
            <p>Home / Transparency Seal</p>
        </div>
    </div>
</div>

<!-- SYMBOLISM SECTION -->
<div class="container my-5">
    <div class="row justify-content-center align-items-start">
        <div class="col-md-4 text-center ">
            <img src="images/transparencyseal.png" alt="Philippine Transparency Seal" class="img-fluid ms-n3" style="max-width: 500px;">
        </div>
        <div class="col-md-8">
            <h4 class="gradient-text mb-4 fw-bold text-success">SYMBOLISM</h4>
            <p class="text-justify">
                “A pearl buried inside a tightly-shut shell is practically worthless. Government information is a pearl, meant to be shared with the public to maximize its inherent value. The Transparency Seal, depicted by a pearl shining out of an open shell, symbolizes a policy shift towards openness in access to government information. On the one hand, it hopes to inspire Filipinos in the civil service to be more open to citizen engagement; on the other, to invite the Filipino citizenry to exercise their right to participate in governance. This initiative is envisioned as a step in the right direction towards solidifying the position of the Philippines as the Pearl of the Orient — a shining example of democratic virtue in the region.”
            </p>
        </div>
    </div>
</div>

<?php
    include 'includes/footer.php';
?>
