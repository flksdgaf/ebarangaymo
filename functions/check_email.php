<?php
require_once 'dbconn.php';

header('Content-Type: application/json');

if (isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    $stmt = $conn->prepare("SELECT email FROM user_accounts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode(['exists' => $result->num_rows > 0]);
    
    $stmt->close();
    $conn->close();
}
?>