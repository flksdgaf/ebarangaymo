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
        <img src="images/about_banner.png" alt="About Banner" class="img-fluid w-100">
        
        <!-- Overlay content -->
        <div class="position-absolute top-50 start-50 translate-middle">
            <h1 class="fw-semibold text-uppercase">About Us</h1>
            <p>Home / About</p>
        </div>
    </div>
</div>

<!-- EBARANGAY INFO SECTION -->
<div class="container py-5">
  <div class="row align-items-center">
    <!-- Text Content -->
    <div class="col-lg-6 mb-4 mb-lg-0">
      <h2 class="fw-bold mb-2">
        Fast. Easy. <span class="gradient-text">eBarangay Mo.</span>
      </h2>
      <h5 class="gradient-text mb-4">
        Bringing Barangay Services Closer to You.
      </h5>
      <p><strong>eBarangay Mo</strong> is the online portal of Barangay Magang, Daet, Camarines Norte, developed to bring essential barangay services closer and more accessible to the community. This digital platform aims to simplify and modernize the way residents interact with their local government.</p>
      <p>Through eBarangay Mo, residents can conveniently access a range of services — from applying for business permits and requesting certificates, to checking the status of transactions — all from the comfort of their homes, anytime and anywhere. It is our goal to improve transparency, efficiency, and public service delivery by embracing technology that meets the growing needs of our barangay.</p>
    </div>

    <!-- Image Grid -->
    <div class="col-lg-6">
      <div class="row g-2">
        <div class="info-image col-6">
          <img src="images/info_image.png" alt="Event 1" class="img-fluid rounded-4 shadow img-size">
        </div>
        <div class="info-image2 col-6 ml-2">
          <img src="images/info_image2.png" alt="Event 2" class="img-fluid rounded-4 shadow img-size">
        </div>
        <div class="info-image4 col-12">
          <img src="images/info_image4.png" alt="Event 3" class="img-fluid rounded-4 shadow img-size">
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container">
    <div>
        <h2 class="text-uppercase fw-bold gradient-text">Officials</h2>
        <p>This section presents the duly elected officials of Barangay Magang, Daet, Camarines Norte. These public servants are entrusted with the responsibility of leading the barangay, implementing policies, and ensuring the delivery of essential services for the welfare and development of the community.</p>
    </div>

    <div>
        <img src="images/barangay_officials.png" alt="Barangay Officials" class="w-100">
    </div>
</div>

<!-- MISSION VISION VALUES SECTION -->
<!-- <div class="container-fluid px-0">
    <div class="mission-vision-values text-center">
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
</div> -->

<!-- MISSION VISION SECTION -->
<div class="container custom-padding">
    <div class="row justify-content-center align-items-stretch g-4 text-center">
        <!-- Mission Card -->
        <div class="col-lg-5 col-md-6 d-flex">
            <div class="card-custom mission-shape w-100 d-flex flex-column justify-content-center">
                <h4 class="section-title mb-3">MISSION</h4>
                <p class="mb-0">
                    "We members of of Sangguniang Barangay will continue to strive more to effectively deliver basic services needed by the people, promote peace and order, protect the interest, promote social and economic development in pursuit of peaceful reliant towards a develop and progressive community within a just VARI social order."
                </p>
            </div>
        </div>

        <!-- Vision Card -->
        <div class="col-lg-5 col-md-6 d-flex">
            <div class="card-custom vision-shape w-100 d-flex flex-column justify-content-center">
                <h4 class="section-title mb-3">VISION</h4>
                <p class="mb-0">
                    "Barangay Magang is one of the most widely competitive community in Daet with well-developed, self-reliant, vigorously, God fearing and empowered people, economically adequate with expensive infrastructure facilities an and ecologically balance environment governed by effective and service centered leaders ready to implement the Good Governance and Ethical Leadership."
                </p>
            </div>
        </div>
    </div>
</div>



<div class="container mb-5">
    <div>
        <h2 class="text-uppercase fw-bold gradient-text">Citizens Charter</h2>
        <p>The Citizen's Charter  outlines the commitment of the barangay Magang to provide efficient, transparent, and accountable public service. It serves as a guide for residents on the available services, step-by-step procedures, requirements, processing time, and contact information. This charter reflects our dedication to upholding the rights of every constituent and ensuring quality service delivery.</p>
    </div>

    <div>
        <img src="images/citizens_charter.png" alt="Barangay Magang Citizens Charter" class="w-100">
    </div>
</div>

<div class="container mb-5">
    <div>
        <h2 class="text-uppercase fw-bold gradient-text">Barangay Map</h2>
        <p>This map serves as a visual guide for residents, visitors, and service planning within the community.</p>
    </div>

    <div>
        <img src="images/barangay_map.png" alt="Barangay Magang Spot Map" class="w-100">
    </div>
</div>

<?php
    include 'includes/footer.php';
?>
