<?php
// functions/getRequestDetails.php
session_start();
require 'dbconn.php';

$tid = $_GET['transaction_id'] ?? '';
if (!$tid) {
    http_response_code(400);
    echo json_encode(['error'=>'No transaction_id provided']);
    exit;
}

// 1) find the request_type
$stmt = $conn->prepare("
    SELECT request_type
      FROM view_general_requests
     WHERE transaction_id = ?
     LIMIT 1
");
$stmt->bind_param('s',$tid);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows===0) {
    http_response_code(404);
    echo json_encode(['error'=>'Not found']);
    exit;
}
$type = $res->fetch_assoc()['request_type'];
$stmt->close();

// 2) pick table and columns
switch($type) {
  case 'Barangay ID':
    $tbl = 'barangay_id_requests';
    $cols = ['transaction_id','full_name','address','height','weight','birthdate','birthplace','civil_status','religion','contact_person','claim_date','payment_method', 'payment_status', 'document_status'];
    break;
  case 'Business Permit':
    $tbl = 'business_permit_requests';
    $cols = ['transaction_id','full_name','full_address','civil_status','purok','barangay','age','claim_date','name_of_business','type_of_business','payment_method', 'payment_status', 'document_status'];
    break;
  case 'Residency':
    $tbl = 'residency_requests';
    $cols = ['transaction_id','full_name','age','civil_status','purok','residing_years','claim_date','purpose','payment_method', 'payment_status', 'document_status'];
    break;
  // add other cases as needed
  default:
    http_response_code(400);
    echo json_encode(['error'=>'Unknown request type']);
    exit;
}

$colList = implode(', ', $cols);
$q = $conn->prepare("SELECT {$colList} FROM {$tbl} WHERE transaction_id = ? LIMIT 1");
$q->bind_param('s',$tid);
$q->execute();
$detail = $q->get_result()->fetch_assoc();
$q->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode([
  'request_type'=>$type,
  'details'=>$detail
]);
exit;       
?>