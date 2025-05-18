<?php
// adminVerifications.php
require 'functions/dbconn.php';

if (!isset($_SESSION['auth']) || !$_SESSION['auth']) {
    header("Location: index.php");
    exit();
}

// Fetch all pending requests
$sql    = "SELECT * FROM pending_accounts ORDER BY time_creation DESC";
$result = $conn->query($sql);
$allRequests = [];
if ($result) {
    while ($r = $result->fetch_assoc()) {
        $allRequests[$r['account_ID']] = $r;
    }
}
?>
<div class="container py-3">
  <div class="card shadow-sm p-3">
    <div class="card-body p-0">
      <div class="table-responsive admin-table" style="height:500px;overflow-y:auto;">
        <table class="table mb-0 align-middle text-start">
          <thead class="table-light">
            <tr>
              <th class="text-nowrap">Account ID</th>
              <th class="text-nowrap">Name</th>
              <th class="text-nowrap">Time Created</th>
              <th class="text-nowrap">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($allRequests)): ?>
              <tr><td colspan="4">No pending requests</td></tr>
            <?php else: ?>
              <?php foreach ($allRequests as $row):
                $formatted = date("F d, Y - h:i A", strtotime($row['time_creation']));
              ?>
                <tr class="request-row" data-id="<?php echo $row['account_ID'] ?>">
                  <td><?php echo $row['account_ID'] ?></td>
                  <td><?php echo htmlspecialchars($row['full_name']) ?></td>
                  <td><?php echo $formatted ?></td>
                  <td>
                    <form method="POST"
                          action="functions/approve_account.php"
                          class="d-inline approve-form">
                      <input type="hidden" name="account_ID" value="<?php echo $row['account_ID'] ?>">
                      <input type="hidden" name="name"       value="<?php echo htmlspecialchars($row['full_name']) ?>">
                      <input type="hidden" name="purok"      value="<?php echo htmlspecialchars($row['purok']) ?>">
                      <button type="submit" class="btn btn-sm btn-success">
                        Approve
                      </button>
                    </form>
                    <button class="btn btn-sm btn-danger decline-btn"
                            data-account-id="<?php echo $row['account_ID'] ?>">
                      Decline
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="requestDetailsModalLabel">Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"></div>
    </div>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmApproveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Verification</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="confirmApproveBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmApproveBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script>
// Make PHP data available in JS
const requestsData = <?php echo json_encode($allRequests, JSON_HEX_TAG) ?>;

document.addEventListener('DOMContentLoaded', () => {
  // Details modal setup
  const detailsModalEl = document.getElementById('requestDetailsModal');
  const detailsModal   = new bootstrap.Modal(detailsModalEl);
  const detailsBody    = detailsModalEl.querySelector('.modal-body');

  document.querySelectorAll('.request-row').forEach(row => {
    row.addEventListener('click', e => {
      if (e.target.closest('button')) return; // skip button clicks
      const id   = row.dataset.id;
      const data = requestsData[id];
      if (!data) return;

      let html = '<dl class="row">';
      const exclude = ['username','password'];
      for (let key in data) {
        if (exclude.includes(key)) continue;
        let label = key.replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase());
        if (/\.(jpg|jpeg|png|gif)$/i.test(data[key])) {
          const folder = key.includes('front') ? 'frontID'
                       : key.includes('back')   ? 'backID'
                       : 'profilePictures';
          html += `
            <dt class="col-sm-5">${label}</dt>
            <dd class="col-sm-7 mb-3">
              <img src="${folder}/${data[key]}"
                   class="img-fluid img-thumbnail" style="max-height:200px;">
            </dd>`;
        } else {
          html += `
            <dt class="col-sm-5">${label}</dt>
            <dd class="col-sm-7 mb-3">${data[key]}</dd>`;
        }
      }
      html += '</dl>';
      detailsBody.innerHTML = html;
      detailsModal.show();
    });
  });

  // Approval flow
  const confirmModalEl = document.getElementById('confirmApproveModal');
  const confirmModal   = new bootstrap.Modal(confirmModalEl);
  const confirmBody    = document.getElementById('confirmApproveBody');
  const confirmBtn     = document.getElementById('confirmApproveBtn');
  let pendingForm = null;

  document.querySelectorAll('.approve-form').forEach(form => {
    form.addEventListener('submit', async e => {
      e.preventDefault();
      pendingForm = form;

      const acct   = form.querySelector('input[name="account_ID"]').value;
      const name   = form.querySelector('input[name="name"]').value;
      const chosen = form.querySelector('input[name="purok"]').value;

      // Ask backend if name exists elsewhere
      const resp = await fetch('functions/check_name.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({name})
      });
      const data = await resp.json();

      if (data.found) {
        if (data.purok !== chosen) {
          // transfer case
          confirmBody.innerHTML = `
            <p><strong>${name}</strong> is currently in ${data.purok} RBI.</p>
            <p>Transfer record from <strong>${data.purok}</strong> to <strong>${chosen}</strong> and update?</p>`;
        } else {
          // attach in same purok
          confirmBody.innerHTML = `
            <p><strong>${name}</strong> already in <strong>${data.purok}</strong> RBI (ID: ${data.existingAccountId}).</p>
            <p>Attach new Account ID <strong>${acct}</strong> and update?</p>`;
        }
      } else {
        // brand new
        confirmBody.innerHTML = `
          <p>Approve <strong>${name}</strong> (ID ${acct}) into <strong>${chosen}</strong> RBI?</p>`;
      }

      confirmBtn.textContent = data.found ? 'Attach & Approve' : 'Confirm';
      confirmModal.show();
    });
  });

  confirmBtn.addEventListener('click', () => {
    if (pendingForm) pendingForm.submit();
  });
});
</script>
