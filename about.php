<?php 
include 'functions/dbconn.php';
$page = 'index';
include 'includes/header.php'; 

$about = $conn->query("SELECT title, background_image FROM about_banner WHERE id=1")->fetch_assoc();
$bannerUrl = 'images/' . ($about['background_image'] ?? 'about_banner.png');
$barangayInfo = $conn->query("SELECT name, address FROM barangay_info WHERE id=1")->fetch_assoc();
$ebarangay = $conn->query("SELECT first_image, second_image, third_image FROM about_ebarangaymo WHERE id = 1")->fetch_assoc();
$officials = $conn->query("SELECT * FROM about_barangay_officials WHERE id = 1")->fetch_assoc();
$missionVision = $conn->query("SELECT mission, vision FROM about_mission_vision WHERE id = 1")->fetch_assoc();
$citizensCharter = $conn->query("SELECT description, image FROM about_citizens_charter WHERE id = 1")->fetch_assoc();
$barangayMap = $conn->query("SELECT description, image FROM about_barangay_map WHERE id = 1")->fetch_assoc();

?>

<link rel="stylesheet" href="about.css">

<!-- BANNER SECTION -->
<div class="container-fluid px-0">
    <!-- Background image with overlay -->
    <div class="position-relative text-white text-center">
        <img src="<?= htmlspecialchars($bannerUrl) ?>" alt="About Banner" class="img-fluid w-100">
        
        <!-- Overlay content -->
        <div class="position-absolute top-50 start-50 translate-middle">
            <h1 class="fw-semibold text-uppercase"><?= htmlspecialchars($about['title']) ?></h1>
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
                <p>Ang <strong>eBarangay Mo</strong> ay ang online portal ng <?= htmlspecialchars($barangayInfo['name']) ?>, <?= htmlspecialchars($barangayInfo['address']) ?>, na binuo upang mas mapalapit at madaliang ma‑access ng komunidad ang mahahalagang serbisyo ng barangay. Layunin ng digital platform na ito na pasimplehin at i‑modernisa ang paraan ng pakikipag‑ugnayan ng mga residente sa kanilang lokal na pamahalaan.</p>
                <p>Sa pamamagitan ng eBarangay Mo, maaaring mag‑apply ng business permit, humiling ng iba't ibang certificates, o suriin ang katayuan ng kanilang transaksyon—lahat ng ito ay magagawa kahit sa inyong tahanan, anumang oras at saanman. Pangarap namin na mapabuti ang transparency, kahusayan, at kalidad ng serbisyo publiko sa pagtanggap at paggamit ng teknolohiya na tutugon sa lumalaking pangangailangan ng ating barangay.</p>
            </div>

            <!-- Image Grid -->
            <div class="col-lg-6">
                <div class="row g-2">
                    <div class="info-image col-6">
                        <?php if (!empty($ebarangay['first_image']) && file_exists('images/' . $ebarangay['first_image'])): ?>
                            <img src="images/<?= htmlspecialchars($ebarangay['first_image']) ?>?v=<?= time() ?>" alt="Ebarangay Image 1" class="img-fluid rounded-4 shadow img-size">
                        <?php else: ?>
                            <div class="bg-light text-muted p-5 text-center rounded-4">No Image</div>
                        <?php endif; ?>
                    </div>
                    <div class="info-image2 col-6 ml-2">
                        <?php if (!empty($ebarangay['second_image']) && file_exists('images/' . $ebarangay['second_image'])): ?>
                            <img src="images/<?= htmlspecialchars($ebarangay['second_image']) ?>?v=<?= time() ?>" alt="Ebarangay Image 2" class="img-fluid rounded-4 shadow img-size">
                        <?php else: ?>
                            <div class="bg-light text-muted p-5 text-center rounded-4">No Image</div>
                        <?php endif; ?>
                    </div>
                    <div class="info-image4 col-12">
                        <?php if (!empty($ebarangay['third_image']) && file_exists('images/' . $ebarangay['third_image'])): ?>
                            <img src="images/<?= htmlspecialchars($ebarangay['third_image']) ?>?v=<?= time() ?>" alt="Ebarangay Image 3" class="img-fluid rounded-4 shadow img-size">
                        <?php else: ?>
                            <div class="bg-light text-muted p-5 text-center rounded-4">No Image</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- BARANGAY OFFICIALS SECTION -->
    <div id="officials" class="container custom-padding">
    <div class="autoShow">
        <h2 class="text-uppercase fw-bold gradient-text">Barangay Officials</h2>
        <p><?= htmlspecialchars($officials['description']) ?></p>
    </div>

    <?php if (!empty($officials['image']) && file_exists("images/" . $officials['image'])): ?>
        <div class="autoShow">
        <img src="images/<?= htmlspecialchars($officials['image']) ?>?v=<?= time() ?>" alt="Barangay Officials" class="w-100">
        </div>
    <?php endif; ?>
    </div>

    <!-- MISSION VISION SECTION -->
    <div id="mission-vision" class="container custom-padding">
    <h2 class="text-uppercase fw-bold gradient-text mb-4 autoShow">Mission and Vision</h2>
    <div class="row justify-content-center align-items-stretch g-4 text-center">
        
        <!-- Mission -->
        <div class="col-lg-5 col-md-6 d-flex fadeUp">
        <div class="card-custom mission-shape w-100 d-flex flex-column justify-content-center">
            <h4 class="section-title mb-3">MISSION</h4>
            <p class="mb-0"><?= htmlspecialchars($missionVision['mission']) ?></p>
        </div>
        </div>

        <!-- Vision -->
        <div class="col-lg-5 col-md-6 d-flex fadeUp">
        <div class="card-custom vision-shape w-100 d-flex flex-column justify-content-center">
            <h4 class="section-title mb-3">VISION</h4>
            <p class="mb-0"><?= htmlspecialchars($missionVision['vision']) ?></p>
        </div>
        </div>

    </div>
    </div>


    <!-- CITIZENS CHARTER SECTION -->
    <div id="citizens-charter" class="container custom-padding">
    <div class="autoShow">
        <h2 class="text-uppercase fw-bold gradient-text">Citizen's Charter</h2>
        <p><?= htmlspecialchars($citizensCharter['description']) ?></p>
    </div>

    <?php if (!empty($citizensCharter['image']) && file_exists("images/" . $citizensCharter['image'])): ?>
        <div class="autoShow">
        <img src="images/<?= htmlspecialchars($citizensCharter['image']) ?>?v=<?= time() ?>" alt="Citizen's Charter" class="w-100">
        </div>
    <?php endif; ?>
    </div>

    <!-- BARANGAY MAP SECTION -->
    <div id="barangay-map" class="container custom-padding">
    <div class="autoShow">
        <h2 class="text-uppercase fw-bold gradient-text">Barangay Map</h2>
        <p><?= htmlspecialchars($barangayMap['description']) ?></p>
    </div>

    <?php if (!empty($barangayMap['image']) && file_exists("images/" . $barangayMap['image'])): ?>
        <div class="autoShow">
        <img src="images/<?= htmlspecialchars($barangayMap['image']) ?>?v=<?= time() ?>" alt="Barangay Magang Spot Map" class="w-100">
        </div>
    <?php endif; ?>
    </div>

    <!-- CONTACT US SECTION -->
    <div id="contact-us" class="container custom-padding">
        <div>
            <div class="col-lg-10 autoShow">
                <h2 class="text-uppercase fw-bold gradient-text">Contact Us</h2>
                <p>Kung mayroon mang katanungan o kailangan ng tulong, mangyaring i-fill out ang form sa ibaba at agad susagutin o tutulungan sa lalong madaling panahon.</p>
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

<!-- Back to Top Button -->
<button id="backToTopBtn" title="Back to top">
  <i class="fas fa-chevron-up"></i>
</button>


<script>
document.addEventListener("DOMContentLoaded", function () {
  const backToTopBtn = document.getElementById("backToTopBtn");
  const officialsTrigger = document.getElementById("officials");

  window.addEventListener("scroll", function () {
    if (!officialsTrigger) return;
    
    const triggerTop = officialsTrigger.getBoundingClientRect().top + window.scrollY;
    const scrollY = window.scrollY || document.documentElement.scrollTop;

    if (scrollY >= triggerTop - 100) {
      backToTopBtn.style.display = "block";
    } else {
      backToTopBtn.style.display = "none";
    }
  });

  backToTopBtn.addEventListener("click", function () {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
});
</script>


<?php
    include 'includes/footer.php';
?>
