<?php include 'functions/dbconn.php'; ?>
<?php include 'includes/account_header.php'; ?>
<?php include 'includes/userSidebar.php'; ?>

<style>
.user-main-content {
    margin-left: 280px;
    padding: 20px;
    transition: margin-left 0.3s ease;
}

/* Mobile/Tablet view - now applies below 1024px */
@media (max-width: 1023px) {
    .user-main-content {
        margin-left: 0 !important;
        padding-top: 70px;
        padding-left: 1rem;
        padding-right: 1rem;
    }
}

/* Ensure smooth transitions */
@media (min-width: 1024px) {
    body {
        overflow-x: hidden;
    }
}
</style>

<div class="user-main-content">
    <?php
        // Default to 'dashboard' if no page is set
        $page = $_GET['page'] ?? 'userDashboard';

        // List of allowed pages for security
        $allowed_pages = ['userDashboard', 'userRequest', 'userServices', 'userSettings', 'userTransactions', 'serviceBarangayClearance', 'serviceBarangayID', 'serviceCertification', 'serviceEquipmentBorrowing', 'serviceBusinessClearance'];
        // Check if the requested page is allowed
        if (in_array($page, $allowed_pages)) {
            $page_file = "{$page}.php";
            
            // Check if the file exists
            if (file_exists($page_file)) {
                include $page_file;
            } else {
                echo "<div class='alert alert-danger'>Page file not found: $page_file</div>";
            }
        } else {
            echo "<div class='alert alert-warning'>Invalid page requested.</div>";
        }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
<script src="js/adminPanel.js"></script>
</body>
</html>
