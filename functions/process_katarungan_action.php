<?php
session_start();
require 'dbconn.php';

$pageNum = $_POST['katarungan_page'] ?? 1;
$action = $_POST['action_type'] ?? '';


if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

if ($action === 'clear') {
    require 'process_clear_katarungan.php';
    exit();
} elseif ($action === 'proceed') {
    require 'process_proceed_katarungan.php';
    exit();
} else {
    // fallback or error
    header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&error=no_action");
    exit();
}
?>
