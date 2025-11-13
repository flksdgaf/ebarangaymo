<?php
session_start();
require_once __DIR__ . '/../dbconn.php';
require_once __DIR__ . '/config.php';

$transactionId = $_GET['tid'] ?? null;

if (!$transactionId) {
    $_SESSION['payment_error'] = 'Invalid transaction reference';
    header("Location: ../../userPanel.php?page=userDashboard");
    exit();
}

// Get request type info
$requestInfo = getRequestTypeInfo($transactionId);
if (!$requestInfo) {
    $_SESSION['payment_error'] = 'Invalid transaction type';
    header("Location: ../../userPanel.php?page=userDashboard");
    exit();
}

// Update payment status to failed
$stmt = $conn->prepare("
    UPDATE `{$requestInfo['table']}` 
    SET payment_status = 'failed' 
    WHERE transaction_id = ?
");
$stmt->bind_param("s", $transactionId);
$stmt->execute();
$stmt->close();

// Determine redirect page
$pageMap = [
    'barangay_id_requests' => 'serviceBarangayID',
    'barangay_clearance_requests' => 'serviceBarangayClearance',
    'business_clearance_requests' => 'serviceBusinessClearance',
    'residency_requests' => 'serviceCertification',
    'indigency_requests' => 'serviceCertification',
    'good_moral_requests' => 'serviceCertification',
    'solo_parent_requests' => 'serviceCertification',
    'guardianship_requests' => 'serviceCertification',
    'job_seeker_requests' => 'serviceCertification'
];

$redirectPage = $pageMap[$requestInfo['table']] ?? 'userDashboard';

$_SESSION['payment_failed'] = 'Payment was cancelled or failed. Please try again.';
header("Location: ../../userPanel.php?page={$redirectPage}&tid={$transactionId}&step=2&retry_payment=1");
exit();