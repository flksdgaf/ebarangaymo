<?php
session_start();
require_once __DIR__ . '/../dbconn.php';
require_once __DIR__ . '/handler.php';
require_once __DIR__ . '/config.php';

$transactionId = $_GET['tid'] ?? null;

if (!$transactionId) {
    $_SESSION['payment_error'] = 'Invalid transaction reference';
    header("Location: ../../userPanel.php?page=userDashboard");
    exit();
}

// Get request type to determine redirect page
$requestInfo = getRequestTypeInfo($transactionId);
if (!$requestInfo) {
    $_SESSION['payment_error'] = 'Invalid transaction type';
    header("Location: ../../userPanel.php?page=userDashboard");
    exit();
}

// Determine which page to redirect to based on request type
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

// Process payment
$handler = new UniversalGCashHandler($conn);
$result = $handler->handleSuccess($transactionId);

if ($result['success']) {
    $_SESSION['payment_success'] = 'Payment successful! Please review your information.';
    header("Location: ../../userPanel.php?page={$redirectPage}&tid={$transactionId}&step=3&payment_success=1");
} else {
    $_SESSION['payment_error'] = $result['error'] ?? 'Payment verification failed';
    header("Location: ../../userPanel.php?page={$redirectPage}&tid={$transactionId}&step=2&payment_error=1");
}
exit();