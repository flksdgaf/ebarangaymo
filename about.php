<?php 
$page = 'index';
include 'includes/header.php'; 
?>

<link rel="stylesheet" href="about.css">

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

<!-- BARANGAY OFFICIALS SECTION -->
<div id="officials" class="container custom-padding">
    <div>
        <h2 class="text-uppercase fw-bold gradient-text">Barangay Officials</h2>
        <p>This section presents the duly elected officials of Barangay Magang, Daet, Camarines Norte. These public servants are entrusted with the responsibility of leading the barangay, implementing policies, and ensuring the delivery of essential services for the welfare and development of the community.</p>
    </div>

    <div>
        <img src="images/barangay_officials.png" alt="Barangay Officials" class="w-100">
    </div>
</div>

<!-- MISSION VISION SECTION -->
<div id="mission-vision" class="container custom-padding">
    <h2 class="text-uppercase fw-bold gradient-text mb-4">Mission and Vision</h2>
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

<!-- CITIZENS CHARTER SECTION -->
<div id="citizens-charter" class="container custom-padding">
    <div>
        <h2 class="text-uppercase fw-bold gradient-text">Citizen's Charter</h2>
        <p>The Citizen's Charter  outlines the commitment of the barangay Magang to provide efficient, transparent, and accountable public service. It serves as a guide for residents on the available services, step-by-step procedures, requirements, processing time, and contact information. This charter reflects our dedication to upholding the rights of every constituent and ensuring quality service delivery.</p>
    </div>

    <div>
        <img src="images/citizens_charter.png" alt="Barangay Magang Citizens Charter" class="w-100">
    </div>
</div>

<!-- BARANGAY MAP SECTION -->
<div id="barangay-map" class="container custom-padding">
    <div>
        <h2 class="text-uppercase fw-bold gradient-text">Barangay Map</h2>
        <p>This map serves as a visual guide for residents, visitors, and service planning within the community.</p>
    </div>

    <div>
        <img src="images/barangay_map.png" alt="Barangay Magang Spot Map" class="w-100">
    </div>
</div>

<!-- CONTACT US SECTION -->
<div id="contact-us" class="container custom-padding">
    <div>
        <div class="col-lg-10">
            <h2 class="text-uppercase fw-bold gradient-text">Contact Us</h2>
            <p>If you have any questions or need assistance, please fill out the form below and we will get back to you as soon as possible.</p>
            <form action="contact_process.php" method="post">
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="Your Name" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="@email.com" required>
                </div>
                <div class="mb-3">
                    <label for="message" class="form-label">Message</label>
                    <textarea class="form-control" id="message" name="message" rows="5" placeholder="Your Message" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        </div>
    </div>
</div>

<?php
    include 'includes/footer.php';
?>
