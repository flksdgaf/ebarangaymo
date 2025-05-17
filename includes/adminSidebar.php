<!-- Sidebar (Bootstrap-based) -->
<nav id="sidebar" class="sidebar">
    <div class="text-center mb-4 mt-3">
        <div class="d-flex justify-content-center align-items-center gap-2">
            <img src="images/good_governance_logo.png" alt="Good Governance Logo" style="width: 50px;">
            <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 50px;">
        </div>
        <h1 class="mt-1 mb-1">Barangay Magang</h1>
        <h2 class="text-uppercase">Daet, Camarines Norte</h2>
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
            Request
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminBlotter" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminBlotter') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">edit_document</span>
            Blotter Record
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminSummon" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminSummon') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">event</span>
            Summon
            </a>
        </li>
        <li>
            <a href="adminPanel.php?page=adminKatarungangPambarangay" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminKatarungangPambarangay') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">balance</span>
            Katarungang Pambarangay
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
            <a href="adminPanel.php?page=adminTransactions" class="nav-link d-flex align-items-center <?= ($currentPage === 'adminTransactions') ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">receipt_long</span>
            Transaction History
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
            Logs
            </a>
        </li>
    </ul>
</nav>

<!-- Hamburger Button -->
<button id="hamburger-btn" class="btn position-fixed top-0 start-0 m-2 d-md-none">
    <span class="material-symbols-outlined">menu</span>
</button>
