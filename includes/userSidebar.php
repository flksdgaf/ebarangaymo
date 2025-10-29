<!-- Sidebar (Bootstrap-based) -->
 <?php 
include 'functions/dbconn.php'; 

$info = $conn->query("SELECT logo, name, address FROM barangay_info WHERE id=1")->fetch_assoc();
$logoUrl = 'images/' . $info['logo'];
?>

<style>
/* Hamburger button styling - now integrated in header */
#hamburger-btn {
    z-index: 1050;
}

#hamburger-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(20, 82, 20, 0.3);
}

/* Push header to the right of sidebar on desktop */
@media (min-width: 1024px) {
    .top-bar {
        margin-left: 280px !important;
        width: calc(100% - 280px) !important;
    }
}

/* Show hamburger button below 1024px */
@media (max-width: 1023px) {
    #hamburger-btn {
        display: inline-flex !important;
    }
}

/* Reset on mobile/tablet */
@media (max-width: 1023px) {
    .top-bar {
        margin-left: 0 !important;
        width: 100% !important;
    }
}

#sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 280px;
    height: 100vh;
    background: #13411F;
    z-index: 1030;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Desktop - sidebar always visible */
@media (min-width: 1024px) {
    #sidebar {
        transform: translateX(0) !important;
    }
}

/* Mobile/Tablet - sidebar hidden by default */
@media (max-width: 1023px) {
    #sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 1050;
    }
    
    #sidebar.active {
        transform: translateX(0);
    }
    
    /* Add overlay when sidebar is open */
    body.sidebar-open::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1040;
    }
}

/* Close button in sidebar */
#close-btn {
    display: none;
    position: absolute;
    top: 10px;
    right: 10px;
    background: transparent;
    border: none;
    color: #ffffff;
}

@media (max-width: 1023px) {
    #close-btn {
        display: block;
    }
}

/* Ensure user name and role stay visible on all screen sizes */
.user-label {
    display: inline !important;
}

/* Responsive header adjustments */
@media (max-width: 1023px) {
    .top-bar {
        padding-left: 8px !important;
        padding-right: 8px !important;
    }
    
    .top-bar .container-fluid {
        padding: 0 !important;
    }
    
    .topbar-text {
        font-size: 16px !important;
    }
    
    .user-dropdown-btn {
        padding: 4px 8px !important;
        font-size: 11px !important;
        white-space: nowrap;
    }
    
    .user-label {
        font-size: 11px !important;
        display: inline !important;
    }
    
    .user-profile-pic {
        width: 32px !important;
        height: 32px !important;
        margin-left: 4px;
    }
}

@media (max-width: 767px) {
    .topbar-text {
        font-size: 14px !important;
    }
    
    .user-dropdown-btn {
        padding: 3px 6px !important;
        font-size: 10px !important;
    }
    
    .user-label {
        font-size: 10px !important;
    }
    
    .user-profile-pic {
        width: 30px !important;
        height: 30px !important;
    }
    
    #hamburger-btn {
        padding: 4px 7px !important;
    }
    
    #hamburger-btn .material-symbols-outlined {
        font-size: 16px !important;
    }
}

/* For very small screens - abbreviate if needed */
@media (max-width: 400px) {
    .topbar-text {
        font-size: 13px !important;
    }
    
    .user-dropdown-btn {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
}
</style>

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
            <span class="material-symbols-outlined">close</span>
        </button> 
    </div>

    <?php
    // Determine current "page"
    $currentPage = $_GET['page'] ?? 'userDashboard';

    // Services should be active on both userServices *and* any serviceX pages
    $isServicesActive = in_array($currentPage, ['userServices', 'serviceBarangayID', 'serviceCertification', 'serviceEquipmentBorrowing', 'serviceBarangayClearance', 'serviceBusinessClearance']);
    ?>

    <ul class="nav flex-column gap-2">
        <li>
        <a href="userPanel.php?page=userDashboard"
            class="nav-link <?= $currentPage === 'userDashboard' ? 'active' : '' ?>">
            <span class="material-symbols-outlined me-2">home</span> Home
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
    </ul>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const closeBtn = document.getElementById('close-btn');
    const sidebar = document.getElementById('sidebar');
    
    // Open sidebar
    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.add('active');
            document.body.classList.add('sidebar-open');
        });
    }
    
    // Close sidebar
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            sidebar.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        });
    }
    
    // Close sidebar when clicking overlay
    document.addEventListener('click', function(e) {
        if (document.body.classList.contains('sidebar-open') && 
            !sidebar.contains(e.target) && 
            !hamburgerBtn.contains(e.target)) {
            sidebar.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        }
    });
});
</script>