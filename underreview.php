<?php 
$page = 'signinup'; 
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
<link rel="stylesheet" href="underreview.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<body>
    <div class="d-flex align-items-center justify-content-center min-vh-100">
        <div class="review-container">
            <!-- Animated Icon -->
            <div class="icon-wrapper">
                <div class="icon-stack">
                    <span class="material-symbols-outlined review-icon-bg">circle</span>
                    <span class="material-symbols-outlined review-icon">hourglass_empty</span>
                </div>
            </div>
    
            <!-- Content -->
            <h1 class="review-title">UNDER REVIEW</h1>
            <p class="review-message">
                Your account details are being verified by the system and will take a few minutes.
                This page will update once your account has been successfully verified.<br><br>
                If you have any urgent concerns, feel free to contact our support team.<br>
                Thank you for your patience!
            </p>

            <!-- Back Home Button -->
            <a href="index.php" class="btn btn-back-home">
                <span class="material-symbols-outlined">home</span>
                BACK TO HOME
            </a>
        </div>
    </div>
</body>