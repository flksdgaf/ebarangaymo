<?php
session_start(); // ADD THIS
// DB credentials
require_once 'dbconn.php';
date_default_timezone_set('Asia/Manila');

// Atomically get next ID starting at 100000001
function getNextAccountId($conn) {
    $res = $conn->query("SELECT MAX(account_id) AS max_id FROM user_accounts");
    $row = $res->fetch_assoc();
    return $row['max_id'] ? $row['max_id'] + 1 : 100000001;
}

$account_id = getNextAccountId($conn);

// // Step 1 inputs
// $fn = trim($_POST['firstname']   ?? '');
// $mn = trim($_POST['middlename']  ?? '');
// $ln = trim($_POST['lastname']    ?? '');
// $bd = $_POST['birthdate']        ?? '';

// Step 1 inputs - Format names to Title Case
$fn = ucwords(strtolower(trim($_POST['firstname']   ?? '')));
$mn = ucwords(strtolower(trim($_POST['middlename']  ?? '')));
$ln = ucwords(strtolower(trim($_POST['lastname']    ?? '')));
$bd = $_POST['birthdate']        ?? '';

// Step 4 creds (moved up for early validation)
$username = trim($_POST['username'] ?? '');
$pwd_plain= $_POST['password']       ?? '';

// Check for duplicate username (case-sensitive)
$checkUsername = $conn->prepare("SELECT account_id FROM user_accounts WHERE BINARY username = ?");
$checkUsername->bind_param("s", $username);
$checkUsername->execute();
$checkUsername->store_result();

if ($checkUsername->num_rows > 0) {
    $checkUsername->close();
    $_SESSION['signup_error'] = "Username already exists. Please choose a different username.";
    header("Location: ../signup.php");
    exit;
}
$checkUsername->close();

// Check for duplicate email if provided
$email = trim($_POST['email'] ?? '');
if (!empty($email)) {
    $checkEmail = $conn->prepare("SELECT account_id FROM user_accounts WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();
    
    if ($checkEmail->num_rows > 0) {
        $checkEmail->close();
        $_SESSION['signup_error'] = "Email already exists. Please use a different email.";
        header("Location: ../signup.php");
        exit;
    }
    $checkEmail->close();
}

// Validate age - must be 10 years or older
if ($bd) {
    $birthDate = new DateTime($bd);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    
    if ($age < 10) {
        $_SESSION['signup_error'] = "You must be at least 10 years old to register.";
        header("Location: ../signup.php");
        exit;
    }
} else {
    $_SESSION['signup_error'] = "Birthdate is required.";
    header("Location: ../signup.php");
    exit;
}

// Build the optional pieces
$middlePart = $mn ? ", {$mn}" : '';

// Format: LastName, FirstName, MiddleName
$full_name = "{$ln}, {$fn}{$middlePart}";

// Step 2 inputs
$pu = $_POST['purok']                ?? '';

// Step 3 file fields (front & back ID only)
$validID  = $_POST['validID']                ?? '';
$front    = $_FILES['frontID']   ?? null;
$back     = $_FILES['backID']    ?? null;

// Use default profile picture
$profileName = 'default_profile_pic.png';

// Hash password (username and email already validated above)
$pwd_hash = password_hash($pwd_plain, PASSWORD_DEFAULT);

// Directories (ensure they exist & are writable)
$dirs = [
  'front'   => '../frontID/',
  'back'    => '../backID/'
];

// Build unique filenames for ID uploads only
$time = time();
$frontName   = $time . "_front_"  . basename($front['name']);
$backName    = $time . "_back_"   . basename($back['name']);
$now = date('Y-m-d H:i:s');

if (
  move_uploaded_file($front['tmp_name'],   $dirs['front']   . $frontName)  &&
  move_uploaded_file($back['tmp_name'],    $dirs['back']    . $backName)
) {
  // Pending table insert
  $stmt1 = $conn->prepare("
    INSERT INTO pending_accounts (account_ID, full_name, birthdate, purok, valid_ID, front_ID, back_ID, profile_picture, time_creation)
    VALUES (?,?,?,?,?,?,?,?,?)
  ");
  $stmt1->bind_param(
    "issssssss",
    $account_id, $full_name, $bd, $pu, $validID, $frontName, $backName, $profileName, $now
   );

  // User-accounts insert (now includes email)
  $stmt2 = $conn->prepare("
    INSERT INTO user_accounts (account_id, username, password, email)
    VALUES (?,?,?,?)
  ");
  $stmt2->bind_param(
    "isss", 
    $account_id, $username, $pwd_hash, $email
  );

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
