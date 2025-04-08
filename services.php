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
        <img src="images/about_banner.png" alt="Services Banner" class="img-fluid w-100">
        
        <!-- Overlay content -->
        <div class="position-absolute top-50 start-50 translate-middle">
            <h1 class="fw-semibold text-uppercase">Services</h1>
            <p>Home / Services</p>
        </div>
    </div>
</div>

<div class="container my-5">
    <h4 class="gradient-text fw-bold text-success">BARANGAY SERVICES</h4>
    <p class="mb-4">
        Access the range of services offered by Barangay Magang, including permits, certificates, and community assistance programs. 
        This page provides information to help residents conveniently avail of barangay services.
    </p>

    <div class="row g-4">
        <!-- BARANGAY ID -->
        <div class=" col-md-6">
            <div class="barangay-id d-flex p-3 rounded text-white">
                <div class="me-3">
                    <i class="bi bi-person-badge fs-1"></i>
                </div>
                <div>
                    <h5 class="barangay-id-title fw-bold mb-1">Barangay ID</h5>
                    <p class="mb-0">An official identification card issued by the barangay that serves as proof of residency and identity.</p>
                </div>
            </div>
        </div>

        <!-- Service 2 -->
        <div class="col-md-6">
            <div class="d-flex p-3 rounded text-white" style="background-color: #7ac142;">
                <div class="me-3">
                    <i class="bi bi-check-circle fs-1"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Barangay Clearance</h5>
                    <p class="mb-0">An official document that certifies a resident has no pending issues in the barangay.</p>
                </div>
            </div>
        </div>

        <!-- Repeat this block for other services -->
        <!-- Certification -->
        <div class="col-md-6">
            <div class="d-flex p-3 rounded text-white" style="background-color: #2c3e50;">
                <div class="me-3">
                    <i class="bi bi-card-text fs-1"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Certification</h5>
                    <p class="mb-0">An official document issued by the barangay to confirm a resident’s identity, residency, or specific status.</p>
                </div>
            </div>
        </div>

        <!-- Business Permit -->
        <div class="col-md-6">
            <div class="d-flex p-3 rounded text-white" style="background-color: #27ae60;">
                <div class="me-3">
                    <i class="bi bi-shop fs-1"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Business Permit</h5>
                    <p class="mb-0">An official authorization issued by the barangay that allows a business to operate legally within the community.</p>
                </div>
            </div>
        </div>

        <!-- Katarungang Pambarangay -->
        <div class="col-md-6">
            <div class="d-flex p-3 rounded text-white" style="background-color: #7ac142;">
                <div class="me-3">
                    <i class="bi bi-balance-scale fs-1"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Katarungang Pambarangay</h5>
                    <p class="mb-0">A community-based justice system in the barangay that helps settle disputes peacefully.</p>
                </div>
            </div>
        </div>

        <!-- Environmental Services -->
        <div class="col-md-6">
            <div class="d-flex p-3 rounded text-white" style="background-color: #2c3e50;">
                <div class="me-3">
                    <i class="bi bi-tree fs-1"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Environmental Services</h5>
                    <p class="mb-0">Programs and initiatives to help maintain cleanliness, proper waste management, and environmental protection.</p>
                </div>
            </div>
        </div>

        <!-- Cash Incentives -->
        <div class="col-md-6">
            <div class="d-flex p-3 rounded text-white" style="background-color: #27ae60;">
                <div class="me-3">
                    <i class="bi bi-cash-stack fs-1"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Cash Incentives</h5>
                    <p class="mb-0">Offering cash incentives to recognize and reward outstanding students for academic excellence.</p>
                </div>
            </div>
        </div>
    </div>
</div>
