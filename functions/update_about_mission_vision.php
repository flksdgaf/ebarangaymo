<?php
require_once 'dbconn.php';
session_start();

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

const RECORD_ID = 1;
$idVar = RECORD_ID;

// Update Mission
if (!empty($_POST['mission'])) {
    $mission = trim($_POST['mission']);
    $stmt = $conn->prepare("UPDATE about_mission_vision SET mission = ? WHERE id = ?");
    $stmt->bind_param("si", $mission, $idVar);
    $stmt->execute();
    $stmt->close();
}

// Update Vision
if (!empty($_POST['vision'])) {
    $vision = trim($_POST['vision']);
    $stmt = $conn->prepare("UPDATE about_mission_vision SET vision = ? WHERE id = ?");
    $stmt->bind_param("si", $vision, $idVar);
    $stmt->execute();
    $stmt->close();
}

header("Location: ../adminPanel.php?page=adminWebsite");
exit();
