<?php
require 'functions/dbconn.php';
$userId  = (int) $_SESSION['loggedInUserID'];


// Adjust the table/column names below to your actual audit-logs schema
// $sql = "
//   SELECT
//     transaction_id,
//     request_type,
//     DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS created_date,
//     edited_by,
//     change_description
//   FROM request_logs
//   ORDER BY created_at DESC
// ";
// $stmt   = $conn->prepare($sql);
// $stmt->execute();
// $result = $stmt->get_result();
// ?>

<div class="container py-3">
  <div class="card shadow-sm p-3">
    <div class="table-responsive admin-table" style="max-height:500px; overflow-y:auto;">
      <table class="table table-hover align-middle text-center">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">Transaction ID</th>
            <th class="text-nowrap">Request Type</th>
            <th class="text-nowrap">Created Date</th>
            <th class="text-nowrap">Edited By</th>
            <th class="text-nowrap">Change Details</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['transaction_id'])       ?></td>
                <td><?= htmlspecialchars($row['request_type'])         ?></td>
                <td><?= htmlspecialchars($row['created_date'])         ?></td>
                <td><?= htmlspecialchars($row['edited_by'])             ?></td>
                <td class="text-start"><?= htmlspecialchars($row['change_description']) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center">No logs found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$stmt->close();
$conn->close();
?>
