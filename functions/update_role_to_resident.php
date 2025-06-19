<?php
// functions/update_role.php
require 'dbconn.php';
session_start();

// Ensure user is authenticated
if (!isset($_SESSION['auth']) || !$_SESSION['auth']) {
    // Not logged in: redirect to login or home
    header("Location: ../index.php");
    exit;
}

// Get account ID from session
$accountId = $_SESSION['loggedInUserID'] ?? null;
if (!$accountId) {
    // No account ID: redirect or error
    header("Location: ../index.php");
    exit;
}

// Optional: check current role is “Approved” to avoid unnecessary updates
// e.g., if you store role in session:
$currentRole = $_SESSION['loggedInUserRole'] ?? '';
if ($currentRole !== 'Approved') {
    // Maybe skip update or handle differently
    // For now, we still proceed to redirect
    header("Location: ../userPanel.php");
    exit;
}

// Update role in user_accounts table
$stmt = $conn->prepare("UPDATE user_accounts SET role = ? WHERE account_ID = ?");
if ($stmt) {
    $newRole = 'Resident';  // or capitalized "Resident" depending on how you store it
    $stmt->bind_param("ss", $newRole, $accountId);
    $stmt->execute();
    $stmt->close();

    // Update session role
    $_SESSION['loggedInUserRole'] = $newRole;
}

// Redirect to user panel
header("Location: ../userPanel.php");
exit;
