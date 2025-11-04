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

// Determine which table
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

// Check payment source status to determine exact failure reason
$stmt = $conn->prepare("SELECT paymongo_source_id FROM `$table` WHERE transaction_id = ? LIMIT 1");
$stmt->bind_param("s", $transactionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $sourceId = $row['paymongo_source_id'];
    
    // Try to get failure reason from PayMongo
    $failureReason = 'Payment was cancelled or failed.';
    
    if ($sourceId) {
        try {
            $handler = new GCashPaymentHandler($conn);
            $reflection = new ReflectionClass($handler);
            $method = $reflection->getMethod('makeApiRequest');
            $method->setAccessible(true);
            
            $sourceResponse = $method->invoke($handler, 'GET', "/sources/{$sourceId}");
            
            if ($sourceResponse['success']) {
                $sourceStatus = $sourceResponse['data']['data']['attributes']['status'] ?? 'expired';
                
                // Determine user-friendly message based on status
                switch ($sourceStatus) {
                    case 'expired':
                        $failureReason = 'Payment session expired. Please try again.';
                        break;
                    case 'cancelled':
                        $failureReason = 'Payment was cancelled. You can retry if you wish.';
                        break;
                    case 'failed':
                        $failureReason = 'Payment failed. This may be due to insufficient funds or network issues.';
                        break;
                    default:
                        $failureReason = 'Payment could not be completed. Please try again.';
                }
            }
        } catch (Exception $e) {
            // Log error but continue with generic message
            error_log("Failed to get source status: " . $e->getMessage());
        }
    }
    
    // Update payment status
    $updateStmt = $conn->prepare("UPDATE `$table` SET payment_status = 'failed' WHERE transaction_id = ?");
    $updateStmt->bind_param("s", $transactionId);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Log the failure
    logGCashTransaction('Payment failed - user returned', [
        'transaction_id' => $transactionId,
        'table' => $table,
        'reason' => $failureReason
    ]);
    
    $_SESSION['payment_failed_message'] = $failureReason;
} else {
    $_SESSION['payment_failed_message'] = 'Transaction not found. Please contact support.';
}

$stmt->close();
$conn->close();

// Redirect back to payment step (step 2) with retry option
header("Location: ../userPanel.php?page=serviceCertification&tid={$transactionId}&step=2&retry_payment=1");
exit();