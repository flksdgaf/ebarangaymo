<?php
require_once 'dbconn.php';

header('Content-Type: application/json');

if (isset($_POST['username'])) {
    $username = trim($_POST['username']);
    
    $stmt = $conn->prepare("SELECT username FROM user_accounts WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode(['exists' => $result->num_rows > 0]);
    
    $stmt->close();
    $conn->close();
}
?>