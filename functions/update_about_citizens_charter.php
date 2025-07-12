<?php
require_once 'dbconn.php';
session_start();

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

const RECORD_ID = 1;
$idVar = RECORD_ID;

// Update description
if (!empty($_POST['description'])) {
    $desc = trim($_POST['description']);
    $stmt = $conn->prepare("UPDATE about_citizens_charter SET description = ? WHERE id = ?");
    $stmt->bind_param("si", $desc, $idVar);
    $stmt->execute();
    $stmt->close();
}

// Update image
if (!empty($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $stmt = $conn->prepare("SELECT image FROM about_citizens_charter WHERE id = ?");
    $stmt->bind_param("i", $idVar);
    $stmt->execute();
    $result = $stmt->get_result();
    $old = $result->fetch_assoc()['image'] ?? '';
    $stmt->close();

    $tmp = $_FILES['image']['tmp_name'];
    $name = basename($_FILES['image']['name']);

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        http_response_code(400);
        exit('Invalid image type');
    }

    $dest = __DIR__ . '/../images/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        http_response_code(500);
        exit('Image upload failed');
    }

    if ($old && $old !== $name) {
        $oldPath = __DIR__ . '/../images/' . $old;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    $stmt = $conn->prepare("UPDATE about_citizens_charter SET image = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $idVar);
    $stmt->execute();
    $stmt->close();
}

header("Location: ../adminPanel.php?page=adminWebsite");
exit();
