<?php
// print_certificate.php
require_once __DIR__ . '/dbconn.php';

$transactionId = $_GET['transaction_id'] ?? '';
if (!$transactionId) {
    die('Transaction ID is required');
}

// 1) Determine request type from view_request
$stmt = $conn->prepare("SELECT request_type FROM view_request WHERE transaction_id = ?");
$stmt->bind_param('s', $transactionId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows !== 1) {
    die('Invalid transaction ID');
}
$requestType = $res->fetch_assoc()['request_type'];
$stmt->close();

// 2) Based on type, query the specific table for all fields
switch ($requestType) {
    case 'Barangay ID':
        $table = 'barangay_id_requests';
        break;
    case 'Business Permit':
        $table = 'business_permit_requests';
        break;
    case 'Good Moral':
        $table = 'good_moral_requests';
        break;
    case 'Guardianship':
        $table = 'guardianship_requests';
        break;
    case 'Indigency':
        $table = 'indigency_requests';
        break;
    case 'Residency':
        $table = 'residency_requests';
        break;
    case 'Solo Parent':
        $table = 'solo_parent_requests';
        break;
    default:
        die('Unknown request type');
}

// // 3) Fetch all columns
// $colStmt = $conn->prepare("SHOW COLUMNS FROM `$table`");
// $colStmt->execute();
// $colsRes = $colStmt->get_result();
// $columns = [];
// while ($col = $colsRes->fetch_assoc()) {
//     $columns[] = $col['Field'];
// }
// $colStmt->close();

// 3) Define columns manually per table/request type
$requestFields = [
  'barangay_id_requests' => [
    'transaction_id', 'request_type', 'transaction_type', 'full_name', 'purok', 'birth_date', 'birth_place', 
    'civil_status', 'religion', 'height', 'weight', 'emergency_contact_person', 'emergency_contact_number', 
    'formal_picture', 'payment_method', 'amount', 'created_at'
  ],
  'business_permit_requests' => [
    'transaction_id', 'request_type', 'transaction_type', 'full_name', 'purok', 'barangay', 'age', 
    'civil_status', 'name_of_business', 'type_of_business', 'full_address', 'payment_method', 'amount', 'created_at'
  ],
  'good_moral_requests' => [
    'transaction_id', 'request_type', 'full_name', 'civil_status', 'sex', 'age', 'purok', 'subdivision', 
    'purpose', 'payment_method', 'amount', 'created_at'
  ],
  'guardianship_requests' => [
    'transaction_id', 'request_type', 'full_name', 'civil_status', 'age', 'purok', 'child_name', 'purpose', 
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
    'transaction_id', 'request_type', 'full_name', 'civil_status', 'age', 'purok', 'years_solo_parent', 'child_name', 
    'child_age', 'child_sex', 'purpose', 'payment_method', 'amount', 'created_at'
  ],
];

// Check if we have predefined fields for this table
if (!isset($requestFields[$table])) {
  die('Unsupported request type for printing');
}

$columns = $requestFields[$table];

// Build dynamic select
$sql = 'SELECT ' . implode(',', array_map(function($c){ return "`$c`"; }, $columns))
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

// 4) Render HTML certificate
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Print <?= htmlspecialchars($requestType) ?> Certificate</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; }
    h1 { text-align: center; margin-bottom: 1.5rem; }
    .field { margin-bottom: 0.75rem; }
    .field label { font-weight: bold; display: inline-block; width: 200px; }
  </style>
</head>
<body>
  <h1><?= htmlspecialchars($requestType) ?> Certificate</h1>
  <?php foreach ($data as $field => $value): ?>
    <div class="field">
      <label><?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?>:</label>
      <span><?= htmlspecialchars($value) ?></span>
    </div>
  <?php endforeach; ?>
  <hr>
  <p style="text-align:center; font-size:0.9rem; color:#666;">
    Printed on <?= date('F j, Y \\a\t g:i A') ?>
  </p>
</body>
</html>
<?php 
exit;
?>
