<?php
session_start();
require_once 'dbconn.php';
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['token']) || empty($_POST['password'])) {
    header('Location: ../signin.php');
    exit;
}

$token = $_POST['token'];
$password = $_POST['password'];
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate passwords match
if ($password !== $confirmPassword) {
    $_SESSION['login_error'] = 'Passwords do not match.';
    header('Location: ../reset_password.php?token=' . urlencode($token));
    exit;
}

// Validate password strength
if (strlen($password) < 8) {
    $_SESSION['login_error'] = 'Password must be at least 8 characters long.';
    header('Location: ../reset_password.php?token=' . urlencode($token));
    exit;
}

// Verify token is valid and not expired
$stmt = $conn->prepare("
    SELECT account_id, expires_at 
    FROM password_reset_tokens 
    WHERE token = ? 
    LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['login_error'] = 'Invalid reset token.';
    header('Location: ../signin.php');
    exit;
}

$tokenData = $result->fetch_assoc();
$expiresAt = strtotime($tokenData['expires_at']);

if ($expiresAt < time()) {
    $_SESSION['login_error'] = 'Reset token has expired.';
    header('Location: ../forgot_password.php');
    exit;
}

$account_id = $tokenData['account_id'];

// Hash the new password
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Update password in user_accounts
$stmt2 = $conn->prepare("UPDATE user_accounts SET password = ? WHERE account_id = ?");
$stmt2->bind_param("si", $passwordHash, $account_id);
$stmt2->execute();

// Delete used token
$stmt3 = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
$stmt3->bind_param("s", $token);
$stmt3->execute();

$stmt->close();
$stmt2->close();
$stmt3->close();
$conn->close();

// Success - redirect to login
$_SESSION['login_error'] = 'Password reset successful! You can now log in with your new password.';
header('Location: ../signin.php');
exit;
?>