<?php
// get_amount.php (flexible prefix -> table dispatcher)
require_once 'dbconn.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_GET['transaction_id'])) {
  http_response_code(400);
  echo json_encode(['error' => 'missing transaction_id']);
  exit;
}

$txnRaw = trim($_GET['transaction_id']);

// ---- Configure prefix -> table/columns here ----
// Keys are prefixes (case-insensitive). The script will pick the longest matching prefix.
$mapping = [
  // prefix => [ 'table' => 'table_name', 'amount' => 'amount_col', 'status' => 'payment_status_col' ]
  'RES'     => ['table'=>'residency_requests',          'amount'=>'amount', 'status'=>'payment_status'],
  'BID'     => ['table'=>'barangay_id_requests',        'amount'=>'amount', 'status'=>'payment_status'],
  'BUS'     => ['table'=>'business_permit_requests',    'amount'=>'amount', 'status'=>'payment_status'],
  'CLR'     => ['table'=>'barangay_clearance_requests', 'amount'=>'amount', 'status'=>'payment_status'],
  'CGM'     => ['table'=>'good_moral_requests',         'amount'=>'amount', 'status'=>'payment_status'],
  'CSP'     => ['table'=>'solo_parent_requests',        'amount'=>'amount', 'status'=>'payment_status'],
  'GUA'     => ['table'=>'guardianship_requests',       'amount'=>'amount', 'status'=>'payment_status'],
  // Add more mappings as needed...
];

// Normalize input for prefix matching (case-insensitive)
$txnUpper = strtoupper($txnRaw);

// pick the longest matching prefix
$matchedKey = null;
$matchedSpec = null;
$keys = array_keys($mapping);
// sort by length desc to prefer longest match (avoids collisions)
usort($keys, function($a,$b){ return strlen($b) - strlen($a); });

foreach ($keys as $k) {
  if (stripos($txnUpper, $k) === 0) { // starts with prefix
    $matchedKey = $k;
    $matchedSpec = $mapping[$k];
    break;
  }
}

if (!$matchedSpec) {
  // no prefix matched
  http_response_code(404);
  echo json_encode(['error' => 'unknown transaction prefix', 'transaction_id' => $txnRaw]);
  $conn->close();
  exit;
}

// validate table/column names are safe identifiers (simple whitelist check)
// Note: Since mapping is defined in code, this is just a guard in case someone changes it dynamically.
$tbl = $matchedSpec['table'];
$amtCol = $matchedSpec['amount'];
$stCol = $matchedSpec['status'];

$identifierPattern = '/^[A-Za-z0-9_]+$/';
if (!preg_match($identifierPattern, $tbl) ||
    !preg_match($identifierPattern, $amtCol) ||
    !preg_match($identifierPattern, $stCol)) {
  http_response_code(500);
  echo json_encode(['error' => 'server misconfiguration (invalid table/column name)']);
  $conn->close();
  exit;
}

// Prepared statement: transaction_id is bound safely
$sql = "SELECT {$amtCol} AS amt, {$stCol} AS status
        FROM {$tbl}
        WHERE transaction_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
  http_response_code(500);
  echo json_encode(['error' => 'db prepare failed', 'detail' => $conn->error]);
  $conn->close();
  exit;
}

$stmt->bind_param('s', $txnRaw);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
  // Normalize output: float amount and string payment_status
  $amountOut = isset($row['amt']) ? floatval($row['amt']) : 0.0;
  $statusOut = isset($row['status']) ? $row['status'] : '';

  echo json_encode([
    'amount' => $amountOut,
    'payment_status' => $statusOut
  ]);
} else {
  http_response_code(404);
  echo json_encode(['error' => 'not found', 'transaction_id' => $txnRaw]);
}

$stmt->close();
$conn->close();
