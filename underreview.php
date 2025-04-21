<?php 
$page = 'signinup'; 
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="underreview.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<body>

    <!-- Overlay second image -->
    <img src="images/bg_nothover.png" class="overlay-image" alt="Overlay Image">

    <!-- Centered content -->
    <div class="d-flex align-items-center justify-content-center vh-100">
        <div class="content">
            <!-- Hourglass GIF -->
            <canvas id="canvas" width="300" height="300"></canvas>
            <script type="module">
            import { DotLottie } from "https://cdn.jsdelivr.net/npm/@lottiefiles/dotlottie-web/+esm";

            new DotLottie({
                autoplay: true,
                loop: true,
                canvas: document.getElementById("canvas"),
                src: "https://lottie.host/d0aee06e-c4f8-41ce-900f-8fc92274c294/3lsI0L5C6d.lottie", 
            });
            </script>
    
            <!-- Texts -->
            <h1 class="mb-5">UNDER REVIEW</h2>
            <p class="mb-40">
                Your account details is being verified by the system and will take a few minutes.<br>
                You will receive an email or SMS notification once your account has been successfully verified.<br>
                If you have any urgent concerns, feel free to contact our support team.<br>
                Thank you for your patience!
            </p>

            <!-- Back Home Button -->
            <a href="index.php" class="btn btn-light btn-home">BACK TO HOME</a>
        </div>
    </div>

</body>

