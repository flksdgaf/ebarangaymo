<?php
session_start();
require 'dbconn.php';
require 'gcash_handler.php';

$transactionId = $_GET['transaction_id'] ?? null;

if (!$transactionId) {
    $_SESSION['payment_error_message'] = 'Invalid transaction reference.';
    header("Location: ../userPanel.php?page=serviceCertification&error=missing_transaction");
    exit();
}

// Determine which table this transaction belongs to
$map = [
    'RES-'  => 'residency_requests',
    'IND-'  => 'indigency_requests',
    'CGM-'  => 'good_moral_requests',
    'CSP-'  => 'solo_parent_requests',
    'GUA-'  => 'guardianship_requests',
    'FJS-'  => 'job_seeker_requests',
];

$table = null;
foreach ($map as $prefix => $tbl) {
    if (strpos($transactionId, $prefix) === 0) {
        $table = $tbl;
        break;
    }
}

if (!$table) {
    $_SESSION['payment_error_message'] = 'Invalid transaction type.';
    header("Location: ../userPanel.php?page=serviceCertification&error=invalid_transaction");
    exit();
}

// Log the success callback
logGCashTransaction('Success callback received', [
    'transaction_id' => $transactionId,
    'table' => $table
]);

// Process GCash payment
$result = handleGCashSuccessForTable($transactionId, $table);

if ($result['success']) {
    // Payment successful - redirect to step 3 (review)
    $_SESSION['gcash_payment_complete'] = true;
    $_SESSION['payment_success_message'] = 'Payment successful! Please review your information before final submission.';
    
    logGCashTransaction('Payment processed successfully', [
        'transaction_id' => $transactionId,
        'payment_id' => $result['payment_id'] ?? 'unknown',
        'reference' => $result['reference'] ?? 'unknown'
    ]);
    
    header("Location: ../userPanel.php?page=serviceCertification&tid={$transactionId}&step=3");
} else {
    // Payment processing failed - might be due to timing, insufficient funds, etc.
    $errorMessage = $result['error'] ?? 'Payment verification failed.';
    
    logGCashTransaction('Payment processing failed', [
        'transaction_id' => $transactionId,
        'error' => $errorMessage
    ]);
    
    // Check if it's a temporary issue (source not yet chargeable)
    if (strpos($errorMessage, 'not yet completed') !== false) {
        $_SESSION['payment_pending_message'] = 'Payment is being processed. Please wait a moment and refresh the page.';
        header("Location: ../userPanel.php?page=serviceCertification&tid={$transactionId}&step=2&payment_pending=1");
    } else {
        // Actual failure - go back to payment step
        $_SESSION['payment_error_message'] = $errorMessage;
        header("Location: ../userPanel.php?page=serviceCertification&tid={$transactionId}&step=2&payment_error=1");
    }
}
exit();