<?php 
session_start();
include('dbconn.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendemail_verify($name, $email, $verify_token)
{
    $mail = new PHPMailer(true);
    // $mail->SMTPDebug = 2;
    $mail->isSMTP();
    $mail->SMTPAuth = true;

    $mail->Host = 'smtp.gmail.com';
    $mail->Username = 'ichirakumen@gmail.com';
    $mail->Password = 'xsnx knli zbpp uday';

    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('ichirakumen@gmail.com',$name);
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'E-Barangay Mo: Email Verification';

    $email_template = '
        <h2>You have Registered with E-Barangay Mo</h2>
        <p>Verify your email address to Login with the given link below</p>
        <br><br>
        <a href="http://localhost:3000/functions/verify-email.php?token='.$verify_token.'">Click Here to Verify Email</a>
    ';

    $mail->Body = $email_template;
    $mail->send();
    // echo "Email has been sent";
}

if(isset($_POST['register_btn']))
{
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $verify_token = md5(rand());

    //Email Exists or Not
    $check_email_query = "SELECT email FROM users WHERE email='$email' LIMIT 1";
    $check_email_query_run = mysqli_query($conn, $check_email_query);

    if(mysqli_num_rows($check_email_query_run) > 0)
    {
        $_SESSION['status'] = "Email Already Exists";
        header('Location: /signup_try.php');
        exit;

    }
    else
    {
        // Insert User or Register User Data
        $query = "INSERT INTO users (name, phone, email, password, verify_token) VALUES ('$name', '$phone', '$email', '$password', '$verify_token')";
        $query_run = mysqli_query($conn, $query);

        if($query_run)
        {
            sendemail_verify("$name", "$email", "$verify_token");

            $_SESSION['status'] = "Registration Successful! Please Verify Your Email to Activate Your Account";
            header('Location: /signup_try.php');
            exit;

        }
        else
        {
            $_SESSION['status'] = "Registration Failed";
            header('Location: /signup_try.php');
            exit;

        }
    }
}

?>
