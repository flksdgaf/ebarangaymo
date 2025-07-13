<?php
require_once 'dbconn.php';
session_start();

// 1) Auth check
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

// 2) Delete service
if (!empty($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM services_list WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    header('Location: ../adminPanel.php?page=adminWebsite');
    exit;
}

// 3) Add new service
if (!empty($_POST['title']) && !empty($_POST['description']) && !empty($_POST['button_color']) && !empty($_FILES['icon_image']['tmp_name'])) {
    $title        = trim($_POST['title']);
    $description  = trim($_POST['description']);
    $buttonColor  = trim($_POST['button_color']);

    $tmp  = $_FILES['icon_image']['tmp_name'];
    $orig = basename($_FILES['icon_image']['name']);
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

    if (!in_array($ext, $allowed, true)) {
        http_response_code(400);
        exit('Invalid image type');
    }

    $filename = uniqid('icon_', true) . '.' . $ext;
    $dest = __DIR__ . '/../images/' . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        http_response_code(500);
        exit('Upload failed');
    }

    $icon = 'img:' . $filename;

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO services_list (icon, title, description, button_color) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $icon, $title, $description, $buttonColor);
    $stmt->execute();
    $stmt->close();

    header('Location: ../adminPanel.php?page=adminWebsite');
    exit;
}

// 4) Fallback
http_response_code(400);
exit('Bad request');
