<?php
require '../functions/dbconn.php';
$data = json_decode(file_get_contents('php://input'), true);

$tid  = $data['transaction_id']  ?? '';
$type = $data['request_type']    ?? '';
if (!$tid || !$type) {
  echo json_encode(['success'=>false,'error'=>'Missing identifiers']); exit;
}

// define which fields are safe to update for each request_type
$fields_map = [
  'Barangay ID'     => ['transaction_type','full_name','address','height','weight','birthday','birthplace','civil_status','religion','contact_person','claim_date','payment_method'],
  'Business Permit' => ['transaction_type','full_name','address','civil_status','purok','barangay','age','claim_date','business_name','business_type','payment_method'],
  'Certification'   => ['transaction_type','full_name','street','purok','birthdate','birthplace','age','civil_status','purpose','claim_date','payment_method'],
];

if (!isset($fields_map[$type])) {
  echo json_encode(['success'=>false,'error'=>'Unknown request type']); exit;
}
$allowed = $fields_map[$type];

// build SET clause dynamically
$sets = [];
$params = [];
$types = '';
foreach ($allowed as $field) {
  if (isset($data[$field])) {
    $sets[]      = "`$field` = ?";
    $params[]    = $data[$field];
    $types      .= 's';
  }
}

if (empty($sets)) {
  echo json_encode(['success'=>false,'error'=>'Nothing to update']); exit;
}

$sql = "UPDATE requests_table
         SET " . implode(',', $sets) . "
       WHERE transaction_id = ?";
$types .= 's';
$params[] = $tid;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
  echo json_encode(['success'=>false,'error'=>$stmt->error]);
} else {
  echo json_encode(['success'=>true]);
}
$stmt->close();
$conn->close();
