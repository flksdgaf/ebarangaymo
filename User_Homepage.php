<?php 
session_start();
include 'functions/dbconn.php'; 
$page = 'user_homepage';
include 'includes/header.php'; 
?>

<!-- BANNER SECTION -->
<div class="container-fluid px-0">
    <img src="images/userbanner.png" alt="Banner" class="img-fluid w-auto">    
</div>

<!-- CAROUSEL SECTION -->
<div class="carousel-wrapper">
    <div class="carousel-blur-bg"></div> 

    <div class="carousel-container">
        <div id="carouselExampleIndicators" class="carousel slide">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
            </div>

            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="images/carousel.png" class="d-block w-100 carousel-image" alt="...">
                </div>
                <div class="carousel-item">
                    <img src="images/carousel.png" class="d-block w-100 carousel-image" alt="...">
                </div>
                <div class="carousel-item">
                    <img src="images/carousel.png" class="d-block w-100 carousel-image" alt="...">
                </div>
            </div>

            <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>

            <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
</div>

<!-- ABOUT US SECTION -->
<div class="container-fluid px-0">
    <div class="about-container text-center">
        <h1 class="gradient-text mt-2">ABOUT US</h1>
        <p class="w-50 mx-auto text-center mt-3">
            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
        </p>

        <div class="row g-4 mt-4 justify-content-center text-center">
            <div class="col-lg-3 col-md-5 col-sm-6 d-flex justify-content-center">
                <div class="card-custom p-4">
                    <h5 class="gradient-text">Mission</h5>
                    <p>
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris...
                    </p>
                </div>
            </div>
            <div class="col-lg-3 col-md-5 col-sm-6 d-flex justify-content-center">
                <div class="card-custom p-4">
                    <h5 class="gradient-text">Vision</h5>
                    <p>
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris...
                    </p>
                </div>
            </div>
            <div class="col-lg-3 col-md-5 col-sm-6 d-flex justify-content-center">
                <div class="card-custom p-4">
                    <h5 class="gradient-text">Values</h5>
                    <p>
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris...
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SERVICE SECTION -->
<div class="container-fluid mt-5 services-container">
    <h1 class="text-center gradient-text">SERVICES</h1>
    <div class="container mt-5">
        <div class="row row-cols-1 row-cols-md-2 g-3">
            <div class="col d-flex">
                <a href="#" class="service-card mid-green w-100">
                    <i class="fas fa-id-card icon"></i>
                    <div>
                        <h4>Barangay ID</h4>
                        <p>An official identification card issued by the barangay.</p>
                    </div>
                </a>
            </div>
            <div class="col d-flex">
                <a href="#" class="service-card light-green w-100">
                    <i class="fas fa-file-alt icon"></i>
                    <div>
                        <h4>Barangay Clearance</h4>
                        <p>Certifies a resident has no pending issues.</p>
                    </div>
                </a>
            </div>
            <div class="col d-flex">
                <a href="#" class="service-card dark-green w-100">
                    <i class="fas fa-certificate icon"></i>
                    <div>
                        <h4>Certification</h4>
                        <p>Confirms a resident's identity, residency, or status.</p>
                    </div>
                </a>
            </div>
            <div class="col d-flex">
                <a href="#" class="service-card mid-green w-100">
                    <i class="fas fa-store icon"></i>
                    <div>
                        <h4>Business Permit</h4>
                        <p>Authorization to operate within the barangay.</p>
                    </div>
                </a>
            </div>
            <div class="col d-flex">
                <a href="#" class="service-card light-green w-100">
                    <i class="fas fa-balance-scale icon"></i>
                    <div>
                        <h4>Katarungang Pambarangay</h4>
                        <p>Barangay justice system for settling disputes.</p>
                    </div>
                </a>
            </div>
            <div class="col d-flex">
                <a href="#" class="service-card dark-green w-100">
                    <i class="fas fa-leaf icon"></i>
                    <div>
                        <h4>Environmental Services</h4>
                        <p>Programs for cleanliness and waste management.</p>
                    </div>
                </a>
            </div>
        </div>
        <a href="#" class="view-more">View More Services</a>
    </div>
</div>

<!-- NEWS AND UPDATES SECTION -->
<div class="container-fluid px-4 mt-5">
    <div class="row align-items-center">
        <!-- Title Section -->
        <div class="col-md-5">
            <h2 class="fw-bold text-success">NEWS AND UPDATES</h2>
        </div>

        <!-- Scrollable Content Section -->
        <div class="col-md-7">
            <div class="scrollable-content d-flex flex-row">
                <div class="news-card">
                    <img src="images/carousel.png" alt="News Image">
                    <p>Breaking News 1: Lorem ipsum dolor sit amet.</p>
                </div>
                <div class="news-card">
                    <img src="images/carousel.png" alt="News Image">
                    <p>Breaking News 2: Lorem ipsum dolor sit amet.</p>
                </div>
                <div class="news-card">
                    <img src="images/carousel.png" alt="News Image">
                    <p>Breaking News 3: Lorem ipsum dolor sit amet.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
    include 'includes/footer.php'; 
?> 

<script src="js/carousel.js"></script>
