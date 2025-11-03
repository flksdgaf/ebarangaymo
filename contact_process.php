<?php
session_start();
date_default_timezone_set('Asia/Manila');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitize and validate inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate that all fields are filled
    if (empty($name) || empty($email) || empty($message)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: about.php#contact-us");
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: about.php#contact-us");
        exit();
    }
    
    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        // $mail->SMTPDebug = 2; // Uncomment for debugging
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        
        // Use environment variables
        $mail->Host = $_ENV['EMAIL_HOST'];
        $mail->Username = $_ENV['EMAIL_USERNAME'];
        $mail->Password = $_ENV['EMAIL_PASSWORD'];
        $mail->SMTPSecure = $_ENV['EMAIL_ENCRYPTION'];
        $mail->Port = $_ENV['EMAIL_PORT'];
        
        // Recipients
        $mail->setFrom($_ENV['EMAIL_FROM'], $_ENV['EMAIL_FROM_NAME']);
        $mail->addAddress('paulinekate.villafuerte41@gmail.com'); // Your personal email
        $mail->addAddress('ebarangaymo@qpcamnorte.com'); // eBarangay Mo official email
        $mail->addReplyTo($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Contact Form Submission - eBarangay Mo';
        
        // Email template
        $email_template = '
            <h2>New Contact Us Form Submission</h2>
            <p>You have received a new message from the eBarangay Mo contact form:</p>
            <hr>
            <p><strong>From:</strong> ' . htmlspecialchars($name) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
            <p><strong>Message:</strong></p>
            <p>' . nl2br(htmlspecialchars($message)) . '</p>
            <hr>
            <p><em>Sent from eBarangay Mo Contact Form on ' . date('F j, Y, g:i a') . '</em></p>
        ';
        
        $mail->Body = $email_template;
        
        // Plain text version for non-HTML email clients
        $mail->AltBody = "Name: " . $name . "\n";
        $mail->AltBody .= "Email: " . $email . "\n";
        $mail->AltBody .= "Message: " . $message;
        
        // Send email
        $mail->send();
        $_SESSION['success'] = "Your message has been sent successfully! We'll get back to you soon.";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to send message. Please try again later.";
        error_log("Contact Form Email Error: " . $mail->ErrorInfo);
    }
    
    // Redirect back to about page
    header("Location: about.php#contact-us");
    exit();
    
} else {
    // If not POST request, redirect to about page
    header("Location: about.php");
    exit();
}
?>