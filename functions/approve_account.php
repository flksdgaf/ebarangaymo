<?php
// functions/approve_account.php

require_once 'dbconn.php';  // $conn = new mysqli(...);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['account_ID'])) {
    http_response_code(400);
    die("Invalid request");
}

$accountId = (int)$_POST['account_ID'];

// 1) Pull the pending record
$stmt = $conn->prepare("
    SELECT full_name, birthdate, sex, civil_status, blood_type, birth_registration_number,
           highest_educational_attainment, occupation, purok, profile_picture
    FROM pending_accounts
    WHERE account_ID = ?
");
$stmt->bind_param("i", $accountId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    die("No such pending account");
}

$row = $result->fetch_assoc();
$stmt->close();

// 2) Pick the destination table based on purok
//    sanitize to prevent injection—our enum is Purok 1..6
$validPuroks = [
  'Purok 1'=>'purok1_rbi',
  'Purok 2'=>'purok2_rbi',
  'Purok 3'=>'purok3_rbi',
  'Purok 4'=>'purok4_rbi',
  'Purok 5'=>'purok5_rbi',
  'Purok 6'=>'purok6_rbi'
];

if (!isset($validPuroks[$row['purok']])) {
    die("Invalid purok: " . htmlspecialchars($row['purok']));
}

$destTable = $validPuroks[$row['purok']];

// 3) Insert into the purokX_rbi table
//    (we skip valid_ID, front_ID, back_ID, time_creation)
$insertSql = "
  INSERT INTO `$destTable`
    (account_ID, full_name, birthdate, sex,
     civil_status, blood_type, birth_registration_number,
     highest_educational_attainment, occupation, profile_picture)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmtIns = $conn->prepare($insertSql);
$stmtIns->bind_param(
  "isssssssss",
  $accountId,
  $row['full_name'],
  $row['birthdate'],
  $row['sex'],
  $row['civil_status'],
  $row['blood_type'],
  $row['birth_registration_number'],
  $row['highest_educational_attainment'],
  $row['occupation'],
  $row['profile_picture']
);

if (!$stmtIns->execute()) {
    die("Insert into $destTable failed: " . $stmtIns->error);
}
$stmtIns->close();

// 4) Update user_accounts role to 'Resident'
$upd = $conn->prepare("
    UPDATE user_accounts
      SET role = 'Resident'
    WHERE account_id = ?
");
$upd->bind_param("i", $accountId);

if (!$upd->execute()) {
    die("Failed to update user_accounts: " . $upd->error);
}
$upd->close();

// 5) Delete from pending_accounts
// $del = $conn->prepare("
//     DELETE FROM pending_accounts
//     WHERE account_id = ?
// ");
// $del->bind_param("i", $accountId);

// if (!$del->execute()) {
//     die("Failed to delete pending_accounts: " . $del->error);
// }
// $del->close();

// 6) Finally, redirect back to the listing
header("Location: ../adminpanel.php?page=adminVerifications");
exit;


