<?php
// functions/update_barangay_info.php
require_once 'dbconn.php';
session_start();

// 1) Auth check — only allow admins
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: index.php");
    exit();
}

// We'll always update the single row with id=1
const INFO_ID = 1;

// 2) If a file was uploaded under the "logo" field, handle that first
if (!empty($_FILES['logo']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
    $stmt = $conn->prepare("SELECT logo FROM barangay_info WHERE id = ?");
    $idVar = INFO_ID;
    $stmt->bind_param('i', $idVar);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc()['logo'] ?? '';
    $stmt->close();

    $tmp  = $_FILES['logo']['tmp_name'];
    $name = basename($_FILES['logo']['name']);
    // Validate extension
    $allowed = ['png','jpg','jpeg','gif'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        http_response_code(400);
        exit('Invalid image type');
    }
    // Move to /images and store only the filename in DB
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

    $stmt = $conn->prepare("
    UPDATE barangay_info
        SET logo = ?
    WHERE id = ?
    ");

    $idVar = INFO_ID;
    $stmt->bind_param('si', $name, $idVar);
    $stmt->execute();
    $stmt->close();

    header('Location: ../adminPanel.php?page=adminWebsite');
    exit;
}

// 3) Otherwise, check for a simple field update: name or address
if (
    !empty($_POST['field']) &&
    in_array($_POST['field'], ['name','address'], true) &&
    isset($_POST['value'])
) {
    $field = $_POST['field'];
    $value = trim($_POST['value']);

    $stmt = $conn->prepare("
    UPDATE barangay_info
        SET `{$field}` = ?
    WHERE id = ?
    ");

    $idVar = INFO_ID;
    $stmt->bind_param('si', $value, $idVar);
    $stmt->execute();
    $stmt->close();

    // Redirect back
    header('Location: ../adminPanel.php?page=adminWebsite');
    exit;
}

// 4) Nothing valid to do
http_response_code(400);
exit('Invalid request');
