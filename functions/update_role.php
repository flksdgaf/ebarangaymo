<?php
// functions/update_role.php
require_once 'dbconn.php';

if ($_SERVER['REQUEST_METHOD']!=='POST'
 || empty($_POST['account_id'])
 || empty($_POST['role'])
 || !isset($_POST['purok'])) {
  http_response_code(400);
  exit('Invalid');
}

$acct = (int)$_POST['account_id'];
$role = trim($_POST['role']);
$purok= (int)$_POST['purok'];

// validate role
$valid = ['Resident','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Staff'];
if (!in_array($role,$valid,true)) {
  http_response_code(400);
  exit('Bad role');
}

// update user_accounts
$stmt = $conn->prepare("
  UPDATE user_accounts
    SET role = ?
  WHERE account_id = ?
");
$stmt->bind_param("si",$role,$acct);
$stmt->execute();
$stmt->close();

// return JSON
echo json_encode(['success'=>true]);
