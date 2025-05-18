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

// 7) Now insert the new KP case
$smnId      = $conn->insert_id;       // the PK of the summon we just made
$casePrefix = 'KPC-';                 
// generate next case_no: e.g. KPC-0000001
$last = $conn->query("
    SELECT case_no
      FROM katarungang_pambarangay
     ORDER BY id DESC
     LIMIT 1
")->fetch_assoc()['case_no'] ?? null;

if ($last && preg_match('/^' . preg_quote($casePrefix) . '(\d+)$/', $last, $m)) {
    $nextNum = intval($m[1]) + 1;
} else {
    $nextNum = 1;
}
$caseNo = sprintf('%s%07d', $casePrefix, $nextNum);

// choose an initial status—if you want “Punong Barangay” as default, change here:
$initialStatus = 'Punong Barangay';

$kpStmt = $conn->prepare("
    INSERT INTO katarungang_pambarangay
      (case_no, smn_id, blt_id, subject, status)
    VALUES (?,?,?,?,?)
");
$kpStmt->bind_param(
    'siiss',
    $caseNo,
    $smnId,
    $blotterId,
    $subject,
    $initialStatus
);
$kpStmt->execute();
$kpStmt->close();

// 6) Redirect back with the new SMN ID for your alert
if (!empty($_POST['superAdminRedirect'])) {
    // Came from the super‐admin panel → send back there
    header("Location: ../superAdminPanel.php?page=superAdminSummon&transaction_id={$transactionId}");
    exit();
}

if (!empty($_POST['adminRedirect'])) {
    // Came from the admin panel → send back there
    header("Location: ../adminPanel.php?page=adminSummon&transaction_id={$transactionId}");
    exit();
}

// Default: user panel
header("Location: ../userPanel.php?page=Summon&transaction_id={$transactionId}");
exit();