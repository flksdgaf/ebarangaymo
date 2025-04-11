<?php
if (!isset($page)) {
    $page = 'default'; // Default page
}
require 'functions/dbconn.php';
session_start();

// Ensure the user is authenticated.
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("Location: index.php");
    exit();
}

// Get the user's account id from session.
$userId = $_SESSION['loggedInUser'];

// Query user_profiles to retrieve the profile name and picture.
$query = "SELECT profilePic FROM user_profiles WHERE account_id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$profilePic = "profilePictures/default_profile_pic.png"; // Default image if no record is found.
if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    // Use the full path to the profile picture directory.
    $profilePic = "profilePictures/" . $row['profilePic'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Barangay Mo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" >
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/e30afd7a6b.js" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="<?php echo $page; ?>">

<!-- NAVIGATION SECTION -->
<header>
    <div class="container-fluid bg-body-tertiary fixed-top">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="#">
                    <img src="images/republic_seal.png" alt="Republic Seal of the Philippines" width="36" height="36">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        
                        <li class="nav-item me-3">
                            <a class="nav-link active" href="#">Home</a>
                        </li>
                        <li class="nav-item dropdown me-3">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                About
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Officials</a></li>
                                <li><a class="dropdown-item" href="#">Mission, Vision and Values</a></li>
                                <li><a class="dropdown-item" href="#">Citizens Charter</a></li>
                                <li><a class="dropdown-item" href="#">Map</a></li>
                                <li><a class="dropdown-item" href="#">Contact Us</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown me-3">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                Services
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Barangay ID</a></li>
                                <li><a class="dropdown-item" href="#">Barangay Clearance</a></li>
                                <li><a class="dropdown-item" href="#">Certification</a></li>
                                <li><a class="dropdown-item" href="#">Business Permit</a></li>
                                <li><a class="dropdown-item" href="#">Katarungang Pambarangay</a></li>
                                <li><a class="dropdown-item" href="#">Environmental Services</a></li>
                            </ul>
                        </li>
                        <li class="nav-item me-3">
                            <a class="nav-link" href="#">Transparency Seal</a>
                        </li>
                        <li class="nav-item me-3">
                            <a class="nav-link" href="#">My Requests</a>
                        </li>
                    </ul>

                    <!-- Right side of the navbar -->
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <!-- Notification icon can be here if needed -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="profile" style="width:32px; height:32px; border-radius:50%; border:2px solid black; object-fit:cover;">
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                                <li><a class="dropdown-item" href="#">My Profile</a></li>
                                <li><a class="dropdown-item" href="#">Settings</a></li>
                                <li><a class="dropdown-item" href="#">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>
</header>
