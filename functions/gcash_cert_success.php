<?php
session_start();
require 'dbconn.php';
require 'gcash_handler.php';

$transactionId = $_GET['transaction_id'] ?? null;

if (!$transactionId) {
    header("Location: ../userPanel.php?page=serviceCertification&error=missing_transaction");
    exit();
}

// Determine which table this transaction belongs to
$map = [
    'RES-'  => 'residency_requests',
    'IND-'  => 'indigency_requests',
    'GM-'   => 'good_moral_requests',
    'SP-'   => 'solo_parent_requests',
    'GUA-'  => 'guardianship_requests',
    'FTJS-' => 'job_seeker_requests',
];

$table = null;
foreach ($map as $prefix => $tbl) {
    if (strpos($transactionId, $prefix) === 0) {
        $table = $tbl;
        break;
    }
}

if (!$table) {
    header("Location: ../userPanel.php?page=serviceCertification&error=invalid_transaction");
    exit();
}

// Process GCash payment
$result = handleGCashSuccessForTable($transactionId, $table);

if ($result['success']) {
    header("Location: ../userPanel.php?page=serviceCertification&tid={$transactionId}&payment_success=1");
} else {
    header("Location: ../userPanel.php?page=serviceCertification&tid={$transactionId}&payment_error=1");
}
exit();