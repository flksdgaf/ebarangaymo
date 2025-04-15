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
    $currentPage = $_GET['page'] ?? 'adminDashboard';
    ?>

    <ul class="nav flex-column gap-2">
        <li>
            <a href="superadminpanel.php?page=superadminDashboard" class="nav-link d-flex align-items-center <?= ($currentPage === 'superadminDashboard') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">dashboard</span>
            Dashboard
            </a>
        </li>
        <li>
            <a href="superadminpanel.php?page=superadminRequest" class="nav-link d-flex align-items-center <?= ($currentPage === 'superadminRequest') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">description</span>
            Request
            </a>
        </li>
        <li>
            <a href="superadminpanel.php?page=superadminBlotter" class="nav-link d-flex align-items-center <?= ($currentPage === 'superadminBlotter') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">edit_document</span>
            Blotter Record
            </a>
        </li>
        <li>
            <a href="superadminpanel.php?page=superadminResidents" class="nav-link d-flex align-items-center <?= ($currentPage === 'superadminResidents') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">folder_shared</span>
            Residents
            </a>
        </li>
        <li>
            <a href="superadminpanel.php?page=superadminWebsite" class="nav-link d-flex align-items-center <?= ($currentPage === 'superadminWebsite') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">web</span>
            eBarangay Mo - Website
            </a>
        </li>
        <li>
            <a href="superadminpanel.php?page=superadminUsers" class="nav-link d-flex align-items-center <?= ($currentPage === 'superadminUsers') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">group</span>
            Users
            </a>
        </li>
        <li>
            <a href="superadminpanel.php?page=superadminTransactions" class="nav-link d-flex align-items-center <?= ($currentPage === 'superadminTransactions') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">receipt_long</span>
            Transaction History
            </a>
        </li>
        <li>
            <a href="superadminpanel.php?page=superadminLogs" class="nav-link d-flex align-items-center <?= ($currentPage === 'superadminLogs') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">badge</span>
            Logs
            </a>
        </li>
        <li>
            <a href="superadminpanel.php?page=superadminVerifications" class="nav-link d-flex align-items-center <?= ($currentPage === 'superadminVerifications') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">verified</span>
            Account Verifications
            </a>
        </li>
        <li>
            <a href="superadminpanel.php?page=superadminSettings" class="nav-link d-flex align-items-center <?= ($currentPage === 'superadminSettings') ? 'active' : '' ?>">
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
