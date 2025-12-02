<?php
require 'functions/dbconn.php';

if (!isset($_SESSION['auth'])||!$_SESSION['auth']) {
    header("Location:index.php"); 
    exit;
}

// Determine which view we're on
$currentView = isset($_GET['view']) && $_GET['view'] === 'declined' ? 'declined' : 'pending';

// Pagination setup
$limit = 8;
$page_num = max((int)($_GET['page_num'] ?? 1), 1);
$offset = ($page_num - 1) * $limit;

// Determine table and order
if ($currentView === 'pending') {
    $tableName = 'pending_accounts';
    $orderBy = 'time_creation DESC';
} else {
    $tableName = 'declined_accounts';
    $orderBy = 'time_declined DESC';
}

// Get total count
$countSQL = "SELECT COUNT(*) AS total FROM `{$tableName}`";
$countStmt = $conn->prepare($countSQL);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);
if ($totalPages < 1) $totalPages = 1;
if ($page_num > $totalPages) $page_num = $totalPages;
$offset = ($page_num - 1) * $limit;

// Fetch initial data with pagination
$sql0 = "SELECT * FROM `{$tableName}` ORDER BY {$orderBy} LIMIT ? OFFSET ?";
$stmt0 = $conn->prepare($sql0);
$stmt0->bind_param('ii', $limit, $offset);
$stmt0->execute();
$res0 = $stmt0->get_result();
$initial = [];
if ($res0) {
    while ($r = $res0->fetch_assoc()) {
        $initial[] = $r;
    }
}
$stmt0->close();

// Build base query string for pagination
$qs = $_GET;
unset($qs['page_num']);
$baseQS = http_build_query($qs);
if ($baseQS) $baseQS .= '&';

// Display counters
$shownCount = count($initial);
$startDisplay = $totalRows > 0 ? ($offset + 1) : 0;
$endDisplay = $offset + $shownCount;
?>

<title>eBarangay Mo | Account Verifications</title>

<div class="container-fluid p-3">
  <?php if ($id = ($_GET['approved_account_id'] ?? false)): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
      <div>Account record: <strong><?= htmlspecialchars($id) ?></strong> successfully added!</div>
      <div class="ms-3 d-flex align-items-center">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($id = ($_GET['declined_account_id'] ?? false)): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
      <div>Account record: <strong><?= htmlspecialchars($id) ?></strong> declined!</div>
      <div class="ms-3 d-flex align-items-center">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($id = ($_GET['deleted_account_id'] ?? false)): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
      <div>Account record: <strong><?= htmlspecialchars($id) ?></strong> permanently deleted!</div>
      <div class="ms-3 d-flex align-items-center">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  <?php endif; ?>
  
  <div class="card shadow-sm p-3">
    <div class="card-body p-0">
      <div class="d-flex justify-content-end mb-2">
        <div class="dropdown">
          <button class="btn btn-secondary dropdown-toggle" type="button" id="viewDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            Current Pending Accounts
          </button>
          <ul class="dropdown-menu" aria-labelledby="viewDropdown">
            <li><button class="dropdown-item" data-view="pending">Current Pending Accounts</button></li>
            <li><button class="dropdown-item" data-view="declined">Declined Accounts</button></li>
          </ul>
        </div>
      </div>
      <div class="table-responsive admin-table" style="height:500px;overflow-y:auto;"> <!--style="height:500px;overflow-y:auto; -->
        <table class="table mb-0 align-middle text-start" id="requestsTable">
          <thead class="table-light">
            <tr>
              <th class="text-nowrap">Account ID</th>
              <th class="text-nowrap">Name</th>
              <th class="text-nowrap"><?= $currentView === 'pending' ? 'Date - Time Created' : 'Date - Time Declined' ?></th>
              <?php if ($currentView === 'declined'): ?>
                <th class="text-nowrap">Reason</th>
              <?php endif; ?>
              <th class="text-nowrap">Action</th>
            </tr>
          </thead>
          <tbody id="requestsTbody">
            <?php if (empty($initial)): ?>
              <tr><td colspan="<?= $currentView === 'declined' ? 5 : 4 ?>" class="text-center">
                <?= $currentView === 'pending' ? 'No pending requests' : 'No declined accounts' ?>
              </td></tr>
            <?php else: ?>
              <?php foreach ($initial as $row):
                // Use the appropriate time field based on current view
                $timeField = $currentView === 'pending' ? 'time_creation' : 'time_declined';
                $fmt = date("F d, Y - h:i A", strtotime($row[$timeField]));
              ?>
                <tr class="request-row" style="cursor:pointer;" data-id="<?php echo $row['account_ID'] ?>" data-full='<?php echo json_encode($row, JSON_HEX_TAG) ?>'>
                  <td><?php echo $row['account_ID'] ?></td>
                  <td><?php 
                    // Convert "Lastname, Firstname, Middlename" to "Firstname Middlename Lastname"
                    $nameParts = array_map('trim', explode(',', $row['full_name']));
                    $displayName = isset($nameParts[1]) ? $nameParts[1] : '';
                    if (isset($nameParts[2])) $displayName .= ' ' . $nameParts[2];
                    $displayName .= ' ' . ($nameParts[0] ?? '');
                    echo htmlspecialchars(trim($displayName));
                  ?></td>
                  <td><?php echo $fmt ?></td>
                  <?php if ($currentView === 'declined'): ?>
                    <td><?php echo htmlspecialchars($row['reason'] ?? ''); ?></td>
                  <?php endif; ?>
                  <td>
                    <?php if ($currentView === 'pending'): ?>
                    <div class="d-flex gap-1">
                      <form method="POST" action="functions/approve_account.php" class="d-inline approve-form">
                        <input type="hidden" name="account_ID" value="<?php echo $row['account_ID'] ?>">
                        <input type="hidden" name="name" value="<?php echo htmlspecialchars($row['full_name']) ?>">
                        <input type="hidden" name="purok" value="<?php echo htmlspecialchars($row['purok']) ?>">
                        <input type="hidden" name="redirectTo" value="admin">
                        <button type="submit" class="btn btn-sm btn-success me-1" style="min-width:90px;">Approve</button>
                      </form>
                      <button class="btn btn-sm btn-danger decline-btn" style="min-width:90px;" data-account-id="<?php echo $row['account_ID'] ?>">Decline</button>
                    </div>
                    <?php else: ?>
                      <!-- Delete permanently form for declined view -->
                      <form method="POST" action="functions/delete_declined_account.php" class="d-inline">
                        <input type="hidden" name="account_ID" value="<?php echo $row['account_ID'] ?>">
                        <button type="button" class="btn btn-sm btn-danger btn-delete-declined" style="min-width:170px;">Delete Permanently</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
          <ul class="pagination justify-content-center pagination-sm">
            <!-- Prev Button -->
            <li class="page-item <?= $page_num <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= $baseQS ?>page_num=<?= $page_num - 1 ?>">Previous</a>
            </li>

            <?php
            $range = 2;
            $dots = false;
            for ($i = 1; $i <= $totalPages; $i++) {
              if ($i == 1 || $i == $totalPages || ($i >= $page_num - $range && $i <= $page_num + $range)) {
                $active = $i == $page_num ? 'active' : '';
                $query = $baseQS . "page_num=$i";
                echo "<li class='page-item {$active}'><a class='page-link' href='?{$query}'>$i</a></li>";
                $dots = true;
              } elseif ($dots) {
                echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                $dots = false;
              }
            }
            ?>

            <!-- Next Button -->
            <li class="page-item <?= $page_num >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= $baseQS ?>page_num=<?= $page_num + 1 ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>

      <!-- Item counter -->
      <div class="position-absolute end-0 bottom-0 pe-3 pb-2 text-muted user-select-none pointer-events-none">
        <small class="d-block fs-6">
          <?php if ($totalRows > 0): ?>
            <span class="text-muted small">
              Showing <strong><?= $startDisplay ?></strong>–<strong><?= $endDisplay ?></strong> of <strong><?= $totalRows ?></strong> Accounts
            </span>
          <?php else: ?>
            <span class="text-muted small">No Accounts Found</span>
          <?php endif; ?>
        </small>
      </div>

    </div>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 850px; height: 90vh;">
    <div class="modal-content" style="height: 100%;">
      <!-- Header -->
      <div class="modal-header py-3" style="background: linear-gradient(135deg, #13411F 0%, #1a5c2e 100%); color: white;">
        <div>
          <h6 class="modal-title fw-bold mb-0" id="requestDetailsModalLabel">Account Verification Details</h6>
          <small class="opacity-75" style="font-size: 0.8rem;">Review applicant information carefully</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <!-- Body -->
      <div class="modal-body p-0" style="overflow-y: auto; flex: 1;">
        <div id="detailsContent">
          <!-- JS will inject content here -->
        </div>
      </div>

      <div class="modal-footer bg-light py-2">
        <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">
          <span class="material-icons align-middle" style="font-size: 18px;">close</span>
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Confirmation Modal for Approve/Attach (pending) -->
<div class="modal fade" id="confirmApproveModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 550px;">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold">Confirm Verification</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4" id="confirmApproveBody" style="font-size: 0.95rem;"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success px-4" id="confirmApproveBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>

<!-- Decline Reason Modal -->
<div class="modal fade" id="declineReasonModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="declineReasonModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 550px;">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title fw-bold" id="declineReasonModalLabel">
          Decline Account Confirmation
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <form id="declineReasonForm" method="POST" action="functions/decline_account.php">
        <div class="modal-body">
          <input type="hidden" name="account_ID" id="declineAccountId" value="">

          <div class="mb-3">
            <label id="declineReasonLabel" for="declineReasonInput" class="form-label"></label>
            <textarea class="form-control form-control-sm" id="declineReasonInput" name="reason" rows="4" required placeholder="Enter reason here..."></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger px-4">Confirm Decline</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Permanently Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 550px;">
    <div class="modal-content">
      <!-- Header -->
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title fw-bold" id="confirmDeleteModalLabel">
          Permanent Deletion Confirmation
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <!-- Form -->
      <form id="confirmDeleteForm" method="POST" action="functions/delete_declined_account.php">
        <div class="modal-body">
          <input type="hidden" name="account_ID" id="deleteAccountId" value="">
          
          <p id="confirmDeleteLabel" class="mb-0"></p>
          <small class="text-muted">Note: This action cannot be undone and all associated records will be lost permanently.</small>
        </div>

        <!-- Footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger px-4">Delete Permanently</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Zoom Modal -->
<div class="modal fade" id="zoomImageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content bg-dark text-white border-0 shadow-lg">
      <div class="modal-header border-0 pb-2">
        <h6 class="modal-title fw-bold" id="zoomImageTitle">
          <span class="material-icons align-middle me-1" style="font-size: 20px;">fullscreen</span>
          <span></span>
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-3">
        <img id="zoomedImage" src="" class="img-fluid rounded shadow" style="max-height: 80vh; object-fit: contain;">
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const dropdownItems = document.querySelectorAll('.dropdown-item[data-view]');
    const dropdownBtn = document.getElementById('viewDropdown');
    const tbody = document.getElementById('requestsTbody');
    const thead = document.querySelector('#requestsTable thead tr');

    window.loggedInUserRole = <?= json_encode($_SESSION['loggedInUserRole'] ?? '') ?>;
    const isViewOnly = window.loggedInUserRole === 'Brgy Kagawad';
    document.querySelectorAll('.approve-form button, .decline-btn').forEach(btn => btn.disabled = isViewOnly);

    // Helpers to rebuild header and rows
    function rebuildHeader(view) {
      thead.innerHTML = '';
      const th1 = document.createElement('th');
      th1.className = 'text-nowrap'; th1.textContent = 'Account ID';
      const th2 = document.createElement('th');
      th2.className = 'text-nowrap'; th2.textContent = 'Name';
      const th3 = document.createElement('th');
      th3.className = 'text-nowrap';
      th3.textContent = (view==='pending') ? 'Date - Time Created' : 'Date - Time Declined';
      thead.append(th1, th2, th3);
      if (view === 'declined') {
        const thReason = document.createElement('th');
        thReason.className = 'text-nowrap'; thReason.textContent = 'Reason';
        thead.append(thReason);
      }
      const thAction = document.createElement('th');
      thAction.className = 'text-nowrap'; thAction.textContent = 'Action';
      thead.append(thAction);
    }

    function formatDateTime(dtString) {
      const d = new Date(dtString);
      if (isNaN(d)) return dtString;
      const monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];
      const month = monthNames[d.getMonth()];
      const day = String(d.getDate()).padStart(2,'0');
      const year = d.getFullYear();
      let hours = d.getHours();
      const minutes = String(d.getMinutes()).padStart(2,'0');
      const ampm = hours >= 12 ? 'PM' : 'AM';
      hours = hours % 12 || 12;
      return `${month} ${day}, ${year} - ${hours}:${minutes} ${ampm}`;
    }


    function bindRowEvents() {
      // Details modal binding
      const detailsModalEl = document.getElementById('requestDetailsModal');
      const detailsModal = new bootstrap.Modal(detailsModalEl);
      const detailsBody = detailsModalEl.querySelector('.modal-body');
      document.querySelectorAll('.request-row').forEach(row => {
        row.addEventListener('click', e => {
          if (e.target.closest('button') || e.target.closest('form')) return;
          const dataObj = JSON.parse(row.getAttribute('data-full'));

          let html = '';
          const exclude = ['username','password', 'sex', 'civil_status'];

          // Profile Header Section
          html += `
          <div class="bg-light border-bottom p-3">
            <div class="row align-items-center">
              <div class="col-md-8">
                <div class="d-flex align-items-center gap-2">
                  <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <span class="material-icons text-success" style="font-size: 24px;">person</span>
                  </div>
                  <div>
                    <h6 class="mb-0 fw-bold text-dark">${(() => {
                      const nameParts = dataObj.full_name.split(',').map(s => s.trim());
                      let displayName = nameParts[1] || '';
                      if (nameParts[2]) displayName += ' ' + nameParts[2];
                      displayName += ' ' + (nameParts[0] || '');
                      return displayName.trim();
                    })()}</h6>
                    <small class="text-muted" style="font-size: 0.8rem;">
                      <span class="material-icons align-middle" style="font-size: 14px;">location_on</span>${dataObj.purok} | 
                      <span class="material-icons align-middle" style="font-size: 14px;">tag</span>${dataObj.account_ID}
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>
          `;

          // Main Content
          html += '<div class="p-3">';

          // Personal Information Card
          html += `
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">
              <h6 class="fw-bold text-success mb-2" style="font-size: 0.9rem;">Personal Information</h6>
              <div class="row g-2">
                <div class="col-md-6">
                  <div>
                    <small class="text-muted d-block" style="font-size: 0.75rem;">Date of Birth</small>
                    <span class="fw-semibold" style="font-size: 0.85rem;">${dataObj.birthdate}</span>
                  </div>
                </div>
                <div class="col-md-6">
                  <div>
                    <small class="text-muted d-block" style="font-size: 0.75rem;">Purok</small>
                    <span class="fw-semibold" style="font-size: 0.85rem;">${dataObj.purok}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          `;

          // Identification Documents Card
          html += `
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">
              <h6 class="fw-bold text-success mb-2" style="font-size: 0.9rem;">Identification Documents</h6>
              <div class="row g-2">
          `;

          if (dataObj.front_ID) {
            html += `
                <div class="col-md-6">
                  <div class="position-relative">
                    <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">Front ID</small>
                    <div class="border rounded overflow-hidden bg-light position-relative" style="height: 160px;">
                      <img src="frontID/${dataObj.front_ID}" 
                          class="img-fluid w-100 h-100 object-fit-cover id-preview" 
                          style="cursor: pointer; transition: transform 0.2s;"
                          onmouseover="this.style.transform='scale(1.05)'"
                          onmouseout="this.style.transform='scale(1)'"
                          data-full-src="frontID/${dataObj.front_ID}" 
                          data-title="Front Identification">
                      <div class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-50 text-white text-center py-1" style="font-size: 0.75rem;">
                        <span class="material-icons align-middle" style="font-size: 14px;">zoom_in</span> Click to enlarge
                      </div>
                    </div>
                  </div>
                </div>
            `;
          }

          if (dataObj.back_ID) {
            html += `
                <div class="col-md-6">
                  <div class="position-relative">
                    <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">Back ID</small>
                    <div class="border rounded overflow-hidden bg-light position-relative" style="height: 160px;">
                      <img src="backID/${dataObj.back_ID}" 
                          class="img-fluid w-100 h-100 object-fit-cover id-preview" 
                          style="cursor: pointer; transition: transform 0.2s;"
                          onmouseover="this.style.transform='scale(1.05)'"
                          onmouseout="this.style.transform='scale(1)'"
                          data-full-src="backID/${dataObj.back_ID}" 
                          data-title="Back Identification">
                      <div class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-50 text-white text-center py-1" style="font-size: 0.75rem;">
                        <span class="material-icons align-middle" style="font-size: 14px;">zoom_in</span> Click to enlarge
                      </div>
                    </div>
                  </div>
                </div>
            `;
          }

          html += `
              </div>
            </div>
          </div>
          `;

          // Account Timeline Card
          html += `
          <div class="card border-0 shadow-sm">
            <div class="card-body p-3">
              <h6 class="fw-bold text-success mb-2" style="font-size: 0.9rem;">Account Timeline</h6>
              <div>
                <small class="text-muted d-block" style="font-size: 0.75rem;">Account Created</small>
                <span class="fw-semibold" style="font-size: 0.85rem;">${dataObj.time_creation}</span>
              </div>
            </div>
          </div>
          `;

          html += '</div>'; // Close p-3

          detailsBody.innerHTML = html;
          detailsModal.show();
        });
      });
      // Pending action bindings
      if (currentView==='pending') {
        // Approve flow
        const confirmModalEl = document.getElementById('confirmApproveModal');
        const confirmModal = new bootstrap.Modal(confirmModalEl);
        const confirmBody = document.getElementById('confirmApproveBody');
        const confirmBtn = document.getElementById('confirmApproveBtn');
        let pendingForm = null;
        document.querySelectorAll('.approve-form button, .decline-btn').forEach(btn => btn.disabled = isViewOnly);
        
        document.querySelectorAll('.approve-form').forEach(form => {
          form.addEventListener('submit', async e => {
            e.preventDefault();
            pendingForm = form;
            const acct = form.querySelector('input[name="account_ID"]').value;
            const name = form.querySelector('input[name="name"]').value;
            const purok = form.querySelector('input[name="purok"]').value;
            const resp = await fetch('functions/check_name.php', {
              method:'POST',
              headers: {'Content-Type':'application/x-www-form-urlencoded'},
              body: new URLSearchParams({name})
            });
            const data = await resp.json();
            if (data.found) {
              if (data.purok !== purok) {
                confirmBody.innerHTML = `<p><strong>${name}</strong> is currently in ${data.purok} RBI.</p>
                  <p>Transfer record from <strong>${data.purok}</strong> to <strong>${purok}</strong> and update?</p>`;
              } else {
                confirmBody.innerHTML = `<p><strong>${name}</strong> already in <strong>${data.purok}</strong> RBI (ID: ${data.existingAccountId}).</p>
                  <p>Attach new Account ID <strong>${acct}</strong> and update?</p>`;
              }
            } else {
              confirmBody.innerHTML = `<p>Approve <strong>${name}</strong> (ID ${acct}) into <strong>${purok}</strong> RBI?</p>`;
            }
            confirmBtn.textContent = data.found ? 'Attach & Approve' : 'Confirm';
            confirmModal.show();
          });
        });
        confirmBtn.addEventListener('click', () => {
          if (pendingForm) pendingForm.submit();
        });

        // Decline
        document.querySelectorAll('.decline-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            // Find the row and its data
            const row = btn.closest('tr.request-row');
            const dataObj = JSON.parse(row.getAttribute('data-full'));
            const acctId = dataObj.account_ID || row.dataset.accountId;
            const fullName = dataObj.full_name || '';

            // Set hidden input
            document.getElementById('declineAccountId').value = acctId;
            // Set label text
            const labelEl = document.getElementById('declineReasonLabel');
            // labelEl.textContent = `This action cannot be undone. Please state the reason below for declining ${fullName}’s account request (ID ${acctId}).`;
            labelEl.innerHTML = `This action cannot be undone. Please state the reason below for declining <strong>${fullName}’s</strong> account request <strong>(ID ${acctId})</strong>.`;

            // Clear textarea
            document.getElementById('declineReasonInput').value = '';

            // Show modal
            const declineModalEl = document.getElementById('declineReasonModal');
            const declineModal = new bootstrap.Modal(declineModalEl);
            declineModal.show();
          });
        });

      } else {
        // Declined action bindings
        document.querySelectorAll('.btn-delete-declined').forEach(btn => btn.disabled = isViewOnly);

        document.querySelectorAll('.btn-delete-declined').forEach(btn => {
          btn.addEventListener('click', () => {
            // Get account ID and name if available
            const form = btn.closest('form');
            const acctId = form.querySelector('input[name=account_ID]').value;
            // If data-full is present on the row, you can get full_name:
            let fullName = '';
            const row = btn.closest('tr.request-row');
            if (row && row.getAttribute('data-full')) {
              try {
                const dataObj = JSON.parse(row.getAttribute('data-full'));
                fullName = dataObj.full_name || '';
              } catch(_) {}
            }
            // Set hidden input in modal form
            document.getElementById('deleteAccountId').value = acctId;
            // Set confirmation text
            const labelP = document.getElementById('confirmDeleteLabel');
            // labelP.textContent = `Are you sure you want to permanently delete ${fullName}’s declined record (ID ${acctId})? This action cannot be undone.`;
            labelP.innerHTML = `Are you sure you want to permanently delete <strong>${fullName}’s</strong> declined record <strong>(ID ${acctId})</strong>? This action cannot be undone.`;

            // Show modal
            const deleteModalEl = document.getElementById('confirmDeleteModal');
            const deleteModal = new bootstrap.Modal(deleteModalEl);
            deleteModal.show();
          });
        });
      }
    }

    // Get current view from URL or PHP
    let currentView = '<?= $currentView ?>';
    // Update dropdown button text on page load
    dropdownBtn.textContent = currentView === 'declined' ? 'Declined Accounts' : 'Current Pending Accounts';
    // Handle dropdown clicks with pagination
    dropdownItems.forEach(item => {
      item.addEventListener('click', () => {
        const view = item.getAttribute('data-view');
        if (view === currentView) return;
        
        // Update button label
        dropdownBtn.textContent = item.textContent;
        
        // Redirect with pagination reset
        const url = new URL(window.location.href);
        url.searchParams.set('view', view);
        url.searchParams.set('page_num', '1');
        window.location.href = url;
      });
    });

    // Initial bind for pending
    bindRowEvents();

    // Bootstrap alerts that can be dismissed
    document.querySelectorAll('.alert-dismissible').forEach(alertEl => {
      // after 3 seconds (3000ms), close the alert
      setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
        bsAlert.close();
      }, 3000);
    });

    // Move this OUTSIDE bindRowEvents
    document.getElementById('requestDetailsModal').addEventListener('shown.bs.modal', () => {
      document.querySelectorAll('.id-preview').forEach(img => {
        img.addEventListener('click', () => {
          const src = img.getAttribute('data-full-src');
          const title = img.getAttribute('data-title') || 'Identification';
          document.getElementById('zoomedImage').src = src;
          document.getElementById('zoomImageTitle').textContent = title;
          const zoomModal = new bootstrap.Modal(document.getElementById('zoomImageModal'));
          zoomModal.show();
        });
      });
    });
  });
</script>
