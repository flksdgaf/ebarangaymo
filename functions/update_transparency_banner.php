<?php
require_once 'dbconn.php';
session_start();

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

const BANNER_ID = 1;
$id = BANNER_ID;

// Update background image
if (!empty($_FILES['background_image']) && is_uploaded_file($_FILES['background_image']['tmp_name'])) {
    $stmt = $conn->prepare("SELECT background_image FROM transparency_banner WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldImage = $result->fetch_assoc()['background_image'] ?? '';
    $stmt->close();

    $tmp = $_FILES['background_image']['tmp_name'];
    $orig = basename($_FILES['background_image']['name']);
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($ext, $allowed, true)) {
        http_response_code(400);
        exit('Invalid image type');
    }

    $dest = __DIR__ . '/../images/' . $orig;
    if (!move_uploaded_file($tmp, $dest)) {
        http_response_code(500);
        exit('Upload failed');
    }

    if ($oldImage && $oldImage !== $orig) {
        @unlink(__DIR__ . '/../images/' . $oldImage);
    }

    $stmt = $conn->prepare("UPDATE transparency_banner SET background_image = ? WHERE id = ?");
    $stmt->bind_param('si', $orig, $id);
    $stmt->execute();
    $stmt->close();
}

// Update title
if (!empty($_POST['title'])) {
    $title = trim($_POST['title']);
    $stmt = $conn->prepare("UPDATE transparency_banner SET title = ? WHERE id = ?");
    $stmt->bind_param('si', $title, $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: ../adminPanel.php?page=adminWebsite');
exit;
