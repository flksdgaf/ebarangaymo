<?php
session_start();
require 'dbconn.php';

// AUTH CHECK
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Fetch all Lupon Tagapamayapa members from all purok tables
$luponMembers = [];

$puroks = ['purok1_rbi', 'purok2_rbi', 'purok3_rbi', 'purok4_rbi', 'purok5_rbi', 'purok6_rbi'];

foreach ($puroks as $purok) {
    $sql = "
        SELECT p.full_name, p.account_ID 
        FROM {$purok} p
        INNER JOIN user_accounts ua ON p.account_ID = ua.account_id
        WHERE ua.role = 'Lupon Tagapamayapa'
        AND p.account_ID != 0
        ORDER BY p.full_name ASC
    ";
    
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $luponMembers[] = [
                'account_id' => $row['account_ID'],
                'full_name' => $row['full_name']
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($luponMembers);
?>