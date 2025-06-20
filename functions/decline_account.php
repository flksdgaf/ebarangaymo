<?php
// functions/decline_account.php
require 'dbconn.php';
session_start();
if (!isset($_SESSION['auth']) || !$_SESSION['auth']) {
  header("HTTP/1.1 403 Forbidden");
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accountId = $_POST['account_ID'] ?? '';
  $reason = trim($_POST['reason'] ?? '');
  
  // Fetch pending record
  $stmt = $conn->prepare("SELECT * FROM pending_accounts WHERE account_ID = ?");
  $stmt->bind_param("s", $accountId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  // Build insert with appropriate columns. Example uses placeholders dynamically:
  $columns = [];
  $placeholders = [];
  $types = '';
  $values = [];

  $columns = ['account_ID', 'full_name', 'birthdate', 'purok', 'valid_ID', 'front_ID', 'back_ID', 'profile_picture', 'time_creation', 'reason'];
  $placeholders = array_fill(0, count($columns), '?');
  $types = 'isssssssss';
  $values = [
    $row['account_ID'],
    $row['full_name'],
    $row['birthdate'],
    $row['purok'],
    $row['valid_ID'],
    $row['front_ID'],
    $row['back_ID'],
    $row['profile_picture'],
    $row['time_creation'],
    $reason
  ];
  
  $sql = "INSERT INTO declined_accounts (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
  $stmt2 = $conn->prepare($sql);
  $stmt2->bind_param($types, ...$values);
  $stmt2->execute();
  $stmt2->close();

  // Update role in user_accounts
  $upd = $conn->prepare("UPDATE user_accounts SET role = ? WHERE account_ID = ?");
  if ($upd) {
    $newRole = 'Declined';
    $upd->bind_param("ss", $newRole, $accountId);
    $upd->execute();
    $upd->close();
  }

  // Delete from pending_accounts
  $stmt3 = $conn->prepare("DELETE FROM pending_accounts WHERE account_ID = ?");
  $stmt3->bind_param("s", $accountId);
  $stmt3->execute();
  $stmt3->close();

  // Redirect back, optionally to declined view
  header("Location: ../adminPanel.php?page=adminVerifications");
  exit;
}
// If not POST, reject
header("Location: /adminPanel.php?page=adminVerifications");
exit;
