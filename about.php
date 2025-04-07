<?php 
session_start();
include 'functions/dbconn.php'; 
$page = 'user_homepage';
include 'includes/header.php'; 
?>

<!-- BANNER SECTION -->
<div class="container-fluid px-0 position-relative">
    <!-- Background image -->
    <img src="images/about_banner.png" alt="About Banner" class="image-fluid w-100 about-banner-image">
    <div class="position-absolute text-white text-center">
        <div class="container">
            <div class="row align-items-center justify-content-center d-none d-md-flex">
                <div class="text-center">
                    <h1 class="fw-semibold">eBarangay Mo</h1>
                </div>
            </div>
        </div>
    </div>
</div>