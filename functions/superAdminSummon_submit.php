<?php
session_start();
require 'dbconn.php';

// 1) Auth guard
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}
$userId = (int) $_SESSION['loggedInUserID'];

// 2) Collect posted fields
$blotterId   = (int) ($_POST['blotter_id']   ?? 0);
$summonDate  =        ($_POST['summon_date'] ?? '');
$summonTime  =        ($_POST['summon_time'] ?? '');
$subject     =        ($_POST['subject']     ?? '');

// 3) Validate blotter exists
$stmt = $conn->prepare("
    SELECT 1 
      FROM blotter_records 
     WHERE id = ?
    LIMIT 1
");
$stmt->bind_param('i', $blotterId);
$stmt->execute();
$res = $stmt->get_result();
if (! $res || $res->num_rows !== 1) {
    header("Location: ../superAdminPanel.php?page=superAdminSummon&error=invalidBlotter");
    exit();
}
$stmt->close();

// 4) Generate next SMN transaction_id
//    Format: SMN-0000001, etc.
$stmt = $conn->prepare("
    SELECT transaction_id
      FROM summon_records
     ORDER BY id DESC
     LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    // strip 'SMN-' prefix and parse the numeric part
    $num = intval(substr($lastTid, 4)) + 1;
} else {
    $num = 1;
}
$stmt->close();

$transactionId = sprintf('SMN-%07d', $num);

// 5) Insert into summon_records
$stmt = $conn->prepare("
    INSERT INTO summon_records
      (account_id,
       blotter_id,
       transaction_id,
       summon_date,
       summon_time,
       subject)
    VALUES (?,?,?,?,?,?)
");
$stmt->bind_param(
    'iissss',
    $userId,
    $blotterId,
    $transactionId,
    $summonDate,
    $summonTime,
    $subject
);
$stmt->execute();
$stmt->close();

// 6) Redirect back with the new SMN ID for your alert
header("Location: ../superAdminPanel.php?page=superAdminSummon&transaction_id={$transactionId}");
exit();
