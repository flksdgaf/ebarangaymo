<?php 
session_start();
include 'functions/dbconn.php'; 
include 'includes/header.php'; 
?>

<!-- NAVIGATION SECTION -->
<header>
    <div class="container-fluid bg-body-tertiary">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="#">
                    <img src="images/seal.png" alt="Republic Seal of the Philippines" width="36" height="36">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item ms-3 me-3">
                            <a class="nav-link active" aria-current="page" href="#">
                                Home
                            </a>
                        </li>
                        <li class="nav-item dropdown me-3">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                About
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Mission, Vision and Values</a></li>
                                <li><a class="dropdown-item" href="#">Citizens Charter</a></li>
                                <li><a class="dropdown-item" href="#">Map</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown me-3">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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
                            <a class="nav-link " href="#">
                                Transparency Seal
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                Contact Us
                            </a>
                        </li>
                    </ul>
                    <form class="d-flex" role="search">
                        <input class="form-control custom-input me-2" type="search" placeholder="Search" aria-label="Search">
                    </form>
                </div>
            </div> 
        </nav>
    </div>
</header>

<div class="container-fluid px-0">
    <img src="images/banner.png" alt="Banner" class="img-fluid w-100">
</div>

<!-- CAROUSEL SECTION -->
<div class="container-fluid px-0">
    <div id="carouselExampleIndicators" class="carousel slide">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
    </div>
    <div class="carousel-inner">
        <div class="carousel-item active">
        <img src="images/carousel.png" class="d-block w-100" alt="...">
        </div>
        <div class="carousel-item">
        <img src="images/carousel.png" class="d-block w-100" alt="...">
        </div>
        <div class="carousel-item">
        <img src="images/carousel.png" class="d-block w-100" alt="...">
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
    </div>
</div>

<!-- ABOUT SECTION -->
<div class="container-fluid px-0">
    <div class="about-container text-center">
        <h1 class="gradient-text">ABOUT US</h1>
        <p class="w-50 mx-auto text-center">
            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
        </p>

        <div class="row g-2 mt-4 justify-content-center text-center">
            <div class="col-lg-3 col-md-4 col-sm-6 d-flex justify-content-center">
                <div class="card-custom p-4">
                    <h5 class="gradient-text">Mission</h5>
                    <p>
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris... View More
                    </p>
                </div>
            </div>
            <div class="col-lg-3 col-md-4 col-sm-6 d-flex justify-content-center">
                <div class="card-custom p-4">
                    <h5 class="gradient-text">Vision</h5>
                    <p>
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris... View More
                    </p>
                </div>
            </div>
            <div class="col-lg-3 col-md-4 col-sm-6 d-flex justify-content-center">
                <div class="card-custom p-4">
                    <h5 class="gradient-text">Values</h5>
                    <p>
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris... View More
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SERVICES SECTION -->
<div class="container-fluid px-0 mt-5">
    <div class="services-container text-center">
        <h1 class="gradient-text">SERVICES</h1>

        <div class="container text-center">
            <div class="row mt-5">
                <div class="col">
                <h3>Barangay ID</h3>
                </div>
                <div class="col">
                <h3>Barangay Clearance</h3>
                </div>
            </div>
            <!-- <div class="row">
                <div class="col">
                <h3>Certification</h3>
                </div>
                <div class="col">
                <h3>Business Permit</h3>
                </div>
            </div>
            <div class="row">
                <div class="col">
                <h3>Katarungang Pambarangay</h3>
                </div>
                <div class="col">
                <h3>Environmental Services</h3>
                </div>
            </div> -->
        </div>
    </div>
</div>


<!-- <?php include 'includes/footer.php'; ?> -->
