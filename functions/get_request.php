<?php
// functions/get_request.php
require_once __DIR__ . '/dbconn.php';
header('Content-Type: application/json; charset=utf-8');

$transaction_id = $_GET['transaction_id'] ?? '';
if (!$transaction_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'transaction_id required']);
    exit;
}

// 1) Fetch the generic view row first
$stmt = $conn->prepare("SELECT * FROM view_request WHERE transaction_id = ? LIMIT 1");
$stmt->bind_param('s', $transaction_id);
$stmt->execute();
$viewRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$viewRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Record not found']);
    exit;
}

// 2) Map common request_type values to their table names
// adjust these keys if your request_type values are different (e.g. "Barangay ID" vs "barangay_id_requests")
$map = [
    'barangay_id_requests' => 'barangay_id_requests',
    'business_permit_requests' => 'business_permit_requests',
    'good_moral_requests' => 'good_moral_requests',
    'guardianship_requests' => 'guardianship_requests',
    'indigency_requests' => 'indigency_requests',
    'residency_requests' => 'residency_requests',
    'solo_parent_requests' => 'solo_parent_requests'
];

// Try to normalize request_type from the view row
$rtype = strtolower(trim($viewRow['request_type'] ?? $viewRow['request'] ?? ''));

// create candidates
$candidates = [
    $rtype,
    str_replace(' ', '_', $rtype),
    $rtype . '_requests',
    str_replace(' ', '_', $rtype) . '_requests',
    strtolower($viewRow['source_table'] ?? ''),
    strtolower($viewRow['table_name'] ?? '')
];

$tableToQuery = null;
foreach ($candidates as $c) {
    if (!$c) continue;
    if (isset($map[$c])) { $tableToQuery = $map[$c]; break; }
    // quick check if the table actually exists in your DB
    // (this is optional; comment out if you don't want extra queries)
    if ($c && preg_match('/[a-z0-9_]+/', $c)) {
        // don't attempt existence check here to keep code simple
    }
}

// 3) If we have a candidate table, query it and merge results
$detailRow = [];
if ($tableToQuery) {
    $q = $conn->prepare("SELECT * FROM `{$tableToQuery}` WHERE transaction_id = ? LIMIT 1");
    if ($q) {
        $q->bind_param('s', $transaction_id);
        $q->execute();
        $d = $q->get_result()->fetch_assoc();
        if ($d) $detailRow = $d;
        $q->close();
    }
}

// 4) Merge the two rows (detailRow wins if same key exists)
$merged = array_replace((array)$viewRow, (array)$detailRow);

// 5) Tidy up file paths (optional): if you store only filenames in DB, create URL path
if (!empty($merged['formal_picture'])) {
    $fp = $merged['formal_picture'];
    // If it already looks like a full URL, leave it
    if (!preg_match('#^https?://#i', $fp)) {
        // adjust this prefix to wherever you store pictures on your server
        $merged['formal_picture'] = '/barangayIDpictures/' . ltrim($fp, '/');
    }
}

// 6) Return merged row
echo json_encode(['success' => true, 'data' => $merged]);
exit;
?>