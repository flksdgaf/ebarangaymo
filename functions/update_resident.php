<?php
// functions/update_resident.php
require_once 'dbconn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' 
    || empty($_POST['account_id']) 
    || !isset($_POST['original_purok']) 
    || !isset($_POST['new_purok'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_request']);
    exit;
}
$acct        = (int) $_POST['account_id'];
$orig        = (int) $_POST['original_purok'];
$new         = (int) $_POST['new_purok'];

$full_name   = $_POST['full_name']                      ?? '';
$birthdate   = $_POST['birthdate']                      ?? '';
$sex         = $_POST['sex']                            ?? '';
$civil_status= $_POST['civil_status']                   ?? '';
$blood_type  = $_POST['blood_type']                     ?? '';
$birth_registration_number = $_POST['birth_registration_number'] ?? '';
$highest_educational_attainment = $_POST['highest_educational_attainment'] ?? '';
$occupation  = $_POST['occupation']                     ?? '';
$profile_picture = $_POST['profile_picture'] ?? '';
$remarks     = $_POST['remarks']                        ?? '';
// Non-transferred fields (house_number etc.) still read but only used if same purok
$house_number = $_POST['house_number'] !== '' ? (int)$_POST['house_number'] : null;
$relationship_to_head = $_POST['relationship_to_head']           ?? '';
$registry_number = $_POST['registry_number'] !== '' ? (int)$_POST['registry_number'] : null;
$total_population = $_POST['total_population'] !== ''   ? (int)$_POST['total_population']   : null;

$conn->begin_transaction();
try {
    if ($new !== $orig) {
        // Insert into new purok table only the allowed fields
        $insertSql = "INSERT INTO `purok{$new}_rbi`
            (account_ID, full_name, birthdate, sex, civil_status, blood_type, birth_registration_number, highest_educational_attainment, occupation, profile_picture, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertSql);
        if (!$stmt) throw new Exception("prepare failed: ".$conn->error);
        $stmt->bind_param(
            'issssssssss',
            $acct,
            $full_name,
            $birthdate,
            $sex,
            $civil_status,
            $blood_type,
            $birth_registration_number,
            $highest_educational_attainment,
            $occupation,
            $profile_picture,
            $remarks
        );
        $stmt->execute() or throw new Exception("insert failed: ".$stmt->error);
        $stmt->close();
        // Delete from old purok table
        $delSql = "DELETE FROM `purok{$orig}_rbi` WHERE full_name = ?";
        $stmt2 = $conn->prepare($delSql);
        if (!$stmt2) throw new Exception("prepare delete failed: ".$conn->error);
        $stmt2->bind_param('s', $full_name);
        $stmt2->execute() or throw new Exception("delete failed: ".$stmt2->error);
        $stmt2->close();
    } else {
        // Same purok: update all editable fields including non-transferred ones
        $updateSql = "UPDATE `purok{$orig}_rbi` SET
            full_name = ?, birthdate = ?, sex = ?, civil_status = ?, blood_type = ?, birth_registration_number = ?, highest_educational_attainment = ?, occupation = ?, house_number = ?, relationship_to_head = ?, registry_number = ?, total_population = ?
            WHERE account_ID = ?";
        $stmt3 = $conn->prepare($updateSql);
        if (!$stmt3) throw new Exception("prepare update failed: ".$conn->error);
        $stmt3->bind_param(
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
        $stmt3->execute() or throw new Exception("update failed: ".$stmt3->error);
        $stmt3->close();
    }
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
$conn->close();
exit;
?>
