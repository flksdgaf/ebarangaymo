<?php
// functions/updateRequestDetails.php
session_start();
header('Content-Type: application/json');

require 'dbconn.php';

// 1) Decode JSON
$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload || empty($payload['transaction_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payload']);
    exit;
}
$tid = $payload['transaction_id'];

// 2) Fetch request_type so we know which table
$stmt = $conn->prepare("
    SELECT request_type
      FROM view_general_requests
     WHERE transaction_id = ?
     LIMIT 1
");
$stmt->bind_param('s', $tid);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    echo json_encode(['success' => false, 'error' => 'Unknown transaction']);
    exit;
}

$type = $res['request_type'];

// 3) Map to table & allowed fields
switch ($type) {
    case 'Residency':
        $table  = 'residency_requests';
        $fields = [
            'full_name','age','civil_status',
            'purok','residing_years','claim_date',
            'purpose','payment_method','payment_status','document_status'
        ];
        break;

    case 'Barangay ID':
        $table = 'barangay_id_requests';
        $fields = [
            'transaction_type','full_name','address',
            'height','weight','birthdate','birthplace',
            'civil_status','religion','contact_person',
            'claim_date','payment_method','payment_status','document_status'
        ];
        break;

    case 'Business Permit':
        $table = 'business_permit_requests';
        $fields = [
            'transaction_type','full_name','full_address',
            'civil_status','purok','barangay','age',
            'claim_date','name_of_business','type_of_business',
            'payment_method','payment_status','document_status'
        ];
        break;

    // add other cases as needed...
    case 'Indigency':
        $table = 'indigency_requests';
        $fields = [
            'full_name','age','civil_status',
            'purok','claim_date',
            'purpose','payment_method','payment_status','document_status'
        ];
        break;

    case 'Good Moral':
        $table = 'good_moral_requests';
        $fields = [
            'full_name','age','civil_status',
            'purok','claim_date',
            'purpose','payment_method','payment_status','document_status'
        ];
        break;
    
    case 'Guardianship':
        $table = 'guardianship_requests';
        $fields = [
            'full_name','age','civil_status',
            'purok','child_name','claim_date',
            'purpose','payment_method','payment_status','document_status'
        ];
        break;

    case 'Solo Parent':
        $table = 'solo_parent_requests';
        $fields = [
            'full_name','age','civil_status',
            'purok','child_name','child_age',
            'years_solo_parent','claim_date',
            'purpose','payment_method','payment_status','document_status'
        ];
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unsupported request type']);
        exit;
}

// 4) Build SET clauses dynamically based on payload
$sets = [];
$vals = [];
foreach ($fields as $col) {
    if (isset($payload[$col])) {
        $sets[] = "`$col` = ?";
        $vals[] = $payload[$col];
    }
}

if (count($sets) === 0) {
    echo json_encode(['success' => false, 'error' => 'Nothing to update']);
    exit;
}

// 5) Prepare & execute UPDATE
$sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE transaction_id = ?";
$stmt = $conn->prepare($sql);

// all-binding as strings for simplicity
$types = str_repeat('s', count($vals)) . 's';
$params = array_merge($vals, [$tid]);
$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
} else {
    echo json_encode(['success' => true]);
}
$stmt->close();
$conn->close();
exit; 
?>