<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Barangay Mo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
    <!-- <link rel="stylesheet" href="about.css"> -->
    <!-- <link rel="stylesheet" href="signinup.css"> -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <script src="https://kit.fontawesome.com/e30afd7a6b.js" crossorigin="anonymous"></script>
</head>

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
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    
                    <!-- About Dropdown -->
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle" href="about.php" id="aboutDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">About</a>
                            <ul class="dropdown-menu" aria-labelledby="aboutDropdown">
                                <li><a class="dropdown-item" href="about.php#officials">Officials</a></li>
                                <li><a class="dropdown-item" href="about.php#mission-vision">Mission and Vision </a></li>
                                <li><a class="dropdown-item" href="about.php#citizens-charter">Citizen's Charter</a></li>
                                <li><a class="dropdown-item" href="about.php#barangay-map">Barangay Map</a></li>
                                <li><a class="dropdown-item" href="about.php#contact-us">Contact Us</a></li>
                            </ul>
                    </li>
                    
                    <!-- Services Dropdown -->
                    <!-- <li class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle" href="services.php" id="servicesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Services</a>
                            <ul class="dropdown-menu" aria-labelledby="servicesDropdown">
                                <li><a class="dropdown-item" href="serviceBarangayID.php">Barangay ID</a></li>
                                <li><a class="dropdown-item" href="#">Barangay Clearance</a></li>
                                <li><a class="dropdown-item" href="#">Certification</a></li>
                                <li><a class="dropdown-item" href="#">Business Permit</a></li>
                                <li><a class="dropdown-item" href="#">Katarungang Pambarangay</a></li>
                                <li><a class="dropdown-item" href="#">Environmental Services</a></li>
                            </ul>
                    </li> -->

                    <li class="nav-item me-3">
                        <a class="nav-link" href="services.php">Services</a>
                    </li>

                    <!-- Transparency Seal -->                
                    <li class="nav-item me-3">
                        <a class="nav-link" href="transparencyseal.php">Transparency Seal</a>
                    </li>
                </ul>

                <?php if ($page == 'index') { ?>
                    <a class="btn custom-signin-btn" href="signin.php">Get Started</a>
                <?php } ?>
            </div>
        </nav>
    </div>
</header>


<!-- Bootstrap JS Bundle placed at the end of the body -->
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->
