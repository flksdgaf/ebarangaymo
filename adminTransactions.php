<?php
// include 'functions/dbconn.php';

// Modify the query to only get records where payment_status is 'Paid' and document_status is 'Released'
$stmt = $conn->prepare("SELECT * FROM view_general_requests WHERE payment_status = 'Paid' AND document_status = 'Released'");
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container py-3">
  <div class="card shadow-sm p-3">
    <div class="table-responsive admin-table" style="height:500px;overflow-y:auto;">
      <table class="table align-middle text-center table-hover">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">Transaction ID</th>
            <th class="text-nowrap">Full Name</th>
            <th class="text-nowrap">Request Type</th>
            <th class="text-nowrap">Payment Method</th>
            <th class="text-nowrap">Payment Status</th>
            <th class="text-nowrap">Document Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['request_type']) ?></td>
                <td><?= htmlspecialchars($row['payment_method']) ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars($row['payment_status']) ?></td>
                <td><?= htmlspecialchars($row['document_status']) ?></td>
                <td>
                  <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-outline-success viewRequestBtn" 
                      data-transaction_id="<?= htmlspecialchars($row['transaction_id']) ?>"
                      data-full_name="<?= htmlspecialchars($row['full_name']) ?>"
                      data-request_type="<?= htmlspecialchars($row['request_type']) ?>"
                      data-payment_method="<?= htmlspecialchars($row['payment_method']) ?>"
                      data-created_at="<?= htmlspecialchars($row['created_at']) ?>"
                      data-payment_status="<?= htmlspecialchars($row['payment_status']) ?>"
                      data-document_status="<?= htmlspecialchars($row['document_status']) ?>"
                      data-bs-toggle="modal" data-bs-target="#requestViewModal">
                      View
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
    </div>
  </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="requestViewModal" tabindex="-1" aria-labelledby="requestViewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header d-flex justify-content-between align-items-center">
        <h5 class="modal-title fw-bold" id="requestViewModalLabel">Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p><strong>Transaction ID:</strong> <span id="viewTransactionID"></span></p>
        <p><strong>Full Name:</strong> <span id="viewFullName"></span></p>
        <p><strong>Request Type:</strong> <span id="viewRequestType"></span></p>
        <p><strong>Payment Method:</strong> <span id="viewPaymentMethod"></span></p>
        <p><strong>Created At:</strong> <span id="viewCreatedAt"></span></p>
        <p><strong>Payment Status:</strong> <span id="viewPaymentStatus"></span></p>
        <p><strong>Document Status:</strong> <span id="viewDocumentStatus"></span></p>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const viewRequestButtons = document.querySelectorAll('.viewRequestBtn');

  // View request modal
  viewRequestButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('viewTransactionID').textContent = this.getAttribute('data-transaction_id');
      document.getElementById('viewFullName').textContent = this.getAttribute('data-full_name');
      document.getElementById('viewRequestType').textContent = this.getAttribute('data-request_type');
      document.getElementById('viewPaymentMethod').textContent = this.getAttribute('data-payment_method');
      document.getElementById('viewCreatedAt').textContent = this.getAttribute('data-created_at');
      document.getElementById('viewPaymentStatus').textContent = this.getAttribute('data-payment_status');
      document.getElementById('viewDocumentStatus').textContent = this.getAttribute('data-document_status');
    });
  });
});
</script>
