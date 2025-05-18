<?php
// functions/update_resident.php
require_once 'dbconn.php';

header('Content-Type: application/json');

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    empty($_POST['account_id']) ||
    empty($_POST['purok'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_request']);
    exit;
}

$acct  = (int) $_POST['account_id'];
$purok = (int) $_POST['purok'];

$full_name                      = $_POST['full_name']                      ?? '';
$birthdate                      = $_POST['birthdate']                      ?? '';
$sex                            = $_POST['sex']                            ?? '';
$civil_status                   = $_POST['civil_status']                   ?? '';
$blood_type                     = $_POST['blood_type']                     ?? '';
$birth_registration_number      = $_POST['birth_registration_number']      ?? '';
$highest_educational_attainment = $_POST['highest_educational_attainment'] ?? '';
$occupation                     = $_POST['occupation']                     ?? '';
$house_number                   = $_POST['house_number'] !== '' ? (int)$_POST['house_number'] : null;
$relationship_to_head           = $_POST['relationship_to_head']           ?? '';
$registry_number                = $_POST['registry_number'] !== '' ? (int)$_POST['registry_number'] : null;
$total_population               = $_POST['total_population'] !== ''   ? (int)$_POST['total_population']   : null;

$table = "purok{$purok}_rbi";
$sql = "UPDATE `$table` SET
    full_name                      = ?,
    birthdate                      = ?,
    sex                            = ?,
    civil_status                   = ?,
    blood_type                     = ?,
    birth_registration_number      = ?,
    highest_educational_attainment = ?,
    occupation                     = ?,
    house_number                   = ?,
    relationship_to_head           = ?,
    registry_number                = ?,
    total_population               = ?
  WHERE account_ID = ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>"prepare_failed: {$conn->error}"]);
    exit;
}

$stmt->bind_param(
    'ssssssssisiii',
    $full_name,
    $birthdate,
    $sex,
    $civil_status,
    $blood_type,
    $birth_registration_number,
    $highest_educational_attainment,
    $occupation,
    $house_number,
    $relationship_to_head,
    $registry_number,
    $total_population,
    $acct
);

$ok = $stmt->execute();
if (!$ok) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>"execute_failed: {$stmt->error}"]);
} else {
    echo json_encode(['success'=>true]);
}

$stmt->close();
$conn->close();
exit;
?>