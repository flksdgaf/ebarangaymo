<?php 
include 'functions/dbconn.php'; 
$page = 'user_homepage';
include 'includes/user_header.php'; 

// Ensure the user is logged in and is not an admin (constituent)
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: index.php");
    exit();
}

// Retrieve the user's account id from session
$userId = $_SESSION['loggedInUserID'];

?>

<!-- BANNER SECTION -->
<div class="container-fluid px-0 position-relative">
  <!-- Background image -->
  <img src="images/landing_banner.png" alt="Banner" class="img-fluid w-100 banner-image">

  <!-- Content overlay -->
  <div class="position-absolute banner-overlay text-white text-center">
    <div class="container">

      <!-- DESKTOP VIEW -->
      <div class="row align-items-center justify-content-center d-none d-md-flex">
        <div class="col-md-3 text-end">
          <img src="images/good_governance_logo.png" alt="Left Logo" class="img-fluid" style="max-width: 100px;">
        </div>
        <div class="col-md-3 text-center">
          <h6 class="mb-1">Republic of the Philippines</h6>
          <hr class="mx-auto" style="width: 80%; border-top: 2px solid white; opacity: 1; margin: 0;">
          <h1 class="fw-semibold">eBarangay Mo</h1>
          <p class="mb-0">BARANGAY SERVICES PORTAL OF <br> DAET, CAMARINES NORTE</p>
        </div>
        <div class="col-md-3 text-start">
          <img src="images/magang_logo.png" alt="Right Logo" class="img-fluid" style="max-width: 100px;">
        </div>
      </div>

      <!-- MOBILE VIEW -->
      <div class="d-flex d-md-none flex-column align-items-center">
        <div class="d-flex justify-content-center gap-3 mb-3">
          <img src="images/good_governance_logo.png" alt="Left Logo" class="img-fluid">
          <img src="images/magang_logo.png" alt="Right Logo" class="img-fluid">
        </div>
        <div class="text-center">
          <h6 class="mb-1 small">Republic of the Philippines</h6>
          <hr class="mx-auto" style="width: 60%; border-top: 2px solid white; opacity: 1; margin: 0;">
          <h3 class="fw-semibold my-2">eBarangay Mo</h3>
          <p class="mb-0 small">BARANGAY SERVICES PORTAL OF <br> DAET, CAMARINES NORTE</p>
        </div>
      </div>
    </div>
  </div>
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
            <h2 class="fw-bold text-success mx-40">NEWS AND UPDATES</h2>
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

