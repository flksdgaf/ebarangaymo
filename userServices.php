<?php
// userServices.php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];

// 1) Find the purok table
$purokTable = null;
for ($i = 1; $i <= 6; $i++) {
    $tbl = "purok{$i}_rbi";
    $chk = $conn->prepare("SELECT remarks FROM {$tbl} WHERE account_ID = ?");
    $chk->bind_param('i', $userId);
    $chk->execute();
    $res = $chk->get_result();
    if ($res->num_rows) {
        $row = $res->fetch_assoc();
        $userRemark = strtolower(trim($row['remarks'] ?? ''));
        $purokTable = $tbl;
        $chk->close();
        break;
    }
    $chk->close();
}
if (!$purokTable) {
    $userRemark = '';
}

// Function for icons
function getEquipmentIcon($name) {
    $name = strtolower($name);
    return match(true) {
        str_contains($name, 'chair') => 'event_seat',
        str_contains($name, 'table') => 'table_restaurant',
        str_contains($name, 'tent') => 'holiday_village',
        str_contains($name, 'speaker') => 'speaker',
        str_contains($name, 'microphone') => 'mic',
        str_contains($name, 'light') => 'highlight',
        str_contains($name, 'sound') => 'volume_up',
        str_contains($name, 'projector') => 'movie',
        str_contains($name, 'camera') => 'photo_camera',
        str_contains($name, 'generator') => 'power',
        default => 'inventory_2',
    };
}

// Get equipment list
$stmt = $conn->prepare("
  SELECT equipment_sn, name, description, total_qty, available_qty
    FROM equipment_list
   ORDER BY name
");
$stmt->execute();
$equipments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<title>eBarangay Mo | Services</title>

<!-- Bootstrap & Material Icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<style>
html, body {
  height: 100%;
  background-color: #efefef !important;
}

:root {
  --card-width: 280px;
  --card-height: 350px;
  --icon-size: 80px;
  --title-size: 1.4rem;
  --qty-size: 3rem;
  --btn-bottom: 1.5rem;
}
.container.py-4,
.services-container,
#servicesMainContainer,
#equipmentContainer {
  background: transparent !important;
}
#equipmentContainer { padding-top: 3rem; padding-bottom: 3rem; }
/* Entrance animation */
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slideInUp {
  from { 
    opacity: 0; 
    transform: translateY(60px); 
  }
  to { 
    opacity: 1; 
    transform: translateY(0); 
  }
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.05); }
  100% { transform: scale(1); }
}

/* Updated Equipment Card Styling */
.equipment-card {
  border-radius: 0.8rem;
  background: linear-gradient(135deg, #1e7e34 0%, #28a745 100%);
  width: var(--card-width);
  height: var(--card-height);
  max-width: 100%;
  color: #fff;
  position: relative;
  overflow: hidden;
  padding: 2rem 1.5rem;
  text-align: center;
  animation: slideInUp 0.6s ease-out forwards;
  transition: all 0.8s cubic-bezier(0.25, 0.8, 0.25, 1);
  cursor: pointer;
  box-shadow: 0 8px 25px rgba(30, 126, 52, 0.3);
  border: none;
}

/* Icon styling - positioned at top */
.equipment-icon {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  width: var(--icon-size);
  height: var(--icon-size);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
  transition: all 0.3s ease;
}

.equipment-card:hover .equipment-icon {
  background: rgba(255, 255, 255, 0.3);
  animation: pulse 1s infinite;
}

.icon-main {
  font-size: 2.5rem;
  color: #fff;
}

/* Equipment title */
.equipment-title {
  font-size: var(--title-size);
  font-weight: 700;
  color: #fff;
  margin-bottom: 1rem;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Availability text */
.availability-section {
  margin-bottom: 1.5rem;
  border: 2px solid;
  border-radius: 10px;
  padding-right: 10px;
  padding-left: 10px;
}

.availability-text {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.9);
  line-height: 1.3;
  text-align: left;
  flex: 1;
  margin-right: 1rem;
}

.quantity-display {
  font-size: var(--qty-size);
  font-weight: 900;
  color: #fff;
  text-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
  animation: fadeInUp 0.8s ease-out;
  flex-shrink: 0;
}

/* Borrow button styling */
.btn-borrow {
  background: rgba(255, 255, 255, 0.9);
  border: 2px solid transparent;
  color: #28a745;
  padding: 0.7rem 2rem;
  font-size: 1rem;
  font-weight: 700;
  border-radius: 2rem;
  transition: all 0.3s ease;
  text-transform: uppercase;
  letter-spacing: 1px;
  position: absolute;
  bottom: 1.5rem;
  left: 50%;
  transform: translateX(-50%);
  min-width: 140px;
}

.btn-borrow:hover {
  background: #fff;
  border-color: #fff;
  color: #28a745;
  transform: translateX(-50%) translateY(-3px);
  box-shadow: 0 5px 15px rgba(255, 255, 255, 0.4);
}

.btn-borrow:active {
  transform: translateX(-50%) translateY(-1px);
}

/* Responsive adjustments */

/* Large tablets and small desktops (768px - 991px) */
@media (max-width: 991px) {
  :root {
    --card-width: 260px;
    --card-height: 340px;
    --icon-size: 75px;
    --title-size: 1.3rem;
    --qty-size: 2.8rem;
  }
  
  .equipment-card {
    padding: 1.8rem 1.3rem;
  }
  
  .icon-main {
    font-size: 2.3rem;
  }
  
  .availability-text {
    font-size: 12px;
  }
  
  .btn-borrow {
    padding: 0.65rem 1.8rem;
    font-size: 0.95rem;
    min-width: 130px;
  }
  
  #equipmentContainer {
    padding-top: 2.5rem;
    padding-bottom: 2.5rem;
  }
}

/* Tablets (768px and below) */
@media (max-width: 767px) {
  :root {
    --card-width: 240px;
    --card-height: 320px;
    --icon-size: 70px;
    --title-size: 1.2rem;
    --qty-size: 2.5rem;
  }
  
  .equipment-card {
    padding: 1.5rem 1rem;
  }
  
  .icon-main {
    font-size: 2rem;
  }
  
  .equipment-icon {
    margin-bottom: 1.2rem;
  }
  
  .availability-section {
    margin-bottom: 1.2rem;
    padding: 0.5rem 0.7rem;
  }
  
  .availability-text {
    font-size: 11px;
    margin-right: 0.7rem;
  }
  
  .btn-borrow {
    padding: 0.6rem 1.5rem;
    font-size: 0.9rem;
    min-width: 120px;
    bottom: 1.2rem;
  }
  
  #equipmentContainer {
    padding-top: 2rem;
    padding-bottom: 2rem;
  }
  
  /* Back button adjustments */
  #backToServicesBtn .material-icons {
    font-size: 40px;
  }
  
  /* Heading adjustments */
  #equipmentContainer h1 {
    font-size: 1.5rem;
  }
}

/* Mobile devices (576px and below) */
@media (max-width: 576px) {
  :root {
    --card-width: 220px;
    --card-height: 300px;
    --icon-size: 65px;
    --title-size: 1.1rem;
    --qty-size: 2.2rem;
  }
  
  .equipment-card {
    padding: 1.3rem 0.9rem;
  }
  
  .icon-main {
    font-size: 1.8rem;
  }
  
  .equipment-icon {
    margin-bottom: 1rem;
  }
  
  .availability-section {
    margin-bottom: 1rem;
    padding: 0.4rem 0.6rem;
  }
  
  .availability-text {
    font-size: 10px;
    margin-right: 0.5rem;
  }
  
  .btn-borrow {
    padding: 0.55rem 1.3rem;
    font-size: 0.85rem;
    min-width: 110px;
    bottom: 1rem;
  }
  
  #equipmentContainer {
    padding-top: 1.5rem;
    padding-bottom: 1.5rem;
  }
  
  /* Container padding adjustments */
  .container.py-4 {
    padding-left: 0.75rem;
    padding-right: 0.75rem;
  }
  
  /* Back button adjustments */
  #backToServicesBtn .material-icons {
    font-size: 35px;
  }
  
  /* Heading adjustments */
  #equipmentContainer h1 {
    font-size: 1.3rem;
    padding: 0 2.5rem;
  }
  
  /* Grid adjustments */
  #equipmentContainer .row {
    gap: 1rem;
  }
}

/* Extra small devices (below 400px) */
@media (max-width: 399px) {
  :root {
    --card-width: 200px;
    --card-height: 280px;
    --icon-size: 60px;
    --title-size: 1rem;
    --qty-size: 2rem;
  }
  
  .equipment-card {
    padding: 1.2rem 0.8rem;
  }
  
  .icon-main {
    font-size: 1.6rem;
  }
  
  .equipment-icon {
    margin-bottom: 0.8rem;
  }
  
  .availability-section {
    margin-bottom: 0.8rem;
    padding: 0.3rem 0.5rem;
  }
  
  .availability-text {
    font-size: 9px;
    margin-right: 0.4rem;
  }
  
  .btn-borrow {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    min-width: 100px;
    bottom: 0.8rem;
  }
  
  #equipmentContainer h1 {
    font-size: 1.1rem;
    padding: 0 2rem;
  }
  
  #backToServicesBtn .material-icons {
    font-size: 30px;
  }
}

/* Grid animation stagger */
.equipment-card:nth-child(1) { animation-delay: 0.1s; }
.equipment-card:nth-child(2) { animation-delay: 0.25s; }
.equipment-card:nth-child(3) { animation-delay: 0.4s; }
.equipment-card:nth-child(4) { animation-delay: 0.55s; }
.equipment-card:nth-child(5) { animation-delay: 0.7s; }
.equipment-card:nth-child(6) { animation-delay: 0.85s; }
.equipment-card:nth-child(n+7) { animation-delay: 1s; }
</style>

<div class="container py-4">
  <!-- Main Container -->
  <div id="servicesMainContainer" class="container-fluid mt-5 mb-5 services-container">
      <h1 class="text-center gradient-text text-uppercase">Services Offered</h1>
      <div class="container mt-5">
          <div class="row row-cols-1 row-cols-md-2 g-3">
              <div class="col d-flex">
                  <a href="userPanel.php?page=serviceBarangayID" class="service-card mid-green w-100">
                      <i class="fas fa-id-card icon"></i>
                      <div>
                          <h4>Barangay ID</h4>
                          <p>Opisyal na identification card na inilalaan ng barangay bilang patunay ng paninirahan at pagkakakilanlan.</p>
                      </div>
                  </a>
              </div>
              <div class="col d-flex">
                <a href="userPanel.php?page=serviceBarangayClearance" class="service-card light-green w-100">
                    <i class="fas fa-file-alt icon"></i>
                    <div>
                        <h4>Barangay Clearance</h4>
                        <p>Opisyal na dokumento na nagpapatunay na ang residente ay walang hindi pa natapos o hindi naayos na isyu sa barangay.</p>
                    </div>
                </a>
            </div>
              <div class="col d-flex">
                  <a href="userPanel.php?page=serviceCertification" class="service-card dark-green w-100">
                      <i class="fas fa-certificate icon"></i>
                      <div>
                          <h4>Certification</h4>
                          <p>Opisyal na dokumento upang patunayan ang pagkakakilanlan, paninirahan, o tiyak na katayuan ng residente.</p>
                      </div>
                  </a>
              </div>
              <div class="col d-flex">
                  <a href="userPanel.php?page=serviceBusinessClearance" class="service-card mid-green w-100">
                      <i class="fas fa-store icon"></i>
                      <div>
                          <h4>Business Clearance</h4>
                          <p>Opisyal na pahintulot na ibinibigay ng barangay para makapagsagawa ng negosyo nang legal sa komunidad.</p>
                      </div>
                  </a>
              </div>
              <div class="col d-flex">
                  <a href="#" id="equipmentServiceBtn" class="service-card light-green w-100">
                      <i class="fas fa-chair icon"></i>
                      <div>
                          <h4>Equipment Borrowing</h4>
                          <p>Pagpapahiram ng kagamitan ng barangay para sa pansamantalang gamit.</p>
                      </div>
                  </a>
              </div>
          </div>
      </div>
  </div>

  <!-- Equipment Borrowing Container -->
  <div id="equipmentContainer" class="container py-5" style="display: none;">
        <div class="d-flex align-items-center justify-content-center mb-5 position-relative">
            <!-- Back Icon -->
            <button id="backToServicesBtn" class="btn btn-link text-success position-absolute start-0" style="font-size: 2rem; text-decoration: none;">
                <span class="material-icons" style="font-size: 50px;">chevron_left</span>
            </button>
            <!-- Heading -->
            <h1 class="text-center gradient-text text-uppercase">Barangay Equipment Borrowing</h1>
        </div>

        <div class="row g-4 justify-content-center">
            <?php foreach ($equipments as $i => $eq): ?>
                <div class="col-sm-6 col-md-4 col-lg-3 d-flex justify-content-center">
                    <div class="equipment-card">
                        <!-- Equipment Icon at Top -->
                        <div class="equipment-icon">
                            <i class="material-icons icon-main">
                                <?= getEquipmentIcon($eq['name']) ?>
                            </i>
                        </div>
                        
                        <!-- Equipment Title -->
                        <h5 class="equipment-title">
                            <?= htmlspecialchars($eq['name']) ?>
                        </h5>
                        
                        <!-- Number of Available -->
                        <div class="availability-section d-flex align-items-center justify-content-between mb-4">
                            <p class="availability-text mb-0">
                                Today's available<br>
                                <strong><?= htmlspecialchars($eq['name']) ?></strong><br>
                                for borrowing:
                            </p>
                            <div class="quantity-display">
                                <?= (int)$eq['available_qty'] ?>
                            </div>
                        </div>
                        
                        <!-- Borrow Button -->
                        <form action="userPanel.php?page=serviceEquipmentBorrowing" method="POST">
                            <input type="hidden" name="equipment_sn" value="<?= htmlspecialchars($eq['equipment_sn']) ?>">
                            <button type="submit" class="btn btn-borrow">Borrow</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal for blocked users -->
<div class="modal fade" id="remarkModal" tabindex="-1" aria-labelledby="remarkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
        <div class="modal-header bg-warning text-dark">
            <h5 class="modal-title" id="remarkModalLabel">Service Request Unavailable</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p id="remarkModalBody"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
        </div>
        </div>
    </div>
</div>

<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script> -->
<script>
    const userRemark = <?= json_encode($userRemark) ?>;
    const blockedRemarks = {
        'on hold': 'Your account is currently on hold and cannot request services.',
        'transferred': 'Your record shows a "transferred" status. You cannot request services here.',
        'deceased': 'Our records show your account as "deceased." No service requests are allowed.'
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.service-card').forEach(card => {
            card.addEventListener('click', e => {
                if (userRemark && blockedRemarks[userRemark]) {
                    e.preventDefault();
                    document.getElementById('remarkModalBody').textContent = blockedRemarks[userRemark];
                    new bootstrap.Modal(document.getElementById('remarkModal')).show();
                }
            });
        });

        document.getElementById('equipmentServiceBtn').addEventListener('click', e => {
            if (!(userRemark && blockedRemarks[userRemark])) {
                e.preventDefault();
                document.getElementById('servicesMainContainer').style.display = 'none';
                document.getElementById('equipmentContainer').style.display = 'block';
            }
        });

        document.getElementById('backToServicesBtn').addEventListener('click', () => {
            document.getElementById('equipmentContainer').style.display = 'none';
            document.getElementById('servicesMainContainer').style.display = 'block';
        });

        // Add equipment card interaction animations
        document.querySelectorAll('.equipment-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    });
</script>