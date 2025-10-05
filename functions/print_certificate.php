<?php
// functions/print_certificate.php
require_once __DIR__ . '/dbconn.php';

$transactionId = $_GET['transaction_id'] ?? '';
if (!$transactionId) {
    die('Transaction ID is required');
}

// 1) Find request type via view_request
$stmt = $conn->prepare("SELECT request_type FROM view_request WHERE transaction_id = ?");
$stmt->bind_param('s', $transactionId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows !== 1) {
    die('Invalid transaction ID');
}
$requestType = $res->fetch_assoc()['request_type'];
$stmt->close();

// 2) Map requestType → actual table name
$typeMap = [
    'Barangay ID'       => 'barangay_id_requests',
    'Business Permit'   => 'business_permit_requests',
    'Good Moral'        => 'good_moral_requests',
    'Guardianship'      => 'guardianship_requests',
    'Indigency'         => 'indigency_requests',
    'Residency'         => 'residency_requests',
    'Solo Parent'       => 'solo_parent_requests',
];
if (!isset($typeMap[$requestType])) {
    die('Unknown request type');
}
$table = $typeMap[$requestType];

// 3) Define, per‑table, which columns to select
$requestFields = [
  'barangay_id_requests' => [
    'transaction_id', 'request_type', 'transaction_type', 'full_name', 'purok', 'birth_date', 'birth_place', 
    'civil_status', 'religion', 'height', 'weight', 'emergency_contact_person', 'emergency_contact_address', 
    'formal_picture', 'payment_method', 'amount', 'created_at'
  ],
  'business_permit_requests' => [
    'transaction_id', 'request_type', 'transaction_type', 'full_name', 'purok', 'barangay', 'age', 
    'civil_status', 'name_of_business', 'type_of_business', 'full_address', 'payment_method', 'amount', 'created_at'
  ],
  'good_moral_requests' => [
    'transaction_id', 'request_type', 'full_name', 'civil_status', 'sex', 'age', 'purok', 'address', 
    'purpose', 'payment_method', 'amount', 'created_at'
  ],
  'guardianship_requests' => [
    'transaction_id', 'request_type', 'full_name', 'civil_status', 'age', 'purok', 'child_name', 'child_relationship', 'purpose', 
    'payment_method', 'amount', 'created_at'
  ],
  'indigency_requests' => [
    'transaction_id', 'request_type', 'full_name', 'civil_status', 'age', 'purok', 'purpose', 'created_at'
  ],
  'residency_requests' => [
    'transaction_id', 'request_type', 'full_name', 'civil_status', 'age', 'purok', 'residing_years', 'purpose', 
    'payment_method', 'amount', 'created_at'
  ],
  'solo_parent_requests' => [
    'transaction_id', 'request_type', 'full_name', 'civil_status', 'age', 'sex', 'purok', 'years_solo_parent', 
    'children_data', 'purpose', 'payment_method', 'amount', 'created_at'
  ],
];

if (!isset($requestFields[$table])) {
    die('Unsupported request type for printing');
}
$columns = $requestFields[$table];

// 4) Fetch only those columns
$sql = 'SELECT ' . implode(',', array_map(fn($c)=>"`$c`", $columns))
     . " FROM `$table` WHERE transaction_id = ? LIMIT 1";
$rowStmt = $conn->prepare($sql);
$rowStmt->bind_param('s', $transactionId);
$rowStmt->execute();
$dataRes = $rowStmt->get_result();
if (!$dataRes || $dataRes->num_rows !== 1) {
    die('Record not found');
}
$data = $dataRes->fetch_assoc();
$rowStmt->close();

// 4.5) Update document_status to 'Released' (or another appropriate status)
// $updateStmt = $conn->prepare("UPDATE `$table` SET document_status = 'Ready to Release' WHERE transaction_id = ?");
// $updateStmt = $conn->prepare("UPDATE `$table` SET document_status = 'Ready to Release' WHERE transaction_id = ? AND document_status <> 'Released'");
// $updateStmt->bind_param('s', $transactionId);
// $updateStmt->execute();
// $updateStmt->close();

if ((isset($_GET['print']) && $_GET['print']=='1') || (isset($_GET['download']) && $_GET['download']=='1')) {
  $updateStmt = $conn->prepare(
    "UPDATE `$table`
        SET document_status = 'Ready to Release'
      WHERE transaction_id = ?
        AND document_status <> 'Released'"
  );
  $updateStmt->bind_param('s', $transactionId);
  $updateStmt->execute();
  $updateStmt->close();
}


// 5) Derive template name from table: drop '_requests' suffix
$templateName = str_replace('_requests', '', $table);
$templateFile = __DIR__ . '/../templates/' . $templateName . '.php';
if (!file_exists($templateFile)) {
    die('Template file not found: ' . htmlspecialchars($templateName));
}

// 6) Make $data and $requestType available and include template
//    The template will output the full HTML (and call window.print())
include $templateFile;
exit;
?>
