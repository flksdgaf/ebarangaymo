<?php
session_start();
require_once 'dbconn.php';
date_default_timezone_set('Asia/Manila');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['email'])) {
    $_SESSION['reset_message'] = 'Please provide an email address.';
    $_SESSION['reset_message_type'] = 'error';
    header('Location: ../forgot_password.php');
    exit;
}

$email = trim($_POST['email']);

// Check if email exists in user_accounts
$stmt = $conn->prepare("SELECT account_id, username FROM user_accounts WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Don't reveal if email exists or not (security practice)
    $_SESSION['reset_message'] = 'If this email is registered, you will receive a password reset link shortly.';
    $_SESSION['reset_message_type'] = 'success';
    header('Location: ../forgot_password.php');
    exit;
}

$user = $result->fetch_assoc();
$account_id = $user['account_id'];
$username = $user['username'];

// Generate a secure reset token
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

// Store token in database
$stmt2 = $conn->prepare("
    INSERT INTO password_reset_tokens (account_id, token, expires_at, created_at) 
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()
");
$stmt2->bind_param("issss", $account_id, $token, $expires, $token, $expires);
$stmt2->execute();

// Create reset link
$reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;

// Send email using PHPMailer
$mail = new PHPMailer(true);

try {
    // $mail->SMTPDebug = 2; // Uncomment for debugging
    $mail->isSMTP();
    $mail->SMTPAuth = true;

    // Use environment variables
    $mail->Host = $_ENV['EMAIL_HOST'];
    $mail->Username = $_ENV['EMAIL_USERNAME'];
    $mail->Password = $_ENV['EMAIL_PASSWORD'];

    $mail->SMTPSecure = $_ENV['EMAIL_ENCRYPTION'];
    $mail->Port = $_ENV['EMAIL_PORT'];

    $mail->setFrom($_ENV['EMAIL_FROM'], $_ENV['EMAIL_FROM_NAME']);
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'eBarangay Mo: Password Reset Request';

    $email_template = '
        <h2>Password Reset Request</h2>
        <p>Hello <strong>' . htmlspecialchars($username) . '</strong>,</p>
        <p>You requested to reset your password. Click the button below to reset it:</p>
        <br>
        <a href="' . $reset_link . '" style="background-color: #2A9245; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">Reset Password</a>
        <br><br>
        <p><strong>This link will expire in 1 hour.</strong></p>
        <p>If you did not request this, please ignore this email.</p>
        <br>
        <p>Best regards,<br>E-Barangay Mo Team</p>
    ';

    // <p>Or copy and paste this link into your browser:</p>
    // <p>' . $reset_link . '</p>
    // <br>

    $mail->Body = $email_template;
    $mail->send();

    $_SESSION['reset_message'] = 'A password reset link has been sent to your email address.';
    $_SESSION['reset_message_type'] = 'success';
} catch (Exception $e) {
    $_SESSION['reset_message'] = 'Failed to send reset email. Please try again later.';
    $_SESSION['reset_message_type'] = 'error';
    error_log("Email Error: " . $mail->ErrorInfo);
}

$stmt->close();
$stmt2->close();
$conn->close();

header('Location: ../forgot_password.php');
exit;
?>