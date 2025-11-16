<?php
session_start();
require 'dbconn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$search = trim($_GET['search'] ?? '');

if (strlen($search) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

// Search for existing Barangay ID records
$sql = "SELECT transaction_id, full_name, purok, birth_date, birth_place, civil_status, religion, 
        height, weight, emergency_contact_person, emergency_contact_address, valid_id_number, formal_picture
        FROM barangay_id_requests 
        WHERE full_name LIKE ? AND document_status = 'Released'
        ORDER BY full_name ASC LIMIT 10";

$stmt = $conn->prepare($sql);
$searchTerm = "%{$search}%";
$stmt->bind_param('s', $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$results = [];
while ($row = $result->fetch_assoc()) {
    $results[] = $row;
}

echo json_encode(['success' => true, 'results' => $results]);

$stmt->close();
$conn->close();
?>