<?php
require_once 'dbconn.php';
session_start();

// 1) Auth check — only allow admins
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

const BANNER_ID = 1;
$idVar = BANNER_ID;

// 2) Update background image if uploaded
if (!empty($_FILES['background_image']) && is_uploaded_file($_FILES['background_image']['tmp_name'])) {
    $stmt = $conn->prepare("SELECT background_image FROM about_banner WHERE id = ?");
    $stmt->bind_param('i', $idVar);
    $stmt->execute();
    $result = $stmt->get_result();
    $old = $result->fetch_assoc()['background_image'] ?? '';
    $stmt->close();

    $tmp = $_FILES['background_image']['tmp_name'];
    $name = basename($_FILES['background_image']['name']);

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        http_response_code(400);
        exit('Invalid image type');
    }

    $dest = __DIR__ . '/../images/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        http_response_code(500);
        exit('Upload failed');
    }

    if ($old && $old !== $name) {
        $oldPath = __DIR__ . '/../images/' . $old;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    $stmt = $conn->prepare("UPDATE about_banner SET background_image = ? WHERE id = ?");
    $stmt->bind_param('si', $name, $idVar);
    $stmt->execute();
    $stmt->close();
}

// 3) Update title if provided
if (!empty($_POST['title'])) {
    $title = trim($_POST['title']);
    $stmt = $conn->prepare("UPDATE about_banner SET title = ? WHERE id = ?");
    $stmt->bind_param('si', $title, $idVar);
    $stmt->execute();
    $stmt->close();
}

// 4) Redirect back
header('Location: ../adminPanel.php?page=adminWebsite');
exit;
