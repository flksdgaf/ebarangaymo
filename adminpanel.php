<?php 
$page = isset($_GET['page']) ? $_GET['page'] : 'adminDashboard'; 

$valid_pages = [
    'adminDashboard',
    'adminRequest',
    'adminBlotter',
    'adminResidents',
    'adminWebsite',
    'adminUsers',
    'adminTransaction',
    'adminLogs',
    'adminAccount',
    'adminSettings'
];

if (!in_array($page, $valid_pages)) {
    $page = 'adminDashboard'; 
}

include 'functions/dbconn.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - eBarangayMo</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="adminpanel.css">
    <link rel="stylesheet" href="forms.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/e30afd7a6b.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php 
    $current = isset($_GET['page']) ? $_GET['page'] : 'adminDashboard';
    include 'includes/sidebar.php'; 
    ?>
    
    <div class="container-main">
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-left">
                    <div class="admin-logo">
                        <img src="images/good_governance_logo.png" alt="Good Governance Logo">
                        <img src="images/magang_logo.png" alt="Barangay Magang Logo">
                    </div>
                    <h4 class="brand-name">eBarangay Mo</h4>
                </div>

                <div class="topbar-right">
                    <img src="images/magang_logo.png" alt="Admin Profile" class="profile-pic">
                    
                    <button class="username-btn">
                        <span class="username">Admin1</span>
                        <span class="material-symbols-outlined arrow">expand_more</span>
                    </button>

                    <div class="dropdown-menu-admin">
                        <a href="#" class="admin-nav-link" data-page="#">My Profile</a>
                    </div>
                </div>
            </div>
                
            <div id="content-wrapper">
                <?php include "$page.php"; ?>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
