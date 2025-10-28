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

$userId = $_SESSION['loggedInUserID'];
$requestType = $_POST['request_type'] ?? '';

switch($requestType) {
  case 'Barangay ID':
    $transactionType = trim($_POST['barangay_id_transaction_type'] ?? '');
    $renewalTransactionId = trim($_POST['renewal_transaction_id'] ?? '');
    $existingPhoto = trim($_POST['barangay_id_existing_photo'] ?? '');

    // 1) Collect posted fields
    $fn = trim($_POST['barangay_id_first_name'] ?? '');
    $mn = trim($_POST['barangay_id_middle_name'] ?? '');
    $ln = trim($_POST['barangay_id_last_name'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $fullName = "{$ln}, {$fn}{$middlePart}";

    $purok = trim($_POST['barangay_id_purok'] ?? '');
    $birthDate = trim($_POST['barangay_id_dob'] ?? '');
    $birthPlace = trim($_POST['barangay_id_birth_place'] ?? '');
    $civilStatus = trim($_POST['barangay_id_civil_status'] ?? '');
    $religion = ($_POST['barangay_id_religion'] ?? '') === 'Other' ? trim($_POST['barangay_id_religion_other'] ?? '') : trim($_POST['barangay_id_religion'] ?? '');
    $height = isset($_POST['barangay_id_height']) && $_POST['barangay_id_height'] !== '' ? (float) $_POST['barangay_id_height'] : null;
    $weight = isset($_POST['barangay_id_weight']) && $_POST['barangay_id_weight'] !== '' ? (float) $_POST['barangay_id_weight'] : null;
    $contactPerson = trim($_POST['barangay_id_emergency_contact_person'] ?? '');
    $contactAddress = trim($_POST['barangay_id_emergency_contact_address'] ?? '');
    $paymentMethod = 'Over-the-Counter';
    $documentStatus = 'For Verification';
    $claimDate = null;

    // Basic validation
    $errors = [];
    if ($fullName === '') $errors[] = 'Full name required.';
    if ($purok === '') $errors[] = 'Purok required.';
    if ($birthDate === '') $errors[] = 'Birth date required.';
    else {
        $dt = DateTime::createFromFormat('Y-m-d', $birthDate);
        if (!($dt && $dt->format('Y-m-d') === $birthDate)) {
            $errors[] = 'Birth date must be in YYYY-MM-DD format.';
        }
    }
    if ($birthPlace === '') $errors[] = 'Birth place required.';
    if ($civilStatus === '') $errors[] = 'Civil status required.';
    if ($religion === '') $errors[] = 'Religion required.';
    if ($height === null) $errors[] = 'Height required.';
    if ($weight === null) $errors[] = 'Weight required.';

    if ($errors) {
        // store error (optionally more structured) and redirect back
        $_SESSION['process_new_request_errors'] = $errors;
        header("Location: ../{$redirectBase}?page={$redirectPage}&error=validation");
        exit();
    }

    // 2) Handle file upload
    $formalPicName = null;
    // For renewal: use existing photo if no new photo uploaded
    if ($transactionType === 'Renewal' && $existingPhoto && empty($_FILES['barangay_id_photo']['name'])) {
        $formalPicName = $existingPhoto;
    } elseif (!empty($_FILES['barangay_id_photo']['name']) && $_FILES['barangay_id_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../barangayIDpictures/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                // cannot create upload directory
                error_log("Cannot create upload dir: {$uploadDir}");
            }
        }

        // sanitize original name and prefix uniqid
        $orig = basename($_FILES['barangay_id_photo']['name']);
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $allowedExt = ['jpg','jpeg','png','gif'];
        $extLower = strtolower($ext);
        if (!in_array($extLower, $allowedExt, true)) {
            // skip saving and log warning
            error_log("Rejected upload (bad extension): {$orig}");
        } else {
            $formalPicName = uniqid('bid_', true) . '.' . $extLower;
            $target = $uploadDir . $formalPicName;
            if (!move_uploaded_file($_FILES['barangay_id_photo']['tmp_name'], $target)) {
                error_log("Failed to move uploaded file to {$target}");
                $formalPicName = null;
            }
        }
    }

    // 3) Generate next transaction_id
    $prefix = 'BID-'; // BRGYID
    $num = 1;
    $stmt = $conn->prepare("SELECT transaction_id FROM barangay_id_requests ORDER BY id DESC LIMIT 1");
    if ($stmt) {
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $lastTid = $res->fetch_assoc()['transaction_id'];
                // try to extract tailing digits
                if (is_string($lastTid) && preg_match('/(\d+)$/', $lastTid, $m)) {
                    $num = intval($m[1]) + 1;
                } else {
                    $num = 1;
                }
            }
        } else {
            error_log("Failed to execute select last transaction_id: " . $stmt->error);
        }
        $stmt->close();
    }
    $transactionId = sprintf($prefix . '%07d', $num);

    // 4) Insert into barangay_id_requests
    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO barangay_id_requests 
          (account_id, transaction_id, transaction_type, full_name, purok, birth_date, birth_place, civil_status, religion, 
          height, weight, emergency_contact_person, emergency_contact_address, formal_picture, claim_date, payment_method, 
          document_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NULLIF(?, ''),NULLIF(?, ''),?,?)";

        $ins = $conn->prepare($sql);
        if (!$ins) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $heightVal = $height !== null ? $height : null;
        $weightVal = $weight !== null ? $weight : null;

        $ins->bind_param('issssssssddssssss',$userId,$transactionId,$transactionType,$fullName,$purok,$birthDate,$birthPlace,
          $civilStatus,$religion,$heightVal,$weightVal,$contactPerson,$contactAddress,$formalPicName,$claimDate,$paymentMethod,
          $documentStatus
        );

        if (!$ins->execute()) {
            throw new Exception('Insert failed: ' . $ins->error);
        }

        $newId = $ins->insert_id;
        $ins->close();

        // Activity logging only if admin/staff
        $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper'];
        if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
            $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
            if (!$logStmt) {
                throw new Exception('Prepare activity log failed: ' . $conn->error);
            }
            $admin_id = (int) $_SESSION['loggedInUserID'];
            $roleName = $_SESSION['loggedInUserRole'];
            $action = 'CREATE';
            $table_name = 'barangay_id_requests';
            $record_id = $transactionId;
            $description = 'Created Barangay ID Request: ' . $record_id;

            if (!$logStmt->bind_param('isssss', $admin_id, $roleName, $action, $table_name, $record_id, $description)) {
                throw new Exception('Bind activity log failed: ' . $logStmt->error);
            }
            if (!$logStmt->execute()) {
                throw new Exception('Execute activity log failed: ' . $logStmt->error);
            }
            $logStmt->close();
        }

        $conn->commit();

        // 5) Redirect to panel with transaction id
        header("Location: ../{$redirectBase}?page={$redirectPage}&transaction_id=" . urlencode($transactionId));
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("process_new_request error: " . $e->getMessage());
        // optionally remove uploaded file if it was stored
        if (!empty($formalPicName)) {
            $fileToDelete = __DIR__ . '/../barangayIDpictures/' . $formalPicName;
            if (file_exists($fileToDelete)) @unlink($fileToDelete);
        }
        $_SESSION['process_new_request_errors'] = ['server_error' => $e->getMessage()];
        header("Location: ../{$redirectBase}?page={$redirectPage}&error=db");
        exit();
    }

  case 'Business Permit':
    $transactionType = $_POST['business_permit_transaction_type'] ?? '';

    // 1) Collect posted fields (owner name)
    $fn = trim($_POST['business_permit_first_name'] ?? '');
    $mn = trim($_POST['business_permit_middle_name'] ?? '');
    $ln = trim($_POST['business_permit_last_name'] ?? '');
    $sn = trim($_POST['business_permit_suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";
    // $fullName = trim($_POST['full_name'] ?? '');

    // 2) Other form inputs
    $purok = $_POST['business_permit_purok'] ?? '';
    $barangay = $_POST['business_permit_barangay'] ?? '';
    $age = (int)$_POST['business_permit_age'] ?? 0;
    $civilStatus = $_POST['business_permit_civil_status'] ?? '';
    $businessName = trim($_POST['business_permit_name_of_business'] ?? '');
    $businessType = trim($_POST['business_permit_type_of_business'] ?? '');
    $fullAddress = trim($_POST['business_permit_full_address'] ?? '');
    $paymentMethod = 'Over-the-Counter';
    // $documentStatus = 'Processing';
    $documentStatus = 'For Verification';
    // $claimDate = $_POST['claim_date'] ?? '';
    // $amount = (float)$_POST['amount'] ?? 0.0;

    // 3) Generate next transaction_id
    $stmt = $conn->prepare("SELECT transaction_id FROM business_permit_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $lastTid = $res->fetch_assoc()['transaction_id'];
      $num = intval(substr($lastTid, 6)) + 1; // e.g. skip "BPERMIT-"
    } else {
      $num = 1;
    }
    $transactionId = sprintf('BPRMT-%07d', $num);
    $stmt->close();

    // 4) Insert into business_permit_requests
    $sql = "INSERT INTO business_permit_requests (account_id, transaction_id, transaction_type, full_name, purok, barangay, age, civil_status, name_of_business, type_of_business, full_address, claim_date, payment_method, document_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,?,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('isssssissssss', $userId, $transactionId, $transactionType, $fullName, $purok, $barangay, $age, $civilStatus,
    $businessName, $businessType, $fullAddress, $paymentMethod, $documentStatus);
    $ins->execute();
    $ins->close();

    // 5) Activity logging
    $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer', 'Lupon Tagapamayapa'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
      $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
      $admin_id = $_SESSION['loggedInUserID'];
      $role = $_SESSION['loggedInUserRole'];
      $action = 'CREATE';
      $table_name = 'business_permit_requests';
      $record_id = $transactionId;
      $description = 'Created Business Permit Request';

      $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
      $logStmt->execute();
      $logStmt->close();
    }

    // 6) Redirect back to superAdminPanel with new ID
    header("Location: ../{$redirectBase}?page={$redirectPage}&transaction_id={$transactionId}");
    exit();
    break;

  case 'Good Moral':
    // 1) Collect posted fields
    $fn = trim($_POST['good_moral_first_name'] ?? '');
    $mn = trim($_POST['good_moral_middle_name'] ?? '');
    $ln = trim($_POST['good_moral_last_name'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    // $fullName = "{$ln}, {$fn}, {$middlePart}, {$suffixPart}";
    $fullName = "{$ln}, {$fn}{$middlePart}";
    // $fullName = trim($_POST['full_name'] ?? '');

    $civilStatus = $_POST['good_moral_civil_status'] ?? '';
    $sex = $_POST['good_moral_sex'] ?? '';
    $age = (int)($_POST['good_moral_age'] ?? 0);
    $purok = $_POST['good_moral_purok'] ?? '';
    // $barangay = $_POST['barangay'] ?? '';
    $address = trim($_POST['good_moral_address'] ?? '');
    // $fullAddress = "{$subdivision}, {$purok}, {$barangay}";
    $purpose = trim($_POST['good_moral_purpose'] ?? '');
    $paymentMethod = 'Over-the-Counter';
    // $documentStatus = 'Processing';
    $documentStatus = 'For Verification';
    // $claimDate = $_POST['claim_date'] ?? '';

    // 2) Generate next transaction_id
    $stmt = $conn->prepare("SELECT transaction_id FROM good_moral_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $lastTid = $res->fetch_assoc()['transaction_id'];
      $num = intval(substr($lastTid, 3)) + 1;
    } else {
      $num = 1;
    }
    $transactionId = sprintf('CGM-%07d', $num); // GM
    $stmt->close();

    // 3) Insert into good_moral_requests
    $sql = "INSERT INTO good_moral_requests (account_id, transaction_id, full_name, civil_status, sex, age, purok, address, purpose, claim_date, payment_method, document_status) VALUES (?,?,?,?,?,?,?,?,?,NULL,?,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('issssisssss', $userId, $transactionId, $fullName, $civilStatus, $sex, $age, $purok, $address, $purpose, $paymentMethod, $documentStatus);
    $ins->execute();
    $ins->close();

    // 4) Activity logging
    $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer', 'Lupon Tagapamayapa'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
      $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
      $admin_id = $_SESSION['loggedInUserID'];
      $role = $_SESSION['loggedInUserRole'];
      $action = 'CREATE';
      $table_name = 'good_moral_requests';
      $record_id = $transactionId;
      $description = 'Created Good Moral Certificate Request';

      $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
      $logStmt->execute();
      $logStmt->close();
    }

    // 5) Redirect back to superAdminPanel
    header("Location: ../{$redirectBase}?page={$redirectPage}&transaction_id={$transactionId}");
    exit();
    break;

  case 'Guardianship':
    // 1) Collect posted fields & assemble full name
    $fn = trim($_POST['guardianship_first_name'] ?? '');
    $mn = trim($_POST['guardianship_middle_name'] ?? '');
    $ln = trim($_POST['guardianship_last_name'] ?? '');
    $sn = trim($_POST['guardianship_suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";

    $civilStatus = $_POST['guardianship_civil_status'] ?? '';
    $age = (int)($_POST['guardianship_age'] ?? 0);
    $purok = $_POST['guardianship_purok'] ?? '';

    // Child's Details
    $childName = trim($_POST['child_full_name'] ?? '');
    $childRelationship = trim($_POST['child_relationship'] ?? ''); // NEW FIELD

    $purpose = trim($_POST['guardianship_purpose'] ?? '');
    $paymentMethod = 'Over-the-Counter';
    $documentStatus = 'For Verification';

    // 2) Generate next transaction_id
    $stmt = $conn->prepare("SELECT transaction_id FROM guardianship_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $lastTid = $res->fetch_assoc()['transaction_id'];
      $num = intval(substr($lastTid, 4)) + 1; 
    } else {
      $num = 1;
    }
    $transactionId = sprintf('GUA-%07d', $num);
    $stmt->close();
    
    // 3) Insert into guardianship_requests (updated to include child_relationship)
    $sql = "INSERT INTO guardianship_requests (account_id, transaction_id, full_name, civil_status, age, purok, child_name, child_relationship, purpose, claim_date, payment_method, document_status) VALUES (?,?,?,?,?,?,?,?,?,NULL,?,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('isssissssss', $userId, $transactionId, $fullName, $civilStatus, $age, $purok, $childName, $childRelationship, $purpose, $paymentMethod, $documentStatus);
    $ins->execute();
    $ins->close();

    // 4) Activity logging
    $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer', 'Lupon Tagapamayapa'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
      $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
      $admin_id = $_SESSION['loggedInUserID'];
      $role = $_SESSION['loggedInUserRole'];
      $action = 'CREATE';
      $table_name = 'guardianship_request';
      $record_id = $transactionId;
      $description = 'Created Guardianship Request';
      
      $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
      $logStmt->execute();
      $logStmt->close();
    }

    // 5) Redirect back
    header("Location: ../{$redirectBase}?page={$redirectPage}&transaction_id={$transactionId}");
    exit();
    break;

  case 'Indigency':
    // 1) Collect posted fields & assemble full name
    $fn = trim($_POST['indigency_first_name'] ?? '');
    $mn = trim($_POST['indigency_middle_name'] ?? '');
    $ln = trim($_POST['indigency_last_name'] ?? '');
    $sn = trim($_POST['indigency_suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";
    // $fullName = trim($_POST['full_name'] ?? '');

    // 2) Other form inputs
    $civilStatus = $_POST['indigency_civil_status'] ?? '';
    $age = (int) ($_POST['indigency_age'] ?? 0);
    // $barangay = $_POST['barangay'] ?? '';
    $purok = $_POST['indigency_purok'] ?? '';
    // $subdivision = trim($_POST['subdivision'] ?? '');
    $purpose = trim($_POST['indigency_purpose'] ?? '');
    // $documentStatus = 'Processing';
    $documentStatus = 'For Verification';

    // 3) Generate next transaction_id
    $stmt = $conn->prepare("SELECT transaction_id FROM indigency_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $lastTid = $res->fetch_assoc()['transaction_id'];
      $num = intval(substr($lastTid, 4)) + 1; 
    } else {
      $num = 1;
    }
    $transactionId = sprintf('IND-%07d', $num);
    $stmt->close();

    // 4) Insert into indigency_requests
    $sql = "INSERT INTO indigency_requests (account_id, transaction_id, full_name, civil_status, age, purok, purpose, claim_date, document_status) VALUES (?,?,?,?,?,?,?,NULL,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('isssisss', $userId, $transactionId, $fullName, $civilStatus, $age, $purok, $purpose, $documentStatus);
    $ins->execute();
    $ins->close();

    // 5) ACTIVITY LOGGING
    $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer', 'Lupon Tagapamayapa'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
      $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
      $admin_id = $_SESSION['loggedInUserID'];
      $role = $_SESSION['loggedInUserRole'];
      $action = 'CREATE';
      $table_name = 'indigency_requests';
      $record_id = $transactionId;
      $description = 'Created Indigency Certificate Request';

      $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
      $logStmt->execute();
      $logStmt->close();
    }

    // 6) Redirect back to the appropriate panel
    header("Location: ../{$redirectBase}?page={$redirectPage}&transaction_id={$transactionId}");
    exit();
    break;

  case 'Residency':
    // 1) Collect posted fields & assemble full name
    $fn = trim($_POST['residency_first_name'] ?? '');
    $mn = trim($_POST['residency_middle_name'] ?? '');
    $ln = trim($_POST['residency_last_name'] ?? '');
    $sn = trim($_POST['residency_suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";
    // $fullName = trim($_POST['full_name'] ?? '');

    // 2) Other form inputs
    $civilStatus = $_POST['residency_civil_status'] ?? '';
    $age = (int) ($_POST['residency_age'] ?? 0);
    // $barangay = $_POST['barangay'] ?? '';
    $purok = $_POST['residency_purok'] ?? '';
    // $subdivision = trim($_POST['subdivision'] ?? '');
    $yearsResiding = (int) ($_POST['residency_residing_years'] ?? 0);
    $purpose = trim($_POST['residency_purpose'] ?? '');
    $paymentMethod = 'Over-the-Counter';
    // $documentStatus = 'Processing';
    $documentStatus = 'For Verification';
    // $claimDate = $_POST['claim_date'] ?? '';

    // 3) Generate next transaction_id
    $stmt = $conn->prepare("SELECT transaction_id FROM residency_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $lastTid = $res->fetch_assoc()['transaction_id'];
      $num = intval(substr($lastTid, 4)) + 1;
    } else {
      $num = 1;
    }
    $transactionId = sprintf('RES-%07d', $num);
    $stmt->close();

    // 4) Insert into residency_requests
    $sql = "INSERT INTO residency_requests (account_id, transaction_id, full_name, civil_status, age, purok, residing_years, purpose, payment_method, claim_date, document_status) VALUES (?,?,?,?,?,?,?,?,?,NULL,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('isssisisss', $userId, $transactionId, $fullName, $civilStatus, $age, $purok, $yearsResiding, $purpose, $paymentMethod, $documentStatus);
    $ins->execute();
    $ins->close();

    // 5) ACTIVITY LOGGING
    $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer', 'Lupon Tagapamayapa'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
      $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
      $admin_id = $_SESSION['loggedInUserID'];
      $role = $_SESSION['loggedInUserRole'];
      $action = 'CREATE';
      $table_name = 'residency_requests';
      $record_id = $transactionId;
      $description = 'Created Residency Certificate Request';

      $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
      $logStmt->execute();
      $logStmt->close();
    }

    // 6) Redirect back to superAdminPanel
    header("Location: ../{$redirectBase}?page={$redirectPage}&transaction_id={$transactionId}");
    exit();
    break;

  case 'Solo Parent':
    // 1) Collect posted fields & assemble full name
    $fn = trim($_POST['solo_parent_first_name'] ?? '');
    $mn = trim($_POST['solo_parent_middle_name'] ?? '');
    $ln = trim($_POST['solo_parent_last_name'] ?? '');
    $sn = trim($_POST['solo_parent_suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";

    // 2) Other form inputs
    $civilStatus = $_POST['solo_parent_civil_status'] ?? '';
    $age = (int) ($_POST['solo_parent_age'] ?? 0);
    $sex = $_POST['solo_parent_sex'] ?? '';
    $purok = $_POST['solo_parent_purok'] ?? '';
    $yearsSoloParent = (int) ($_POST['solo_parent_years_solo_parent'] ?? 0);
    
    // 3) Collect children data (now an array)
    $childrenData = $_POST['children'] ?? [];
    
    // Build JSON string for children
    $childrenJson = json_encode($childrenData);
    
    $purpose = trim($_POST['solo_parent_purpose'] ?? '');
    $paymentMethod = 'Over-the-Counter';
    $documentStatus = 'For Verification';

    // 4) Generate next transaction_id
    $stmt = $conn->prepare("SELECT transaction_id FROM solo_parent_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $lastTid = $res->fetch_assoc()['transaction_id'];
      $num = intval(substr($lastTid, 3)) + 1;
    } else {
      $num = 1;
    }
    $transactionId = sprintf('CSP-%07d', $num); // SP
    $stmt->close();

    // 5) Insert into solo_parent_requests with children as JSON
    // 12 placeholders: account_id, transaction_id, full_name, civil_status, age, sex, purok, years_solo_parent, children_data, purpose, payment_method, document_status
    $sql = "INSERT INTO solo_parent_requests (account_id, transaction_id, full_name, civil_status, age, sex, purok, years_solo_parent, children_data, purpose, payment_method, claim_date, document_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,?)";
    $ins = $conn->prepare($sql);
    // Type string: i=integer, s=string
    // 12 parameters total: i s s s i s s i s s s s
    $ins->bind_param('isssississss', 
        $userId,           // i - account_id
        $transactionId,    // s - transaction_id
        $fullName,         // s - full_name
        $civilStatus,      // s - civil_status
        $age,              // i - age
        $sex,              // s - sex
        $purok,            // s - purok
        $yearsSoloParent,  // i - years_solo_parent
        $childrenJson,     // s - children_data
        $purpose,          // s - purpose
        $paymentMethod,    // s - payment_method
        $documentStatus    // s - document_status
    );
    $ins->execute();
    $ins->close();

    // 6) ACTIVITY LOGGING
    $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer', 'Lupon Tagapamayapa'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
      $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
      $admin_id = $_SESSION['loggedInUserID'];
      $role = $_SESSION['loggedInUserRole'];
      $action = 'CREATE';
      $table_name = 'solo_parent_requests';
      $record_id = $transactionId;
      $description = 'Created Solo Parent Request';

      $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
      $logStmt->execute();
      $logStmt->close();
    }

    // 7) Redirect back to superAdminPanel
    header("Location: ../{$redirectBase}?page={$redirectPage}&transaction_id={$transactionId}");
    exit();
    break;

  case 'Barangay Clearance':
    // 1) Collect posted fields & assemble full name
    $fn = trim($_POST['clearance_first_name'] ?? '');
    $mn = trim($_POST['clearance_middle_name'] ?? '');
    $ln = trim($_POST['clearance_last_name'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $fullName = "{$ln}, {$fn}{$middlePart}";

    // 2) Other form inputs
    $street = trim($_POST['clearance_street'] ?? '');
    $purok = $_POST['clearance_purok'] ?? '';
    $barangay = $_POST['clearance_barangay'] ?? 'MAGANG';
    $municipality = $_POST['clearance_municipality'] ?? 'DAET';
    $province = $_POST['clearance_province'] ?? 'CAMARINES NORTE';
    $birthDate = $_POST['clearance_birthdate'] ?? '';
    $age = (int)($_POST['clearance_age'] ?? 0);
    $birthPlace = trim($_POST['clearance_birthplace'] ?? '');
    $maritalStatus = $_POST['clearance_marital_status'] ?? '';
    $ctcNumber = trim($_POST['clearance_ctc_number'] ?? '');
    $ctcNumber = $ctcNumber ? (int)$ctcNumber : null;
    $purpose = trim($_POST['clearance_purpose'] ?? '');
    $paymentMethod = 'Over-the-Counter';
    $documentStatus = 'For Verification';

    // 3) Handle file upload
    $pictureName = null;
    if (!empty($_FILES['clearance_photo']['name']) && $_FILES['clearance_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../barangayClearancePictures/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $orig = basename($_FILES['clearance_photo']['name']);
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $allowedExt = ['jpg','jpeg','png','gif'];
        if (in_array(strtolower($ext), $allowedExt, true)) {
            $pictureName = 'clr_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
            $target = $uploadDir . $pictureName;
            if (!move_uploaded_file($_FILES['clearance_photo']['tmp_name'], $target)) {
                error_log("Failed to move uploaded file");
                $pictureName = null;
            }
        }
    }

    // 4) Generate next transaction_id
    $stmt = $conn->prepare("SELECT transaction_id FROM barangay_clearance_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $lastTid = $res->fetch_assoc()['transaction_id'];
      $num = intval(substr($lastTid, 4)) + 1;
    } else {
      $num = 1;
    }
    $transactionId = sprintf('CLR-%07d', $num);
    $stmt->close();

    // 5) Insert into barangay_clearance_requests
    $sql = "INSERT INTO barangay_clearance_requests (account_id, transaction_id, full_name, street, purok, barangay, municipality, province, birth_date, age, birth_place, marital_status, ctc_number, purpose, picture, payment_method, document_status, request_source) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Walk-In')";
    $ins = $conn->prepare($sql);
    $ins->bind_param('issssssssssssssss', $userId, $transactionId, $fullName, $street, $purok, $barangay, $municipality, $province, $birthDate, $age, $birthPlace, $maritalStatus, $ctcNumber, $purpose, $pictureName, $paymentMethod, $documentStatus);
    $ins->execute();
    $ins->close();

    // 6) Activity logging
    $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
      $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
      $admin_id = $_SESSION['loggedInUserID'];
      $role = $_SESSION['loggedInUserRole'];
      $action = 'CREATE';
      $table_name = 'barangay_clearance_requests';
      $record_id = $transactionId;
      $description = 'Created Barangay Clearance Request';
      $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
      $logStmt->execute();
      $logStmt->close();
    }

    // 7) Redirect back
    header("Location: ../{$redirectBase}?page={$redirectPage}&transaction_id={$transactionId}");
    exit();
    break;

  case 'Business Clearance':
    // 1) Collect posted fields & assemble full name
    $fn = trim($_POST['business_first_name'] ?? '');
    $mn = trim($_POST['business_middle_name'] ?? '');
    $ln = trim($_POST['business_last_name'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $fullName = "{$ln}, {$fn}{$middlePart}";

    // 2) Other form inputs
    $purok = $_POST['business_purok'] ?? '';
    $barangay = $_POST['business_barangay'] ?? 'MAGANG';
    $municipality = $_POST['business_municipality'] ?? 'DAET';
    $province = $_POST['business_province'] ?? 'CAMARINES NORTE';
    $age = (int)($_POST['business_age'] ?? 0);
    $maritalStatus = $_POST['business_marital_status'] ?? '';
    $businessName = trim($_POST['business_name'] ?? '');
    $businessType = trim($_POST['business_type'] ?? '');
    $businessAddress = trim($_POST['business_address'] ?? '');
    $ctcNumber = trim($_POST['business_ctc_number'] ?? '');
    $ctcNumber = $ctcNumber ? (int)$ctcNumber : 0;
    $paymentMethod = 'Over-the-Counter';
    $documentStatus = 'For Verification';

    // 3) Handle file upload
    $pictureName = null;
    if (!empty($_FILES['business_photo']['name']) && $_FILES['business_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../businessClearancePictures/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $orig = basename($_FILES['business_photo']['name']);
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $allowedExt = ['jpg','jpeg','png','gif'];
        if (in_array(strtolower($ext), $allowedExt, true)) {
            $pictureName = 'bc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
            $target = $uploadDir . $pictureName;
            if (!move_uploaded_file($_FILES['business_photo']['tmp_name'], $target)) {
                error_log("Failed to move uploaded file");
                $pictureName = null;
            }
        }
    }

    // 4) Generate next transaction_id
    $stmt = $conn->prepare("SELECT transaction_id FROM business_clearance_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $lastTid = $res->fetch_assoc()['transaction_id'];
      $num = intval(substr($lastTid, 4)) + 1;
    } else {
      $num = 1;
    }
    $transactionId = sprintf('BUS-%07d', $num);
    $stmt->close();

    // 5) Insert into business_clearance_requests
    $sql = "INSERT INTO business_clearance_requests (account_id, transaction_id, full_name, purok, barangay, municipality, province, age, marital_status, business_name, business_type, address, ctc_number, picture, payment_method, document_status, request_source) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Walk-In')";
    $ins = $conn->prepare($sql);
    $ins->bind_param('issssssissssisss', $userId, $transactionId, $fullName, $purok, $barangay, $municipality, $province, $age, $maritalStatus, $businessName, $businessType, $businessAddress, $ctcNumber, $pictureName, $paymentMethod, $documentStatus);
    $ins->execute();
    $ins->close();

    // 6) Activity logging
    $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
      $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
      $admin_id = $_SESSION['loggedInUserID'];
      $role = $_SESSION['loggedInUserRole'];
      $action = 'CREATE';
      $table_name = 'business_clearance_requests';
      $record_id = $transactionId;
      $description = 'Created Business Clearance Request';
      $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
      $logStmt->execute();
      $logStmt->close();
    }

    // 7) Redirect back
    header("Location: ../{$redirectBase}?page={$redirectPage}&transaction_id={$transactionId}");
    exit();
    break;

  case 'First Time Job Seeker':
    // 1) Collect posted fields & assemble full name
    $fn = trim($_POST['first_time_job_seeker_first_name'] ?? '');
    $mn = trim($_POST['first_time_job_seeker_middle_name'] ?? '');
    $ln = trim($_POST['first_time_job_seeker_last_name'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $fullName = "{$ln}, {$fn}{$middlePart} "; // Note: different format for job seeker based on DB

    // 2) Other form inputs
    $age = (int)($_POST['first_time_job_seeker_age'] ?? 0);
    $civilStatus = $_POST['first_time_job_seeker_civil_status'] ?? '';
    $purok = $_POST['first_time_job_seeker_purok'] ?? '';
    $documentStatus = 'For Verification';

    // 3) Generate next transaction_id
    $stmt = $conn->prepare("SELECT transaction_id FROM job_seeker_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $lastTid = $res->fetch_assoc()['transaction_id'];
      $num = intval(substr($lastTid, 4)) + 1;
    } else {
      $num = 1;
    }
    $transactionId = sprintf('FJS-%07d', $num);
    $stmt->close();

    // 4) Insert into job_seeker_requests (no claim_date, claim_time yet - Walk-In)
    $sql = "INSERT INTO job_seeker_requests (account_id, transaction_id, full_name, age, civil_status, purok, payment_status, document_status, request_source) VALUES (?,?,?,?,?,?,'Free of Charge',?,'Walk-In')";
    $ins = $conn->prepare($sql);
    $ins->bind_param('issssss', $userId, $transactionId, $fullName, $age, $civilStatus, $purok, $documentStatus);
    $ins->execute();
    $ins->close();

    // 5) Activity logging
    $admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
      $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");
      $admin_id = $_SESSION['loggedInUserID'];
      $role = $_SESSION['loggedInUserRole'];
      $action = 'CREATE';
      $table_name = 'job_seeker_requests';
      $record_id = $transactionId;
      $description = 'Created First Time Job Seeker Request';
      $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
      $logStmt->execute();
      $logStmt->close();
    }

    // 6) Redirect back
    header("Location: ../{$redirectBase}?page={$redirectPage}&transaction_id={$transactionId}");
    exit();
    break;

  default:
    die("Unknown request type: {$requestType}");
}

$conn->close();
exit;
?>
