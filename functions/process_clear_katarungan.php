<?php
session_start();
require 'dbconn.php';

$pageNum = $_POST['katarungan_page'] ?? 1;

// AUTH
if (!($_SESSION['auth'] ?? false)) {
  header("Location: ../index.php");
  exit();
}

$tid = $_POST['transaction_id'] ?? '';
if (!$tid) {
  header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&error=missing_tid");
  exit();
}

// UPDATE complaint status
$upd = $conn->prepare("
  UPDATE complaint_records
     SET complaint_status = 'Cleared'
   WHERE transaction_id = ?
");
$upd->bind_param('s', $tid);
$ok = $upd->execute();
$upd->close();

if ($ok) {
  header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&cleared_tid={$tid}");
} else {
  header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&error=db_fail");
}
exit();
?>
