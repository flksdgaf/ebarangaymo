<div class="container py-4">
  <!-- Title and Filter -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold">Account Requests</h4>
  </div>

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
            $sql = "SELECT * FROM new_acc_requests";
            $result = $conn->query($sql);
            $allRequests = [];

            if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                $formatted = date("F d, Y h:i A", strtotime($row['time_creation']));
                $allRequests[$row['id']] = $row;

                echo "<tr class='request-row' data-id='{$row['id']}' style='cursor:pointer'>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['full_name']}</td>";
                echo "<td>{$formatted}</td>";
                echo "<td>";
                echo "<div class='d-flex justify-content-center gap-2'>";
                echo "<button class='btn btn-sm btn-outline-success' data-account-id='{$row['id']}'>Approve</button>";
                echo "<button class='btn btn-sm btn-danger text-white' data-account-id='{$row['id']}'>Decline</button>";
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

<!-- Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg"> <!-- Adjusted for a larger modal size -->
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="requestDetailsModalLabel">Account Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- content will be inserted by JS -->
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
    document.querySelectorAll('.request-row').forEach(row => {
    row.addEventListener('click', (e) => {
        // Prevent action when clicking a button inside the row
        if (e.target.tagName === 'BUTTON') return;

        const id = row.dataset.id;
        const data = requestsData[id];

        if (data) {
            let html = '';

            const excludedKeys = ['username', 'password']; // exclude sensitive fields

            for (let key in data) {
            if (excludedKeys.includes(key)) continue;

            let label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

            // Check if the value is an image file (basic check)
            if (/\.(jpg|jpeg|png|gif|bmp|webp)$/i.test(data[key])) {
                let imagePath = '';

                // Decide the correct folder based on the key
                if (key.includes('front')) {
                imagePath = `frontID/${data[key]}`;
                } else if (key.includes('back')) {
                imagePath = `backID/${data[key]}`;
                } else {
                imagePath = `uploads/${data[key]}`; // default folder
                }

                html += `
                <div class="mb-3">
                    <strong>${label}:</strong><br>
                    <img src="${imagePath}" class="img-fluid rounded" style="max-height:300px;">
                </div>
                `;
            } else {
                html += `<p><strong>${label}:</strong> ${data[key]}</p>`;
            }
            }

            document.querySelector('#requestDetailsModal .modal-body').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('requestDetailsModal'));
            modal.show();
        }
        });
    });
    });
</script>
