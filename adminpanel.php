<?php 
$page = 'admin'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - eBarangayMo</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="admincontent.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Top Bar -->
        <!-- <div class="top-bar">
            <div class="logo-section">
                <img src="images/republic_seal.png" alt="Logo" class="logo">
                <h1>eBarangay Mo</h1>
            </div>
            <div class="admin-profile-section">
                <div class="admin-img-container">
                    <img src="images/admin-profile.jpg" alt="Admin" class="admin-img">
                </div>
                <span class="admin-name">Admin Name</span>
            </div>
        </div> -->

        <div class="main-content">
            <div id="content-wrapper">
                <!-- Content will be loaded here via JS -->
                <h1>Welcome to the Admin Panel</h1>
            </div>
        </div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
