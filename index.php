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
        <div class="col-md-3 offset-md-1 text-end">
          <img src="images/magang_logo.png" alt="Barangay Logo" class="img-fluid" style="max-width: 110px;">
        </div>
        <div class="col-md-6 text-start">
          <h6 class="mb-2">Republic of the Philippines</h6>
          <hr class="my-1" style="width: 55%; border-top: 2px solid white; opacity: 1; margin-left: 0;">
          <h2 class="fw-bold my-0" aria-label="Barangay Name">BARANGAY MAGANG</h2>
          <p class="mt-0 mb-0" aria-label="Barangay Address">Daet, Camarines Norte, Philippines</p>
        </div>
      </div>
    </div>
      
    <!-- MOBILE VIEW -->
    <div class="d-flex d-md-none flex-column align-items-center">
      <div class="d-flex justify-content-center gap-3 mb-3">
        <img src="images/magang_logo.png" alt="Brgy. Magang Logo" class="img-fluid">
      </div>
      <div class="text-center">
        <h6 class="mb-1 small">Republic of the Philippines</h6>
        <hr class="my-1" style="width: 100%; border-top: 2px solid white; opacity: 1; margin: 0;">
        <h3 class="fw-bold my-0">BARANGAY MAGANG</h3>
        <p class="mt-0 mb-0">Daet, Camarines Norte, Philippines</p>
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
<div class="container-fluid mt-5 mb-5 services-container">
  <h1 class="text-center gradient-text text-uppercase">Services Offered</h1>
    <div class="container mt-5">
        <div class="row row-cols-1 row-cols-md-2 g-3">
            <div class="col d-flex">
                <button type="button" class="service-card light-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#barangayIDModal">
                    <i class="fas fa-id-card icon"></i>
                    <div>
                        <h4>Barangay ID</h4>
                        <p>
                          Opisyal na identification card na inilalaan ng barangay.
                        </p>
                    </div>
                </button>
            </div>
            <div class="col d-flex">
                  <button type="button" class="service-card mid-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#barangayClearanceModal">
                    <i class="fas fa-file-alt icon"></i>
                    <div>
                        <h4>Barangay Clearance</h4>
                        <p>
                          Patunay na ang residente ay walang nakabinbing isyu.
                        </p>
                    </div>
                  </button>
            </div>
            <div class="col d-flex">
                <button type="button" class="service-card dark-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#certificationModal">
                    <i class="fas fa-certificate icon"></i>
                    <div>
                        <h4>Certification</h4>
                        <p>
                          Patunay ng pagkakakilanlan, paninirahan, o katayuan ng residente.
                        </p>
                    </div>
                </button>
            </div>
            <div class="col d-flex">
                <button type="button" class="service-card light-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#businessPermitModal">
                    <i class="fas fa-store icon"></i>
                    <div>
                        <h4>Business Permit</h4>
                        <p>
                          Pahintulot para mag‑operate sa loob ng barangay.
                        </p>
                    </div>
                </button>
            </div>
            <!-- <div class="col d-flex">
                <button type="button" class="service-card mid-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#barangayIDModal">
                    <i class="fas fa-balance-scale icon"></i>
                    <div>
                        <h4>Katarungang Pambarangay</h4>
                        <p>
                          Sistema ng katarungan ng barangay para sa pag‑aayos ng alitan.
                        </p>
                    </div>
                </button>
            </div> -->
            <div class="col d-flex">
                <button type="button" class="service-card mid-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#equipmentModal">
                    <i class="fas fa-chair icon"></i>
                    <div>
                        <h4>Equipment Borrowing</h4>
                        <p>
                          Paghiram ng mga barangay equipments.
                        </p>
                    </div>
                </button>
            </div>
            <div class="col d-flex">
                <button type="button" class="service-card dark-green w-100 border-0" data-bs-toggle="modal" data-bs-target="#cashModal">
                    <i class="fas fa-money-bill cash_icon"></i>
                    <div>
                        <h4>Cash Incentives</h4>
                        <p>
                          Insentibong salapi para sa mga natatanging mag-aaral.
                        </p>
                    </div>
                </button>
            </div>
        </div>
      </div>
    <!-- <a href="services.php" class="view-more">View More Services</a> -->
</div>

<!-- Barangay ID Modal -->
<div class="modal fade" id="barangayIDModal" tabindex="-1" aria-labelledby="barangayIDModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="barangayIDModalLabel">Barangay ID</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6>Narito ang flowchart na nagpapakita ng mga hakbang sa pagkuha ng Barangay ID.</br></br></h6>
        <img src="images/flowchart_barangay_id.png" alt="Barangay ID Process Flowchart" class="img-fluid rounded">
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
        <h5 class="modal-title" id="barangayClearanceModalLabel">Barangay Clearance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6>Narito ang flowchart na nagpapakita ng mga hakbang sa pagkuha ng Barangay Clearance.</br></br></h6>
        <img src="images/flowchart_barangay_clearance.png" alt="Barangay Clearance Process Flowchart" class="img-fluid rounded">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Barangay Certification Modal -->
<div class="modal fade" id="certificationModal" tabindex="-1" aria-labelledby="barangayCertificationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="barangayCertificationModalLabel">Barangay Certification</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6>Narito ang flowchart na nagpapakita ng mga hakbang sa pagkuha ng Barangay Certification.</br></br></h6>
        <img src="images/flowchart_barangay_certification.png" alt="Barangay Certification Process Flowchart" class="img-fluid rounded">
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
        <h5 class="modal-title" id="businessPermitModalLabel">Barangay Business Permit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6>Narito ang flowchart na nagpapakita ng mga hakbang sa pagkuha ng Barangay Business Permit.</br></br></h6>
        <img src="images/flowchart_business_permit.png" alt="Barangay Business Permit Process Flowchart" class="img-fluid rounded">
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
        <h5 class="modal-title" id="equipmentModalLabel">Equipment Borrowing</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6>Narito ang flowchart na nagpapakita ng mga hakbang sa Barangay Equipment Borrowing .</br></br></h6>
        <img src="images/flowchart_equipment_borrowing.png" alt="Barangay Equipment Borrowing Process Flowchart" class="img-fluid rounded">
        <!-- <ol>
          <li>Log in your account to the eBarangay Mo website.</li>
          <li>Click the "Apply for Barangay ID" button.</li>
          <li>Fill out the form with your personal details.</li>
          <li>Double check your information and choose a payment option.</li>
          <li>Submit your application</li> 
          <li>Wait for the verification and processing of your application.</li>
          <li>Collect your Barangay ID once notified or the date specified.</li>
        </ol> -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Cash Incentives Modal -->
<div class="modal fade" id="cashModal" tabindex="-1" aria-labelledby="cashModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="cashModalLabel">Cash Incentives</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6>Narito ang flowchart na nagpapakita ng mga hakbang sa pag-apply sa Cash Incentives program.</br></br></h6>
        <img src="images/flowchart_cash_incentives.png" alt="Cash Incentives Application Process Flowchart" class="img-fluid rounded">
        <!-- <ol>
          <li>Log in your account to the eBarangay Mo website.</li>
          <li>Click the "Apply for Barangay ID" button.</li>
          <li>Fill out the form with your personal details.</li>
          <li>Double check your information and choose a payment option.</li>
          <li>Submit your application</li> 
          <li>Wait for the verification and processing of your application.</li>
          <li>Collect your Barangay ID once notified or the date specified.</li>
        </ol> -->
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
                    <strong>eBarangay Mo</strong> ay isang digital platform na dinisenyo upang gawing mas 
                    maayos ang mga serbisyo ng barangay, na nagbibigay sa mga residente ng madaling access 
                    sa kanilang mga kahilingan, aplikasyon, at balita sa komunidad. Pinapabuti nito ang 
                    kahusayan, transparency, at kaginhawahan sa lokal na pamamahala sa pamamagitan ng ligtas 
                    at madaling gamitin na online na transaksyon.
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
            <p>Pumunta sa website ng eBarangay Mo at i‑click ang <strong><a href="signup.php" class="text-decoration-none text-black sign-up-text">Sign Up</a></strong>.</p>
        </div>

        <!-- Step 2 -->
        <div class="col-md-3">
            <img src="images/step_2.png" alt="Register an Account" class="img-fluid mx-auto d-block w-75">
            <h4 class="mt-3 fw-bold">Register an Account</h4>
            <hr class="underline">
            <p>Kumpletuhin ang form sa pagregister gamit ang kinakailangang personal na impormasyon.</p>
        </div>

        <!-- Step 3 -->
        <div class="col-md-3">
            <img src="images/step_3.png" alt="Wait for Verification" class="img-fluid mx-auto d-block w-75">
            <h4 class="mt-3 fw-bold">Wait for Verification</h4>
            <hr class="underline">
            <p>Maghintay ng notification para sa verification ng iyong account.</p>
        </div>

        <!-- Step 4 -->
        <div class="col-md-3">
            <img src="images/step_4.png" alt="Avail Barangay Services" class="img-fluid mx-auto d-block w-75">
            <h4 class="mt-3 fw-bold">Avail Barangay Services</h4>
            <hr class="underline">
            <p>Kapag na‑verify na ang iyong account, maaari ka nang magsubmit ng request para sa serbisyong nais mo.</p>
        </div>
    </div>
</div>


<script src="js/carousel.js"></script>

<?php 
    include 'includes/footer.php'; 
?> 