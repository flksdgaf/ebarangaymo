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
    $currentPage = $_GET['page'] ?? 'adminDashboard';
    ?>

    <ul class="nav flex-column gap-1">
        <li>
            <a href="adminPanel.php?page=adminDashboard" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminDashboard') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">dashboard</span>
            Dashboard
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminRequest" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminRequest') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">description</span>
            Document Request
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminEquipmentBorrowing" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminEquipmentBorrowing') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">hand_package</span>
            Equipment Borrowing
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminComplaints" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminComplaints') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">release_alert</span>
            Blotter & Complaints
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminResidents" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminResidents') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">folder_shared</span>
            Residents
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminVerifications" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminVerifications') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">verified</span>
            Account Verifications
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminHistory" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminHistory') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">history</span>
            Transaction History
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminTransactions" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminTransactions') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">receipt_long</span>
            Generate Reports
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminWebsite" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminWebsite') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">web</span>
            Website Configuration
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminDeviceStatus" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminDeviceStatus') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">devices</span>
            Device Status
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminLogs" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminLogs') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">badge</span>
            Activity Logs
            </a>
        </li>
    </ul>
</nav>

<!-- Hamburger Button -->
<button id="hamburger-btn" class="btn position-fixed top-0 start-0 m-2 d-md-none">
    <span class="material-symbols-outlined">menu</span>
</button>
