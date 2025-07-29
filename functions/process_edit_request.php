<?php
session_start();
require 'dbconn.php';

// --- Authentication & redirect config (same as new) ---
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

$userId        = $_SESSION['loggedInUserID'];
$requestType   = $_POST['request_type']     ?? '';
$transactionId = $_POST['transaction_id']   ?? '';

if (!$requestType || !$transactionId) {
    die("Missing request type or transaction ID");
}

// Map to the proper table
$mapping = [
  'Barangay ID'     => 'barangay_id_requests',
  'Business Permit' => 'business_permit_requests',
  'Good Moral'      => 'good_moral_requests',
  'Guardianship'    => 'guardianship_requests',
  'Indigency'       => 'indigency_requests',
  'Residency'       => 'residency_requests',
  'Solo Parent'     => 'solo_parent_requests',
];
$table = $mapping[$requestType] ?? null;
if (!$table) {
    die("Unknown request type: {$requestType}");
}

// Helper to log activity
function logAction($conn, $transactionId, $tableName, $description) {
    $admin_roles = ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Treasurer','Lupon Tagapamayapa'];
    if (in_array($_SESSION['loggedInUserRole'], $admin_roles, true)) {
        $stmt = $conn->prepare("
          INSERT INTO activity_logs
            (admin_id, role, action, table_name, record_id, description)
          VALUES (?, ?, 'UPDATE', ?, ?, ?)
        ");
        $stmt->bind_param(
          'issss',
          $_SESSION['loggedInUserID'],
          $_SESSION['loggedInUserRole'],
          $tableName,
          $transactionId,
          $description
        );
        $stmt->execute();
        $stmt->close();
    }
}

// --- Branch by type ---
switch ($requestType) {
  case 'Barangay ID':
    // collect form fields
    $transactionType       = $_POST['barangay_id_transaction_type'];
    $fn                    = trim($_POST['barangay_id_first_name']);
    $mn                    = trim($_POST['barangay_id_middle_name']);
    $ln                    = trim($_POST['barangay_id_last_name']);
    $sn                    = trim($_POST['barangay_id_suffix']);
    $fullName              = "{$ln}" . ($sn?" {$sn}":"") . ", {$fn}" . ($mn?" {$mn}":"");
    $purok                 = $_POST['barangay_id_purok'];
    $birthDate             = $_POST['barangay_id_dob'];
    $birthPlace            = trim($_POST['barangay_id_birth_place']);
    $civilStatus           = $_POST['barangay_id_civil_status'];
    $religion = $_POST['barangay_id_religion'] === 'Other' ? $_POST['barangay_id_religion_other'] : $_POST['barangay_id_religion'];
    $height                = (float)$_POST['barangay_id_height'];
    $weight                = (float)$_POST['barangay_id_weight'];
    $contactPerson         = trim($_POST['barangay_id_emergency_contact_person']);
    $contactAddress        = trim($_POST['barangay_id_emergency_contact_address']); // adjust name
    // handle optional photo upload
    $newPhotoName = null;
    if (!empty($_FILES['barangay_id_photo']['name']) && $_FILES['barangay_id_photo']['error']===UPLOAD_ERR_OK) {
      $uploadDir = __DIR__ . '/../barangayIDpictures/';
      if (!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
      $newPhotoName = uniqid() . '_' . basename($_FILES['barangay_id_photo']['name']);
      move_uploaded_file($_FILES['barangay_id_photo']['tmp_name'], $uploadDir.$newPhotoName);
    }

    // prepare UPDATE
    $sql = "
      UPDATE {$table}
         SET transaction_type             = ?,
             full_name                    = ?,
             purok                        = ?,
             birth_date                   = ?,
             birth_place                  = ?,
             civil_status                 = ?,
             religion                     = ?,
             height                       = ?,
             weight                       = ?,
             emergency_contact_person     = ?,
             emergency_contact_address    = ?,
             formal_picture               = COALESCE(?, formal_picture)
       WHERE transaction_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      'sssssssddssss',
      $transactionType,
      $fullName,
      $purok,
      $birthDate,
      $birthPlace,
      $civilStatus,
      $religion,
      $height,
      $weight,
      $contactPerson,
      $contactAddress,
      $newPhotoName,
      $transactionId
    );
    $stmt->execute();
    $stmt->close();

    logAction($conn, $transactionId, $table, 'Edited Barangay ID Request');
    break;

  case 'Business Permit':
    // collect your POST fields...
    $transactionType = $_POST['business_permit_transaction_type'];
    // assemble fullName etc...
    // then UPDATE business_permit_requests SET ... WHERE transaction_id = ?
    // Example skeleton:
    /*
    $sql = "
      UPDATE {$table}
         SET transaction_type       = ?,
             full_name              = ?,
             purok                  = ?,
             barangay               = ?,
             age                    = ?,
             civil_status           = ?,
             name_of_business       = ?,
             type_of_business       = ?,
             full_address           = ?
       WHERE transaction_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      'ssssisssss',
      $transactionType,
      $fullName,
      $purok,
      $barangay,
      $age,
      $civilStatus,
      $businessName,
      $businessType,
      $fullAddress,
      $transactionId
    );
    $stmt->execute();
    $stmt->close();

    logAction($conn, $transactionId, $table, 'Edited Business Permit Request');
    */
    break;

  // TODO: replicate the pattern above for each of:
  //  - Good Moral
  //  - Guardianship
  //  - Indigency
  //  - Residency
  //  - Solo Parent

  default:
    // nothing else
    break;
}

// redirect back
header("Location: ../{$redirectBase}?page={$redirectPage}&edited_id={$transactionId}");
exit;
?>
