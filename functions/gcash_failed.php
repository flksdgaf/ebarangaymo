<?php
session_start();
require 'dbconn.php';

$transactionId = $_GET['transaction_id'] ?? null;

if ($transactionId) {
    // Update payment status to failed
    $stmt = $conn->prepare("UPDATE barangay_id_requests SET payment_status = 'failed' WHERE transaction_id = ?");
    $stmt->bind_param("s", $transactionId);
    $stmt->execute();
    $stmt->close();
    
    header("Location: ../userPanel.php?page=serviceBarangayID&tid={$transactionId}&payment_failed=1");
} else {
    header("Location: ../userPanel.php?page=serviceBarangayID&error=missing_transaction");
}
exit();