<?php
require 'functions/dbconn.php';

if (!isset($_SESSION['auth'])||!$_SESSION['auth']) {
    header("Location:index.php"); 
    exit;
}
// Fetch initial pending for server-render
$sql0 = "SELECT * FROM pending_accounts ORDER BY time_creation DESC";
$res0 = $conn->query($sql0);
$initial = [];
if ($res0) {
    while ($r = $res0->fetch_assoc()) {
        $initial[] = $r;
    }
}
?>

<title>eBarangay Mo | Account Verifications</title>

<div class="container-fluid p-3">
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
  <div class="card shadow-sm p-3">
    <div class="card-body p-0">
      <div class="table-responsive admin-table" style="height:500px;overflow-y:auto;">
        <table class="table mb-0 align-middle text-start" id="requestsTable">
          <thead class="table-light">
            <tr>
              <th class="text-nowrap">Account ID</th>
              <th class="text-nowrap">Name</th>
              <th class="text-nowrap">Date - Time Created</th>
              <th class="text-nowrap">Action</th>
            </tr>
          </thead>
          <tbody id="requestsTbody">
            <?php if (empty($initial)): ?>
              <tr><td colspan="4">No pending requests</td></tr>
            <?php else: ?>
              <?php foreach ($initial as $row):
                $fmt = date("F d, Y - h:i A", strtotime($row['time_creation']));
              ?>
                <tr class="request-row" style="cursor:pointer;" data-id="<?php echo $row['account_ID'] ?>" data-full='<?php echo json_encode($row, JSON_HEX_TAG) ?>'>
                  <td><?php echo $row['account_ID'] ?></td>
                  <td><?php echo htmlspecialchars($row['full_name']) ?></td>
                  <td><?php echo $fmt ?></td>
                  <td>
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

<!-- Details Modal (same for both views) -->
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

<!-- Confirmation Modal for Approve/Attach (pending) -->
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

<!-- Decline Reason Modal -->
<div class="modal fade" id="declineReasonModal" tabindex="-1" aria-labelledby="declineReasonModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="declineReasonModalLabel">Decline Account Confirmation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="declineReasonForm" method="POST" action="functions/decline_account.php">
        <div class="modal-body">
          <input type="hidden" name="account_ID" id="declineAccountId" value="">
          <div class="mb-3">
            <label id="declineReasonLabel" for="declineReasonInput" class="form-label"></label>
            <textarea class="form-control" id="declineReasonInput" name="reason" rows="3" required></textarea>
          </div>
        </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Confirm Decline</button>
      </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Permanently Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Permanent Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="confirmDeleteForm" method="POST" action="functions/delete_declined_account.php">
        <div class="modal-body">
          <input type="hidden" name="account_ID" id="deleteAccountId" value="">
          <p id="confirmDeleteLabel"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete Permanently</button>
        </div>
      </form>
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
          let html = '<dl class="row">';
          const exclude = ['username','password'];
          for (let key in dataObj) {
            if (exclude.includes(key)) continue;
            let label = key.replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase());
            let val = dataObj[key];
            if (/\.(jpg|jpeg|png|gif)$/i.test(val)) {
              const folder = key.includes('front') ? 'frontID'
                          : key.includes('back') ? 'backID'
                          : 'profilePictures';
              html += `<dt class="col-sm-3">${label}</dt>
                      <dd class="col-sm-9 mb-3">
                        <img src="${folder}/${val}"
                              class="img-fluid img-thumbnail" style="max-height:200px;">
                      </dd>`;
            } else {
              html += `<dt class="col-sm-3">${label}</dt>
                      <dd class="col-sm-9 mb-3">${val}</dd>`;
            }
          }
          html += '</dl>';
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
            labelEl.textContent = 
              `This action cannot be undone. Please state the reason below for declining ${fullName}’s account request (ID ${acctId}).`;

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
            labelP.textContent = `Are you sure you want to permanently delete ${fullName}’s declined record (ID ${acctId})? This action cannot be undone.`;

            // Show modal
            const deleteModalEl = document.getElementById('confirmDeleteModal');
            const deleteModal = new bootstrap.Modal(deleteModalEl);
            deleteModal.show();
          });
        });
      }
    }

    let currentView = 'pending'; // track state
    // Handle dropdown clicks
    dropdownItems.forEach(item => {
      item.addEventListener('click', () => {
        const view = item.getAttribute('data-view');
        if (view === currentView) return;
        // update label
        dropdownBtn.textContent = item.textContent;
        // fetch JSON
        fetch('functions/account_request_type.php?view=' + view)
          .then(res => res.json())
          .then(obj => {
            currentView = obj.view;
            // rebuild header
            rebuildHeader(currentView);
            // rebuild body
            tbody.innerHTML = '';
            const arr = obj.data;
            if (!arr.length) {
              const tr = document.createElement('tr');
              const td = document.createElement('td');
              td.colSpan = (currentView==='pending') ? 4 : 5;
              td.classList.add('text-center');
              td.textContent = currentView==='pending' ? 'No pending requests' : 'No declined accounts';
              tr.appendChild(td);
              tbody.appendChild(tr);
            } else {
              arr.forEach(row => {
                const tr = document.createElement('tr');
                tr.className='request-row';
                tr.style.cursor='pointer';
                tr.dataset.id = row.account_ID;
                tr.setAttribute('data-full', JSON.stringify(row));
                
                // Account ID
                const td1 = document.createElement('td');
                td1.textContent = row.account_ID;
                // Name
                const td2 = document.createElement('td');
                td2.textContent = row.full_name;
                // Date
                const td3 = document.createElement('td');
                const dtField = (currentView==='pending') ? row.time_creation : row.time_declined;
                td3.textContent = formatDateTime(dtField);
                tr.append(td1, td2, td3);

                if (currentView === 'declined') {
                  const tdReason = document.createElement('td');
                  tdReason.textContent = row.reason || '';
                  tr.append(tdReason);
                }

                // Action cell
                const tdAction = document.createElement('td');
                if (currentView==='pending') {
                  const container = document.createElement('div');
                  container.className = 'd-flex gap-1';

                  // Approve form
                  const formA = document.createElement('form');
                  formA.method='POST'; formA.action='functions/approve_account.php';
                  formA.className='approve-form';
                  ['account_ID','name','purok','redirectTo'].forEach(key => {
                    const inp = document.createElement('input');
                    inp.type='hidden'; inp.name=key;
                    if (key==='account_ID') inp.value=row.account_ID;
                    else if (key==='name') inp.value=row.full_name;
                    else if (key==='purok') inp.value=row.purok;
                    else if (key==='redirectTo') inp.value='admin';
                    formA.appendChild(inp);
                  });

                  // Approve button
                  const btnA = document.createElement('button');
                  btnA.type = 'submit';
                  btnA.className = 'btn btn-sm btn-success';
                  btnA.style.minWidth = '90px';
                  btnA.textContent = 'Approve';
                  formA.appendChild(btnA);
                  container.appendChild(formA);

                  // Decline button
                  const btnD = document.createElement('button');
                  btnD.type = 'button';
                  btnD.className = 'btn btn-sm btn-danger decline-btn';
                  btnD.style.minWidth = '90px';
                  btnD.dataset.accountId = row.account_ID;
                  btnD.textContent = 'Decline';
                  container.appendChild(btnD);
                  tdAction.appendChild(container);

                } else {
                  // Delete permanently form
                  const formDel = document.createElement('form');
                  formDel.method = 'POST';
                  formDel.action = 'functions/delete_declined_account.php';
                  formDel.className = 'd-inline';
                  const inp = document.createElement('input');
                  inp.type = 'hidden';
                  inp.name = 'account_ID';
                  inp.value = row.account_ID;
                  formDel.appendChild(inp);
                  const btnDel = document.createElement('button');
                  btnDel.type = 'button';
                  btnDel.className = 'btn btn-sm btn-danger btn-delete-declined';
                  btnDel.style.minWidth = '170px';
                  btnDel.textContent = 'Delete Permanently';
                  formDel.appendChild(btnDel);
                  tdAction.appendChild(formDel);
                }
                tr.append(tdAction);
                tbody.appendChild(tr);
              });
            }
            // rebind events on new rows
            bindRowEvents();
          }).catch(err => console.error(err));
      });
    });

    // Initial bind for pending
    bindRowEvents();
  });
</script>
