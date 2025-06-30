<?php
// userServices.php

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
?>

<title>eBarangay Mo | Services</title>

<div class="container py-4">
  <!-- Services Offered -->
  <div class="container-fluid mt-5 mb-5 services-container">
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
                  <a href="#" class="service-card light-green w-100">
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
                  <a href="#" class="service-card mid-green w-100">
                      <i class="fas fa-store icon"></i>
                      <div>
                          <h4>Business Permit</h4>
                          <p>Opisyal na pahintulot na ibinibigay ng barangay para makapagsagawa ng negosyo nang legal sa komunidad.</p>
                      </div>
                  </a>
              </div>
              <div class="col d-flex">
                  <a href="#" class="service-card light-green w-100">
                      <i class="fas fa-chair icon"></i>
                      <div>
                          <h4>Equipment Borrowing</h4>
                          <p>Pagpapahiram ng kagamitan mula sa barangay para sa pansamantalang gamit, ayon sa itinakdang alituntunin at iskedyul.</p>
                      </div>
                  </a>
              </div>
              <div class="col d-flex">
                  <a href="#" class="service-card dark-green w-100">
                      <i class="fas fa-money-bill icon"></i>
                      <div>
                          <h4>Cash Incentives</h4>
                          <p>Pagbibigay ng insentibong pera bilang pagkilala at parangal sa mga mag-aaral na may natatanging tagumpay sa akademiko.</p>
                      </div>
                  </a>
              </div>
          </div>
      </div>
  </div>
</div>

<!-- 2) Modal for blocked users -->
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

<!-- 3) Embed the remark into JS -->
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
            // Prevent the link
            e.preventDefault();
            // Fill and show the modal
            document.getElementById('remarkModalBody').textContent = blockedRemarks[userRemark];
            new bootstrap.Modal(document.getElementById('remarkModal')).show();
            }
            // else, do nothing and let the link proceed
        });
        });
    });
</script>

