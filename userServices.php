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

<!-- Inline CSS for Equipment Cards -->
<style>
/* === PAGE BACKGROUND FIX (ADDED) ===
   Ensure full-page background is #efefef and avoid inner containers creating a white split.
   Only these rules were added/adjusted to fix the visual split you described.
*/
html, body {
  height: 100%;
  background-color: #efefef !important;
}

/* make sure your top-level wrapper and the two main containers are transparent *
   so the body background shows through (avoids white bands caused by nested blocks) */
.container.py-4,
.services-container,
#servicesMainContainer,
#equipmentContainer {
  background: transparent !important;
}

/* Slight spacing safety for equipment container so the green cards don't butt up against the edges */
#equipmentContainer { padding-top: 3rem; padding-bottom: 3rem; }

/* Entrance animation */
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
/* Base card style */
.equipment-card {
  border-radius: 1rem;
  background: #28a745;
  width: 300px;
  height: 300px;
  color: #fff;
  position: relative;
  overflow: hidden;
  padding: 2rem 1rem;
  text-align: center;
  animation: fadeInUp 0.5s forwards;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  cursor: pointer;
}
.equipment-card:hover {
  transform: scale(1.03);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}
/* Icon styling */
.icon-main {
  font-size: 3rem;
  color: #fff;
  margin-bottom: 0.5rem;
  transition: color 0.3s;
}
/* Equipment title */
.card-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #fff;
}
/* Hover panel */
.availability-panel {
  background: #fff;
  color: #145214;
  border-radius: 0.75rem;
  padding: 1rem 0.5rem;
  position: absolute;
  bottom: -150px;
  left: 10%;
  width: 80%;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
  opacity: 0;
  transition: all 0.4s ease;
}
.equipment-card:hover .availability-panel {
  bottom: 10px;
  opacity: 1;
}
/* Quantity styling */
.qty {
  font-size: 2rem;
  font-weight: 700;
  color: #28a745;
  margin-top: 0.25rem;
}
/* Borrow button styling */
.btn-borrow {
  background: linear-gradient(to right, #28a745, #145214);
  border: none;
  color: #fff;
  padding: 0.4rem 1.5rem;
  font-size: 0.9rem;
  font-weight: 600;
  border-radius: 0.375rem;
  transition: background 0.3s ease, transform 0.2s;
}
.btn-borrow:hover {
  background: linear-gradient(to right, #145214, #28a745);
  transform: translateY(-2px);
}
/* Responsive tweak */
@media (max-width: 576px) {
  .equipment-card { padding: 1.5rem 1rem; }
  .availability-panel { left: 5%; width: 90%; }
}
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
                          <h4>Business Permit</h4>
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
            <h1 class="text-success fw-bold mb-0 text-center">Barangay Equipment Borrowing</h1>
        </div>

        <div class="row g-5 justify-content-center">
            <?php foreach ($equipments as $i => $eq): ?>
                <div class="col-sm-6 col-md-4 col-lg-3 text-center">
                    <div class="card shadow-sm equipment-card" style="animation-delay: <?= $i * 0.1 ?>s">
                        <div class="card-body text-center">
                            <i class="material-icons display-3 icon-main mx-auto d-block mb-2">
                                <?= getEquipmentIcon($eq['name']) ?>
                            </i>
                            <h5 class="card-title fw-bold mb-0">
                                <?= htmlspecialchars($eq['name']) ?>
                            </h5>
                        </div>
                        <div class="availability-panel text-center">
                            <p class="mb-1 small">Today’s available<br><strong><?= htmlspecialchars($eq['name']) ?></strong> for borrowing:</p>
                            <div class="qty"><?= (int)$eq['available_qty'] ?></div>
                            
                            <!-- Borrow form -->
                            <form action="userPanel.php?page=serviceEquipmentBorrowing" method="POST" style="margin-top: 0.5rem;">
                                <input type="hidden" name="equipment_sn" value="<?= htmlspecialchars($eq['equipment_sn']) ?>">
                                <button type="submit" class="btn btn-borrow">Borrow</button>
                            </form>
                        </div>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
    const userRemark = <?= json_encode($userRemark) ?>;
    const blockedRemarks = {
        'on hold': 'Your account is currently on hold and cannot request services.',
        'transferred': 'Your record shows a “transferred” status. You cannot request services here.',
        'deceased': 'Our records show your account as “deceased.” No service requests are allowed.'
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
    });
</script>
