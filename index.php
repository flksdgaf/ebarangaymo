<?php 
session_start();
include 'functions/dbconn.php'; 
include 'includes/header.php'; 
?>

<div class="container-fluid px-0">
    <img src="images/banner.png" alt="Banner" class="img-fluid w-100">
</div>

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

<!-- <div class="container-fluid px-0">
    <div class="about-container text-center">
        <h2 class="about gradient-text">ABOUT US</h2>
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

<div class="container-fluid px-0">
    <div class="services-container text-center">
        <h2 class="services gradient-text">SERVICES</h2>
    </div>
</div> -->


<!-- <?php include 'includes/footer.php'; ?> -->
