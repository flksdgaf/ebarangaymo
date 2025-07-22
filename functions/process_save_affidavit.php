<?php
require 'dbconn.php';
session_start();

$txn   = $_POST['transaction_id'] ?? '';
$stage = $_POST['stage'] ?? '';
$action = $_POST['action_type'] ?? '';

if (!$txn || !$stage || $action !== 'clear') {
    exit('Missing or invalid data');
}

$pageNum = $_POST['katarungan_page'] ?? 1;

// Map stage (1st, 2nd, 3rd) to proper DB column names
$fieldMap = [
  '1st' => ['complainant_affidavit_unang_patawag', 'respondent_affidavit_unang_patawag'],
  '2nd' => ['complainant_affidavit_ikalawang_patawag', 'respondent_affidavit_ikalawang_patawag'],
  '3rd' => ['complainant_affidavit_ikatlong_patawag', 'respondent_affidavit_ikatlong_patawag'],
];

if (!isset($fieldMap[$stage])) {
    exit('Invalid stage value');
}

list($complainantField, $respondentField) = $fieldMap[$stage];

$affidavit1 = $_POST["complainant_affidavit_{$stage}"] ?? '';
$affidavit2 = $_POST["respondent_affidavit_{$stage}"] ?? '';

$sql = "UPDATE katarungang_pambarangay_records
        SET {$complainantField} = ?, {$respondentField} = ?
        WHERE transaction_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('sss', $affidavit1, $affidavit2, $txn);
$stmt->execute();
$stmt->close();

// Redirect after saving
header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&cleared_tid=" . urlencode($txn));
exit;
?>