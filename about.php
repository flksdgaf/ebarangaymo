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
<div class="about-page-container">
    <div class="container py-5">
    <div class="row align-items-center">
        <!-- Text Content -->
        <div class="col-lg-6 mb-4 mb-lg-0">
        <h2 class="tagline fw-bold mb-2">
            Fast. Easy. <span class="gradient-text">eBarangay Mo.</span>
        </h2>
        <h5 class="gradient-text mb-4">
            Bringing Barangay Services Closer to You.
        </h5>
        <p>Ang <strong>eBarangay Mo</strong> ay ang online portal ng Barangay Magang, Daet, Camarines Norte, na binuo upang mas mapalapit at madaliang ma‑access ng komunidad ang mahahalagang serbisyo ng barangay. Layunin ng digital na plataporma na ito na pasimplehin at i‑modernisa ang paraan ng pakikipag‑ugnayan ng mga residente sa kanilang lokal na pamahalaan.</p>
        <p>Sa pamamagitan ng eBarangay Mo, maaaring mag‑apply ng business permit, humiling ng iba't ibang sertipiko, o suriin ang katayuan ng transaksyon—lahat ng ito ay magagawa nang maginhawa mula sa inyong tahanan, anumang oras at saanman. Pangarap namin na mapabuti ang transparency, kahusayan, at kalidad ng serbisyo publiko sa pagtanggap at paggamit ng teknolohiya na tutugon sa lumalaking pangangailangan ng ating barangay.</p>
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
            <p>Ipinapakilala ng seksyong ito ang mga opisyal na halal ng Barangay Magang, Daet, Camarines Norte. Sila ang inatasang pamunuan ang barangay, magpatupad ng mga patakaran, at tiyakin ang maayos na paghahatid ng mahahalagang serbisyo para sa kapakanan at kaunlaran ng komunidad.</p>
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
                        We members of of Sangguniang Barangay will continue to strive more to effectively deliver basic services needed by the people, promote peace and order, protect the interest, promote social and economic development in pursuit of peaceful reliant towards a develop and progressive community within a just VARI social order.
                    </p>
                </div>
            </div>

            <!-- Vision Card -->
            <div class="col-lg-5 col-md-6 d-flex">
                <div class="card-custom vision-shape w-100 d-flex flex-column justify-content-center">
                    <h4 class="section-title mb-3">VISION</h4>
                    <p class="mb-0">
                        Barangay Magang is one of the most widely competitive community in Daet with well-developed, self-reliant, vigorously, God fearing and empowered people, economically adequate with expensive infrastructure facilities an and ecologically balance environment governed by effective and service centered leaders ready to implement the Good Governance and Ethical Leadership.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- CITIZENS CHARTER SECTION -->
    <div id="citizens-charter" class="container custom-padding">
        <div>
            <h2 class="text-uppercase fw-bold gradient-text">Citizen's Charter</h2>
            <p>Inilalahad ng Citizen’s Charter ang pangako ng Barangay Magang na magbigay ng serbisyong mabilis, transparent, at may pananagutan. Gabay ito para sa mga residente upang malaman ang mga serbisyong iniaalok, hakbang‑hakbang na proseso, mga kinakailangang dokumento, oras ng pag‑proseso, at impormasyon sa pakikipag‑ugnayan. Sumasalamin ang charter na ito sa aming dedikasyon sa pagprotekta sa karapatan ng bawat mamamayan at sa pagbibigay ng de‑kalidad na serbisyo.</p>
        </div>

        <div>
            <img src="images/citizens_charter.png" alt="Barangay Magang Citizens Charter" class="w-100">
        </div>
    </div>

    <!-- BARANGAY MAP SECTION -->
    <div id="barangay-map" class="container custom-padding">
        <div>
            <h2 class="text-uppercase fw-bold gradient-text">Barangay Map</h2>
            <p>Ang mapang ito ay nagsisilbing visual na gabay para sa mga residente, bisita, at sa maayos na pagpaplano ng mga serbisyo sa loob ng komunidad.</p>
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
                <p>Kung mayroon kayong mga tanong o kailangan ng tulong, mangyaring i-fill out ang form sa ibaba at agad naming susagutin o tutulungan kayo sa lalong madaling panahon.</p>
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
                    <button type="submit" class="btn btn-send btn-primary">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
    include 'includes/footer.php';
?>
