<?php
// include 'functions/dbconn.php';

// Define request types and their corresponding tables
$requestTypes = [
  'Barangay ID' => 'barangay_id_requests',
  'Business Permit' => 'business_permit_requests',
  'Certification' => 'certification_requests'
  // You can add more in the future like:
  // 'Clearance' => 'clearance_requests'
];

$filter = $_GET['filter'] ?? 'All';
$queries = [];
$params = [];
$types = '';
$all = $filter === 'All' || !array_key_exists($filter, $requestTypes);

// Construct query: all or filtered
if ($all) {
  foreach ($requestTypes as $typeName => $tableName) {
    $queries[] = "SELECT transaction_id, full_name, request_type AS original_request_type, payment_method, payment_status, document_status, '$typeName' AS table_type FROM $tableName";
  }

  $sql = implode(" UNION ALL ", $queries);
  $stmt = $conn->prepare($sql);
} else {
  $tableName = $requestTypes[$filter];
  $sql = "SELECT transaction_id, full_name, request_type AS original_request_type, payment_method, payment_status, document_status, ? AS table_type FROM $tableName";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $filter);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!-- UI starts here -->
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold">Requests</h4>
    <div class="dropdown">
      <button class="btn btn-outline-success dropdown-toggle" type="button" id="requestFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <?= htmlspecialchars($filter) ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="requestFilterDropdown">
        <li><a class="dropdown-item" href="adminPanel.php?page=adminRequest&filter=All">All</a></li>
        <?php foreach ($requestTypes as $name => $table): ?>
          <li><a class="dropdown-item" href="adminPanel.php?page=adminRequest&filter=<?= urlencode($name) ?>"><?= $name ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div class="card shadow-sm p-3">
    <div class="table-responsive">
      <table class="table align-middle text-start">
        <thead class="table-light">
          <tr>
            <th>Transaction No.</th>
            <th>Name</th>
            <th>Request</th>
            <th>Payment Method</th>
            <th>Payment Status</th>
            <th>Document Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= $row['transaction_id'] ?></td>
                <td><?= $row['full_name'] ?></td>
                <td><?= $row['original_request_type'] ?></td>
                <td><?= $row['payment_method'] ?></td>
                <td><?= $row['payment_status'] ?></td>
                <td><?= $row['document_status'] ?></td>
                <td>
                  <div class="d-flex justify-content-center gap-2">
                    <button 
                      class="btn btn-sm btn-outline-success viewBtn" 
                      data-transaction="<?= $row['transaction_id'] ?>" 
                      data-table="<?= $row['table_type'] ?>" 
                      data-bs-toggle="modal" 
                      data-bs-target="#viewModal">
                      View
                    </button>

                    <button 
                      class="btn btn-sm btn-success text-white editBtn" 
                      data-transaction="<?= $row['transaction_id'] ?>" 
                      data-table="<?= $row['table_type'] ?>" 
                      data-payment-status="<?= $row['payment_status'] ?>"
                      data-document-status="<?= $row['document_status'] ?>"
                      data-bs-toggle="modal" 
                      data-bs-target="#editModal">
                      Edit
                    </button>

                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8">No requests found.</td></tr>
          <?php endif; ?>
          <?php
          $stmt->close();
          $conn->close();
          ?>
        </tbody>
      </table>

      <!-- View Modal -->
      <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="viewModalLabel">View Details</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
              <!-- Data will be loaded here dynamically -->
              Loading...
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Modal -->
      <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <form id="editForm" method="POST" action="functions/update_data.php">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              
              <div class="modal-body">
                <input type="hidden" name="transaction_id" id="editTransactionId">
                <input type="hidden" name="table_type" id="editTableType">
                
                <div class="mb-3">
                  <label for="paymentStatus" class="form-label">Payment Status</label>
                  <select class="form-select" id="paymentStatus" name="payment_status" required>
                    <option value="Unpaid">Unpaid</option>
                    <option value="Paid">Paid</option>
                  </select>
                </div>
                
                <div class="mb-3">
                  <label for="documentStatus" class="form-label">Document Status</label>
                  <select class="form-select" id="documentStatus" name="document_status" required>
                    <option value="Processing">Processing</option>
                    <option value="Ready To Release">Ready To Release</option>
                    <option value="Released">Released</option>
                  </select>
                </div>
              </div>
              <input type="hidden" name="current_filter" id="editCurrentFilter">
              
              <div class="modal-footer">
                <button type="submit" class="btn btn-success">Save Changes</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // View Button Handler
  const viewButtons = document.querySelectorAll('.viewBtn');
  viewButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      const transactionId = this.getAttribute('data-transaction');
      const tableType = this.getAttribute('data-table');

      fetch(`functions/fetch_data.php?transaction_id=${transactionId}&table_type=${encodeURIComponent(tableType)}`)
        .then(response => response.text())
        .then(data => {
          document.getElementById('viewModalBody').innerHTML = data;
        })
        .catch(error => {
          console.error('Error fetching full data:', error);
          document.getElementById('viewModalBody').innerHTML = 'Error loading data.';
        });
    });
  });

  // Edit Button Handler
  const editButtons = document.querySelectorAll('.editBtn');
  editButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('editTransactionId').value = this.getAttribute('data-transaction');
      document.getElementById('editTableType').value = this.getAttribute('data-table');
      document.getElementById('paymentStatus').value = this.getAttribute('data-payment-status');
      document.getElementById('documentStatus').value = this.getAttribute('data-document-status');
      document.getElementById('editCurrentFilter').value = "<?= htmlspecialchars($filter) ?>";
    });
  });
});
</script>
