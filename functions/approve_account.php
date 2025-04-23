<?php
// functions/approve_account.php

// 0) Manually include PHPMailer classes
// require __DIR__ . '/../libs/PHPMailer/src/Exception.php';
// require __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
// require __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

// now include your DB connection
require __DIR__ . '/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['account_id'])) {
    $account_id = $_POST['account_id'];

    // 1) FETCH from new_acc_requests (unchanged) …
    $stmt = $conn->prepare("
      SELECT account_ID, full_name, birthdate, sex, contact_number,
             email_address, full_address, purok, username, password
      FROM new_acc_requests
      WHERE account_ID = ?
    ");
    $stmt->bind_param("s", $account_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $r = $res->fetch_assoc();
        $stmt->close();

        // 2) INSERT into user_profiles (unchanged) …
        $defaultPic = 'default_profile_pic.png';
        $p = $conn->prepare("
          INSERT INTO user_profiles
            (account_id, full_name, birthdate, sex, contact, email,
             full_address, purok, profilePic)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $p->bind_param(
          "sssssssss",
          $r['account_ID'],
          $r['full_name'],
          $r['birthdate'],
          $r['sex'],
          $r['contact_number'],
          $r['email_address'],
          $r['full_address'],
          $r['purok'],
          $defaultPic
        );
        $p->execute();
        $p->close();

        // 3) INSERT into user_accounts (unchanged) …
        $role = 'Resident';
        $a = $conn->prepare("
          INSERT INTO user_accounts
            (account_id, username, password, role)
          VALUES (?, ?, ?, ?)
        ");
        $a->bind_param(
          "ssss",
          $r['account_ID'],
          $r['username'],
          $r['password'],
          $role
        );
        $a->execute();
        $a->close();

        // 4) SEND email via PHPMailer
        // $mail = new PHPMailer(true);

        // // SMTP settings — fill in with your provider’s details
        // $mail->isSMTP();
        // $mail->Host       = 'smtp.gmail.com';      // e.g. smtp.gmail.com
        // $mail->SMTPAuth   = true;
        // $mail->Username   = 'kentgabriel.britos@gmail.com';  // your SMTP user
        // $mail->Password   = 'oaptwdstezsxjtmp';       // your SMTP pass
        // $mail->SMTPSecure = 'tls';
        // $mail->Port       = 587;

        // // sender & recipient
        // $mail->setFrom('kentgabriel.britos@gmail.com', 'Admin Team');
        // $mail->addAddress($r['email_address']);

        // $mail->isHTML(true);
        // // content
        // $mail->Subject = 'Your account has been approved';
        // $mail->Body    = "
        //                 Hello {$r['full_name']},

        //                 Good news! Your account (ID: {$r['account_ID']}) has been approved. You can now log in here:
        //                 https://ebarangay.com/login

        //                 Your username is: {$r['username']}

        //                 Thank you for registering.

        //                 — The Admin Team
        //                 ";
        // $mail->send();

        // 5) DELETE the original request (unchanged) …
        // $d = $conn->prepare("
        //   DELETE FROM new_acc_requests
        //   WHERE account_ID = ?
        // ");
        // $d->bind_param("s", $account_id);
        // $d->execute();
        // $d->close();

        // 6) Redirect back to admin panel
        header("Location: ../adminpanel.php?page=adminVerifications");
        exit;
    }

    // if not found…
    header("Location: indexx.php");
    exit;
}

// bad request
header("Location: index.php");
exit;

