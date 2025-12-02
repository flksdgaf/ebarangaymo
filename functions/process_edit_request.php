<?php
session_start();
require 'dbconn.php';

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: ../index.php");
    exit();
}

$role = $_SESSION['loggedInUserRole'] ?? '';
if ($role === 'SuperAdmin') {
    $redirectBase = 'superAdminPanel.php';
    $redirectPage = 'superAdminRequest';
} else {
    $redirectBase = 'adminPanel.php';
    $redirectPage = 'adminRequest';
}

$transactionId = $_POST['transaction_id'] ?? '';
$requestType = $_POST['request_type'] ?? '';

if (!$transactionId || !$requestType) {
    $_SESSION['process_edit_request_errors'] = ['Missing transaction ID or request type'];
    header("Location: ../{$redirectBase}?page={$redirectPage}&error=missing_data");
    exit();
}

try {
    $conn->begin_transaction();

    // Variable to track if any update occurred
    $rowsAffected = 0;

    switch($requestType) {
        case 'Barangay ID':
            // Collect posted fields
            $fn = trim($_POST['barangay_id_first_name'] ?? '');
            $mn = trim($_POST['barangay_id_middle_name'] ?? '');
            $ln = trim($_POST['barangay_id_last_name'] ?? '');
            
            $fn = ucwords(strtolower($fn));
            $mn = $mn ? ucwords(strtolower($mn)) : '';
            $ln = ucwords(strtolower($ln));
            
            $middlePart = $mn ? ", {$mn}" : '';
            $fullName = "{$ln}, {$fn}{$middlePart}";
            
            $purok = trim($_POST['barangay_id_purok'] ?? '');
            $birthDate = trim($_POST['barangay_id_dob'] ?? '');
            $birthPlace = ucwords(strtolower(trim($_POST['barangay_id_birth_place'] ?? '')));
            $civilStatus = trim($_POST['barangay_id_civil_status'] ?? '');
            $religion = ($_POST['barangay_id_religion'] ?? '') === 'Other' ? trim($_POST['barangay_id_religion_other'] ?? '') : trim($_POST['barangay_id_religion'] ?? '');
            $height = isset($_POST['barangay_id_height']) && $_POST['barangay_id_height'] !== '' ? (float) $_POST['barangay_id_height'] : null;
            $weight = isset($_POST['barangay_id_weight']) && $_POST['barangay_id_weight'] !== '' ? (float) $_POST['barangay_id_weight'] : null;
            
            // Apply title case to emergency contact fields
            $contactPerson = ucwords(strtolower(trim($_POST['barangay_id_emergency_contact_person'] ?? '')));
            $contactAddress = ucwords(strtolower(trim($_POST['barangay_id_emergency_contact_address'] ?? '')));
            
            $idNumber = trim($_POST['barangay_id_id_number'] ?? '');
            
            // Handle photo update
            $photoUpdate = '';
            $photoParam = null;
            
            if (!empty($_FILES['barangay_id_photo']['name']) && $_FILES['barangay_id_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../barangayIDpictures/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $orig = basename($_FILES['barangay_id_photo']['name']);
                $ext = pathinfo($orig, PATHINFO_EXTENSION);
                $allowedExt = ['jpg','jpeg','png','gif'];
                
                if (in_array(strtolower($ext), $allowedExt, true)) {
                    $photoParam = uniqid('bid_', true) . '.' . strtolower($ext);
                    $target = $uploadDir . $photoParam;
                    
                    if (move_uploaded_file($_FILES['barangay_id_photo']['tmp_name'], $target)) {
                        $photoUpdate = ', formal_picture = ?';
                    } else {
                        $photoParam = null;
                    }
                }
            }
            
            // Build UPDATE query
            $sql = "UPDATE barangay_id_requests SET 
                    full_name = ?, purok = ?, birth_date = ?, birth_place = ?, 
                    civil_status = ?, religion = ?, height = ?, weight = ?, 
                    emergency_contact_person = ?, emergency_contact_address = ?, 
                    valid_id_number = ?" . $photoUpdate . " 
                    WHERE transaction_id = ?";
            
            $stmt = $conn->prepare($sql);
            
            if ($photoParam) {
                $stmt->bind_param('ssssssddsssss', $fullName, $purok, $birthDate, $birthPlace, 
                    $civilStatus, $religion, $height, $weight, $contactPerson, $contactAddress, 
                    $idNumber, $photoParam, $transactionId);
            } else {
                $stmt->bind_param('ssssssddssss', $fullName, $purok, $birthDate, $birthPlace, 
                    $civilStatus, $religion, $height, $weight, $contactPerson, $contactAddress, 
                    $idNumber, $transactionId);
            }
            
            $stmt->execute();
            $rowsAffected = $stmt->affected_rows; // Capture BEFORE close
            $stmt->close();
            break;

        case 'Barangay Clearance':
            $fn = trim($_POST['clearance_first_name'] ?? '');
            $mn = trim($_POST['clearance_middle_name'] ?? '');
            $ln = trim($_POST['clearance_last_name'] ?? '');
            
            $fn = ucwords(strtolower($fn));
            $mn = $mn ? ucwords(strtolower($mn)) : '';
            $ln = ucwords(strtolower($ln));
            
            $middlePart = $mn ? ", {$mn}" : '';
            $fullName = "{$ln}, {$fn}{$middlePart}";
            
            $street = ucwords(strtolower(trim($_POST['clearance_street'] ?? '')));
            $purok = $_POST['clearance_purok'] ?? '';
            $barangay = ucwords(strtolower(trim($_POST['clearance_barangay'] ?? 'MAGANG')));
            $municipality = ucwords(strtolower(trim($_POST['clearance_municipality'] ?? 'DAET')));
            $province = ucwords(strtolower(trim($_POST['clearance_province'] ?? 'CAMARINES NORTE')));
            $birthDate = $_POST['clearance_birthdate'] ?? '';
            $age = (int)($_POST['clearance_age'] ?? 0);
            $birthPlace = ucwords(strtolower(trim($_POST['clearance_birthplace'] ?? '')));
            $maritalStatus = $_POST['clearance_marital_status'] ?? '';
            $ctcNumber = trim($_POST['clearance_ctc_number'] ?? '');
            $ctcNumber = $ctcNumber ? (int)$ctcNumber : null;
            $purpose = ucfirst(strtolower(trim($_POST['clearance_purpose'] ?? '')));
            
            // Handle photo
            $photoUpdate = '';
            $photoParam = null;
            
            if (!empty($_FILES['clearance_photo']['name']) && $_FILES['clearance_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../barangayClearancePictures/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $orig = basename($_FILES['clearance_photo']['name']);
                $ext = pathinfo($orig, PATHINFO_EXTENSION);
                $allowedExt = ['jpg','jpeg','png','gif'];
                
                if (in_array(strtolower($ext), $allowedExt, true)) {
                    $photoParam = 'clr_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                    $target = $uploadDir . $photoParam;
                    
                    if (move_uploaded_file($_FILES['clearance_photo']['tmp_name'], $target)) {
                        $photoUpdate = ', picture = ?';
                    } else {
                        $photoParam = null;
                    }
                }
            }
            
            $sql = "UPDATE barangay_clearance_requests SET 
                    full_name = ?, street = ?, purok = ?, barangay = ?, municipality = ?, 
                    province = ?, birth_date = ?, age = ?, birth_place = ?, marital_status = ?, 
                    ctc_number = ?, purpose = ?" . $photoUpdate . " 
                    WHERE transaction_id = ?";
            
            $stmt = $conn->prepare($sql);
            
            if ($photoParam) {
                $stmt->bind_param('ssssssssssssss', $fullName, $street, $purok, $barangay, $municipality, 
                    $province, $birthDate, $age, $birthPlace, $maritalStatus, $ctcNumber, $purpose, 
                    $photoParam, $transactionId);
            } else {
                $stmt->bind_param('sssssssssssss', $fullName, $street, $purok, $barangay, $municipality, 
                    $province, $birthDate, $age, $birthPlace, $maritalStatus, $ctcNumber, $purpose, 
                    $transactionId);
            }
            
            $stmt->execute();
            $rowsAffected = $stmt->affected_rows; // Capture BEFORE close
            $stmt->close();
            break;

        case 'Business Clearance':
            $fn = trim($_POST['business_first_name'] ?? '');
            $mn = trim($_POST['business_middle_name'] ?? '');
            $ln = trim($_POST['business_last_name'] ?? '');
            
            $fn = ucwords(strtolower($fn));
            $mn = $mn ? ucwords(strtolower($mn)) : '';
            $ln = ucwords(strtolower($ln));
            
            $middlePart = $mn ? ", {$mn}" : '';
            $fullName = "{$ln}, {$fn}{$middlePart}";
            
            $purok = $_POST['business_purok'] ?? '';
            
            // Apply title case to address fields
            $barangay = ucwords(strtolower(trim($_POST['business_barangay'] ?? '')));
            $municipality = ucwords(strtolower(trim($_POST['business_municipality'] ?? '')));
            $province = ucwords(strtolower(trim($_POST['business_province'] ?? '')));
            
            $age = (int)($_POST['business_age'] ?? 0);
            $maritalStatus = $_POST['business_marital_status'] ?? '';
            $businessName = ucwords(strtolower(trim($_POST['business_name'] ?? '')));
            $businessType = ucwords(strtolower(trim($_POST['business_type'] ?? '')));
            $businessAddress = ucwords(strtolower(trim($_POST['business_address'] ?? '')));
            $ctcNumber = trim($_POST['business_ctc_number'] ?? '');
            $ctcNumber = $ctcNumber ? (int)$ctcNumber : 0;
            
            // Handle photo
            $photoUpdate = '';
            $photoParam = null;
            
            if (!empty($_FILES['business_photo']['name']) && $_FILES['business_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../businessClearancePictures/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $orig = basename($_FILES['business_photo']['name']);
                $ext = pathinfo($orig, PATHINFO_EXTENSION);
                $allowedExt = ['jpg','jpeg','png','gif'];
                
                if (in_array(strtolower($ext), $allowedExt, true)) {
                    $photoParam = 'bc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                    $target = $uploadDir . $photoParam;
                    
                    if (move_uploaded_file($_FILES['business_photo']['tmp_name'], $target)) {
                        $photoUpdate = ', picture = ?';
                    } else {
                        $photoParam = null;
                    }
                }
            }
            
            $sql = "UPDATE business_clearance_requests SET 
                    full_name = ?, purok = ?, barangay = ?, municipality = ?, province = ?, 
                    age = ?, marital_status = ?, business_name = ?, business_type = ?, 
                    address = ?, ctc_number = ?" . $photoUpdate . " 
                    WHERE transaction_id = ?";
            
            $stmt = $conn->prepare($sql);
            
            if ($photoParam) {
                $stmt->bind_param('sssssisssssss', $fullName, $purok, $barangay, $municipality, $province, 
                    $age, $maritalStatus, $businessName, $businessType, $businessAddress, $ctcNumber, 
                    $photoParam, $transactionId);
            } else {
                $stmt->bind_param('sssssissssss', $fullName, $purok, $barangay, $municipality, $province, 
                    $age, $maritalStatus, $businessName, $businessType, $businessAddress, $ctcNumber, 
                    $transactionId);
            }
            
            $stmt->execute();
            $rowsAffected = $stmt->affected_rows; // Capture BEFORE close
            $stmt->close();
            break;

        case 'First Time Job Seeker':
            $fn = trim($_POST['first_time_job_seeker_first_name'] ?? '');
            $mn = trim($_POST['first_time_job_seeker_middle_name'] ?? '');
            $ln = trim($_POST['first_time_job_seeker_last_name'] ?? '');
            
            $fn = ucwords(strtolower($fn));
            $mn = $mn ? ucwords(strtolower($mn)) : '';
            $ln = ucwords(strtolower($ln));
            
            $middlePart = $mn ? ", {$mn}" : '';
            $fullName = "{$ln}, {$fn}{$middlePart}";
            
            $age = (int)($_POST['first_time_job_seeker_age'] ?? 0);
            $sex = trim($_POST['first_time_job_seeker_sex'] ?? '');
            $civilStatus = trim($_POST['first_time_job_seeker_civil_status'] ?? '');
            $purok = trim($_POST['first_time_job_seeker_purok'] ?? '');
            
            // Validate required fields
            if (empty($fullName) || $age <= 0 || empty($sex) || empty($civilStatus) || empty($purok)) {
                throw new Exception('All fields are required for First Time Job Seeker');
            }
            
            $sql = "UPDATE job_seeker_requests SET 
                    full_name = ?, age = ?, sex = ?, civil_status = ?, purok = ? 
                    WHERE transaction_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sissss', $fullName, $age, $sex, $civilStatus, $purok, $transactionId);
            $stmt->execute();
            $rowsAffected = $stmt->affected_rows; // Capture BEFORE close
            $stmt->close();
            break;

        case 'Good Moral':
            $fn = trim($_POST['good_moral_first_name'] ?? '');
            $mn = trim($_POST['good_moral_middle_name'] ?? '');
            $ln = trim($_POST['good_moral_last_name'] ?? '');
            
            $fn = ucwords(strtolower($fn));
            $mn = $mn ? ucwords(strtolower($mn)) : '';
            $ln = ucwords(strtolower($ln));
            
            $middlePart = $mn ? ", {$mn}" : '';
            $fullName = "{$ln}, {$fn}{$middlePart}";
            
            $civilStatus = trim($_POST['good_moral_civil_status'] ?? '');
            $sex = trim($_POST['good_moral_sex'] ?? '');
            $age = (int)($_POST['good_moral_age'] ?? 0);
            $purok = trim($_POST['good_moral_purok'] ?? '');
            $purpose = ucfirst(strtolower(trim($_POST['good_moral_purpose'] ?? '')));
            
            // Validate required fields
            if (empty($fullName) || empty($civilStatus) || empty($sex) || $age <= 0 || empty($purok)) {
                throw new Exception('All required fields must be filled for Good Moral');
            }
            
            $sql = "UPDATE good_moral_requests SET 
                    full_name = ?, civil_status = ?, sex = ?, age = ?, purok = ?, purpose = ? 
                    WHERE transaction_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssisss', $fullName, $civilStatus, $sex, $age, $purok, $purpose, $transactionId);
            $stmt->execute();
            $rowsAffected = $stmt->affected_rows; // Capture BEFORE close
            $stmt->close();
            break;

        case 'Guardianship':
            $fn = trim($_POST['guardianship_first_name'] ?? '');
            $mn = trim($_POST['guardianship_middle_name'] ?? '');
            $ln = trim($_POST['guardianship_last_name'] ?? '');
            
            $fn = ucwords(strtolower($fn));
            $mn = $mn ? ucwords(strtolower($mn)) : '';
            $ln = ucwords(strtolower($ln));
            
            $middlePart = $mn ? ", {$mn}" : '';
            $fullName = "{$ln}, {$fn}{$middlePart}";
            
            $civilStatus = $_POST['guardianship_civil_status'] ?? '';
            $age = (int)($_POST['guardianship_age'] ?? 0);
            $purok = $_POST['guardianship_purok'] ?? '';
            
            // Collect children data
            $childrenData = $_POST['guardianship_children'] ?? [];
            $childNames = [];
            $childRelationships = [];
            
            foreach ($childrenData as $child) {
                if (!empty($child['name'])) {
                    $childNames[] = ucwords(strtolower(trim($child['name'])));
                    $relationship = !empty($child['relationship']) ? trim($child['relationship']) : '';
                    $childRelationships[] = $relationship ? ucwords(strtolower($relationship)) : '';
                }
            }
            
            $childName = implode(', ', $childNames);
            $childRelationship = implode(', ', $childRelationships);
            $purpose = ucfirst(strtolower(trim($_POST['guardianship_purpose'] ?? '')));
            
            $sql = "UPDATE guardianship_requests SET 
                    full_name = ?, civil_status = ?, age = ?, purok = ?, 
                    child_name = ?, child_relationship = ?, purpose = ? 
                    WHERE transaction_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssisssss', $fullName, $civilStatus, $age, $purok, 
                $childName, $childRelationship, $purpose, $transactionId);
            $stmt->execute();
            $rowsAffected = $stmt->affected_rows; // Capture BEFORE close
            $stmt->close();
            break;

        case 'Indigency':
            $fn = trim($_POST['indigency_first_name'] ?? '');
            $mn = trim($_POST['indigency_middle_name'] ?? '');
            $ln = trim($_POST['indigency_last_name'] ?? '');
            
            $fn = ucwords(strtolower($fn));
            $mn = $mn ? ucwords(strtolower($mn)) : '';
            $ln = ucwords(strtolower($ln));
            
            $middlePart = $mn ? ", {$mn}" : '';
            $fullName = "{$ln}, {$fn}{$middlePart}";
            
            $civilStatus = $_POST['indigency_civil_status'] ?? '';
            $age = (int) ($_POST['indigency_age'] ?? 0);
            $purok = $_POST['indigency_purok'] ?? '';
            $purpose = ucfirst(strtolower(trim($_POST['indigency_purpose'] ?? '')));
            
            $sql = "UPDATE indigency_requests SET 
                    full_name = ?, civil_status = ?, age = ?, purok = ?, purpose = ? 
                    WHERE transaction_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssisss', $fullName, $civilStatus, $age, $purok, $purpose, $transactionId);
            $stmt->execute();
            $rowsAffected = $stmt->affected_rows; // Capture BEFORE close
            $stmt->close();
            break;

        case 'Residency':
            $fn = trim($_POST['residency_first_name'] ?? '');
            $mn = trim($_POST['residency_middle_name'] ?? '');
            $ln = trim($_POST['residency_last_name'] ?? '');
            
            $fn = ucwords(strtolower($fn));
            $mn = $mn ? ucwords(strtolower($mn)) : '';
            $ln = ucwords(strtolower($ln));
            
            $middlePart = $mn ? ", {$mn}" : '';
            $fullName = "{$ln}, {$fn}{$middlePart}";
            
            $civilStatus = trim($_POST['residency_civil_status'] ?? '');
            $age = (int) ($_POST['residency_age'] ?? 0);
            $purok = trim($_POST['residency_purok'] ?? '');
            $yearsResiding = (int) ($_POST['residency_residing_years'] ?? 0);
            $purpose = ucfirst(strtolower(trim($_POST['residency_purpose'] ?? '')));
            
            // Validate required fields
            if (empty($fullName) || empty($civilStatus) || $age <= 0 || empty($purok) || $yearsResiding < 0) {
                throw new Exception('All required fields must be filled for Residency');
            }
            
            $sql = "UPDATE residency_requests SET 
                    full_name = ?, civil_status = ?, age = ?, purok = ?, 
                    residing_years = ?, purpose = ? 
                    WHERE transaction_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssissss', $fullName, $civilStatus, $age, $purok, 
                $yearsResiding, $purpose, $transactionId);
            $stmt->execute();
            $rowsAffected = $stmt->affected_rows; // Capture BEFORE close
            $stmt->close();
            break;

        case 'Solo Parent':
            $fn = trim($_POST['solo_parent_first_name'] ?? '');
            $mn = trim($_POST['solo_parent_middle_name'] ?? '');
            $ln = trim($_POST['solo_parent_last_name'] ?? '');
            
            $fn = ucwords(strtolower($fn));
            $mn = $mn ? ucwords(strtolower($mn)) : '';
            $ln = ucwords(strtolower($ln));
            
            $middlePart = $mn ? ", {$mn}" : '';
            $fullName = "{$ln}, {$fn}{$middlePart}";
            
            $civilStatus = $_POST['solo_parent_civil_status'] ?? '';
            $age = (int) ($_POST['solo_parent_age'] ?? 0);
            $sex = $_POST['solo_parent_sex'] ?? '';
            $purok = $_POST['solo_parent_purok'] ?? '';
            $yearsSoloParent = (int) ($_POST['solo_parent_years_solo_parent'] ?? 0);
            
            // Collect children data
            $childrenData = $_POST['children'] ?? [];
            foreach ($childrenData as &$child) {
                if (!empty($child['name'])) {
                    $child['name'] = ucwords(strtolower(trim($child['name'])));
                }
            }
            unset($child);
            
            $childrenJson = json_encode($childrenData);
            $purpose = ucfirst(strtolower(trim($_POST['solo_parent_purpose'] ?? '')));
            
            $sql = "UPDATE solo_parent_requests SET 
                    full_name = ?, civil_status = ?, age = ?, sex = ?, purok = ?, 
                    years_solo_parent = ?, children_data = ?, purpose = ? 
                    WHERE transaction_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssississs', $fullName, $civilStatus, $age, $sex, $purok, 
                $yearsSoloParent, $childrenJson, $purpose, $transactionId);
            $stmt->execute();
            $rowsAffected = $stmt->affected_rows; // Capture BEFORE close
            $stmt->close();
            break;

        default:
            throw new Exception("Unknown request type: {$requestType}");
    }

    // Activity logging
    $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
        $admin_id = (int) $_SESSION['loggedInUserID'];
        $roleName = $_SESSION['loggedInUserRole'];
        $action = 'UPDATE';
        
        // Determine table name from request type
        $tableMap = [
            'Barangay ID' => 'barangay_id_requests',
            'Barangay Clearance' => 'barangay_clearance_requests',
            'Business Clearance' => 'business_clearance_requests',
            'First Time Job Seeker' => 'job_seeker_requests',
            'Good Moral' => 'good_moral_requests',
            'Guardianship' => 'guardianship_requests',
            'Indigency' => 'indigency_requests',
            'Residency' => 'residency_requests',
            'Solo Parent' => 'solo_parent_requests'
        ];
        
        $table_name = $tableMap[$requestType] ?? 'unknown';
        $description = "Updated {$requestType} Request: {$transactionId}";
        
        $logStmt->bind_param('isssss', $admin_id, $roleName, $action, $table_name, $transactionId, $description);
        $logStmt->execute();
        $logStmt->close();
    }

    $conn->commit();
    
    // Redirect with appropriate message based on rows affected
    if ($rowsAffected > 0) {
        header("Location: ../{$redirectBase}?page={$redirectPage}&edit_success={$transactionId}");
    } else {
        header("Location: ../{$redirectBase}?page={$redirectPage}&edit_no_change={$transactionId}");
    }
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("process_edit_request error: " . $e->getMessage());
    $_SESSION['process_edit_request_errors'] = [$e->getMessage()];
    header("Location: ../{$redirectBase}?page={$redirectPage}&error=edit_failed");
    exit();
}
?>