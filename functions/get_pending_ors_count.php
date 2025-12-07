<?php
require 'dbconn.php';

header('Content-Type: application/json');

try {
    // Get pending ORs count
    $query = "
        SELECT COUNT(*) as count
        FROM view_dashboard v
        WHERE (v.payment_status = 'Pending' OR v.payment_status = 'Paid')
        AND (v.document_status = 'Processing' OR v.document_status = 'Pending')
        AND v.request_type NOT IN ('Indigency', 'First Time Job Seeker')
        AND v.transaction_id NOT IN (SELECT transaction_id FROM official_receipt_records)
    ";
    
    $result = $conn->query($query);
    
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo json_encode([
            'success' => true,
            'count' => (int)$count
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Query failed'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>