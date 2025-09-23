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

    // 1) Collect posted fields
    $fn = trim($_POST['barangay_id_first_name'] ?? '');
    $mn = trim($_POST['barangay_id_middle_name'] ?? '');
    $ln = trim($_POST['barangay_id_last_name'] ?? '');
    $sn = trim($_POST['barangay_id_suffix'] ?? '');
    $suffixPart = $sn ? " {$sn}" : '';
    $middlePart = $mn ? " {$mn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";

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
    if (!empty($_FILES['barangay_id_photo']['name']) && $_FILES['barangay_id_photo']['error'] === UPLOAD_ERR_OK) {
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
    $prefix = 'BRGYID-';
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
    $sn = trim($_POST['good_moral_suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    // $fullName = "{$ln}, {$fn}, {$middlePart}, {$suffixPart}";
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";
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
    $transactionId = sprintf('GM-%07d', $num);
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
    // $fullName = trim($_POST['full_name'] ?? '');

    // $civilStatus = $_POST['guardian_civil_status'] ?? '';
    $civilStatus = $_POST['guardianship_civil_status'] ?? '';
    // $age = (int)($_POST['guardian_age'] ?? 0);
    $age = (int)($_POST['guardianship_age'] ?? 0);
    // $purok = $_POST['guardian_purok'] ?? '';
    $purok = $_POST['guardianship_purok'] ?? '';

    // Child's Name
    // $fnChild = trim($_POST['child_first_name'] ?? '');
    // $mnChild = trim($_POST['child_middle_name'] ?? '');
    // $lnChild = trim($_POST['child_last_name'] ?? '');
    // $snChild = trim($_POST['child_suffix'] ?? '');
    // $middlePartChild = $mnChild ? " {$mnChild}" : '';
    // $suffixPartChild = $snChild ? " {$snChild}" : '';
    // $fullNameChild = "{$lnChild}{$suffixPartChild}, {$fnChild}{$middlePartChild}";
    $childName = trim($_POST['child_full_name'] ?? '');

    $purpose = trim($_POST['guardianship_purpose'] ?? '');
    $paymentMethod = 'Over-the-Counter';
    // $documentStatus = 'Processing';
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
    
    // 3) Insert into guardianship_request
    $sql = "INSERT INTO guardianship_requests (account_id, transaction_id, full_name, civil_status, age, purok, child_name, purpose, claim_date, payment_method, document_status) VALUES (?,?,?,?,?,?,?,?,NULL,?,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('isssisssss', $userId, $transactionId, $fullName, $civilStatus, $age, $purok, $childName, $purpose, $paymentMethod, $documentStatus);
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
    // $fullName = trim($_POST['full_name'] ?? '');

    // 2) Other form inputs
    $civilStatus = $_POST['solo_parent_civil_status'] ?? '';
    $age = (int) ($_POST['solo_parent_age'] ?? 0);
    // $barangay = $_POST['barangay'] ?? '';
    $purok = $_POST['solo_parent_purok'] ?? '';
    // $subdivision = trim($_POST['subdivision'] ?? '');
    $yearsSoloParent = (int) ($_POST['solo_parent_years_solo_parent'] ?? 0);

    // Child Name
    $fnChild = trim($_POST['solo_parent_child_first_name'] ?? '');
    $mnChild = trim($_POST['solo_parent_child_middle_name'] ?? '');
    $lnChild = trim($_POST['solo_parent_child_last_name'] ?? '');
    $snChild = trim($_POST['solo_parent_child_suffix'] ?? '');
    $middlePartChild = $mnChild ? " {$mnChild}" : '';
    $suffixPartChild = $snChild ? " {$snChild}" : '';
    $fullNameChild = "{$lnChild}{$suffixPartChild}, {$fnChild}{$middlePartChild}";
    // $childName = trim($_POST['child_name'] ?? '');

    $childSex = $_POST['solo_parent_child_sex'] ?? '';
    // $childAge = (int) ($_POST['child_age'] ?? 0);
    $childAge = trim($_POST['solo_parent_child_age'] ?? '');
    $purpose = trim($_POST['solo_parent_purpose'] ?? '');
    $paymentMethod = 'Over-the-Counter';
    // $documentStatus = 'Processing';
    $documentStatus = 'For Verification';
    // $claimDate = $_POST['claim_date'] ?? '';

    // 3) Generate next transaction_id
    $stmt = $conn->prepare("SELECT transaction_id FROM solo_parent_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $lastTid = $res->fetch_assoc()['transaction_id'];
      $num = intval(substr($lastTid, 3)) + 1;
    } else {
      $num = 1;
    }
    $transactionId = sprintf('SP-%07d', $num);
    $stmt->close();

    // 4) Insert into solo_parent_requests
    $sql = "INSERT INTO solo_parent_requests (account_id, transaction_id, full_name, civil_status, age, purok, years_solo_parent, child_name, child_age, child_sex, purpose, payment_method, claim_date, document_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NULL,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('isssisisissss', $userId, $transactionId, $fullName, $civilStatus, $age, $purok, $yearsSoloParent, $fullNameChild, $childAge, $childSex, $purpose, $paymentMethod, $documentStatus);
    $ins->execute();
    $ins->close();

    // 5) ACTIVITY LOGGING
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

    // 6) Redirect back to superAdminPanel
    header("Location: ../{$redirectBase}?page={$redirectPage}&transaction_id={$transactionId}");
    exit();
    break;

  default:
    die("Unknown request type: {$requestType}");
}

$conn->close();
exit;
?>
