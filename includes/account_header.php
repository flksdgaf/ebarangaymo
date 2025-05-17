<?php
if (!isset($page)) {
    $page = $_GET['page'] ?? 'adminDashboard';
}
require 'functions/dbconn.php';
session_start();

$pageTitles = [
    // Admin
    'adminDashboard' => 'Dashboard',
    'adminRequest' => 'Service Requests',
    'Blotter' => 'Blotter Records',
    'adminSummon' => 'Summon Records',
    'adminKatarungangPambarangay' => 'Katarungang Pambarangay',
    'adminResidents' => 'Residents Records',
    'adminVerifications' => 'Account Verifications',
    'adminTransactions' => 'Transaction History',
    'adminWebsite' => 'Website Configuration',
    'adminDeviceStatus' => 'Device Status',
    'adminLogs' => 'Activity Logs',
    
    // Super Admin
    'superAdminDashboard' => 'Dashboard',
    'superAdminRequest' => 'Service Requests',
    'superAdminBlotter' => 'Blotter Records',
    'superAdminSummon' => 'Summon',
    'superAdminKatarungangPambarangay' => 'Katarungang Pambarangay',
    'superAdminResidents' => 'Residents Records',
    'superAdminVerifications' => 'Account Verifications',
    'superAdminTransactions' => 'Transaction History', 
    'superAdminLogs' => 'Activity Logs',
    'superAdminWebsite' => 'Website Management',
    'superAdminDeviceStatus' => 'Device Status',
    'superAdminSettings' => 'Settings'
];

$topbarText = $pageTitles[$page] ?? 'Dashboard';

// Ensure the user is authenticated.
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: index.php");
    exit();
}

// Get the user's account id from session.
$userId = $_SESSION['loggedInUserID'];

// Query user_profiles to retrieve the profile picture.
$query = "SELECT full_name, profilePic FROM user_profiles WHERE account_id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$profilePic = "profilePictures/default_profile_pic.png"; // Default image if no record is found.
$fullName = "User"; // Default name if no record is found.

if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $profilePic = "profilePictures/" . $row['profilePic'];
    $fullName = $row['full_name'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - eBarangayMo</title>
    <!-- CUSTOM CSS -->
    <link rel="stylesheet" href="panels.css">
    <link rel="stylesheet" href="includes/sidebar.css">
    <!-- GOOGLE FONTS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- FULL CALENDAR -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous" defer></script> -->
    <!-- FONT AWESOME -->
    <script src="https://kit.fontawesome.com/e30afd7a6b.js" crossorigin="anonymous"></script>

</head>
<body>
    <nav class="navbar navbar-expand-lg border-bottom px-3 py-2 top-bar">
        <div class="container-fluid d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 topbar-title">
                <!-- <img src="images/good_governance_logo.png" alt="Good Governance Logo" style="width: 40px; height: 40px; object-fit: contain;">
                <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 40px; height: 40px; object-fit: contain;"> -->
                <span class="fw-bold topbar-text">
                    <?php echo htmlspecialchars($topbarText); ?></span>
                </span>
            </div>
            <div class="d-none d-md-block flex-grow-1"></div>
            <div class="dropdown">
                <img src="<?php echo htmlspecialchars($profilePic); ?>" class="rounded-circle" width="40" height="40" style="object-fit: cover; margin-right: 8px; border: 2px solid #000000;">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <!-- Text for large screens -->
                    <span class="d-none d-md-inline"><?php echo htmlspecialchars($fullName); ?> - <?php echo htmlspecialchars($_SESSION['loggedInUserRole']); ?></span>
                    <!-- Icon for smaller screens -->
                    <span class="d-md-none icon"><i class="fas fa-user"></i></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                    <?php if (isset($_SESSION['loggedInUserRole']) && $_SESSION['loggedInUserRole'] === 'Barangay Captain'): ?>
                        <li>
                            <a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item" href="functions/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
