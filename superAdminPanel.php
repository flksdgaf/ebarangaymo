<?php include 'functions/dbconn.php'; ?>
<?php include 'includes/account_header.php'; ?>
<?php include 'includes/superAdminSidebar.php'; ?>

<div class="main-content">
    <?php
        // Default to 'dashboard' if no page is set
        $page = $_GET['page'] ?? 'superAdminDashboard';

        // List of allowed pages for security
        $allowed_pages = ['superAdminDashboard','superAdminRequest', 'superAdminBlotter', 'superAdminResidents', 'superAdminWebsite', 'superAdminUsers', 'superAdminTransactions', 'superAdminLogs', 'superAdminVerifications', 'superAdminSettings'];

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

<script src="js/adminPanel.js"></script>
</body>
</html>
