<?php 
session_start();
include 'functions/dbconn.php'; 
include 'includes/header.php'; 
?>

<div class="container-fluid px-0">
    <img src="/images/banner.png" alt="Banner" class="img-fluid w-100">
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
        <img src="/images/carousel.png" class="d-block w-100" alt="...">
        </div>
        <div class="carousel-item">
        <img src="/images/carousel.png" class="d-block w-100" alt="...">
        </div>
        <div class="carousel-item">
        <img src="/images/carousel.png" class="d-block w-100" alt="...">
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

<div class="container">
    <h1>ABOUT US</h1>

    <h1>SERVICES</h1>

    <h1>NEW AND UPDATES</h1>

</div>


<!-- <?php include 'includes/footer.php'; ?> -->
