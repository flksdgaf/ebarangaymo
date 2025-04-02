<?php
if (!isset($page)) {
    $page = 'default'; // Default page
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Barangay Mo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="signinup.css">
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
                    <img src="images/republicseal.png" alt="Republic Seal of the Philippines" width="36" height="36">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <?php if ($page == 'user_homepage') { ?>
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
                            <li class="nav-item">
                                <a class="nav-link" href="#">Contact Us</a>
                            </li>
                        <?php } elseif ($page == 'index' || $page == 'signinup') { ?>
                            <li class="nav-item me-3">
                                <a class="nav-link active" href="#">Home</a>
                            </li>
                            <li class="nav-item me-3">
                                <a class="nav-link" href="#">About</a>
                            </li>
                            <li class="nav-item me-3">
                                <a class="nav-link" href="#">Services</a>
                            </li>
                        <?php } ?>
                    </ul>
                    <?php if ($page == 'index') { ?>
                        <a class="btn custom-signin-btn" href="signinup.php">Get Started</a>
                    <?php } ?>

                    <?php if ($page == 'user_homepage') { ?>
                    <form class="d-inline me-3" role="search">
                        <input class="form-control custom-input me-2" type="search" placeholder="Search" aria-label="Search">
                    </form>
                    <?php } ?>     
                </div>
            </div>
        </nav>
    </div>
</header>
