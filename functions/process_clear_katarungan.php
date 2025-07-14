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
  // 2. Get respondent name from complaint
  $stmt = $conn->prepare("SELECT respondent_name FROM complaint_records WHERE transaction_id = ?");
  $stmt->bind_param('s', $tid);
  $stmt->execute();
  $stmt->bind_result($respondent);
  $stmt->fetch();
  $stmt->close();

  if ($respondent) {
    // 3. Check if respondent still has other active complaints
    $check = $conn->prepare("
      SELECT COUNT(*) FROM complaint_records 
      WHERE respondent_name = ? 
        AND complaint_status IN ('Pending', 'Scheduled')
    ");
    $check->bind_param('s', $respondent);
    $check->execute();
    $check->bind_result($activeCount);
    $check->fetch();
    $check->close();

    // 4. If none, update remarks to 'none' in all purok tables
    if ($activeCount == 0) {
      for ($i = 1; $i <= 6; $i++) {
        $table = "purok{$i}_rbi";
        $updRemarks = $conn->prepare("UPDATE `$table` SET remarks = 'none' WHERE full_name = ?");
        $updRemarks->bind_param('s', $respondent);
        $updRemarks->execute();
        $updRemarks->close();
      }
    }
  }

  header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&cleared_tid={$tid}");
} else {
  header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&error=db_fail");
}
exit();
?>
