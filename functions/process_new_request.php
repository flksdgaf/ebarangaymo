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
} else {
    $redirectBase = 'adminPanel.php';
}

$userId = $_SESSION['loggedInUserID'];
$requestType = $_POST['request_type'] ?? '';

switch($requestType) {
  case 'Barangay ID':
    // 1) Collect posted fields
    $fn = trim($_POST['first_name'] ?? '');
    $mn = trim($_POST['middle_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    $sn = trim($_POST['suffix'] ?? '');

    // Build the optional pieces
    $suffixPart = $sn ? " {$sn}" : '';
    $middlePart = $mn ? " {$mn}" : '';
    $transactionType = $_POST['transaction_type'];
    
    // Assemble full name as “Last, First Middle Suffix”
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";
    $purok = $_POST['purok'];
    $birthDate = $_POST['dob'];
    $birthPlace = "{$_POST['birth_municipality']}, {$_POST['birth_province']}";
    $civilStatus = $_POST['civil_status'];
    $religion = $_POST['religion'] === 'Other' ? $_POST['religion_other'] : $_POST['religion'];
    $height = (float)$_POST['height_ft'];
    $weight = (float)$_POST['weight_kg'];
    $contactPerson = $_POST['emergency_name'];
    $contactNo = $_POST['emergency_phone'];
    $claimDate = $_POST['claim_date'];
    $paymentMethod = $_POST['payment_method'];

    // 2) Handle file upload
    $formalPicName = null;
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
      $uploadDir    = __DIR__ . '/../barangayIDpictures/';
      if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
      $formalPicName = uniqid() . '_' . basename($_FILES['photo']['name']);
      move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $formalPicName);
    }

    // 3) Generate next transaction_id
    $stmt = $conn->prepare("SELECT transaction_id FROM barangay_id_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $lastTid = $res->fetch_assoc()['transaction_id'];
      $num = intval(substr($lastTid, 7)) + 1;
    } else {
      $num = 1;
    }
    $transactionId = sprintf('BRGYID-%07d', $num);
    $stmt->close();

    // 4) Insert into barangay_id_requests
    $sql = "INSERT INTO barangay_id_requests (account_id, transaction_id, transaction_type, full_name, purok, birth_date, birth_place, civil_status, religion, height, weight, emergency_contact_person, emergency_contact_number, formal_picture, claim_date, payment_method) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('issssssssddsssss', $userId, $transactionId, $transactionType, $fullName, $purok, $birthDate, $birthPlace, 
    $civilStatus, $religion, $height, $weight, $contactPerson, $contactNo, $formalPicName, $claimDate, $paymentMethod);
    $ins->execute();
    $ins->close();

    // ACTIVITY LOGGING 
    $admin_roles = ['SuperAdmin','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Staff'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, role, action, table_name, record_id, description) VALUES (?,?,?,?,?,?)");    
        $admin_id = $_SESSION['loggedInUserID'];
        $role = $_SESSION['loggedInUserRole'];     
        $action = 'CREATE';
        $table_name = 'barangay_id_requests';
        $record_id = $transactionId;
        $description = 'Created Barangay ID Request';

        $logStmt->bind_param('isssss', $admin_id, $role, $action, $table_name, $record_id, $description);
        $logStmt->execute();
        $logStmt->close();
    }
    
    // 5) Redirect back to the appropriate panel
    header("Location: ../{$redirectBase}?page=superAdminRequest&transaction_id={$transactionId}");
    exit();
    break;

  case 'Business Permit':
    // 1) Collect posted fields (owner name)
    $fn = trim($_POST['first_name'] ?? '');
    $mn = trim($_POST['middle_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    $sn = trim($_POST['suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";

    // 2) Other form inputs
    $transactionType = $_POST['transaction_type'] ?? '';
    $purok = $_POST['purok'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $age = (int)$_POST['age'] ?? 0;
    $civilStatus = $_POST['civil_status'] ?? '';
    $businessName = trim($_POST['name_of_business'] ?? '');
    $businessType = trim($_POST['type_of_business'] ?? '');
    $fullAddress = trim($_POST['full_address'] ?? '');
    $claimDate = $_POST['claim_date'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? '';
    $amount = (float)$_POST['amount'] ?? 0.0;

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
    $sql = "INSERT INTO business_permit_requests (account_id, transaction_id, transaction_type, full_name, purok, barangay, age, civil_status, name_of_business, type_of_business, full_address, claim_date, payment_method) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('isssssissssss', $userId, $transactionId, $transactionType, $fullName, $purok, $barangay, $age, $civilStatus,
    $businessName, $businessType, $fullAddress, $claimDate, $paymentMethod,);
    $ins->execute();
    $ins->close();

    // 5) Activity logging
    $admin_roles = ['SuperAdmin','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Staff'];
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
    header("Location: ../{$redirectBase}?page=superAdminRequest&transaction_id={$transactionId}");
    exit();
    break;

  case 'Good Moral':
    // 1) Collect posted fields
    $fn = trim($_POST['first_name'] ?? '');
    $mn = trim($_POST['middle_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    $sn = trim($_POST['suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";

    $civilStatus = $_POST['civil_status'] ?? '';
    $sex = $_POST['sex'] ?? '';
    $age = (int)($_POST['age'] ?? 0);
    $barangay = $_POST['barangay'] ?? '';
    $purok = $_POST['purok'] ?? '';
    $subdivision = trim($_POST['subdivision'] ?? '');
    $fullAddress = "{$subdivision}, {$purok}, {$barangay}";
    $purpose = trim($_POST['purpose'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';
    $claimDate = $_POST['claim_date'] ?? '';

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
    $sql = "INSERT INTO good_moral_requests (account_id, transaction_id, full_name, civil_status, sex, age, full_address, purpose, claim_date, payment_method) VALUES (?,?,?,?,?,?,?,?,?,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('issssissss', $userId, $transactionId, $fullName, $civilStatus, $sex, $age, $fullAddress, $purpose, $claimDate, $paymentMethod);
    $ins->execute();
    $ins->close();

    // 4) Activity logging
    $admin_roles = ['SuperAdmin','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Staff'];
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
    header("Location: ../{$redirectBase}?page=superAdminRequest&transaction_id={$transactionId}");
    exit();
    break;

  case 'Guardianship':
    // 1) Collect posted fields & assemble full name
    $fn = trim($_POST['guardian_first_name'] ?? '');
    $mn = trim($_POST['guardian_middle_name'] ?? '');
    $ln = trim($_POST['guardian_last_name'] ?? '');
    $sn = trim($_POST['guardian_suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";

    $civilStatus = $_POST['guardian_civil_status'] ?? '';
    $age = (int)($_POST['guardian_age'] ?? 0);
    $purok = $_POST['guardian_purok'] ?? '';
    $childName = trim($_POST['child_name'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $claimDate = $_POST['claim_date'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? '';

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
    $sql = "INSERT INTO guardianship_requests (account_id, transaction_id, full_name, civil_status, age, purok, child_name, purpose, claim_date, payment_method) VALUES (?,?,?,?,?,?,?,?,?,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('isssisssss', $userId, $transactionId, $fullName, $civilStatus, $age, $purok, $childName, $purpose, $claimDate, $paymentMethod);
    $ins->execute();
    $ins->close();

    // 4) Activity logging
    $admin_roles = ['SuperAdmin','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Staff'];
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
    header("Location: ../{$redirectBase}?page=superAdminRequest&transaction_id={$transactionId}");
    exit();
    break;

  case 'Indigency':
    // 1) Collect posted fields & assemble full name
    $fn = trim($_POST['first_name'] ?? '');
    $mn = trim($_POST['middle_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    $sn = trim($_POST['suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";

    // 2) Other form inputs
    $civilStatus = $_POST['civil_status'] ?? '';
    $age = (int) ($_POST['age'] ?? 0);
    $barangay = $_POST['barangay'] ?? '';
    $purok = $_POST['purok'] ?? '';
    $subdivision = trim($_POST['subdivision'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $claimDate = $_POST['claim_date'] ?? '';

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
    $sql = "INSERT INTO indigency_requests (account_id, transaction_id, full_name, civil_status, age, purok, purpose, claim_date) VALUES (?,?,?,?,?,?,?,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('isssisss', $userId, $transactionId, $fullName, $civilStatus, $age, $purok, $purpose, $claimDate);
    $ins->execute();
    $ins->close();

    // 5) ACTIVITY LOGGING
    $admin_roles = ['SuperAdmin','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Staff'];
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
    header("Location: ../{$redirectBase}?page=superAdminRequest&transaction_id={$transactionId}");
    exit();
    break;

  case 'Residency':
    // 1) Collect posted fields & assemble full name
    $fn = trim($_POST['first_name'] ?? '');
    $mn = trim($_POST['middle_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    $sn = trim($_POST['suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";

    // 2) Other form inputs
    $civilStatus = $_POST['civil_status'] ?? '';
    $age = (int) ($_POST['age'] ?? 0);
    $barangay = $_POST['barangay'] ?? '';
    $purok = $_POST['purok'] ?? '';
    $subdivision = trim($_POST['subdivision'] ?? '');
    $yearsResiding = (int) ($_POST['residing_years'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';
    $claimDate = $_POST['claim_date'] ?? '';

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
    $sql = "INSERT INTO residency_requests (account_id, transaction_id, full_name, civil_status, age, purok, residing_years, purpose, payment_method, claim_date) VALUES (?,?,?,?,?,?,?,?,?,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('isssisisss', $userId, $transactionId, $fullName, $civilStatus, $age, $purok, $yearsResiding, $purpose, $paymentMethod, $claimDate);
    $ins->execute();
    $ins->close();

    // 5) ACTIVITY LOGGING
    $admin_roles = ['SuperAdmin','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Staff'];
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
    header("Location: ../{$redirectBase}?page=superAdminRequest&transaction_id={$transactionId}");
    exit();
    break;

  case 'Solo Parent':
    // 1) Collect posted fields & assemble full name
    $fn = trim($_POST['first_name'] ?? '');
    $mn = trim($_POST['middle_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    $sn = trim($_POST['suffix'] ?? '');
    $middlePart = $mn ? " {$mn}" : '';
    $suffixPart = $sn ? " {$sn}" : '';
    $fullName = "{$ln}{$suffixPart}, {$fn}{$middlePart}";

    // 2) Other form inputs
    $civilStatus = $_POST['civil_status'] ?? '';
    $age = (int) ($_POST['age'] ?? 0);
    $barangay = $_POST['barangay'] ?? '';
    $purok = $_POST['purok'] ?? '';
    $subdivision = trim($_POST['subdivision'] ?? '');
    $yearsSoloParent = (int) ($_POST['years_solo_parent'] ?? 0);
    $childName = trim($_POST['child_name'] ?? '');
    $childSex = $_POST['child_sex'] ?? '';
    $childAge = (int) ($_POST['child_age'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';
    $claimDate = $_POST['claim_date'] ?? '';

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
    $sql = "INSERT INTO solo_parent_requests (account_id, transaction_id, full_name, civil_status, age, purok, years_solo_parent, child_name, child_age, purpose, payment_method, claim_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
    $ins = $conn->prepare($sql);
    $ins->bind_param('isssisisisss', $userId, $transactionId, $fullName, $civilStatus, $age, $purok, $yearsSoloParent, $childName, $childAge, $purpose, $paymentMethod, $claimDate);
    $ins->execute();
    $ins->close();

    // 5) ACTIVITY LOGGING
    $admin_roles = ['SuperAdmin','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Staff'];
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
    header("Location: ../{$redirectBase}?page=superAdminRequest&transaction_id={$transactionId}");
    exit();
    break;

  default:
    die("Unknown request type: {$requestType}");
}

$conn->close();
exit;
?>
