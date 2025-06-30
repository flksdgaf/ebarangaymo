<?php
session_start();
require 'dbconn.php';

// 1) AUTH CHECK
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = (int)$_SESSION['loggedInUserID'];
$role = $_SESSION['loggedInUserRole'] ?? 'Unknown';

// 2) INPUTS
$type = $_GET['type'] ?? '';
$transactionId = $_GET['transaction_id'] ?? '';

// 3) WHITELIST & MAP TO TABLE NAMES
$map = [
  'Barangay ID' => 'barangay_id_requests',
  'Business Permit' => 'business_permit_requests',
  'Good Moral' => 'good_moral_requests',
  'Guardianship' => 'guardianship_requests',
  'Indigency' => 'indigency_requests',
  'Residency' => 'residency_requests',
  'Solo Parent' => 'solo_parent_requests',
];

if (! isset($map[$type]) || $transactionId === '') {
    // invalid request → back with error
    header("Location: ../superAdminRequest.php?error=invalid");
    exit();
}

$tableName = $map[$type];

// 4) DELETE THE ROW
$delSql = "DELETE FROM `{$tableName}` WHERE transaction_id = ?";
$delStmt = $conn->prepare($delSql);
$delStmt->bind_param('s', $transactionId);
$delStmt->execute();

if ($delStmt->affected_rows > 0) {
    // 5) ACTIVITY LOGGING
    $action = 'DELETE';
    $description = "Deleted {$type} request {$transactionId}";
    
    $logSql = "INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)";
    $logStmt = $conn->prepare($logSql);
    
    $logStmt->bind_param('isssss',$userId,$role,$action,$tableName,$transactionId,$description   );
    $logStmt->execute();
    $logStmt->close();

    // 6) REDIRECT BACK WITH SUCCESS FLAG
    header("Location: ../superAdminPanel.php?page=superAdminRequest&deleted={$transactionId}");
    exit;
}

// 7) NOTHING DELETED → NOT FOUND
header("Location: ../superAdminRequest.php?error=notfound");
exit;
?>
