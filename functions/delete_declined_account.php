<?php
// functions/delete_declined_account.php
require 'dbconn.php';
session_start();
if (!isset($_SESSION['auth']) || !$_SESSION['auth']) {
    // Not logged in: deny or redirect
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountId = $_POST['account_ID'] ?? '';
    // Delete the record
    $stmt = $conn->prepare("DELETE FROM declined_accounts WHERE account_ID = ?");
    $stmt->bind_param("s", $accountId);
    $stmt->execute();
    $stmt->close();

    $upd = $conn->prepare("DELETE FROM user_accounts WHERE account_ID = ?");
    $upd->bind_param("s", $accountId);
    $upd->execute();
    $upd->close();

    // Redirect back to the declined view
    header("Location: ../adminPanel.php?page=adminVerifications&deleted_account_id={$accountId}");
    exit;
}

// If not POST, simply redirect back
header("Location: ../adminPanel.php?page=adminVerifications&deleted_account_id={$accountId}");
exit;
