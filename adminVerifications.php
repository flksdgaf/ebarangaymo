<div class="container py-4">
  <!-- Title and Filter -->
  <!-- <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold">Account Requests</h4>
  </div> -->

  <!-- Account Requests Table -->
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
          <?php
            // Fetch account requests from the database
            $sql = "SELECT * FROM pending_accounts";
            $result = $conn->query($sql);
            $allRequests = [];

            if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                $formatted = date("F d, Y - h:i A", strtotime($row['time_creation']));
                $allRequests[$row['account_ID']] = $row;

                echo "<tr class='request-row' data-id='{$row['account_ID']}' style='cursor:pointer'>";
                echo "<td>{$row['account_ID']}</td>";
                echo "<td>{$row['full_name']}</td>";
                echo "<td>{$formatted}</td>";
                echo "<td>";
                echo "<div class='d-flex justify-content-center gap-2'>";
                // -------------------------------------------------------------------
                // FORM CHANGED: Added class="approve-form" so we can intercept it
                // -------------------------------------------------------------------
                echo "
                  <form action='functions/approve_account.php' method='POST' class='d-inline approve-form'> <!-- MODIFIED -->
                    <input type='hidden' name='account_ID' value='{$row['account_ID']}'>
                    <input type='hidden' name='name' value='{$row['full_name']}'>
                    <button type='submit' class='btn btn-sm btn-outline-success'>Approve</button>
                  </form>
                ";
                echo "<button class='btn btn-sm btn-danger text-white' data-account-id='{$row['account_ID']}'>Decline</button>";
                echo "</div>";
                echo "</td>";
                echo "</tr>";
              }
            }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Details Modal (unchanged) -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="requestDetailsModalLabel">Account Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body"><!-- content by JS --></div>
    </div>
  </div>
</div>

<!-- NEW: Confirmation Modal -->
<div class="modal fade" id="confirmApproveModal" tabindex="-1" aria-labelledby="confirmApproveModalLabel" aria-hidden="true"> <!-- MODIFIED -->
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmApproveModalLabel">Confirm Account Verification</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to approve <strong id="confirmName"></strong>'s account with ID <strong id="confirmAccountId"></strong>?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmApproveBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>

<!-- Pass the PHP array to JS -->
<script>
  const requestsData = <?php echo json_encode($allRequests); ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // === existing details‐modal code (unchanged) ===
  document.querySelectorAll('.request-row').forEach(row => {
    row.addEventListener('click', (e) => {
      if (e.target.tagName === 'BUTTON') return;
      const id   = row.dataset.id;
      const data = requestsData[id];
      if (!data) return;

      // Start a Bootstrap grid + definition list
      let html = '<div class="container-fluid"><dl class="row">';

      const excludedKeys = ['username', 'password'];
      for (let key in data) {
        if (excludedKeys.includes(key)) continue;
        // Turn snake_case into Title Case
        let label = key
          .replace(/_/g, ' ')
          .replace(/\b\w/g, l => l.toUpperCase());

        // Check for image file extensions
        if (/\.(jpg|jpeg|png|gif|bmp|webp)$/i.test(data[key])) {
          const folder = key.includes('front')
            ? 'frontID'
            : key.includes('back')
              ? 'backID'
              : 'profilePictures';
          const imgSrc = `${folder}/${data[key]}`;

          html += `
            <dt class="col-sm-5">${label}</dt>
            <dd class="col-sm-7 mb-4">
              <img src="${imgSrc}" class="img-fluid img-thumbnail" style="max-height:200px;" alt="${label}">
            </dd>`;
        } else {
          html += `
            <dt class="col-sm-5">${label}</dt>
            <dd class="col-sm-7 mb-3">${data[key]}</dd>`;
        }
      }

      html += '</dl></div>';
      
      // Inject and show
      document.querySelector('#requestDetailsModal .modal-body')
              .innerHTML = html;
      new bootstrap.Modal(document.getElementById('requestDetailsModal')).show();
    });

  });

  // === NEW: confirmation flow for Approve buttons ===
  let pendingForm = null;
  const confirmModalEl = document.getElementById('confirmApproveModal');
  const confirmModal = new bootstrap.Modal(confirmModalEl);
  const confirmIdSpan = document.getElementById('confirmAccountId');
  const confirmNameSpan = document.getElementById('confirmName');
  const confirmBtn = document.getElementById('confirmApproveBtn');

  document.querySelectorAll('.approve-form').forEach(form => {              // MODIFIED
    form.addEventListener('submit', e => {
      e.preventDefault();
      pendingForm = form;

      // grab the hidden inputs by their name attributes
      const accInput  = form.querySelector('input[name="account_ID"]');
      const nameInput = form.querySelector('input[name="name"]');

      confirmIdSpan.textContent   = accInput  ? accInput.value  : '';
      confirmNameSpan.textContent = nameInput ? nameInput.value : '';

      confirmModal.show();
    });
  });

  confirmBtn.addEventListener('click', () => {                            // MODIFIED
    if (pendingForm) pendingForm.submit();
  });
});
</script>