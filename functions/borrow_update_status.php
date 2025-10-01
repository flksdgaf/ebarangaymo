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
  if ($old_status !== 'Returned' && $new_status === 'Returned') {
    // Returning equipment - increase available_qty
    $stmt = $conn->prepare("UPDATE equipment_list SET available_qty = available_qty + ? WHERE equipment_sn = ?");
    $stmt->bind_param('is', $qty, $equipment_sn);
    
    if (!$stmt->execute()) {
      throw new Exception('Failed to update equipment availability: ' . $stmt->error);
    }
    $stmt->close();
  } elseif ($old_status === 'Returned' && $new_status !== 'Returned') {
    // Un-returning equipment - decrease available_qty
    $stmt = $conn->prepare("UPDATE equipment_list SET available_qty = available_qty - ? WHERE equipment_sn = ?");
    $stmt->bind_param('is', $qty, $equipment_sn);
    
    if (!$stmt->execute()) {
      throw new Exception('Failed to update equipment availability: ' . $stmt->error);
    }
    $stmt->close();
  }
  
  // Get the new available_qty
  $stmt = $conn->prepare("SELECT available_qty FROM equipment_list WHERE equipment_sn = ?");
  $stmt->bind_param('s', $equipment_sn);
  $stmt->execute();
  $result = $stmt->get_result();
  $equipment = $result->fetch_assoc();
  $new_available_qty = $equipment ? (int)$equipment['available_qty'] : 0;
  $stmt->close();
  
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