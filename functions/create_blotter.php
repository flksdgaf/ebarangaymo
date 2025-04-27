<?php
session_start();
require 'dbconn.php';

// 0) ensure admin access (optional)
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION['loggedInUserID'];

// 1) collect POST
$account_id            = $userId;    
$complainants          = $_POST['complainants'];
$respondents           = $_POST['respondents'];
$date_occurred         = $_POST['date_occurred'];
$complaint_nature      = $_POST['complaint_nature'];
$complaint_description = $_POST['complaint_description'];
$payment_method        = $_POST['payment_method']   ?: null;
$OR_number             = $_POST['OR_number']        ?: null;
$OR_issued_date        = $_POST['OR_issued_date']   ?: null;

// 2) generate transaction_id
$stmt = $conn->prepare("
  SELECT transaction_id FROM blotter_records
  ORDER BY id DESC LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
  $last = $res->fetch_assoc()['transaction_id'];
  $seq  = intval(substr($last, strpos($last, '-')+1)) + 1;
} else {
  $seq = 1;
}
$transaction_id = sprintf('BLTR-%07d', $seq);
$stmt->close();

// 3) insert
$ins = $conn->prepare("
  INSERT INTO blotter_records
    (account_id, transaction_id,
     complainants, respondents,
     date_filed, date_occurred,
     complaint_nature, complaint_description,
     payment_method, OR_number, OR_issued_date)
  VALUES (?,?,?,?, NOW(),?,?,?,?,?,?)
");
+$ins->bind_param(
    "isssssssss",     
    $account_id,
    $transaction_id,
    $complainants,
    $respondents,
    $date_occurred,
    $complaint_nature,
    $complaint_description,
    $payment_method,
    $OR_number,
    $OR_issued_date
);
$ins->execute();
$ins->close();

// 4) redirect back
if (isset($_POST['adminRedirect'])) {
  header("Location: ../adminPanel.php?page=adminBlotter");
} else {
  header("Location: ../userPanel.php?page=adminBlotter&tid={$transaction_id}");
}
exit;
