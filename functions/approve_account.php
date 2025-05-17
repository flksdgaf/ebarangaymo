<?php
// functions/approve_account.php
require_once 'dbconn.php';

if ($_SERVER['REQUEST_METHOD']!=='POST'
    || empty($_POST['account_ID'])
    || empty($_POST['name'])) {
    http_response_code(400);
    die("Invalid request");
}

$accountId = (int) $_POST['account_ID'];
$name      = trim($_POST['name']);

// 1) Pull pending row into $pending
$stmt = $conn->prepare("
    SELECT full_name, birthdate, sex, civil_status, blood_type,
           birth_registration_number, highest_educational_attainment,
           occupation, purok, profile_picture
    FROM pending_accounts
    WHERE account_ID = ?
");
$stmt->bind_param("i", $accountId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows!==1) {
    http_response_code(404);
    die("Pending record not found");
}
$pending = $res->fetch_assoc();
$stmt->close();

// 2) Detect attach mode & target table
$map = [
  'Purok 1'=>'purok1_rbi',
  'Purok 2'=>'purok2_rbi',
  'Purok 3'=>'purok3_rbi',
  'Purok 4'=>'purok4_rbi',
  'Purok 5'=>'purok5_rbi',
  'Purok 6'=>'purok6_rbi'
];
$attachMode  = false;
$targetTable = '';

if (isset($map[$pending['purok']])) {
    $targetTable = $map[$pending['purok']];
    // check if that name exists already
    $chk = $conn->prepare("
      SELECT account_ID
      FROM `$targetTable`
      WHERE full_name = ?
      LIMIT 1
    ");
    $chk->bind_param("s", $pending['full_name']);
    $chk->execute();
    $chkRes = $chk->get_result();
    if ($chkRes && $chkRes->num_rows === 1) {
        $attachMode = true;
    }
    $chk->close();
} else {
    die("Invalid purok '{$pending['purok']}'");
}

if ($attachMode) {
    // 3A) Attach: UPDATE all columns on the existing row
    $up = $conn->prepare("
      UPDATE `$targetTable`
        SET account_ID = ?,
            birthdate = ?,
            sex = ?,
            civil_status = ?,
            blood_type = ?,
            birth_registration_number = ?,
            highest_educational_attainment = ?,
            occupation = ?,
            profile_picture = ?
      WHERE full_name = ?
    ");
    $up->bind_param(
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
      $pending['full_name']
    );
    if (!$up->execute()) {
        die("Attach update failed: " . $up->error);
    }
    $up->close();

} else {
    // 3B) Insert: new record
    $ins = $conn->prepare("
      INSERT INTO `$targetTable`
        (account_ID, full_name, birthdate, sex,
         civil_status, blood_type, birth_registration_number,
         highest_educational_attainment, occupation, profile_picture)
      VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $ins->bind_param(
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
    if (!$ins->execute()) {
        die("Insert failed: " . $ins->error);
    }
    $ins->close();
}

// 4) Promote in user_accounts
$u = $conn->prepare("
  UPDATE user_accounts
    SET role = 'Resident'
  WHERE account_id = ?
");
$u->bind_param("i", $accountId);
if (!$u->execute()) {
    die("Role update failed: " . $u->error);
}
$u->close();

// 5) Delete from pending_accounts
$d = $conn->prepare("
  DELETE FROM pending_accounts
  WHERE account_ID = ?
");
$d->bind_param("i", $accountId);
if (!$d->execute()) {
    die("Delete pending failed: " . $d->error);
}
$d->close();

// 6) Redirect back
header("Location: ../adminpanel.php?page=adminVerifications");
exit;
