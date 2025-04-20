<!-- Sidebar (Bootstrap-based) -->
<nav id="sidebar" class="sidebar">
    <div class="text-center mb-4 mt-3">
        <div class="d-flex justify-content-center align-items-center gap-2">
            <img src="images/good_governance_logo.png" alt="Good Governance Logo" style="width: 50px;">
            <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 50px;">
        </div>
        <h1 class="mt-3 mb-1">eBarangay Mo</h1>
        <h2 class="text-uppercase">Barangay Services Portal of  Brgy. Magang, Daet, Camarines Norte</h2>
        <hr class="custom-hr">

        <button class="btn btn-sm" id="close-btn">
            <h3 class="material-symbols-outlined">close</h3>
        </button> 
    </div>

    <?php
    $currentPage = $_GET['page'] ?? 'superAdminDashboard';
    ?>

    <ul class="nav flex-column gap-2">
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
            <a href="superAdminPanel.php?page=superAdminBlotter" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminBlotter') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">edit_document</span>
            Blotter Record
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminResidents" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminResidents') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">folder_shared</span>
            Residents
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminWebsite" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminWebsite') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">web</span>
            eBarangay Mo - Website
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminUsers" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminUsers') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">group</span>
            Users
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminTransactions" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminTransactions') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">receipt_long</span>
            Transaction History
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminLogs" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminLogs') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">badge</span>
            Logs
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminVerifications" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminVerifications') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">verified</span>
            Account Verifications
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminDeviceStatus" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminDeviceStatus') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">devices</span>
            Device Status
            </a>
        </li>
        <li>
            <a href="superAdminPanel.php?page=superAdminSettings" class="nav-link d-flex align-items-center <?= ($currentPage === 'superAdminSettings') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">settings</span>
            Admin Settings
            </a>
        </li>
    </ul>
</nav>

<!-- Hamburger Button -->
<button id="hamburger-btn" class="btn position-fixed top-0 start-0 m-2 d-md-none">
    <span class="material-symbols-outlined">menu</span>
</button>
