<?php
require 'dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $transaction_id       = $_POST['transaction_id'] ?? '';
  $complainant_name     = $_POST['complainant_name'] ?? '';
  $complainant_address  = $_POST['complainant_address'] ?? '';
  $respondent_name      = $_POST['respondent_name'] ?? '';
  $respondent_address   = $_POST['respondent_address'] ?? '';
  $complaint_type       = $_POST['complaint_type'] ?? '';
  $complaint_affidavit  = $_POST['complaint_affidavit'] ?? '';
  $pleading_statement   = $_POST['pleading_statement'] ?? '';

  // Validate input
  if (!$transaction_id) {
    die('Missing transaction ID.');
  }

  $stmt = $conn->prepare("UPDATE complaint_records SET 
    complainant_name = ?, 
    complainant_address = ?, 
    respondent_name = ?, 
    respondent_address = ?, 
    complaint_type = ?, 
    complaint_affidavit = ?, 
    pleading_statement = ?
    WHERE transaction_id = ?");

  $stmt->bind_param("ssssssss", 
    $complainant_name, 
    $complainant_address, 
    $respondent_name, 
    $respondent_address, 
    $complaint_type, 
    $complaint_affidavit, 
    $pleading_statement,
    $transaction_id
  );

  if ($stmt->execute()) {
    $pageNum = $_POST['summon_page'] ?? 1;

  // header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&transaction_id=$transaction_id&updated=1");
  header("Location: ../adminPanel.php?page=adminComplaints&summon_page=$pageNum&updated_complaint_id=$transaction_id");
  exit;
  } else {
    die("Error updating complaint: " . $stmt->error);
  }
}
?>
