<?php
// adminVerifications.php
require 'functions/dbconn.php';

if (!isset($_SESSION['auth']) || !$_SESSION['auth']) {
    header("Location: index.php");
    exit();
}

// Fetch pending requests
$sql    = "SELECT * FROM pending_accounts ORDER BY time_creation DESC";
$result = $conn->query($sql);
$allRequests = [];
if ($result) {
    while ($r = $result->fetch_assoc()) {
        $allRequests[$r['account_ID']] = $r;
    }
}
?>
<div class="container py-4">
  <div class="card shadow-sm p-3">
    <div class="table-responsive">
      <table class="table align-middle text-center">
        <thead class="table-light">
          <tr>
            <th>Account ID</th>
            <th>Name</th>
            <th>Time Creation</th>
            <th>Action</th>
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
                  <div class="d-flex justify-content-center gap-2">
                      <form method="POST" action="functions/approve_account.php" class="d-inline approve-form">
                      <input type="hidden" name="account_ID" value="<?php echo $row['account_ID'] ?>">
                      <input type="hidden" name="name"       value="<?php echo htmlspecialchars($row['full_name']) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-success">
                        Approve
                      </button>
                    </form>
                    <button class="btn btn-sm btn-danger text-white decline-btn"
                            data-account-id="<?php echo $row['account_ID'] ?>">
                      Decline
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="requestDetailsModalLabel">Account Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><!-- filled by JS --></div>
    </div>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmApproveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Account Verification</h5>
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
// Pass PHP data into JS
const requestsData = <?php echo json_encode($allRequests, JSON_HEX_TAG) ?>;

document.addEventListener('DOMContentLoaded', () => {
  // --- DETAILS MODAL ---
  const detailsModalEl = document.getElementById('requestDetailsModal');
  const detailsModal   = new bootstrap.Modal(detailsModalEl);
  const detailsBody    = detailsModalEl.querySelector('.modal-body');

  document.querySelectorAll('.request-row').forEach(row => {
    row.addEventListener('click', e => {
      // ignore clicks on buttons
      if (e.target.closest('button')) return;

      const id   = row.dataset.id;
      const data = requestsData[id];
      if (!data) return;

      let html = '<div class="container-fluid"><dl class="row">';
      const exclude = ['username','password'];
      for (let key in data) {
        if (exclude.includes(key)) continue;
        let label = key.replace(/_/g,' ')
                       .replace(/\b\w/g, c => c.toUpperCase());
        if (/\.(jpg|jpeg|png|gif)$/i.test(data[key])) {
          let folder = key.includes('front') ? 'frontID'
                     : key.includes('back')  ? 'backID'
                     : 'profilePictures';
          html += `
            <dt class="col-sm-4">${label}</dt>
            <dd class="col-sm-8 mb-3">
              <img src="${folder}/${data[key]}"
                   class="img-fluid img-thumbnail"
                   style="max-height:200px;" alt="${label}">
            </dd>`;
        } else {
          html += `
            <dt class="col-sm-4">${label}</dt>
            <dd class="col-sm-8 mb-3">${data[key]}</dd>`;
        }
      }
      html += '</dl></div>';
      detailsBody.innerHTML = html;
      detailsModal.show();
    });
  });

  // --- APPROVE FLOW ---
  const confirmModalEl   = document.getElementById('confirmApproveModal');
  const confirmModal     = new bootstrap.Modal(confirmModalEl);
  const confirmBody      = document.getElementById('confirmApproveBody');
  const confirmBtn       = document.getElementById('confirmApproveBtn');
  let pendingForm        = null;
  let pendingAccountId   = '';
  let pendingName        = '';

  document.querySelectorAll('.approve-form').forEach(form => {
    form.addEventListener('submit', async e => {
      e.preventDefault();
      pendingForm      = form;
      pendingAccountId = form.querySelector('input[name="account_ID"]').value;
      pendingName      = form.querySelector('input[name="name"]').value;

      // check for existing name
      const resp = await fetch('functions/check_name.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({name: pendingName})
      });
      const data = await resp.json();

      if (data.found) {
        confirmBody.innerHTML = `
          This name is already on <strong>${data.purok}'s RBI</strong>.
          Attach this new Account ID <strong>${pendingAccountId}</strong>
          to the existing record and update based on their updated information?
        `;
      } else {
        confirmBody.innerHTML = `
          Are you sure you want to approve
          <strong>${pendingName}</strong>'s account with ID
          <strong>${pendingAccountId}</strong>?
        `;
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
