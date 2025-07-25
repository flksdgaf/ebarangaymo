<?php
require 'dbconn.php';
session_start();

$txn = $_POST['transaction_id'] ?? '';
$stage = $_POST['stage'] ?? '';
$action = $_POST['action_type'] ?? '';
$pageNum = $_POST['katarungan_page'] ?? 1;

// if (!$txn || !$stage || $action !== 'clear') {
//   exit('Missing or invalid data');
// }

// -- sanity check --
if (!$txn) {
    exit('Missing transaction ID');
}

// 1) If the user clicked “Cleared”:
if ($action === 'clear') {
  // a) Mark the master complaint status as Cleared
  $u = $conn->prepare("UPDATE complaint_records SET complaint_status = 'Cleared' WHERE transaction_id = ?");
  $u->bind_param('s', $txn);
  $u->execute();
  $u->close();

  // b) Get the respondent name from the complaint_records
  $get = $conn->prepare("SELECT respondent_name FROM complaint_records WHERE transaction_id = ?");
  $get->bind_param('s', $txn);
  $get->execute();
  $res = $get->get_result();
  $respondentName = '';
  if ($res && $res->num_rows === 1) {
      $respondentName = $res->fetch_assoc()['respondent_name'];
  }
  $get->close();

  // c) Reset remarks to NULL in all purok tables
  if (!empty($respondentName)) {
      $purokTables = [
        'purok1_rbi','purok2_rbi','purok3_rbi',
        'purok4_rbi','purok5_rbi','purok6_rbi'
      ];
      foreach ($purokTables as $tbl) {
        $upd = $conn->prepare("UPDATE `{$tbl}` SET remarks = NULL WHERE full_name = ?");
        $upd->bind_param('s', $respondentName);
        $upd->execute();
        $upd->close();
      }
  }

  // b) Redirect back with cleared_tid so you can show the alert
  $qs = http_build_query([
    'page' => 'adminComplaints',
    'katarungan_page' => $pageNum,
    'cleared_tid' => $txn,
  ]);
  header("Location: ../adminPanel.php?{$qs}");
  exit;
}


elseif ($action === 'municipal') {
    $stmt = $conn->prepare("UPDATE katarungang_pambarangay_records SET complaint_stage = 'Municipal Court' WHERE transaction_id = ?");
    $stmt->bind_param('s', $txn);
    $stmt->execute();
    $stmt->close();

    header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&cleared_tid=" . urlencode($txn));
    exit;
}

// ─── Action: SAVE AFFIDAVIT ───────────────────────────────
elseif ($action === 'save') {
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

  $sql = "UPDATE katarungang_pambarangay_records SET {$complainantField} = ?, {$respondentField} = ? WHERE transaction_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('sss', $affidavit1, $affidavit2, $txn);
  $stmt->execute();
  $stmt->close();

  // Redirect after saving
  header("Location: ../adminPanel.php?page=adminComplaints&katarungan_page=$pageNum&cleared_tid=" . urlencode($txn));
  exit;
}

else {
    exit('Unknown action');
}
?>