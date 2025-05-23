<?php
require 'dbconn.php';

if ($_SERVER['REQUEST_METHOD']!=='POST'
    || empty($_POST['account_ID'])
    || empty($_POST['name'])
    || empty($_POST['purok'])) {
    http_response_code(400);
    die("Invalid request");
}

$accountId = (int) $_POST['account_ID'];
$name      = trim($_POST['name']);
$newPurok  = trim($_POST['purok']);

$map = [
  'Purok 1'=>'purok1_rbi',
  'Purok 2'=>'purok2_rbi',
  'Purok 3'=>'purok3_rbi',
  'Purok 4'=>'purok4_rbi',
  'Purok 5'=>'purok5_rbi',
  'Purok 6'=>'purok6_rbi'
];

// 1) Fetch pending data
$q = $conn->prepare("
  SELECT full_name, birthdate, sex, civil_status, blood_type,
         birth_registration_number, highest_educational_attainment,
         occupation, purok, profile_picture
  FROM pending_accounts
  WHERE account_ID = ?
");
$q->bind_param("i", $accountId);
$q->execute();
$res = $q->get_result();
if (!$res || $res->num_rows !== 1) {
  die("Pending record not found");
}
$pending = $res->fetch_assoc();
$q->close();

// 2) Detect existing in any purok
$existingPurok = null;
foreach ($map as $label=>$tbl) {
  $c = $conn->prepare("SELECT account_ID FROM `$tbl` WHERE full_name = ? LIMIT 1");
  $c->bind_param("s", $name);
  $c->execute();
  $r = $c->get_result();
  if ($r && $r->num_rows===1) {
    $existingPurok = $label;
    $c->close();
    break;
  }
  $c->close();
}

// 3) Handle transfer / attach / insert
// Determine destination table for newPurok
if (!isset($map[$newPurok])) {
  die("Invalid new purok");
}
$newTable = $map[$newPurok];

if ($existingPurok) {
  $oldTable = $map[$existingPurok];
  if ($existingPurok !== $newPurok) {
    // Cross-purok: delete old record, then insert fresh into new
    // $d = $conn->prepare("DELETE FROM `$oldTable` WHERE full_name = ?");
    // $d->bind_param("s", $name);
    // $d->execute();
    // $d->close();

    // then fall through to INSERT into newTable
  } else {
    // Same purok: attach—UPDATE all columns
    $u = $conn->prepare("
      UPDATE `$newTable`
         SET account_ID = ?,
             birthdate = ?, sex = ?, civil_status = ?, blood_type = ?,
             birth_registration_number = ?, highest_educational_attainment = ?,
             occupation = ?, profile_picture = ?
       WHERE full_name = ?
    ");
    $u->bind_param(
      "isssssssss",
      $accountId,
      $pending['birthdate'],
      $pending['sex'],
      $pending['civil_status'],
      $pending['blood_type'],
      $pending['birth_registration_number'],
      $pending['highest_educational_attainment'],
      $pending['occupation'],
      $pending['profile_picture'],
      $name
    );
    $u->execute();
    if ($u->error) die("Attach failed: ".$u->error);
    $u->close();

    // skip INSERT
    goto promote_and_delete;
  }
}

// INSERT into newPurok table
$i = $conn->prepare("
  INSERT INTO `$newTable`
    (account_ID, full_name, birthdate, sex,
     civil_status, blood_type, birth_registration_number,
     highest_educational_attainment, occupation, profile_picture)
  VALUES (?,?,?,?,?,?,?,?,?,?)
");
$i->bind_param(
  "isssssssss",
  $accountId,
  $pending['full_name'],
  $pending['birthdate'],
  $pending['sex'],
  $pending['civil_status'],
  $pending['blood_type'],
  $pending['birth_registration_number'],
  $pending['highest_educational_attainment'],
  $pending['occupation'],
  $pending['profile_picture']
);
$i->execute();
if ($i->error) die("Insert failed: ".$i->error);
$i->close();

promote_and_delete:

// 4) Promote in user_accounts
$p = $conn->prepare("UPDATE user_accounts SET role='Resident' WHERE account_id = ?");
$p->bind_param("i", $accountId);
$p->execute();
if ($p->error) die("Role update failed: ".$p->error);
$p->close();

// 5) Remove from pending_accounts
// $r = $conn->prepare("DELETE FROM pending_accounts WHERE account_ID = ?");
// $r->bind_param("i", $accountId);
// $r->execute();
// if ($r->error) die("Delete pending failed: ".$r->error);
// $r->close();

// 6) Redirect
// header("Location: ../adminPanel.php?page=adminVerifications");
// exit;

$map = [
  'superAdmin' => '/superAdminPanel.php?page=adminVerifications',
  'admin'      => '/adminPanel.php?page=adminVerifications',
  'user'       => '/userPanel.php?page=adminVerifications',
];

$key = $_POST['redirectTo'] ?? 'user';
if (!isset($map[$key])) {
  $key = 'user';  // fallback
}

header("Location: " . $map[$key]);
exit;
