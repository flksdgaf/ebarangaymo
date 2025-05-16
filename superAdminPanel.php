<?php 
    include 'functions/dbconn.php'; 
    $page = $_GET['page'] ?? 'superAdminDashboard';
    include 'includes/account_header.php';
    include 'includes/superAdminSidebar.php'; 
?>

<div class="main-content">
    <?php
        // List of allowed pages for security
        $allowed_pages = ['superAdminDashboard', 'superAdminRequest', 'superAdminBlotter', 'superAdminSummon', 'superAdminKatarungangPambarangay', 'superAdminResidents', 'superAdminVerifications', 'superAdminTransactions', 'superAdminLogs', 'superAdminWebsite', 'superAdminDeviceStatus', 'superAdminSettings'];

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
