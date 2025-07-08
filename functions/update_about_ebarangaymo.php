<?php
require_once 'dbconn.php';
session_start();

// Only allow admins
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$id = 1; // Fixed record ID (assuming single record design)

$table = 'about_ebarangaymo';
$fields = ['first_image', 'second_image', 'third_image'];
$uploadedImages = [];

foreach ($fields as $field) {
    if (!empty($_FILES[$field]['tmp_name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
        $tmp = $_FILES[$field]['tmp_name'];
        $originalName = basename($_FILES[$field]['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($ext, $allowed)) {
            http_response_code(400);
            exit("Invalid file type for $field");
        }

        $dest = __DIR__ . '/../images/' . $originalName;

        // Optional: Delete old image if it exists and is different
        $result = $conn->query("SELECT $field FROM $table WHERE id = $id");
        if ($result && $row = $result->fetch_assoc()) {
            $oldFile = $row[$field];
            if ($oldFile && $oldFile !== $originalName && file_exists(__DIR__ . '/../images/' . $oldFile)) {
                @unlink(__DIR__ . '/../images/' . $oldFile);
            }
        }

        if (!move_uploaded_file($tmp, $dest)) {
            http_response_code(500);
            exit("Failed to upload $field");
        }

        $uploadedImages[$field] = $originalName;
    }
}

// Update database
if (!empty($uploadedImages)) {
    $setParts = [];
    $params = [];
    $types = '';

    foreach ($uploadedImages as $col => $val) {
        $setParts[] = "$col = ?";
        $params[] = $val;
        $types .= 's';
    }

    $params[] = $id;
    $types .= 'i';

    $sql = "UPDATE $table SET " . implode(", ", $setParts) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
}

header('Location: ../adminPanel.php?page=adminWebsite');
exit;
