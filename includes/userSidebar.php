<!-- Sidebar (Bootstrap-based) -->
 <?php 
include 'functions/dbconn.php'; 

$info = $conn->query("SELECT logo, name, address FROM barangay_info WHERE id=1")->fetch_assoc();
$logoUrl = 'images/' . $info['logo'];
?>

<nav id="sidebar" class="sidebar">
    <div class="text-center mb-4 mt-3">
        <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
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
    // Determine current “page”
    $currentPage = $_GET['page'] ?? 'userDashboard';

    // Services should be active on both userServices *and* any serviceX pages
    $isServicesActive = in_array($currentPage, ['userServices', 'serviceBarangayID']);
    ?>

    <ul class="nav flex-column gap-2">
        <li>
        <a href="userPanel.php?page=userDashboard"
            class="nav-link <?= $currentPage === 'userDashboard' ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">dashboard</span> Dashboard
        </a>
        </li>
        <li>
        <a href="userPanel.php?page=userServices"
            class="nav-link <?= $isServicesActive ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">description</span> Request a Service
        </a>
        </li>
        <li>
        <a href="userPanel.php?page=userRequest"
            class="nav-link <?= $currentPage === 'userRequest' ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">home_storage</span> My Requests
        </a>
        </li>
        <li>
        <a href="userPanel.php?page=userTransactions"
            class="nav-link <?= $currentPage === 'userTransactions' ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">receipt_long</span> Transaction History
        </a>
        </li>
    </ul>
</nav>

<!-- Hamburger Button -->
<button id="hamburger-btn" class="btn position-fixed top-0 start-0 m-2 d-md-none">
    <span class="material-symbols-outlined">menu</span>
</button>