<?php
// userDashboard.php - userDashboard with integrated Where's My Request? section

include 'functions/dbconn.php';

// Only start session if none exists yet.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use the same session key as userRequest.php first, fallback to account_id
$userId = $_SESSION['loggedInUserID'] ?? $_SESSION['account_id'] ?? null;

// ANNOUNCEMENTS (safe even if there are zero rows)
$slides = [];
$res = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
if ($res) {
    $slides = $res->fetch_all(MYSQLI_ASSOC);
}

// Fetch most recent single request for user (only when userId is a positive integer)
$latestReq = null;
if (is_numeric($userId) && intval($userId) > 0) {
    $sql = "
      SELECT id,
             transaction_id,
             request_type,
             payment_method,
             payment_status,
             document_status,
             amount,
             claim_date,
             DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') AS created_at_formatted
        FROM view_request
       WHERE account_id = ?
       ORDER BY created_at DESC
       LIMIT 1
    ";
    if ($st = $conn->prepare($sql)) {
        $uid = intval($userId);
        $st->bind_param('i', $uid);
        $st->execute();
        $latestReq = $st->get_result()->fetch_assoc();
        $st->close();
    } else {
        // prepare failed — optional: log or handle error
        $latestReq = null;
    }
}
?>

<title>eBarangay Mo | Homepage</title>

<!-- Google Material Icons for the circular button -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<style>
/* ---------- variables ---------- */
:root{
  --green-a: #28a745;
  --green-b: #145214;
  --title-size: 20px; /* unified title size */
  --section-gap: 22px; /* consistent spacing between sections */
}

/* ---------- global layout helpers ---------- */
.container.py-4 { padding-top: 1rem; padding-bottom: 1rem; }

/* ---------- section spacing (applied to the 3 main blocks) ---------- */
.section-block { margin-bottom: var(--section-gap); }

/* ---------- unified section title (gradient text) ---------- */
.section-title {
  font-size: var(--title-size);
  font-weight: 700;
  margin-bottom: 0;
  /* gradient text */
  background: linear-gradient(90deg, var(--green-a) 0%, var(--green-b) 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  -webkit-text-fill-color: transparent;
  display:inline-block;
  line-height:1.05;
}

/* More specific fallback to ensure gradient shows in card contexts */
.card .section-title {
  /* re-apply to override any card color inheritance */
  background: linear-gradient(90deg, var(--green-a) 0%, var(--green-b) 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  -webkit-text-fill-color: transparent;
}

/* fallback just in case background-clip unsupported */
.section-title.fallback { color: var(--green-b); }

/* ---------- subtle shared entrance animation ---------- */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.animate-on-scroll { opacity: 0; transform: translateY(8px); transition: opacity .45s ease, transform .45s ease; }
.animate-on-scroll.in-view { opacity: 1; transform: translateY(0); animation: fadeUp .45s ease both; }

/* ---------- Where's My Request card (INTEGRATED SECTION) ---------- */
.request-card {
  border-radius:14px;
  padding:18px;
  background: transparent;
  border: none;
  box-shadow: none;
  display:flex;
  flex-direction:column;
  align-items:center;
  text-align:center;
}

/* request-pill centered and narrower with drop shadow */
.request-pill {
  background-color: #ffffff;
  border-radius:100px;
  padding:14px 20px;
  display:flex;
  gap:50px;
  align-items:center;
  justify-content:center;
  width:58%;
  margin-top: 0;
  border: none;
  /* added drop shadow */
  box-shadow: 0 14px 30px rgba(20,82,20,0.08), 0 4px 8px rgba(11,38,16,0.04);
  transition: transform .28s cubic-bezier(.2,.9,.2,1), box-shadow .28s ease;
}

/* subtle lift on hover for the pill */
.request-pill:hover { transform: translateY(-4px); box-shadow: 0 20px 48px rgba(20,82,20,0.10); }

/* center text inside each column */
.request-item {
  min-width:140px;
  text-align:center;
}

/* labels regular weight and color (use green dark for readability) */
.request-item .label {
  display:block;
  font-size:13px;
  color: var(--green-b);
  font-weight:400;
}

/* values larger, bold, and green */
.request-item .value {
  display:block;
  font-size:17px;
  font-weight:700;
  color: var(--green-b);
  line-height:1.1;
}

/* Title style smaller and in dark green */
.request-card h3 {
  /* use unified title style */
  font-size: var(--title-size);
  margin-bottom:12px;
}

/* Status styling */
.status-badge {
  display:inline-block;
  padding:0;
  border-radius:0;
  font-weight:700;
  font-size:15px;
  color: var(--green-b);
  background: transparent !important;
}

/* circular icon button for View My Requests (centered with items) */
.view-requests-btn {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:44px;
  height:44px;
  border-radius:50%;
  background: linear-gradient(180deg,var(--green-a),var(--green-b));
  color:#ffffff;
  text-decoration:none;
  border:none;
  box-shadow: 0 10px 28px rgba(20,82,20,0.16);
  transition: transform .45s cubic-bezier(.2,.9,.2,1), box-shadow .28s ease;
  will-change: transform;
  /* gentle float animation */
  animation: floatBtn 4s ease-in-out infinite;
}
@keyframes floatBtn {
  0% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
  100% { transform: translateY(0); }
}
.view-requests-btn .material-icons { font-size:20px; line-height:1; }

/* responsive: stack vertically and center everything */
@media (max-width:900px){
  .request-pill { flex-direction:column; gap:15px; align-items:center; padding:12px; width:100%; }
  .view-requests-btn { width:40px; height:40px; }
}

/* ---------- ANNOUNCEMENTS carousel ---------- */
.carousel-wrapper {
  position:relative;
  width:100%;
  max-width:100%;
  margin: 0 auto;
  border-radius:14px;
  overflow:visible;
  padding: 10px 0;
}

/* blurred background that fills the visible card area */
.carousel-blur-bg {
  position:absolute;
  left:0;
  right:0;
  top:8px;
  bottom:8px;
  margin:0;
  background-size:cover;
  background-position:center;
  filter: blur(16px) saturate(0.9);
  z-index:0;
  border-radius:14px;
  transition: background-image .45s ease-in-out, opacity .3s ease;
  opacity: 0.98;
}

/* container above the blur background */
.carousel-container {
  position:relative;
  z-index:2;
  display:flex;
  justify-content:center;
  align-items:center;
  padding: 20px 12px;
}

/* make the inner carousel a bit bigger per request (keeps blur same) */
#carouselExampleIndicators {
  width: 100%;
  max-width: 820px; /* allowed width for the overall component */
}

/* Carousel inner increased */
#carouselExampleIndicators .carousel-inner {
  display:block;
  overflow:hidden;
  width:660px;          /* increased width */
  max-width:100%;
  margin: 0 auto;
  padding-bottom: 6px;
  transition: transform .35s ease, opacity .35s ease;
}

/* carousel items */
#carouselExampleIndicators .carousel-item {
  position: relative;
  width: 100%;
  transition: opacity .35s ease;
  opacity: 0.98;
}

/* center and size the image; slightly larger */
#carouselExampleIndicators .carousel-item img,
#carouselExampleIndicators .carousel-image {
  display:block;
  margin:0 auto;
  max-height:420px;      /* increased height */
  width:auto;
  max-width:100%;
  object-fit:contain;
  border-radius:12px;
  box-shadow: 0 12px 40px rgba(11,38,16,0.12);
  transition: transform .35s ease, opacity .35s ease;
  opacity: 0.98;
}
#carouselExampleIndicators .carousel-item.active img,
#carouselExampleIndicators .carousel-item.active .carousel-image {
  transform: scale(1.025);
  opacity: 1;
}

/* indicators centered below */
#carouselExampleIndicators .carousel-indicators {
  display:flex;
  justify-content:center;
  gap:12px;
  padding: 8px 0 0;
  margin: 0 auto;
  position:relative;
}
#carouselExampleIndicators .carousel-indicators button {
  width:10px;
  height:10px;
  border-radius:50%;
  background: rgba(0,0,0,0.12);
  border:none;
  margin:0 6px;
}
#carouselExampleIndicators .carousel-indicators .active {
  background: rgba(0,0,0,0.45);
}

/* hide prev/next controls completely (we removed markup, but keep this as safety) */
.carousel-control-prev, .carousel-control-next { display:none !important; }

/* responsive adjustments */
@media (max-width:1100px) {
  #carouselExampleIndicators .carousel-inner { width:520px; }
  #carouselExampleIndicators .carousel-item img { max-height:360px; }
}
@media (max-width:700px) {
  #carouselExampleIndicators .carousel-inner { width:340px; }
  #carouselExampleIndicators .carousel-item img { max-height:240px; }
  .carousel-container { padding: 12px; }
}

/* ----------------- MOST REQUESTED SERVICES ----------------- */
.most-requested {
  display:flex;
  gap:28px;
  align-items:flex-start;
  padding:6px 12px;
}

.most-requested-left {
  width:28%;
  min-width:220px;
}
.most-requested-left .section-title {
  font-size: var(--title-size);
  margin-bottom:5px;
}
.most-requested-left p {
  margin:0 0 12px;
  color:#5b7d63;
  font-size:13px;
}

/* right column with tiles */
.most-requested-right { flex:1; }

/* grid: 3 x 2 */
.most-requested-grid {
  display:grid;
  grid-template-columns: repeat(3, 1fr);
  gap:16px;
  align-items:stretch;
}

/* tiles */
.most-requested-tile {
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  padding:18px 12px;
  border-radius:14px;
  min-height:86px;
  text-align:center;
  text-decoration:none;
  transition: transform .18s ease, box-shadow .18s ease, background .18s ease, color .18s ease;
  user-select:none;
  background: #EAF5ED;
  border: 1px solid rgba(42,146,69,0.04);
  color: inherit; /* ensure anchors don't pick default blue */
}

/* ensure tile icons and any .tile-con use green when idle, and transition to white on hover */
.most-requested-tile .tile-icon,
.most-requested-tile .tile-con,
.tile-con {
  font-size:26px;
  margin-bottom:10px;
  line-height:1;
  color: var(--green-b); /* default (idle) icon color */
  transition: color .18s ease; /* smooth color change on hover */
}

/* keep tile-label already green and explicit when idle */
.most-requested-tile .tile-label { font-weight:700; font-size:16px; color:#1f6a3b; transition: color .18s ease; }

/* hover: gradient + lift; make icon and label white on hover */
.most-requested-tile:hover,
.most-requested-tile:focus {
  transform: translateY(-6px);
  box-shadow: 0 26px 60px rgba(11,38,16,0.08);
  background: linear-gradient(180deg, var(--green-a) 0%, var(--green-b) 100%);
  color: #ffffff;
}
.most-requested-tile:hover .tile-icon,
.most-requested-tile:focus .tile-icon,
.most-requested-tile:hover .tile-label,
.most-requested-tile:focus .tile-label { color: #ffffff; }

.most-requested-tile:focus { outline: none; box-shadow: 0 26px 60px rgba(11,38,16,0.08), 0 0 0 4px rgba(40,167,69,0.08); }

/* responsive */
@media (max-width:950px) {
  .most-requested { flex-direction:column; gap:16px; }
  .most-requested-left { width:100%; }
  .most-requested-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width:520px) {
  .most-requested-grid { grid-template-columns: 1fr; }
}

/* small utility */
.text-muted-small { color:#5b7d63; font-size:13px; }

/* ensure consistent card backgrounds */
.card { background: transparent !important; border: none !important; box-shadow: none !important; }

/* ----------------- Targeted white background for Most Requested Services only ----------------- */
.most-requested-card {
  background: #ffffff !important;     /* override global transparent */
  border-radius: 14px;
  box-shadow: 0 8px 28px rgba(11,38,16,0.06);
  padding: 1rem; /* keep the card spacing */
  margin-top: 1rem;
}
</style>

<div class="container py-4">
  <!-- WHERE'S MY REQUEST (INTEGRATED) -->
  <div class="card request-card mb-3 section-block animate-on-scroll" data-anim>
    <h2 class="section-title">Where's My Request?</h2>

    <div class="d-flex request-pill">
      <?php if ($latestReq): ?>
        <div class="request-item">
          <span class="label">Request Type</span>
          <span class="value"><?= htmlspecialchars($latestReq['request_type']) ?></span>
        </div>

        <div class="request-item">
          <span class="label">Claim Date</span>
          <span class="value">
            <?= !empty($latestReq['claim_date']) ? date('M d, Y', strtotime($latestReq['claim_date'])) : 'TBD' ?>
          </span>
        </div>

        <div class="request-item">
          <span class="label">Status</span>
          <?php
            $status = $latestReq['document_status'] ?? 'Unknown';
            $status_slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $status));
            $status_class = 'status-' . $status_slug;
          ?>
          <span class="value">
            <span class="status-badge <?= htmlspecialchars($status_class) ?>">
              <?= htmlspecialchars($status) ?>
            </span>
          </span>
        </div>

        <!-- centered circular icon button -->
        <a href="?page=userRequest" class="view-requests-btn" aria-label="View My Requests">
          <span class="material-icons" aria-hidden="true">receipt_long</span>
        </a>

      <?php else: ?>
        <div style="width:100%; display:flex; gap:12px; align-items:center; justify-content:center; flex-wrap:wrap;">
          <div>
            <div class="label" style="color:var(--green-b);">No recent requests</div>
            <div class="value" style="font-size:14px; font-weight:700; color:var(--green-b)">You have not submitted any request yet.</div>
          </div>

          <a href="?page=requestService" class="view-requests-btn" aria-label="Request a Service">
            <span class="material-icons" aria-hidden="true">add</span>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ANNOUNCEMENTS -->
  <div class="card mb-4 p-3 section-block animate-on-scroll" data-anim>
    <!-- added explicit 'announcement-title' class (but styling handled via .section-title) -->
    <h2 class="section-title announcement-title">Announcements</h2>

    <div class="carousel-wrapper">
      <!-- blur background fills the card area; set via JS to match active slide -->
      <div class="carousel-blur-bg" id="annBlurBg"></div>

      <div class="carousel-container">
        <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4500" data-bs-touch="true">
          <?php if (!empty($slides)): ?>

            <div class="carousel-inner">
              <?php foreach($slides as $i=>$s): ?>
                <div class="carousel-item <?= $i===0 ? 'active' : '' ?>">
                  <img src="announcements/<?=htmlspecialchars($s['image_file'])?>"
                      class="d-block w-100 carousel-image"
                      alt="<?=htmlspecialchars($s['title'])?>">
                </div>
              <?php endforeach; ?>
            </div>

            <!-- indicators below the images -->
            <div class="carousel-indicators" aria-hidden="false">
              <?php foreach($slides as $i=>$s): ?>
                <button type="button"
                        data-bs-target="#carouselExampleIndicators"
                        data-bs-slide-to="<?=$i?>"
                        class="<?=$i===0?'active':''?>"
                        aria-current="<?=$i===0?'true':''?>"
                        aria-label="Slide <?=($i+1)?>"></button>
              <?php endforeach; ?>
            </div>

            <!-- prev/next intentionally removed (controls hidden) -->

          <?php else: ?>
            <div class="text-center py-5">No announcements yet.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- MOST REQUESTED SERVICES -->
  <!-- Added the .most-requested-card class to give this specific card a white background -->
  <div class="card mt-3 p-3 section-block animate-on-scroll most-requested-card" data-anim>
    <div class="most-requested">
      <!-- Left: heading + small subtext -->
      <div class="most-requested-left">
        <h3 class="section-title">Most Requested Services</h3>
        <p class="text-muted-small">Pick a service to get started — we’ll guide you step by step.</p>
      </div>

      <!-- Right: 3x2 tile grid -->
      <div class="most-requested-right">
        <div class="most-requested-grid">
          <!-- top-left -->
          <a href="?page=serviceCertification" class="most-requested-tile service-secondary-tile" tabindex="0" role="button" aria-label="Certificate of Indigency">
            <span class="material-icons tile-icon" aria-hidden="true">receipt_long</span>
            <div class="tile-label">Certificate of Indigency</div>
          </a>

          <!-- top-center -->
          <a href="?page=serviceBarangayClearance" class="most-requested-tile service-primary-tile" tabindex="0" role="button" aria-label="Business Clearance">
            <span class="material-icons tile-icon" aria-hidden="true">inventory_2</span>
            <div class="tile-label">Barangay Clearance</div>
          </a>

          <!-- top-right -->
          <a href="?page=serviceBarangayID" class="most-requested-tile service-secondary-tile" tabindex="0" role="button" aria-label="Barangay ID">
            <span class="material-icons tile-icon" aria-hidden="true">badge</span>
            <div class="tile-label">Barangay ID</div>
          </a>

          <!-- bottom-left -->
          <a href="?page=serviceBusinessClearance" class="most-requested-tile service-secondary-tile" tabindex="0" role="button" aria-label="Business Permit">
            <span class="material-icons tile-icon" aria-hidden="true">business_center</span>
            <div class="tile-label">Business Clearance</div>
          </a>

          <!-- bottom-center -->
          <a href="?page=serviceEquipmentBorrowing" class="most-requested-tile service-secondary-tile" tabindex="0" role="button" aria-label="Equipment Borrowing">
            <span class="material-icons tile-icon" aria-hidden="true">inventory</span>
            <div class="tile-label">Equipment Borrowing</div>
          </a>

          <!-- bottom-right -> "More Services" -->
          <a href="?page=userServices" class="most-requested-tile service-secondary-tile" tabindex="0" role="button" aria-label="More Services">
            <span class="material-icons tile-icon" aria-hidden="true">more_horiz</span>
            <div class="tile-label">More Services</div>
          </a>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
  // guard in case there are no slides or carousel element missing
  const annCarousel = document.getElementById('carouselExampleIndicators');
  const annBlurBg = document.getElementById('annBlurBg');

  function setBlurFromActiveTarget(targetItem) {
    if (!annBlurBg) return;
    const img = targetItem ? targetItem.querySelector('img') : null;
    if (img && img.src) {
      annBlurBg.style.backgroundImage = `url('${img.src}')`;
    } else {
      const activeImg = annCarousel ? annCarousel.querySelector('.carousel-item.active img') : null;
      annBlurBg.style.backgroundImage = activeImg ? `url('${activeImg.src}')` : '';
    }
  }

  if (annCarousel) {
    // update initially (small delay to allow image to load)
    setTimeout(() => setBlurFromActiveTarget(null), 120);

    // When slide starts — change blur to the upcoming slide's image so both change together.
    annCarousel.addEventListener('slide.bs.carousel', function (e) {
      const nextItem = e?.relatedTarget ?? null;
      if (nextItem) {
        setBlurFromActiveTarget(nextItem);
      }
    });

    // After slide completes, ensure blur matches active slide
    annCarousel.addEventListener('slid.bs.carousel', function () {
      setBlurFromActiveTarget(null);
    });
  }

  // IntersectionObserver to animate elements in view
  (function () {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in-view');
          // add helper class used by CSS to finalize animate
          entry.target.classList.add('in-view');
          entry.target.classList.remove('animate-on-scroll');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    // find all elements we want to animate
    document.querySelectorAll('[data-anim]').forEach(el => {
      observer.observe(el);
      // keep initial 'animate-on-scroll' class to allow CSS transitions
      el.classList.add('animate-on-scroll');
    });
  })();
</script>
