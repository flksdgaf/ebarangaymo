<?php
// functions/update_role.php
require_once 'dbconn.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function json_out($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}
function json_err($msg, $code = 400) {
  json_out(['success' => false, 'error' => $msg], $code);
}

// helper: find full_name for an account_id scanning purok tables 1..6
function findFullNameForAccount($conn, int $account_id) : ?string {
  for ($p = 1; $p <= 6; $p++) {
    $tbl = "purok{$p}_rbi";
    // guard against SQL injection on table name by using fixed loop
    $sql = "SELECT full_name FROM `{$tbl}` WHERE account_ID = ? LIMIT 1";
    $st = $conn->prepare($sql);
    if ($st === false) continue; // if table missing, skip
    $st->bind_param('i', $account_id);
    $st->execute();
    $res = $st->get_result();
    if ($row = $res->fetch_assoc()) {
      $st->close();
      return $row['full_name'];
    }
    $st->close();
  }
  return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_err('Invalid method', 405);
}

$acct = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;
$role = isset($_POST['role']) ? trim($_POST['role']) : '';
// optional: client may pass purok; we don't rely on it
$purok = isset($_POST['purok']) ? (int)$_POST['purok'] : null;

if (!$acct || $role === '') json_err('Missing parameters', 400);

$validRoles = [
  'Resident',
  'Brgy Captain',
  'Brgy Secretary',
  'Brgy Bookkeeper',
  'Brgy Kagawad',
  'Brgy Treasurer',
  'Lupon Tagapamayapa'
];
if (!in_array($role, $validRoles, true)) json_err('Invalid role', 400);

// who is changing roles?
$performerRole = $_SESSION['loggedInUserRole'] ?? '';
$performerId   = (int)($_SESSION['loggedInUserID'] ?? 0);

$allowedChangers = ['Brgy Captain', 'Brgy Bookkeeper', 'Brgy Secretary'];
if (!in_array($performerRole, $allowedChangers, true)) {
  json_err('You do not have permission to change roles', 403);
}

// ensure the target account exists and read its previous role
$checkStmt = $conn->prepare("SELECT role FROM user_accounts WHERE account_id = ? LIMIT 1");
if ($checkStmt === false) json_err('Database error (prepare existence check): ' . $conn->error, 500);
$checkStmt->bind_param('i', $acct);
$checkStmt->execute();
$checkRes = $checkStmt->get_result();
if (!$row = $checkRes->fetch_assoc()) {
  $checkStmt->close();
  json_err('Target account not found', 404);
}
$prevRole = $row['role'] ?? '';
$checkStmt->close();

// find the full name (optional) for better messages
$fullName = findFullNameForAccount($conn, $acct);
$whoLabel = $fullName ? "{$fullName} (Account No:{$acct})" : "Account #{$acct}";

// role limits
$limits = [
  'Brgy Captain' => 1,
  'Brgy Secretary' => 1,
  'Brgy Treasurer' => 1,
  'Brgy Kagawad' => 7,
  'Lupon Tagapamayapa' => 8
];

// if target role has a limit, ensure we won't exceed it
if (isset($limits[$role])) {
  $countStmt = $conn->prepare("SELECT account_id FROM user_accounts WHERE role = ? AND account_id <> ?");
  if ($countStmt === false) json_err('Database error (prepare count): ' . $conn->error, 500);
  $countStmt->bind_param('si', $role, $acct);
  $countStmt->execute();
  $res = $countStmt->get_result();
  $holders = [];
  while ($r = $res->fetch_assoc()) {
    $holders[] = (int)$r['account_id'];
  }
  $countStmt->close();

  if (count($holders) >= $limits[$role]) {
    // translate holder account ids into names (first few)
    $names = [];
    foreach (array_slice($holders, 0, 10) as $hid) {
      $n = findFullNameForAccount($conn, $hid);
      $names[] = $n ? "{$n} (acct #{$hid})" : "acct #{$hid}";
    }
    $holderList = implode(', ', $names);
    json_err("Cannot assign role '{$role}': limit of {$limits[$role]} reached. Current holder(s): {$holderList}", 409);
  }
}

// If changing to the same role, return a clear message (no-op)
if ($prevRole === $role) {
  json_out(['success' => true, 'message' => "No change: {$whoLabel} already has role '{$role}'", 'account_id' => $acct, 'full_name' => $fullName], 200);
}

// perform the update
$upd = $conn->prepare("UPDATE user_accounts SET role = ? WHERE account_id = ?");
if ($upd === false) json_err('Database error (prepare update): ' . $conn->error, 500);
$upd->bind_param('si', $role, $acct);
$ok = $upd->execute();
if ($ok === false) {
  $upd->close();
  json_err('Database error (execute update): ' . $conn->error, 500);
}
$affected = $upd->affected_rows;
$upd->close();

if ($affected) {
  json_out([
    'success' => true,
    'message' => "Role updated to '{$role}' for {$whoLabel}",
    'account_id' => $acct,
    'full_name'  => $fullName,
    'previous_role' => $prevRole,
    'new_role' => $role
  ], 200);
} else {
  // unlikely: update ran but 0 rows affected
  json_out([
    'success' => true,
    'message' => "No change (nothing to update) for {$whoLabel}",
    'account_id' => $acct,
    'full_name' => $fullName,
    'previous_role' => $prevRole,
    'new_role' => $role
  ], 200);
}

// // functions/update_role.php
// require_once 'dbconn.php';

// if ($_SERVER['REQUEST_METHOD']!=='POST'
//  || empty($_POST['account_id'])
//  || empty($_POST['role'])
// ) {
//   http_response_code(400);
//   exit('Invalid');
// }

// $acct = (int)$_POST['account_id'];
// $role = trim($_POST['role']);
// $purok= (int)$_POST['purok'];

// // validate role
// $valid = ['Resident','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Treasurer', 'Lupon Tagapamayapa'];
// if (!in_array($role,$valid,true)) {
//   http_response_code(400);
//   exit('Bad role');
// }

// // update user_accounts
// $stmt = $conn->prepare("
//   UPDATE user_accounts
//     SET role = ?
//   WHERE account_id = ?
// ");
// $stmt->bind_param("si",$role,$acct);
// $stmt->execute();
// $stmt->close();

// // return JSON
// echo json_encode(['success'=>true]);
// exit;
?>
