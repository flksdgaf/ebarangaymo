<?php
require 'dbconn.php';

$name = $_POST['full_name'] ?? '';
$response = ['has_pending' => false];

if ($name !== '') {
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM blotter_records WHERE respondent_name = ? AND blotter_status = 'Pending'");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $response['has_pending'] = $row['count'] > 0;
    $stmt->close();
}

echo json_encode($response);
exit;
?>
