<?php
if (!isset($page)) {
    $page = $_GET['page'] ?? 'adminDashboard';
}
require 'functions/dbconn.php';
session_start();

$pageTitles = [
    // Admin
    'adminDashboard' => 'Dashboard',
    'adminRequest' => 'Service Records',
    'adminEquipmentBorrowing' => 'Equipment Borrowing',
    'adminBlotter' => 'Blotter Records',
    'adminSummon' => 'Summon Records',
    'adminComplaints' => 'Complaint Records',
    'adminKatarungangPambarangay' => 'Katarungang Pambarangay',
    'adminResidents' => 'Residents Records',
    'adminVerifications' => 'Account Verifications',
    'adminTransactions' => 'Transaction Reports',
    'adminHistory' => 'Transaction History',
    'adminLogs' => 'Activity Logs',
    'adminWebsite' => 'Website Configuration',
    'adminDeviceStatus' => 'Device Status',
    
    // Super Admin
    'superAdminDashboard' => 'Dashboard',
    'superAdminRequest' => 'Service Records',
    'superAdminBlotter' => 'Blotter Records',
    'superAdminSummon' => 'Summon Records',
    'superAdminComplaints' => 'Complaint Records',
    'superAdminKatarungangPambarangay' => 'Katarungang Pambarangay',
    'superAdminResidents' => 'Residents Records',
    'superAdminVerifications' => 'Account Verifications',
    'superAdminTransactions' => 'Transaction Reports', 
    'superAdminLogs' => 'Activity Logs',
    'superAdminWebsite' => 'Website Configuration',
    'superAdminDeviceStatus' => 'Device Status',
    'superAdminSettings' => 'Settings',
    'superAdminPanelSettings' => 'Admin Settings',

    // User 
    'userDashboard' => 'Home',
    'userRequest' => 'My Requests',
    'userServices' => 'Barangay Services',
    'userSettings' => 'Account Settings',
    'userTransactions' => 'Transaction History',
    'serviceBarangayID' => 'Barangay ID',
    'serviceCertification' => 'Certification',
    'serviceBusinessPermit' => 'Business Permit',
    'serviceEquipmentBorrowing' => 'Equipment Borrowing'
];

$topbarText = $pageTitles[$page] ?? 'Home';

// Ensure the user is authenticated.
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: index.php");
    exit();
}

// Get the user's account id from session.
$userId = $_SESSION['loggedInUserID'];

// Try to find the logged‐in account in any purokX_rbi table:
$sql = "
  SELECT full_name, profile_picture
  FROM purok1_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, profile_picture
  FROM purok2_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, profile_picture
  FROM purok3_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, profile_picture
  FROM purok4_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, profile_picture
  FROM purok5_rbi WHERE account_ID = ?
  UNION ALL
  SELECT full_name, profile_picture
  FROM purok6_rbi WHERE account_ID = ?
  LIMIT 1
";

$stmt = $conn->prepare($sql);
if ($stmt) {
    // bind the same $userId to each placeholder
    $stmt->bind_param("iiiiii",
        $userId, $userId, $userId,
        $userId, $userId, $userId
    );
    $stmt->execute();
    $result = $stmt->get_result();
}

$profilePic = "profilePictures/default_profile_pic.png";
$fullName = "User";

// if we found a record in one of the purok tables, use it
if (isset($result) && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    if (!empty($row['profile_picture'])) {
        $profilePic = "profilePictures/" . $row['profile_picture'];
    }
    $fullName = $row['full_name'];
}
$stmt->close();

$role = $_SESSION['loggedInUserRole'] ?? '';

// ACESS LEVELS ( ALL ACCESS - ALL ACCESS - ALL ACCESS - VIEWING ONLY - TRANSACTIONS - KATARAUNGANG PAMBARANGAY)
$admin_roles = ['Brgy Captain', 'Brgy Secretary', 'Brgy Bookkeeper', 'Brgy Kagawad', 'Brgy Treasurer', 'Lupon Tagapamayapa'];

if (in_array($role, $admin_roles)) {
    $settingsHref = 'adminPanel.php?page=adminSettings';
} else {
    $settingsHref = 'userPanel.php?page=userSettings';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- CUSTOM CSS -->
    <link rel="stylesheet" href="panels.css">
    <link rel="stylesheet" href="panels_user.css">
    <link rel="stylesheet" href="includes/sidebar.css">
    <!-- GOOGLE FONTS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- FULL CALENDAR -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous" defer></script> -->
    <!-- FONT AWESOME -->
    <script src="https://kit.fontawesome.com/e30afd7a6b.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    

</head>
<body>
    <nav class="navbar navbar-expand-lg border-bottom px-3 py-2 top-bar" style="background-color: #C4C4C4;">
        <div class="container-fluid d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 topbar-title">
                <span class="fw-bold topbar-text">
                    <?php echo htmlspecialchars($topbarText); ?></span>
                </span>
            </div>
            <div class="d-none d-md-block flex-grow-1"></div>
            <div class="dropdown">
                <img src="../<?php echo htmlspecialchars($profilePic); ?>?v=<?php echo time() ?>" class="rounded-circle" width="40" height="40" style="object-fit: cover; margin-right: 8px; border: 2px solid #000000;">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <!-- Text for large screens -->
                    <span class="d-none d-md-inline" style="color: #212529; "><?php echo htmlspecialchars($fullName); ?> - <?php echo htmlspecialchars($_SESSION['loggedInUserRole']); ?></span>
                    <!-- Icon for smaller screens -->
                    <span class="d-md-none icon"><i class="fas fa-user"></i></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                    <li><a class="dropdown-item" href="" data-bs-toggle="modal" data-bs-target="#myProfileModal"><i class="fas fa-cog me-2"></i>My Profile</a></li>
                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars($settingsHref); ?>"><i class="fas fa-user-cog me-2"></i>Account Settings</a></li>                    
                    <li><a class="dropdown-item" href="functions/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Modal -->
    <?php
    // Fetch full profile details for the modal (including purok)
    $profileData = [
    'full_name' => 'Resident',
    'profile_picture' => 'default_profile_pic.png',
    'birthdate' => '',
    'sex' => '',
    'civil_status' => '',
    'blood_type' => '',
    'birth_registration_number' => '',
    'highest_educational_attainment' => '',
    'occupation' => '',
    'purok' => ''
    ];

    // Union across purok tables, adding a literal 'Purok X'
    $sql  = "SELECT full_name, profile_picture, birthdate, sex, civil_status, blood_type, ";
    $sql .= "birth_registration_number, highest_educational_attainment, occupation, 'Purok 1' AS purok
            FROM purok1_rbi WHERE account_ID = ?
            UNION ALL
            SELECT full_name, profile_picture, birthdate, sex, civil_status, blood_type,
                    birth_registration_number, highest_educational_attainment, occupation, 'Purok 2'
            FROM purok2_rbi WHERE account_ID = ?
            UNION ALL
            SELECT full_name, profile_picture, birthdate, sex, civil_status, blood_type,
                    birth_registration_number, highest_educational_attainment, occupation, 'Purok 3'
            FROM purok3_rbi WHERE account_ID = ?
            UNION ALL
            SELECT full_name, profile_picture, birthdate, sex, civil_status, blood_type,
                    birth_registration_number, highest_educational_attainment, occupation, 'Purok 4'
            FROM purok4_rbi WHERE account_ID = ?
            UNION ALL
            SELECT full_name, profile_picture, birthdate, sex, civil_status, blood_type,
                    birth_registration_number, highest_educational_attainment, occupation, 'Purok 5'
            FROM purok5_rbi WHERE account_ID = ?
            UNION ALL
            SELECT full_name, profile_picture, birthdate, sex, civil_status, blood_type,
                    birth_registration_number, highest_educational_attainment, occupation, 'Purok 6'
            FROM purok6_rbi WHERE account_ID = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiii", $userId, $userId, $userId, $userId, $userId, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $profileData = $row;
    }
    $stmt->close();
    ?>

    <!-- My Profile Modal -->
    <div class="modal fade" id="myProfileModal" tabindex="-1" aria-labelledby="myProfileLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
            
            <!-- Header -->
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="myProfileLabel">
                <i class="fas fa-user-circle me-2"></i>My Profile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <!-- Body -->
            <div class="modal-body">
                <div class="row">
                <!-- Profile Picture -->
                <div class="col-md-4 text-center mb-4 mb-md-0">
                    <img src="profilePictures/<?php echo htmlspecialchars($profileData['profile_picture']); ?>" class="rounded-circle border-3 border-dark" width="140" height="140" style="object-fit: cover;" alt="Profile Picture">
                </div>
                
                <!-- Details -->
                <div class="col-md-8">
                    <div class="card border-0">
                    <div class="card-body">
                        <dl class="row mb-0">
                        <?php
                        $fields = [
                            'full_name' => 'Full Name:',
                            'birthdate' => 'Birthdate:',
                            'sex' => 'Sex:',
                            'civil_status' => 'Civil Status:',
                            'blood_type' => 'Blood Type:',
                            'birth_registration_number' => 'Birth Reg. No:',
                            'highest_educational_attainment' => 'Education:',
                            'occupation' => 'Occupation:',
                            'purok' => 'Purok:'
                        ];
                        foreach ($fields as $key => $label): ?>
                            <dt class="col-sm-5 text-secondary"><?php echo $label; ?></dt>
                            <dd class="col-sm-7"><?php echo htmlspecialchars($profileData[$key]); ?></dd>
                        <?php endforeach; ?>
                        </dl>
                    </div>
                    </div>
                </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="modal-footer border-0">
                <a href="adminPanel.php?page=adminSettings" class="btn btn-success">
                <i class="fas fa-edit me-1"></i>Edit Profile
                </a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                Close
                </button>
            </div>
            
            </div>
        </div>
    </div>

