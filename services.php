<?php 
require_once 'functions/dbconn.php';
$page = 'index';
include 'includes/header.php'; 

$servicesBanner = $conn->query("SELECT title, background_image FROM services_banner WHERE id=1")->fetch_assoc();
$bannerUrl = 'images/' . ($servicesBanner['background_image'] ?? 'services_banner.png');
$services = $conn->query("SELECT id, icon, title, description, button_color FROM services_list ORDER BY created_at ASC");

// Helper function for older PHP versions
function startsWith($string, $startString) {
  return substr($string, 0, strlen($startString)) === $startString;
}
?>

<link rel="stylesheet" href="services.css">

<!-- BANNER SECTION -->
<div class="container-fluid px-0">
  <div class="position-relative text-white text-center">
    <img src="<?= htmlspecialchars($bannerUrl) ?>" alt="Services Banner" class="img-fluid w-100">

    <div class="position-absolute top-50 start-50 translate-middle">
      <h1 class="fw-semibold text-uppercase"><?= htmlspecialchars($servicesBanner['title'] ?? 'Services') ?></h1>
      <p>Home / Services</p>
    </div>
  </div>
</div>

<div class="container pt-5 mt-3 pb-5 mb-3">
  <h4 class="gradient-text fw-bold text-success">BARANGAY SERVICES</h4>
  <p class="services-desc mb-4">
    Tingnan ang iba't ibang serbisyong iniaalok ng Barangay Magang—mula sa permit at sertipiko hanggang sa mga programang pangkomunidad—upang mas mapadali at maging maginhawa ang pag‑avail ng serbisyo para sa lahat ng residente.
  </p>

  <div class="row g-4">
    <?php while ($row = $services->fetch_assoc()): ?>
      <?php
        $titleSlug = strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z0-9\s]/', '', $row['title'])));
        $btnClass = $titleSlug . '-button';
        $boxClass = $titleSlug;
        $isImage = startsWith($row['icon'], 'img:');
      ?>
      <div class="col-md-6">
        <a href="signin.php" class="<?= $btnClass ?> text-decoration-none br-10px">
          <div class="<?= $boxClass ?> d-flex p-4 text-white rounded align-items-center" style="background: <?= htmlspecialchars($row['button_color']) ?>;">
            <div class="me-3 d-flex align-items-center">
              <?php if ($isImage): ?>
                <img src="images/<?= htmlspecialchars(substr($row['icon'], 4)) ?>" alt="<?= htmlspecialchars($row['title']) ?>" style="height:32px; width:auto;">
              <?php else: ?>
                <i class="<?= htmlspecialchars($row['icon']) ?> icon" style="font-size: 45px;"></i>
              <?php endif; ?>
            </div>
            <div>
              <h5 class="fw-bold mb-1"><?= htmlspecialchars($row['title']) ?></h5>
              <p class="mb-0"><?= htmlspecialchars($row['description']) ?></p>
            </div>
          </div>
        </a>
      </div>
    <?php endwhile; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
