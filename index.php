<?php 
include 'functions/dbconn.php'; 
$page = 'index';
include 'includes/header.php'; 
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

<!-- MISSION VISION VALUES SECTION -->
<div class="container-fluid px-0">
    <div class="mission-vision-values text-center">
        <div class="row g-4 mt-4 justify-content-center text-center">
            <div class="col-lg-5 col-md-6 col-sm-10 d-flex justify-content-center">
                <div class="card-custom p-4">
                    <h5 class="gradient-text text-uppercase">Mission</h5>
                    <p>
                        "We members of Sangguniang Barangay will continue to strive more to effectively deliver basic services needed by the people, promote peace and order, protect the interest, promote social and economic development in pursuit of peaceful reliant towards a develop and progressive community within a just VARI social order."
                    </p>
                </div>
            </div>
            <div class="col-lg-5 col-md-6 col-sm-10 d-flex justify-content-center">
                <div class="card-custom p-4">
                    <h5 class="gradient-text text-uppercase">Vision</h5>
                    <p>
                    "Barangay Magang is one of the most widely competitive community in Daet with well-developed, self-reliant, vigorously, God fearing and empowered people, economically adequate with expensive infrastructure facilities an and ecologically balance environment governed by effective and service centered leaders ready to implement the Good Governance and Ethical Leadership."
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SERVICE SECTION -->
<div class="container-fluid mt-5 mb-5 services-container">
  <h1 class="text-center gradient-text">SERVICES</h1>
  <div class="container mt-5">
    <div class="row row-cols-1 row-cols-md-2 g-3">
      <!-- Barangay ID Button -->
      <div class="col d-flex">
        <button type="button" class="service-card mid-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#barangayIDModal">
          <i class="fas fa-id-card icon"></i>
          <div>
            <h4>Barangay ID</h4>
            <p>An official identification card issued by the barangay.</p>
          </div>
        </button>
      </div>
      <!-- Barangay Clearance Button -->
      <div class="col d-flex">
        <button type="button" class="service-card light-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#barangayClearanceModal">
          <i class="fas fa-file-alt icon"></i>
          <div>
            <h4>Barangay Clearance</h4>
            <p>Certifies a resident has no pending issues.</p>
          </div>
        </button>
      </div>
      <!-- Certification Button -->
      <div class="col d-flex">
        <button type="button" class="service-card dark-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#certificationModal">
          <i class="fas fa-certificate icon"></i>
          <div>
            <h4>Certification</h4>
            <p>Confirms a resident's identity, residency, or status.</p>
          </div>
        </button>
      </div>
      <!-- Business Permit Button -->
      <div class="col d-flex">
        <button type="button" class="service-card mid-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#businessPermitModal">
          <i class="fas fa-store icon"></i>
          <div>
            <h4>Business Permit</h4>
            <p>Authorization to operate within the barangay.</p>
          </div>
        </button>
      </div>
      <!-- Katarungang Pambarangay Button -->
      <div class="col d-flex">
        <button type="button" class="service-card light-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#katarungangModal">
          <i class="fas fa-balance-scale icon"></i>
          <div>
            <h4>Katarungang Pambarangay</h4>
            <p>Barangay justice system for settling disputes.</p>
          </div>
        </button>
      </div>
      <!-- Environmental Services Button -->
      <div class="col d-flex">
        <button type="button" class="service-card dark-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#equipmentModal">
        <i class="fas fa-chair icon"></i>
          <div>
            <h4>Equipment Borrowing Services</h4>
            <p>Provides equipment rental services.</p>
          </div>
        </button>
      </div>
    </div>
    <a href="services.php" class="view-more">View More Services</a>
  </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Barangay ID Modal -->
<div class="modal fade" id="barangayIDModal" tabindex="-1" aria-labelledby="barangayIDModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="barangayIDModalLabel">How to Get a Barangay ID</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ol>
          <li>Log in your account to the eBarangay Mo website.</li>
          <li>Click the "Services" tab in the left sidebar and select "Barangay ID".</li>
          <li>Fill out the form with your personal details.</li>
          <li>Double check your information and choose a payment option.</li>
          <li>Submit your application</li> 
          <li>Wait for the verification and processing of your application.</li>
          <li>Collect your Barangay ID once notified or the date specified.</li>
        </ol>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Barangay Clearance Modal -->
<div class="modal fade" id="barangayClearanceModal" tabindex="-1" aria-labelledby="barangayClearanceModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="barangayClearanceModalLabel">How to Get a Barangay Clearance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ol>
          <li>Log in your account to the eBarangay Mo website.</li>
          <li>Click the "Services" tab in the left sidebar and select "Barangay Clearance".</li>
          <li>Fill out the form with your personal details.</li>
          <li>Double check your information and choose a payment option.</li>
          <li>Submit your application</li> 
          <li>Wait for the verification and processing of your application.</li>
          <li>Collect your Barangay Clearance once notified or the date specified.</li>
        </ol>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Barangay Certification Modal -->
<div class="modal fade" id="barangayCertificationModal" tabindex="-1" aria-labelledby="barangayCertificationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="barangayCertificationModalLabel">How to Get a Barangay Certification</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ol>
          <li>Log in your account to the eBarangay Mo website.</li>
          <li>Click the "Apply for Barangay ID" button.</li>
          <li>Fill out the form with your personal details.</li>
          <li>Double check your information and choose a payment option.</li>
          <li>Submit your application</li> 
          <li>Wait for the verification and processing of your application.</li>
          <li>Collect your Barangay ID once notified or the date specified.</li>
        </ol>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Barangay Business Permit Modal -->
<div class="modal fade" id="businessPermitModal" tabindex="-1" aria-labelledby="businessPermitModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="businessPermitModalLabel">How to Get a Barangay Business Permit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ol>
          <li>Log in your account to the eBarangay Mo website.</li>
          <li>Click the "Apply for Barangay ID" button.</li>
          <li>Fill out the form with your personal details.</li>
          <li>Double check your information and choose a payment option.</li>
          <li>Submit your application</li> 
          <li>Wait for the verification and processing of your application.</li>
          <li>Collect your Barangay ID once notified or the date specified.</li>
        </ol>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Katarungang Pambarangay Modal -->
<div class="modal fade" id="katarungangModal" tabindex="-1" aria-labelledby="katarungangModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="katarungangModalLabel">How to Apply for a Katarungang Pambarangay</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ol>
          <li>Log in your account to the eBarangay Mo website.</li>
          <li>Click the "Apply for Barangay ID" button.</li>
          <li>Fill out the form with your personal details.</li>
          <li>Double check your information and choose a payment option.</li>
          <li>Submit your application</li> 
          <li>Wait for the verification and processing of your application.</li>
          <li>Collect your Barangay ID once notified or the date specified.</li>
        </ol>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Equipment Borrowing Modal -->
<div class="modal fade" id="equipmentModal" tabindex="-1" aria-labelledby="equipmentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="equipmentModalLabel">How to Apply for an Equipment Borrowing</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ol>
          <li>Log in your account to the eBarangay Mo website.</li>
          <li>Click the "Apply for Barangay ID" button.</li>
          <li>Fill out the form with your personal details.</li>
          <li>Double check your information and choose a payment option.</li>
          <li>Submit your application</li> 
          <li>Wait for the verification and processing of your application.</li>
          <li>Collect your Barangay ID once notified or the date specified.</li>
        </ol>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- NEWS AND UPDATES SECTION -->
<div class="container-fluid px-4 mt-5 mb-5 new-updates-container">
    <div class="row align-items-center">
        <!-- Title Section -->
        <div class="col-md-5">
            <h1 class="gradient-text">NEWS AND UPDATES</h1>
        </div>

        <!-- Scrollable Content Section -->
        <div class="col-md-7 news-scrollable">
            <div class="scrollable-content d-flex flex-row">
                <div class="news-card">
                    <img src="images/news_1.png" alt="News Image">
                    <p>February 12, 2025</p>
                    <p>Camarines Norte Sets Highest Number of SGLGB Passers</p>
                </div>
                <div class="news-card">
                    <img src="images/news_2.png" alt="News Image">
                    <p">February 15, 2025</p>
                    <p>Barangay Magang Wins in Search for Child Friendly Barangay 2024</p>
                </div>
                <div class="news-card">
                    <img src="images/news_3.png" alt="News Image">
                    <p">February 20, 2025</p>
                    <p>Philsys On-going Registration</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ABOUT SECTION -->
<div class="container-fluid">
    <div class="row align-items-center">
        <!-- About Text -->
        <div class="col-md-6">
            <div class="p-4 text-white rounded about-text">
                <h1 class="fw-bold">ABOUT</h1>
                <p class="mt-3">
                    <strong>eBarangay Mo</strong> is a digital platform designed to streamline barangay services, 
                    providing residents with easy access to requests, applications, and community updates. 
                    It enhances efficiency, transparency, and convenience in local governance through secure 
                    and user-friendly online transactions.
                </p>
            </div>
        </div>
        
        <!-- About Image -->
        <div class="col-md-6 text-center">
            <div class="about-image-container">
                <img src="images/about_image.png" alt="eBarangay Mo Platform" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<!-- INSTRUCTION SECTION -->
<div class="container text-center py-5 instruction-container">
    <h1 class="gradient-text">SIGN UP NOW</h1>
    <p class="steps">Follow these four simple steps:</p>

    <div class="row text-center mt-4">
        <!-- Step 1 -->
        <div class="col-md-3">
            <img src="images/step_1.png" alt="Visit the Website" class="img-fluid mx-auto d-block w-75">
            <h4 class="mt-3 fw-bold">Visit the Website</h4>
            <hr class="underline">
            <p>Go to the eBarangay Mo website and click <strong>Sign Up</strong>.</p>
        </div>

        <!-- Step 2 -->
        <div class="col-md-3">
            <img src="images/step_2.png" alt="Register an Account" class="img-fluid mx-auto d-block w-75">
            <h4 class="mt-3 fw-bold">Register an Account</h4>
            <hr class="underline">
            <p>Fill out the registration form with your required personal information.</p>
        </div>

        <!-- Step 3 -->
        <div class="col-md-3">
            <img src="images/step_3.png" alt="Wait for Verification" class="img-fluid mx-auto d-block w-75">
            <h4 class="mt-3 fw-bold">Wait for Verification</h4>
            <hr class="underline">
            <p>Wait for the notification in verifying the account you created.</p>
        </div>

        <!-- Step 4 -->
        <div class="col-md-3">
            <img src="images/step_4.png" alt="Avail Barangay Services" class="img-fluid mx-auto d-block w-75">
            <h4 class="mt-3 fw-bold">Avail Barangay Services</h4>
            <hr class="underline">
            <p>Once your account is verified, you can now submit a request for your desired service.</p>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
<script src="js/carousel.js"></script>

<?php 
    include 'includes/footer.php'; 
?> 
