<?php
session_start();
require 'dbconn.php';

$transactionId = $_GET['transaction_id'] ?? null;

if ($transactionId) {
    // Determine which table
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
    
    if ($table) {
        $stmt = $conn->prepare("UPDATE `$table` SET payment_status = 'failed' WHERE transaction_id = ?");
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: ../userPanel.php?page=serviceCertification&tid={$transactionId}&payment_failed=1");
} else {
    header("Location: ../userPanel.php?page=serviceCertification&error=missing_transaction");
}
exit();