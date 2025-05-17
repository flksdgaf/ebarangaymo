<?php
// DB credentials
require_once 'dbconn.php';

// Atomically get next ID starting at 100000001
function getNextAccountId($conn) {
    $res = $conn->query("SELECT MAX(account_id) AS max_id FROM user_accounts");
    $row = $res->fetch_assoc();
    return $row['max_id'] ? $row['max_id'] + 1 : 100000001;
}

$account_id = getNextAccountId($conn);

// Step 1 inputs
$fn = trim($_POST['firstname']   ?? '');
$mn = trim($_POST['middlename']  ?? '');
$ln = trim($_POST['lastname']    ?? '');
$sn = trim($_POST['suffix']      ?? '');
$sx = $_POST['sex']              ?? '';
$bd = $_POST['birthdate']        ?? '';

// Format “Last, First Middle”
$full_name = "{$ln} {$sn}, {$fn}" . ($mn ? " {$mn}" : '');

// Step 2 inputs
$cs = $_POST['civilstatus']          ?? '';
$bt = $_POST['bloodtype']            ?? '';
$br = trim($_POST['birthreg']        ?? '');
if ($br === '') {
    $br = 'Unknown';
}
$ed = $_POST['educationalattainment']?? '';
$oc = trim($_POST['occupation']      ?? '');
$pu = $_POST['purok']                ?? '';

// Step 3 & 4 file fields
$validID  = $_POST['validID']                ?? '';
$front    = $_FILES['frontID']   ?? null;
$back     = $_FILES['backID']    ?? null;
$profile  = $_FILES['profilePic']?? null;

// Step 4 creds
$username = trim($_POST['username'] ?? '');
$pwd_plain= $_POST['password']       ?? '';
$pwd_hash = password_hash($pwd_plain, PASSWORD_DEFAULT);

// Directories (ensure they exist & are writable)
$dirs = [
  'front'   => '../frontID/',
  'back'    => '../backID/',
  'profile' => '../profilePictures/'
];

// Build unique filenames
$time = time();
$frontName   = $time . "_front_"  . basename($front['name']);
$backName    = $time . "_back_"   . basename($back['name']);
$profileName = $time . "_prof_"   . basename($profile['name']);

if (
  move_uploaded_file($front['tmp_name'],   $dirs['front']   . $frontName)  &&
  move_uploaded_file($back['tmp_name'],    $dirs['back']    . $backName)   &&
  move_uploaded_file($profile['tmp_name'], $dirs['profile'] . $profileName)
) {
  // Pending table insert
  $stmt1 = $conn->prepare("
    INSERT INTO pending_accounts
      (account_ID, full_name, birthdate, sex,
       civil_status, blood_type, birth_registration_number,
       highest_educational_attainment, occupation, purok,
       valid_ID, front_ID, back_ID, profile_picture)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ");
  $stmt1->bind_param(
    "isssssssssssss",
    $account_id, $full_name, $bd, $sx,
    $cs, $bt, $br,
    $ed, $oc, $pu,
    $validID,
    $frontName, $backName, $profileName
  );

  // User-accounts insert
  $stmt2 = $conn->prepare("
    INSERT INTO user_accounts
      (account_id, username, password)
    VALUES (?,?,?)
  ");
  $stmt2->bind_param("iss", $account_id, $username, $pwd_hash);

  if ($stmt1->execute() && $stmt2->execute()) {
    header("Location: ../underreview.php");
    exit;
  } else {
    echo "DB error: " . $conn->error;
  }

  $stmt1->close();
  $stmt2->close();
} else {
  echo "Error uploading one or more files.";
}

$conn->close();
?>
