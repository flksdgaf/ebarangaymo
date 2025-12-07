<?php
require 'dbconn.php';

header('Content-Type: application/json');

try {
    // Get filters from query string
    $request_type = $_GET['request_type'] ?? '';
    $payment_status = $_GET['payment_status'] ?? '';
    $document_status = $_GET['document_status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    // Build WHERE clauses (same logic as adminDashboard.php)
    $whereClauses = [];
    $bindTypes = '';
    $bindParams = [];
    
    // GLOBAL FULL-TEXT SEARCH
    if ($search !== '') {
        $whereClauses[] = "
            (transaction_id LIKE ? OR full_name LIKE ? OR request_type LIKE ? OR payment_method LIKE ? OR payment_status LIKE ? 
            OR document_status LIKE ?)
        ";
        $bindTypes .= 'ssssss';
        $term = "%{$search}%";
        $bindParams = array_merge($bindParams, array_fill(0, 6, $term));
    }
    
    // INDIVIDUAL FILTERS
    if ($request_type) {
        $whereClauses[] = 'request_type = ?';
        $bindTypes .= 's';
        $bindParams[] = $request_type;
    }
    if ($payment_status) {
        $whereClauses[] = 'payment_status = ?';
        $bindTypes .= 's';
        $bindParams[] = $payment_status;
    }
    if ($document_status) {
        $whereClauses[] = 'document_status = ?';
        $bindTypes .= 's';
        $bindParams[] = $document_status;
    }
    
    // Only show rows from the current week 
    $whereClauses[] = "YEAR(created_at) = YEAR(CURDATE()) AND WEEK(created_at,1) = WEEK(CURDATE(),1)";
    
    // BUILD WHERE CLAUSE
    $whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    
    // COUNT TOTAL WITH FILTERS
    $countSql = "SELECT COUNT(*) as count FROM view_dashboard {$whereSQL}";
    $stmt = $conn->prepare($countSql);
    
    if (!empty($bindTypes)) {
        $stmt->bind_param($bindTypes, ...$bindParams);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'count' => (int)$count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>