<?php
session_start();
require 'dbconn.php';
require 'gcash_handler.php';

$transactionId = $_GET['transaction_id'] ?? null;

if (!$transactionId) {
    header("Location: ../userPanel.php?page=serviceBarangayID&error=missing_transaction");
    exit();
}

$result = handleGCashSuccess($transactionId);

if ($result['success']) {
    header("Location: ../userPanel.php?page=serviceBarangayID&tid={$transactionId}&payment_success=1");
} else {
    header("Location: ../userPanel.php?page=serviceBarangayID&tid={$transactionId}&payment_error=1");
}
exit();