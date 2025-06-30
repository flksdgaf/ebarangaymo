<!-- Sidebar (Bootstrap-based) -->
<?php 
include 'functions/dbconn.php'; 

$info = $conn->query("SELECT logo, name, address FROM barangay_info WHERE id=1")->fetch_assoc();
$logoUrl = 'images/' . $info['logo'];
?>

<nav id="sidebar" class="sidebar">
    <div class="text-center mb-4 mt-3">
        <div class="d-flex justify-content-center align-items-center gap-2">
            <img src="images/good_governance_logo.png" alt="Good Governance Logo" style="width: 50px;">
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Barangay Magang Logo" style="width: 50px;">
        </div>
        <h1 class="mt-1 mb-1"><?= htmlspecialchars($info['name']) ?></h1>
        <h2 class="text-uppercase"><?= htmlspecialchars($info['address']) ?></h2>
        <hr class="custom-hr">

        <button class="btn btn-sm" id="close-btn">
            <h3 class="material-symbols-outlined">close</h3>
        </button> 
    </div>

    <?php
    $currentPage = $_GET['page'] ?? 'superAdminDashboard';
    ?>

    <ul class="nav flex-column gap-1">
        <li>
            <a href="superAdminPanel.php?page=superAdminDashboard" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminDashboard') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">dashboard</span>
            Dashboard
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminRequest" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminRequest') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">description</span>
            Request
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminComplaints" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminComplaints') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">release_alert</span>
            Complaints
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminResidents" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminResidents') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">folder_shared</span>
            Residents
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminVerifications" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminVerifications') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">verified</span>
            Account Verifications
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminTransactions" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminTransactions') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">receipt_long</span>
            Transaction Reports
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminWebsite" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminWebsite') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">web</span>
            Website Configuration
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminDeviceStatus" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminDeviceStatus') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">devices</span>
            Device Status
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminLogs" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminLogs') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">badge</span>
            Logs
            </a>
        </li>   
        <!-- SUPER ADMIN SETTINGS (NOT YET SURE IF NEEDED) -->
        <li>
            <a href="superAdminPanel.php?page=superAdminPanelSettings" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminPanelSettings') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">manage_accounts</span>
            Admin Settings
            </a>
        </li>
    </ul>
</nav>

<!-- Hamburger Button -->
<button id="hamburger-btn" class="btn position-fixed top-0 start-0 m-2 d-md-none">
    <span class="material-symbols-outlined">menu</span>
</button>
