<?php
// functions/add_resident.php
session_start();
require_once 'dbconn.php';

header('Content-Type: application/json');

// Check if user has permission
$allowedRoles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper'];
$userRole = $_SESSION['loggedInUserRole'] ?? '';

if (!in_array($userRole, $allowedRoles)) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get form data
$purok = isset($_POST['purok']) ? (int)$_POST['purok'] : 0;
$accountID = 0;
$last_name = trim($_POST['last_name'] ?? '');
$first_name = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');

// Format: "Lastname, Firstname Middlename"
$full_name = $last_name . ', ' . $first_name;
if (!empty($middle_name)) {
    $full_name .= ' ' . $middle_name;
}
$birthdate = trim($_POST['birthdate'] ?? '');
$sex = trim($_POST['sex'] ?? '');
$civil_status = trim($_POST['civil_status'] ?? '');
$blood_type = trim($_POST['blood_type'] ?? '');
$house_number = isset($_POST['house_number']) && $_POST['house_number'] !== '' ? (int)$_POST['house_number'] : null;
$relationship_to_head = trim($_POST['relationship_to_head'] ?? '');
$registry_number = isset($_POST['registry_number']) && $_POST['registry_number'] !== '' ? (int)$_POST['registry_number'] : null;
$total_population = isset($_POST['total_population']) && $_POST['total_population'] !== '' ? (int)$_POST['total_population'] : null;
$birth_registration_number = trim($_POST['birth_registration_number'] ?? '');
$highest_educational_attainment = trim($_POST['highest_educational_attainment'] ?? '');
$occupation = trim($_POST['occupation'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

// Validate required fields
if (!$purok || $purok < 1 || $purok > 6) {
    echo json_encode(['success' => false, 'error' => 'Invalid purok selected']);
    exit;
}

if (empty($last_name) || empty($first_name)) {
    echo json_encode(['success' => false, 'error' => 'Last name and first name are required']);
    exit;
}

if (empty($birthdate)) {
    echo json_encode(['success' => false, 'error' => 'Birthdate is required']);
    exit;
}

if (empty($sex)) {
    echo json_encode(['success' => false, 'error' => 'Sex is required']);
    exit;
}

if (empty($civil_status)) {
    echo json_encode(['success' => false, 'error' => 'Civil status is required']);
    exit;
}

// Check if resident with same name already exists in this purok
$tableName = "purok{$purok}_rbi";
$checkSql = "SELECT COUNT(*) as count FROM `{$tableName}` WHERE full_name = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param('s', $full_name);
$checkStmt->execute();
$result = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($result['count'] > 0) {
    echo json_encode(['success' => false, 'error' => 'A resident with this name already exists in this purok']);
    exit;
}

try {
    // Insert new resident (account_ID is NULL since they don't have an account yet)
    $insertSql = "INSERT INTO `{$tableName}` 
        (account_ID, full_name, birthdate, sex, civil_status, blood_type, 
         house_number, relationship_to_head, registry_number, total_population,
         birth_registration_number, highest_educational_attainment, occupation, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insertSql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param(
        'isssssisisssss',
        $accountID,
        $full_name,
        $birthdate,
        $sex,
        $civil_status,
        $blood_type,
        $house_number,
        $relationship_to_head,
        $registry_number,
        $total_population,
        $birth_registration_number,
        $highest_educational_attainment,
        $occupation,
        $remarks
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();
    
    // Log activity
    $logSql = "INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) 
               VALUES (?, ?, 'CREATE', ?, ?, ?)";
    $logStmt = $conn->prepare($logSql);
    $admin_id = $_SESSION['loggedInUserID'];
    $record_id = $full_name;
    $description = "Added new resident: {$full_name} to Purok {$purok}";
    $logStmt->bind_param('issss', $admin_id, $userRole, $tableName, $record_id, $description);
    $logStmt->execute();
    $logStmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Resident added successfully',
        'purok' => $purok
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>