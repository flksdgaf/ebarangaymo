<?php
// update_payment.php (flexible prefix -> table dispatcher)
require_once 'dbconn.php';
header('Content-Type: application/json; charset=utf-8');

// input validation
if (empty($_GET['transaction_id'])) {
  http_response_code(400);
  echo json_encode(['error' => 'missing transaction_id']);
  exit;
}

$txnRaw = trim($_GET['transaction_id']);
$statusIn = isset($_GET['status']) ? trim($_GET['status']) : 'Paid';

// ---- Configure prefix -> table/columns here ----
// Keep this in sync with get_amount.php
$mapping = [
  'RES' => ['table'=>'residency_requests',          'status'=>'payment_status'],
  'BID' => ['table'=>'barangay_id_requests',        'status'=>'payment_status'],
  'BUS' => ['table'=>'business_permit_requests',    'status'=>'payment_status'],
  'CLR' => ['table'=>'barangay_clearance_requests', 'status'=>'payment_status'],
  'CGM' => ['table'=>'good_moral_requests',         'status'=>'payment_status'],
  'CSP' => ['table'=>'solo_parent_requests',        'status'=>'payment_status'],
  'GUA' => ['table'=>'guardianship_requests',       'status'=>'payment_status'],
  // Add new mappings here
];

// choose prefix (longest match first)
$txnUpper = strtoupper($txnRaw);
$matchedSpec = null;
$keys = array_keys($mapping);
usort($keys, function($a,$b){ return strlen($b) - strlen($a); });

foreach ($keys as $k) {
  if (stripos($txnUpper, $k) === 0) {
    $matchedSpec = $mapping[$k];
    break;
  }
}

if (!$matchedSpec) {
  http_response_code(404);
  echo json_encode(['error' => 'unknown transaction prefix', 'transaction_id' => $txnRaw]);
  $conn->close();
  exit;
}

// validate table/column identifiers (safety guard)
$tbl = $matchedSpec['table'];
$stCol = $matchedSpec['status'];
$identifierPattern = '/^[A-Za-z0-9_]+$/';
if (!preg_match($identifierPattern, $tbl) || !preg_match($identifierPattern, $stCol)) {
  http_response_code(500);
  echo json_encode(['error' => 'server misconfiguration (invalid table/column name)']);
  $conn->close();
  exit;
}

// Prepared UPDATE statement (status is bound safely)
$sql = "UPDATE {$tbl} SET {$stCol} = ? WHERE transaction_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
  http_response_code(500);
  echo json_encode(['error' => 'db prepare failed', 'detail' => $conn->error]);
  $conn->close();
  exit;
}

$stmt->bind_param('ss', $statusIn, $txnRaw);
$ok = $stmt->execute();

if ($ok) {
  $affected = $stmt->affected_rows;
  if ($affected > 0) {
    echo json_encode(['ok' => true, 'updated_rows' => $affected, 'transaction_id' => $txnRaw, 'status' => $statusIn]);
  } else {
    // row not found or already had same value
    echo json_encode(['ok' => false, 'updated_rows' => 0, 'message' => 'No matching row updated (maybe not found or status unchanged)']);
  }
} else {
  http_response_code(500);
  echo json_encode(['error' => 'db execute failed', 'detail' => $stmt->error]);
}

$stmt->close();
$conn->close();
