<?php 
$page = 'index';
include 'includes/header.php'; 
?>

<link rel="stylesheet" href="services.css">

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
        <div class="col-md-6">
            <a href="#" class="text-decoration-none br-10px">
                <div class="barangay-id d-flex p-3 rounded text-white">
                <div class="me-3">
                    <img src="images/barangay_id.png" alt="Barangay ID Icon" class="barangay-id-icon">
                </div>
                <div>
                    <h5 class="barangay-id-title fw-bold mb-1">Barangay ID</h5>
                    <p class="mb-0">An official identification card issued by the barangay that serves as proof of residency and identity.</p>
                </div>
                </div>
            </a>
        </div>


        <!-- BARANGAY CLEARANCE -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="barangay-clearance d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <img src="images/barangay_clearance.png" alt="Barangay Clearance Icon" class="barangay-clearance-icon">  
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Barangay Clearance</h5>
                        <p class="mb-0">An official document that certifies a resident has no pending issues in the barangay.</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- CERTIFICATION -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="certification d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <img src="images/certification.png" alt="Certification Icon" class="certification-icon"> 
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Certification</h5>
                        <p class="mb-0">An official document issued by the barangay to confirm a resident’s identity, residency, or specific status.</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- BUSINESS PERMIT -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="business-permit d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <img src="images/business_permit.png" alt="Business Permit Icon" class="business-permit-icon"> 
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Business Permit</h5>
                        <p class="mb-0">An official authorization issued by the barangay that allows a business to operate legally within the community.</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- KATARUNGANG PAMBARANGAY -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="katarungang-pambarangay d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <img src="images/katarungang_pambarangay.png" alt="Katarungang Pambarangay Icon" class="katarungang-pambarangay-icon">
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Katarungang Pambarangay</h5>
                        <p class="mb-0">A community-based justice system in the barangay that helps settle disputes peacefully.</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- ENVIRONMENTAL SERVICES -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="environmental-services d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <img src="images/environmental_services.png" alt="Environmental Services Icon" class="environmental-services-icon">
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Environmental Services</h5>
                        <p class="mb-0">Programs and initiatives to help maintain cleanliness, proper waste management, and environmental protection.</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- CASH INCENTIVES -->
        <div class="col-md-6">
            <a href="#" class="text-decoration-none">
                <div class="cash-incentives d-flex p-3 rounded text-white">
                    <div class="me-3">
                        <img src="images/cash_incentives.png" alt="Cash Incentives Icon" class="cash-incentives-icon">
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Cash Incentives</h5>
                        <p class="mb-0">Offering cash incentives to recognize and reward outstanding students for academic excellence.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
