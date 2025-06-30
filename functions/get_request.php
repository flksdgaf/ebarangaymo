<?php
// api/get_request.php
header('Content-Type: application/json; charset=utf-8');
require 'dbconn.php';

// 1) Check incoming params
$type = $_GET['type']  ?? '';
$tid  = $_GET['tid']   ?? '';

// If missing, bail immediately
if (!$type || !$tid) {
    http_response_code(400);
    echo json_encode(['error'=>'Missing type or tid', 'got'=>['type'=>$type,'tid'=>$tid]]);
    exit;
}

// 2) Map human label → actual table name
$map = [
    'Barangay ID'     => 'barangay_id_requests',
    'Business Permit' => 'business_permit_requests',
    'Good Moral'      => 'good_moral_requests',
    'Guardianship'    => 'guardianship_requests',
    'Indigency'       => 'indigency_requests',
    'Residency'       => 'residency_requests',
    'Solo Parent'     => 'solo_parent_requests',
];

if (! isset($map[$type]) ) {
    http_response_code(400);
    echo json_encode(['error'=>"Unknown request type", 'type'=>$type]);
    exit;
}
$table = $map[$type];

// 3) Prepare & run the query
$sql = "SELECT * FROM `{$table}` WHERE `transaction_id` = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $tid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 4) If no row, return debugging info
if (! $row) {
    echo json_encode([
      'error' => 'No record found',
      'table' => $table,
      'transaction_id' => $tid
    ]);
    exit;
}

// 5) Break apart full_name → last/first/middle/suffix
if (!empty($row['full_name'])) {
    $fn = trim($row['full_name']);
    $last = $first = $middle = $suffix = '';
    if (strpos($fn, ',') !== false) {
        // "Last, First Middle Suffix"
        list($lastPart, $rest) = array_map('trim', explode(',', $fn, 2));
        $last = $lastPart;
        $parts = preg_split('/\s+/', $rest);
    } else {
        // no comma: split on spaces
        $parts = preg_split('/\s+/', $fn);
        if (count($parts) > 1) {
            $first = array_shift($parts);
            $last  = array_pop($parts);
        } else {
            $first = $parts[0];
            $parts = [];
        }
    }
    if (!$first && count($parts)) {
        $first = array_shift($parts);
    }
    if (count($parts) > 1) {
        $suffix = array_pop($parts);
    }
    $middle = implode(' ', $parts);

    $row['last_name'] = $last;
    $row['first_name'] = $first;
    $row['middle_name'] = $middle;
    $row['suffix'] = $suffix;
}

// 6) Send back the full payload
echo json_encode($row);
exit;
?>
