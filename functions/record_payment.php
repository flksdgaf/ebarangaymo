<?php
require_once __DIR__ . '/dbconn.php';
date_default_timezone_set('Asia/Manila');

$tid          = $_POST['transaction_id']  ?? die('Missing transaction_id');
$or           = $_POST['or_number']       ?? '';
$amt          = $_POST['amount_paid']     ?? 0;
$issued       = $_POST['issued_date']     ?? date('Y-m-d');

// 1) Look up request_type & full_name in view_request
$stmt = $conn->prepare("
  SELECT request_type, full_name 
    FROM view_request 
   WHERE transaction_id = ?");
$stmt->bind_param('s',$tid);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows!==1) {
  die('Invalid transaction ID');
}
$row = $res->fetch_assoc();
$requestType = $row['request_type'];
$fullName    = $row['full_name'];
$stmt->close();

// 2) Insert into official_receipt_records
$ins = $conn->prepare("
  INSERT INTO official_receipt_records
    (transaction_id, request_type, full_name,
     payment_method, or_number, amount_paid, issued_date)
  VALUES (?,?,?,?,?,?,?)"
);
$ins->bind_param(
  'sssssss',
  $tid,
  $requestType,
  $fullName,
  $_POST['payment_method'],  // come from hidden form or lookup
  $or,
  $amt,
  $issued
);
$ins->execute();
$ins->close();

// 3) Mark the original request paid
//    Map request_type → table
$typeMap = [
  'Barangay ID'     => 'barangay_id_requests',
  'Business Permit' => 'business_permit_requests',
  'Good Moral'      => 'good_moral_requests',
  'Guardianship'    => 'guardianship_requests',
  'Indigency'       => 'indigency_requests',
  'Residency'       => 'residency_requests',
  'Solo Parent'     => 'solo_parent_requests',
  // etc...
];
$table = $typeMap[$requestType] ?? null;
if ($table) {
  $upd = $conn->prepare("
    UPDATE `$table` 
       SET payment_status='Paid'
     WHERE transaction_id = ?");
  $upd->bind_param('s',$tid);
  $upd->execute();
  $upd->close();
}

header("Location: ../adminPanel.php?page=adminRequest&payment_transaction_id={$tid}");
exit;
?>