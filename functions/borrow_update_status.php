<?php
// functions/borrow_update_status.php
require 'dbconn.php';
header('Content-Type: application/json');

$id = $_POST['id'] ?? '';
$new_status = $_POST['new_status'] ?? '';
$old_status = $_POST['old_status'] ?? '';

if (!$id || !$new_status) {
  echo json_encode(['success' => false, 'error' => 'Missing required fields']);
  exit;
}

// Start transaction
$conn->begin_transaction();

try {
  // Get the borrow request details
  $stmt = $conn->prepare("SELECT equipment_sn, qty FROM borrow_requests WHERE id = ?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $borrow = $result->fetch_assoc();
  $stmt->close();
  
  if (!$borrow) {
    throw new Exception('Borrow request not found');
  }
  
  $equipment_sn = $borrow['equipment_sn'];
  $qty = (int)$borrow['qty'];
  
  // Update the borrow request status
  $stmt = $conn->prepare("UPDATE borrow_requests SET status = ? WHERE id = ?");
  $stmt->bind_param('si', $new_status, $id);
  
  if (!$stmt->execute()) {
    throw new Exception('Failed to update borrow status: ' . $stmt->error);
  }
  $stmt->close();
  
  // Update equipment availability based on status changes
  // Reject from Pending: Return the reserved quantity back to available
  if ($old_status === 'Pending' && $new_status === 'Rejected') {
    $stmt = $conn->prepare("UPDATE equipment_list SET available_qty = available_qty + ? WHERE equipment_sn = ?");
    $stmt->bind_param('is', $qty, $equipment_sn);
    
    if (!$stmt->execute()) {
      throw new Exception('Failed to update equipment availability: ' . $stmt->error);
    }
    $stmt->close();
  }
  // Return from Borrowed: Increase available_qty
  elseif ($old_status === 'Borrowed' && $new_status === 'Returned') {
    $stmt = $conn->prepare("UPDATE equipment_list SET available_qty = available_qty + ? WHERE equipment_sn = ?");
    $stmt->bind_param('is', $qty, $equipment_sn);
    
    if (!$stmt->execute()) {
      throw new Exception('Failed to update equipment availability: ' . $stmt->error);
    }
    $stmt->close();
  }
  // Borrow from Pending: Quantity already deducted when request was created, no change needed
  elseif ($old_status === 'Pending' && $new_status === 'Borrowed') {
    // No change to available_qty - it was already deducted when the borrow request was created
  }
  
  // Get the new available_qty
  $stmt = $conn->prepare("SELECT available_qty FROM equipment_list WHERE equipment_sn = ?");
  $stmt->bind_param('s', $equipment_sn);
  $stmt->execute();
  $result = $stmt->get_result();
  $equipment = $result->fetch_assoc();
  $new_available_qty = $equipment ? (int)$equipment['available_qty'] : 0;
  $stmt->close();
  
  // Activity logging
  session_start();
  $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer'];
  if (isset($_SESSION['loggedInUserRole']) && in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
    // Get transaction_id and resident_name for better logging
    $stmt = $conn->prepare("SELECT transaction_id, resident_name FROM borrow_requests WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $borrowDetails = $result->fetch_assoc();
    $stmt->close();
    
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
    if ($logStmt && $borrowDetails) {
      $admin_id = (int)$_SESSION['loggedInUserID'];
      $roleName = $_SESSION['loggedInUserRole'];
      $action = 'UPDATE';
      $table_name = 'borrow_requests';
      $record_id = $borrowDetails['transaction_id'];
      $description = 'Updated Borrow Status: ' . $borrowDetails['resident_name'] . ' - ' . $old_status . ' → ' . $new_status;
      
      $logStmt->bind_param('isssss', $admin_id, $roleName, $action, $table_name, $record_id, $description);
      $logStmt->execute();
      $logStmt->close();
    }
  }
  
  // Commit transaction
  $conn->commit();
  
  echo json_encode([
    'success' => true,
    'equipment_sn' => $equipment_sn,
    'new_available_qty' => $new_available_qty
  ]);
  
} catch (Exception $e) {
  $conn->rollback();
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>