<?php 
// session_start();
// if (isset($_SESSION['loggedInUserRole'])) {
//     $role = $_SESSION['loggedInUserRole'];

//     if ($role === 'Resident') {
//         header('Location: userPanel.php');
//         exit;
//     } else {
//         header('Location: adminPanel.php');
//         exit;
//     }
// }
require_once 'functions/dbconn.php';
$page = 'index';
include 'includes/header.php'; 

$info = $conn->query("SELECT logo, name, address FROM barangay_info WHERE id=1")->fetch_assoc();
$logoUrl = 'images/' . $info['logo'];

$res = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
$slides = $res->fetch_all(MYSQLI_ASSOC);

$ress = $conn->query("SELECT * FROM news_updates ORDER BY date DESC");
$news = $ress->fetch_all(MYSQLI_ASSOC);
?>

<!-- BANNER SECTION -->
<div class="container-fluid px-0">
  <!-- DESKTOP VIEW - Image with overlay -->
  <div class="position-relative d-none d-md-block desktop-banner">
    <img src="images/landing_banner.png" alt="Banner" class="w-100 landing-banner-image">
    <div class="position-absolute banner-overlay text-white">
      <div class="container">
        <div class="row align-items-center justify-content-center">
          <div class="col-md-3 offset-md-1 text-end">
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Barangay Logo" class="img-fluid" style="max-width: 110px;">
          </div>
          <div class="col-md-6 text-start">
            <h6 class="mb-2">Republic of the Philippines</h6>
            <hr class="my-1" style="width: 55%; border-top: 2px solid white; opacity: 1; margin-left: 0;">
            <h2 class="fw-bold my-0" aria-label="Barangay Name"><?= htmlspecialchars($info['name']) ?></h2>
            <p class="mt-0 mb-0" aria-label="Barangay Address"><?= htmlspecialchars($info['address']) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
      
  <!-- MOBILE VIEW - Solid green banner with proper spacing -->
  <div class="d-block d-md-none mobile-banner">
    <div class="d-flex flex-row align-items-center justify-content-center gap-3 px-3 py-4">
      <div class="flex-shrink-0">
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Brgy. Magang Logo" class="img-fluid" style="max-width: 80px; width: 80px;">
      </div>
      <div class="text-start flex-grow-1">
        <h6 class="mb-1" style="font-size: 0.75rem;">Republic of the Philippines</h6>
        <hr class="my-1" style="border-top: 1.5px solid white; opacity: 1;">
        <h5 class="fw-bold my-0" style="font-size: 1.1rem;"><?= htmlspecialchars($info['name']) ?></h5>
        <p class="mt-0 mb-0" style="font-size: 0.8rem;"><?= htmlspecialchars($info['address']) ?></p>
      </div>
    </div>
  </div>
</div>


<!-- CAROUSEL SECTION -->
<div class="carousel-wrapper mb-3">
    <div class="carousel-blur-bg"></div> 

    <div class="carousel-container">
        <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
          <div class="carousel-indicators">
            <?php foreach($slides as $i=>$s): ?>
              <button type="button"
                      data-bs-target="#carouselExampleIndicators"
                      data-bs-slide-to="<?=$i?>"
                      class="<?=$i===0?'active':''?>"
                      aria-current="<?=$i===0?'true':''?>"
                      aria-label="Slide <?=($i+1)?>"></button>
            <?php endforeach; ?>
          </div>
          <div class="carousel-inner">
            <?php foreach($slides as $i=>$s): ?>
              <div class="carousel-item <?=$i===0?'active':''?>">
                <img src="announcements/<?=htmlspecialchars($s['image_file'])?>"
                    class="d-block w-100 carousel-image"
                    alt="<?=htmlspecialchars($s['title'])?>">
                <!-- <div class="carousel-caption d-none d-md-block">
                  <h5><?=htmlspecialchars($s['title'])?></h5>
                </div> -->
              </div>
            <?php endforeach; ?>
          </div>
          <button class="carousel-control-prev" type="button"
                  data-bs-target="#carouselExampleIndicators"
                  data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          </button>
          <button class="carousel-control-next" type="button"
                  data-bs-target="#carouselExampleIndicators"
                  data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
          </button>
        </div>
    </div>
</div>


<!-- SERVICE SECTION -->
<div class="container-fluid mt-3 mb-3 pt-3 pb-2 services-container">
  <h1 class="text-center gradient-text text-uppercase autoShow">Services Offered</h1>
  <div class="container mt-5">
    <!-- All buttons in one column, centered -->
    <div class="row gy-3">
      <div class="col-12 d-flex justify-content-center buttonReveal">
        <button type="button" class="service-card light-green border-0" style="width: 280px;" data-bs-toggle="modal" data-bs-target="#barangayIDModal">
          <i class="fas fa-id-card icon"></i>
          <div>
            <h4>Barangay ID</h4>
            <p>Opisyal na identification card na inilalaan ng barangay.</p>
          </div>
        </button>
      </div>
      <div class="col-12 d-flex justify-content-center buttonReveal">
        <button type="button" class="service-card mid-green border-0" style="width: 280px;" data-bs-toggle="modal" data-bs-target="#barangayClearanceModal">
          <i class="fas fa-file-alt icon"></i>
          <div>
            <h4>Barangay Clearance</h4>
            <p>Patunay na ang residente ay walang nakabinbing isyu.</p>
          </div>
        </button>
      </div>
      <div class="col-12 d-flex justify-content-center buttonReveal">
        <button type="button" class="service-card dark-green border-0" style="width: 280px;" data-bs-toggle="modal" data-bs-target="#certificationModal">
          <i class="fas fa-certificate icon"></i>
          <div>
            <h4>Certification</h4>
            <p>Patunay ng pagkakakilanlan, paninirahan, o katayuan ng residente.</p>
          </div>
        </button>
      </div>
      <div class="col-12 d-flex justify-content-center buttonReveal">
        <button type="button" class="service-card light-green border-0" style="width: 280px;" data-bs-toggle="modal" data-bs-target="#businessPermitModal">
          <i class="fas fa-store icon"></i>
          <div>
            <h4>Business Clearance</h4>
            <p>Pahintulot upang mag​‑operate sa loob ng barangay.</p>
          </div>
        </button>
      </div>
      <div class="col-12 d-flex justify-content-center buttonReveal">
        <button type="button" class="service-card mid-green border-0" style="width: 280px;" data-bs-toggle="modal" data-bs-target="#equipmentModal">
          <i class="fas fa-chair icon"></i>
          <div>
            <h4>Equipment Borrowing</h4>
            <p>Paghiram ng mga barangay equipments.</p>
          </div>
        </button>
      </div>
    </div>
  </div>
</div>

<div id="servicesTrigger" style="height: 1px;"></div>

<!-- Barangay ID Modal -->
<div class="modal fade" id="barangayIDModal" tabindex="-1" aria-labelledby="barangayIDModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="barangayIDModalLabel">Barangay ID</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6 class="mb-4">Narito ang flowchart na nagpapakita ng mga hakbang sa pagkuha ng Barangay ID:</h6>

        <div class="flowchart-text">
          <ol class="flowchart-steps ps-0">
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step1.png" alt="Step 1" class="step-icon me-2">
              <a href="signin.php" class="step-link text-decoration-none">
                Mag-<strong>Sign In</strong> sa iyong account sa eBarangay Mo website.
              </a>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step2.png" alt="Step 2" class="step-icon me-2">
              <span>Pumunta sa <strong>“Request a Service”</strong> tab at piliin ang <strong>“Barangay ID”</strong> mula sa listahanng serbisyo.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step3.png" alt="Step 3" class="step-icon me-2">
              <span>Ilagay ang iyong personal na impormasyon sa application form.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step4.png" alt="Step 4" class="step-icon me-2">
              <span>Piliin ang paraan ng pagbabayad (kung kinakailangan).</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step5.png" alt="Step 5" class="step-icon me-2">
              <span>Tiyaking tama ang iyong mga detalye at i-click ang <strong>“Submit”</strong> na button.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step6.png" alt="Step 6" class="step-icon me-2">
              <span>Maghintay ng notification para sa verification at pagproseso ng iyong aplikasyon.</span>
            </li>
            <li class="step-item d-flex mb-1">
              <img src="images/flowchart_step7.png" alt="Step 7" class="step-icon me-2">
              <span>Kunin ang Barangay ID kapag nakatanggap ng abiso o sa takdang petsa ng pag-claim.</span>
            </li>
          </ol>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Barangay Clearance Modal -->
<div class="modal fade" id="barangayClearanceModal" tabindex="-1" aria-labelledby="barangayClearanceModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="barangayClearanceModalLabel">Barangay Clearance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <h6 class="mb-4">Narito ang flowchart na nagpapakita ng mga hakbang sa pagkuha ng Barangay Clearance:</h6>

        <div class="flowchart-text">
          <ol class="flowchart-steps ps-0">
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step1.png" alt="Step 1" class="step-icon me-2">
              <a href="signin.php" class="step-link text-decoration-none">
                Mag-<strong>Sign In</strong> sa iyong account sa eBarangay Mo website.
              </a>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step2.png" alt="Step 2" class="step-icon me-2">
              <span>Pumunta sa <strong>“Request a Service”</strong> tab at piliin ang <strong>“Barangay Clearance”</strong> mula sa listahan ng serbisyo.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step3.png" alt="Step 3" class="step-icon me-2">
              <span>Ilagay ang iyong personal na impormasyon sa application form.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step4.png" alt="Step 4" class="step-icon me-2">
              <span>Piliin ang paraan ng pagbabayad (kung kinakailangan).</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step5.png" alt="Step 5" class="step-icon me-2">
              <span>Tiyaking tama ang iyong mga detalye at i-click ang <strong>“Submit”</strong> na button.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step6.png" alt="Step 6" class="step-icon me-2">
              <span>Maghintay ng notification para sa verification at pagproseso ng iyong aplikasyon.</span>
            </li>
            <li class="step-item d-flex mb-1">
              <img src="images/flowchart_step7.png" alt="Step 7" class="step-icon me-2">
              <span>Kunin ang Barangay Clearance kapag nakatanggap ng abiso o sa takdang petsa ng pag-claim.</span>
            </li>
          </ol>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Barangay Certification Modal -->
<div class="modal fade" id="certificationModal" tabindex="-1" aria-labelledby="barangayCertificationModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="barangayCertificationModalLabel">Barangay Certification</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <h6 class="mb-4">Narito ang flowchart na nagpapakita ng mga hakbang sa pagkuha ng Barangay Certification:</h6>

        <div class="flowchart-text">
          <ol class="flowchart-steps ps-0">
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step1.png" alt="Step 1" class="step-icon me-2">
              <a href="signin.php" class="step-link text-decoration-none">
                Mag-<strong>Sign In</strong> sa iyong account sa eBarangay Mo website.
              </a>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step2.png" alt="Step 2" class="step-icon me-2">
              <span>Pumunta sa <strong>“Request a Service”</strong> tab at piliin ang <strong>“Barangay Certification”</strong> mula sa listahan ng serbisyo.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step3.png" alt="Step 3" class="step-icon me-2">
              <span>Ilagay ang iyong personal na impormasyon sa application form.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step4.png" alt="Step 4" class="step-icon me-2">
              <span>Piliin ang paraan ng pagbabayad (kung kinakailangan).</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step5.png" alt="Step 5" class="step-icon me-2">
              <span>Tiyaking tama ang iyong mga detalye at i-click ang <strong>“Submit”</strong> na button.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step6.png" alt="Step 6" class="step-icon me-2">
              <span>Maghintay ng notification para sa verification at pagproseso ng iyong aplikasyon.</span>
            </li>
            <li class="step-item d-flex mb-1">
              <img src="images/flowchart_step7.png" alt="Step 7" class="step-icon me-2">
              <span>Kunin ang Barangay Certification kapag nakatanggap ng abiso o sa takdang petsa ng pag-claim.</span>
            </li>
          </ol>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Barangay Business Permit Modal -->
<div class="modal fade" id="businessPermitModal" tabindex="-1" aria-labelledby="businessPermitModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="businessPermitModalLabel">Business Permit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <h6 class="mb-4">Narito ang flowchart na nagpapakita ng mga hakbang sa pagkuha ng Barangay Business Permit:</h6>

        <div class="flowchart-text">
          <ol class="flowchart-steps ps-0">
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step1.png" alt="Step 1" class="step-icon me-2">
              <a href="signin.php" class="step-link text-decoration-none">
                Mag-<strong>Sign In</strong> sa iyong account sa eBarangay Mo website.
              </a>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step2.png" alt="Step 2" class="step-icon me-2">
              <span>Pumunta sa <strong>“Request a Service”</strong> tab at piliin ang <strong>“Business Permit”</strong> mula sa listahan ng serbisyo.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step3.png" alt="Step 3" class="step-icon me-2">
              <span>Ilagay ang iyong personal na impormasyon sa application form.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step4.png" alt="Step 4" class="step-icon me-2">
              <span>Piliin ang paraan ng pagbabayad (kung kinakailangan).</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step5.png" alt="Step 5" class="step-icon me-2">
              <span>Tiyaking tama ang iyong mga detalye at i-click ang <strong>“Submit”</strong> na button.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step6.png" alt="Step 6" class="step-icon me-2">
              <span>Maghintay ng notification para sa verification at pagproseso ng iyong aplikasyon.</span>
            </li>
            <li class="step-item d-flex mb-1">
              <img src="images/flowchart_step7.png" alt="Step 7" class="step-icon me-2">
              <span>Kunin ang Business Permit kapag nakatanggap ng abiso o sa takdang petsa ng pag-claim.</span>
            </li>
          </ol>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Equipment Borrowing Modal -->
<div class="modal fade" id="equipmentModal" tabindex="-1" aria-labelledby="equipmentModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain; margin-right: 15px;">
        <h5 class="modal-title" id="equipmentModalLabel">Equipment Borrowing</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <h6 class="mb-4">Narito ang flowchart na nagpapakita ng mga hakbang sa Barangay Equipment Borrowing:</h6>

        <div class="flowchart-text">
          <ol class="flowchart-steps ps-0">
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step1.png" alt="Step 1" class="step-icon me-2">
              <a href="signin.php" class="step-link text-decoration-none">
                Mag-<strong>Sign In</strong> sa iyong account sa eBarangay Mo website.
              </a>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step2.png" alt="Step 2" class="step-icon me-2">
              <span>Pumunta sa <strong>“Request a Service”</strong> tab at piliin ang <strong>“Equipment Borrowing”</strong> mula sa listahan ng serbisyo.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step3.png" alt="Step 3" class="step-icon me-2">
              <span>Ilagay ang iyong personal na impormasyon sa application form.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step4.png" alt="Step 4" class="step-icon me-2">
              <span>Piliin ang paraan ng pagbabayad (kung kinakailangan).</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step5.png" alt="Step 5" class="step-icon me-2">
              <span>Tiyaking tama ang iyong mga detalye at i-click ang <strong>“Submit”</strong> na button.</span>
            </li>
            <li class="step-item d-flex mb-3">
              <img src="images/flowchart_step6.png" alt="Step 6" class="step-icon me-2">
              <span>Maghintay ng notification para sa verification at pagproseso ng iyong aplikasyon.</span>
            </li>
            <li class="step-item d-flex mb-1">
              <img src="images/flowchart_step7.png" alt="Step 7" class="step-icon me-2">
              <span>Kunin ang kagamitan na nais hiramin kapag nakatanggap ng abiso o sa takdang petsa ng pag-claim.</span>
            </li>
          </ol>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<!-- NEWS & UPDATES -->
<div class="container-fluid px-4 mt-3 mb-3 pt-3 new-updates-container">
<div id="newsTrigger" style="height: 1px;"></div>
  <div class="row align-items-center autoShow">
    <div class="col-md-5">
      <h1 class="gradient-text">NEWS AND UPDATES</h1>
    </div>
    <div class="col-md-7 news-scrollable">
      <div class="scrollable-content d-flex flex-row ps-1">
        <?php foreach($news as $n): ?>
          <a href="<?=htmlspecialchars($n['link'])?>" target="_blank" class="news-card text-dark text-decoration-none">
            <div class="news-card__img">
              <img src="news/<?=htmlspecialchars($n['cover_file'])?>" alt="News Image">
            </div>
            <div class="news-card__content">
              <p class="news-date"><?=date('F j, Y', strtotime($n['date']))?></p>
              <p class="news-title"><?=htmlspecialchars($n['headline'])?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ABOUT SECTION -->
<div class="mb-5 pb-1 container-fluid autoShow">
    <div class="row align-items-center g-0">
        <div class="col-md-6 col-12">
            <div class="p-4 text-white rounded about-text">
                <h1 class="fw-bold">ABOUT</h1>
                <p class="mt-3" style="text-align: justify;">
                    <strong>eBarangay Mo</strong> ay isang digital platform na dinisenyo upang gawing mas 
                    maayos ang mga serbisyo ng barangay, na nagbibigay sa mga residente ng madaling access 
                    sa kanilang mga kahilingan, aplikasyon, at balita sa komunidad. Pinapabuti nito ang 
                    kahusayan, transparency, at kaginhawahan sa lokal na pamamahala sa pamamagitan ng ligtas 
                    at madaling gamitin na online na transaksyon.
                </p>
            </div>
        </div>
        
      <div class="col-md-6 col-12">
        <div class="about-image-container" style="max-width: 100%; overflow: hidden;">
          <img src="images/about_image.png" alt="eBarangay Mo Platform" class="img-fluid fade-right-scroll" style="max-width: 100%; height: auto; display: block;">
        </div>
      </div>
    </div>
</div>

<!-- INSTRUCTION SECTION -->
<div class="container text-center py-5 mt-5 mb-5 instruction-container autoShow">
    <h1 class="gradient-text">SIGN UP NOW</h1>
    <p class="mb-5 steps">Follow these four simple steps:</p>

    <div class="row text-center mt-4">
        <!-- Step 1 -->
        <div class="col-md-3 fadeUp">
            <img src="images/step_1.png" alt="Visit the Website" class="img-fluid mx-auto d-block w-75">
            <h4 class="mt-3 fw-bold">Visit the Website</h4>
            <hr class="underline">
            <p>Pumunta sa website ng eBarangay Mo at i‑click ang <strong><a href="signup.php" class="text-decoration-none text-black sign-up-text">Sign Up</a></strong>.</p>
        </div>

        <!-- Step 2 -->
        <div class="col-md-3 fadeUp">
            <img src="images/step_2.png" alt="Register an Account" class="img-fluid mx-auto d-block w-75">
            <h4 class="mt-3 fw-bold">Register an Account</h4>
            <hr class="underline">
            <p>Kumpletuhin ang form sa pagregister gamit ang kinakailangang personal na impormasyon.</p>
        </div>

        <!-- Step 3 -->
        <div class="col-md-3 fadeUp">
            <img src="images/step_3.png" alt="Wait for Verification" class="img-fluid mx-auto d-block w-75">
            <h4 class="mt-3 fw-bold">Wait for Verification</h4>
            <hr class="underline">
            <p>Maghintay ng notification para sa verification ng iyong account.</p>
        </div>

        <!-- Step 4 -->
        <div class="col-md-3 fadeUp">
            <img src="images/step_4.png" alt="Avail Barangay Services" class="img-fluid mx-auto d-block w-75">
            <h4 class="mt-3 fw-bold">Avail Barangay Services</h4>
            <hr class="underline">
            <p>Kapag na‑verify na ang iyong account, maaari ka nang magsubmit ng request para sa serbisyong nais mo.</p>
        </div>
    </div>
</div>

<div id="bottomTrigger" style="height: 1px;"></div>

<!-- Back to Top Button -->
<button id="backToTopBtn" title="Go to top">
  <i class="fas fa-chevron-up"></i>
</button>

<script>
  const backToTopBtn = document.getElementById("backToTopBtn");
  const servicesTrigger = document.getElementById("servicesTrigger");

  window.addEventListener("scroll", () => {
    const triggerTop = servicesTrigger.getBoundingClientRect().top;
    const triggerOffset = triggerTop + window.scrollY;

    if (window.scrollY >= triggerOffset - 100) {
      backToTopBtn.style.display = "block";
    } else {
      backToTopBtn.style.display = "none";
    }
  });

  backToTopBtn.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
</script>




<script src="js/carousel.js"></script>

<?php 
    include 'includes/footer.php'; 
?> 