<?php
session_start();
require 'dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $transaction_id = $_POST['transaction_id'] ?? '';
  $admin_id = $_SESSION['loggedInUserID'] ?? null;
  $role = $_SESSION['loggedInUserRole'] ?? 'Unknown';

  if ($transaction_id !== '') {
    // 1. Get respondent_name from the blotter record before deletion
    $resStmt = $conn->prepare("SELECT respondent_name FROM blotter_records WHERE transaction_id = ?");
    $resStmt->bind_param("s", $transaction_id);
    $resStmt->execute();
    $res = $resStmt->get_result();
    $respondentName = '';
    if ($res && $row = $res->fetch_assoc()) {
      $respondentName = $row['respondent_name'];
    }
    $resStmt->close();

    // 2. Proceed to delete the blotter record
    $stmt = $conn->prepare("DELETE FROM blotter_records WHERE transaction_id = ?");
    $stmt->bind_param("s", $transaction_id);

    if ($stmt->execute()) {
      // 3. Insert into activity_logs
      $log_stmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description, created_at) VALUES (?, ?, 'DELETE', 'blotter_records', ?, ?, NOW())");
      $desc = "Deleted blotter record with transaction ID: $transaction_id";
      $log_stmt->bind_param("isss", $admin_id, $role, $transaction_id, $desc);
      $log_stmt->execute();
      $log_stmt->close();

      echo json_encode(['success' => true]);
    } else {
      echo json_encode(['success' => false, 'error' => 'Failed to delete record.']);
    }

    $stmt->close();
  } else {
    echo json_encode(['success' => false, 'error' => 'No transaction ID provided.']);
  }
}
?>
