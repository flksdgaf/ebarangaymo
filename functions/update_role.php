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

// Helper: find full_name for an account_id scanning purok tables 1..6
function findFullNameForAccount($conn, int $account_id) : ?string {
  for ($p = 1; $p <= 6; $p++) {
    $tbl = "purok{$p}_rbi";
    $sql = "SELECT full_name FROM `{$tbl}` WHERE account_ID = ? LIMIT 1";
    $st = $conn->prepare($sql);
    if ($st === false) continue;
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

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_err('Invalid method', 405);
}

// Get parameters
$targetAccountId = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;
$newRole = isset($_POST['role']) ? trim($_POST['role']) : '';

if (!$targetAccountId || $newRole === '') {
  json_err('Missing parameters', 400);
}

// Validate role
$validRoles = [
  'Resident',
  'Brgy Captain',
  'Brgy Secretary',
  'Brgy Bookkeeper',
  'Brgy Kagawad',
  'Brgy Treasurer',
  'Lupon Tagapamayapa'
];
if (!in_array($newRole, $validRoles, true)) {
  json_err('Invalid role', 400);
}

// Get logged-in user info
$performerRole = $_SESSION['loggedInUserRole'] ?? '';
$performerId = (int)($_SESSION['loggedInUserID'] ?? 0);

if (!$performerId) {
  json_err('You must be logged in to perform this action', 401);
}

// CHECK 1: Only Brgy Captain, Brgy Bookkeeper, and Brgy Secretary can change roles
$allowedChangers = ['Brgy Captain', 'Brgy Bookkeeper', 'Brgy Secretary'];
if (!in_array($performerRole, $allowedChangers, true)) {
  json_err('You do not have permission to change roles', 403);
}

// CHECK 2: Prevent users from updating their own role
if ($performerId === $targetAccountId) {
  json_err('You cannot update your own role', 403);
}

// Get target account info (check if exists and get current role)
$checkStmt = $conn->prepare("SELECT role FROM user_accounts WHERE account_id = ? LIMIT 1");
if ($checkStmt === false) {
  json_err('Database error (prepare existence check): ' . $conn->error, 500);
}
$checkStmt->bind_param('i', $targetAccountId);
$checkStmt->execute();
$checkRes = $checkStmt->get_result();
if (!$row = $checkRes->fetch_assoc()) {
  $checkStmt->close();
  json_err('Target account not found', 404);
}
$currentRole = $row['role'] ?? '';
$checkStmt->close();

// CHECK 3: Brgy Bookkeeper and Brgy Secretary cannot update Brgy Captain's role
if (in_array($performerRole, ['Brgy Bookkeeper', 'Brgy Secretary'])) {
  if ($currentRole === 'Brgy Captain') {
    json_err('You do not have permission to change the Brgy Captain\'s role', 403);
  }
}

// Get full name for better messages
$fullName = findFullNameForAccount($conn, $targetAccountId);
$whoLabel = $fullName ? "{$fullName} (Account No:{$targetAccountId})" : "Account #{$targetAccountId}";

// CHECK 4: If changing to the same role, return early (no-op)
if ($currentRole === $newRole) {
  json_out([
    'success' => true, 
    'message' => "<strong>{$fullName}</strong> already has the role <strong>{$newRole}</strong>. No changes made.", 
    'account_id' => $targetAccountId, 
    'full_name' => $fullName
  ], 200);
}

// CHECK 5: Role limits - ensure we don't exceed maximum allowed for each role
$limits = [
  'Brgy Captain' => 1,
  'Brgy Secretary' => 1,
  'Brgy Treasurer' => 1,
  'Brgy Bookkeeper' => 1,
  'Brgy Kagawad' => 7,
  'Lupon Tagapamayapa' => 8
];

if (isset($limits[$newRole])) {
  // Count current holders of this role (excluding the target account)
  $countStmt = $conn->prepare("SELECT account_id FROM user_accounts WHERE role = ? AND account_id <> ?");
  if ($countStmt === false) {
    json_err('Database error (prepare count): ' . $conn->error, 500);
  }
  $countStmt->bind_param('si', $newRole, $targetAccountId);
  $countStmt->execute();
  $res = $countStmt->get_result();
  $holders = [];
  while ($r = $res->fetch_assoc()) {
    $holders[] = (int)$r['account_id'];
  }
  $countStmt->close();

  if (count($holders) >= $limits[$newRole]) {
    // Get names of current holders (limit to 3 for cleaner message)
    $names = [];
    foreach (array_slice($holders, 0, 3) as $hid) {
      $n = findFullNameForAccount($conn, $hid);
      $names[] = $n ? $n : "Account #{$hid}";
    }
    
    $holderCount = count($holders);
    $holderList = implode(', ', $names);
    
    // Add "and X more" if there are more than 3 holders
    if ($holderCount > 3) {
      $remaining = $holderCount - 3;
      $holderList .= " and {$remaining} more";
    }
    
    $errorMessage = "The role <strong>{$newRole}</strong> has reached its maximum limit of <strong>{$limits[$newRole]}</strong>. ";
    $errorMessage .= "Current holder" . ($holderCount > 1 ? "s" : "") . ": {$holderList}.";
    
    json_err($errorMessage, 409);
  }
}

// Perform the update
$upd = $conn->prepare("UPDATE user_accounts SET role = ? WHERE account_id = ?");
if ($upd === false) {
  json_err('Database error (prepare update): ' . $conn->error, 500);
}
$upd->bind_param('si', $newRole, $targetAccountId);
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
    'message' => "Role successfully updated to <strong>{$newRole}</strong> for <strong>{$fullName}</strong>.",
    'account_id' => $targetAccountId,
    'full_name' => $fullName,
    'previous_role' => $currentRole,
    'new_role' => $newRole
  ], 200);
} else {
  // Unlikely: update ran but 0 rows affected
  json_out([
    'success' => true,
    'message' => "No changes were made for <strong>{$fullName}</strong>.",
    'account_id' => $targetAccountId,
    'full_name' => $fullName,
    'previous_role' => $currentRole,
    'new_role' => $newRole
  ], 200);
}
?>
