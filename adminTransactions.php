<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];

// only show Paid + Released
$stmt = $conn->prepare(
  "SELECT 
     transaction_id,
     full_name,
     request_type,
     payment_method,
     payment_status,
     document_status,
     DATE_FORMAT(created_at, '%M %d, %Y') AS created_at
   FROM view_general_requests
   WHERE payment_status = ? 
     AND document_status = ?
   ORDER BY created_at DESC"
);
$paid    = 'Paid';
$released= 'Released';
$stmt->bind_param('ss', $paid, $released);
$stmt->execute();
$result = $stmt->get_result();
?>
<div class="container py-3">
  <div class="card shadow-sm p-3">
    <div class="table-responsive admin-table" style="height:500px;overflow-y:auto;">
      <table class="table table-hover align-middle text-center">
        <thead class="table-light">
          <tr>
            <th>Transaction ID</th>
            <th>Full Name</th>
            <th>Request Type</th>
            <th>Payment Method</th>
            <th>Payment Status</th>
            <th>Document Status</th>
            <th>Created At</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['request_type']) ?></td>
                <td><?= htmlspecialchars($row['payment_method']) ?></td>
                <td><?= htmlspecialchars($row['payment_status']) ?></td>
                <td><?= htmlspecialchars($row['document_status']) ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8">No requests found.</td></tr>
          <?php endif; ?>
          <?php $stmt->close(); $conn->close(); ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- your modal + script unchanged -->
