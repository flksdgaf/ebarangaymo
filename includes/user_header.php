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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
<link rel="stylesheet" href="styles.css">   
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://kit.fontawesome.com/e30afd7a6b.js" crossorigin="anonymous"></script>

<!-- NAVIGATION SECTION -->
<header>
    <div class="container-fluid bg-body-tertiary fixed-top">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand">
                <img src="images/republic_seal.png" alt="Republic Seal of the Philippines" width="36" height="36">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <!-- Left-side links -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item me-3">
                    <a class="nav-link active" href="userHomepage.php">Home</a>
                    </li>
                    
                    <!-- About Dropdown -->
                    <li class="nav-item dropdown me-3">
                    <a class="nav-link dropdown-toggle" href="about.php" id="aboutDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        About
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="aboutDropdown">
                        <li><a class="dropdown-item" href="about.php#officials">Officials</a></li>
                        <li><a class="dropdown-item" href="about.php#mission-vision">Mission and Vision </a></li>
                        <li><a class="dropdown-item" href="about.php#citizens-charter">Citizen's Charter</a></li>
                        <li><a class="dropdown-item" href="about.php#barangay-map">Barangay Map</a></li>
                        <li><a class="dropdown-item" href="about.php#contact-us">Contact Us</a></li>
                    </ul>
                    </li>
                    
                    <!-- Services Dropdown -->
                    <li class="nav-item dropdown me-3">
                    <a class="nav-link dropdown-toggle" href="services.php" id="servicesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Services
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="servicesDropdown">
                        <li><a class="dropdown-item" href="serviceBarangayID.php">Barangay ID</a></li>
                        <li><a class="dropdown-item" href="#">Barangay Clearance</a></li>
                        <li><a class="dropdown-item" href="#">Certification</a></li>
                        <li><a class="dropdown-item" href="#">Business Permit</a></li>
                        <li><a class="dropdown-item" href="#">Katarungang Pambarangay</a></li>
                        <li><a class="dropdown-item" href="#">Environmental Services</a></li>
                    </ul>
                    </li>
                    
                    <li class="nav-item me-3">
                    <a class="nav-link" href="transparency_seal.php">Transparency Seal</a>
                    </li>
                    <li class="nav-item me-3">
                    <a class="nav-link" href="#">My Requests</a>
                    </li>
                </ul>

                <!-- Right side: Profile picture and name dropdown -->
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center profile-dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color: #13411F;">
                            <img src="<?php echo htmlspecialchars($profilePic); ?>" class="rounded-circle" width="30" height="30" style="object-fit: cover; margin-right: 8px; border: 2px solid #13411F;">
                            <span style="margin-right: 3px;"><?php echo htmlspecialchars($fullName); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="functions/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</header>


<!-- Bootstrap JS Bundle placed at the end of the body -->
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->
