<?php 
include 'functions/dbconn.php';
$page = 'index';
include 'includes/header.php'; 

$transparency = $conn->query("SELECT title, background_image FROM transparency_banner WHERE id = 1")->fetch_assoc();
$bannerUrl = 'images/' . ($transparency['background_image'] ?? 'transparency_seal_banner.png');
$transparencyContent = $conn->query("SELECT image, description FROM transparency_content WHERE id = 1")->fetch_assoc();
?>

<link rel="stylesheet" href="transparencyseal.css">

<!-- BANNER SECTION -->
<div class="container-fluid px-0">
  <!-- DESKTOP VIEW - Image with overlay -->
  <div class="position-relative d-none d-md-block">
    <img src="<?= htmlspecialchars($bannerUrl) ?>" alt="Transparency Banner" class="img-fluid w-100">
    <div class="position-absolute top-50 start-50 translate-middle text-white text-center">
      <h1 class="fw-semibold text-uppercase"><?= htmlspecialchars($transparency['title']) ?></h1>
      <p>Home / Transparency Seal</p>
    </div>
  </div>
  
  <!-- MOBILE VIEW - Solid green banner -->
  <div class="d-block d-md-none mobile-banner">
    <div class="text-center py-4">
      <h1 class="fw-semibold text-uppercase text-white mb-2"><?= htmlspecialchars($transparency['title']) ?></h1>
      <p class="text-white mb-0">Home / Transparency Seal</p>
    </div>
  </div>
</div>

<!-- CONTENT SECTION -->
<div class="container">
  <div class="row align-items-center">
    <!-- Image -->
    <?php if (!empty($transparencyContent['image']) && file_exists("images/" . $transparencyContent['image'])): ?>
      <div class="col-md-4 text-center mb-4 mb-md-0 fadeUp">
        <img src="images/<?= htmlspecialchars($transparencyContent['image']) ?>?v=<?= time() ?>" alt="Transparency Content" class="img-fluid" style="max-width: 100%;">
      </div>
    <?php endif; ?>
    
    <!-- Description -->
    <div class="col-md-8">
      <h4 class="gradient-text fw-bold text-success mb-4">SYMBOLISM</h4>
      <p style="text-align: justify;">
        <?= nl2br(htmlspecialchars($transparencyContent['description'])) ?>
      </p>
    </div>
  </div>
</div>

<?php
    include 'includes/footer.php';
?>
