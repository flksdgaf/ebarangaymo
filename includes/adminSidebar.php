<!-- Sidebar (Bootstrap-based) -->
<?php 
include 'functions/dbconn.php'; 

$info = $conn->query("SELECT logo, name, address FROM barangay_info WHERE id=1")->fetch_assoc();
$logoUrl = 'images/' . $info['logo'];

$menuItems = [
  [
    'id'    => 'adminDashboard',
    'label' => 'Dashboard',
    'icon'  => 'home',
    'href'  => 'adminDashboard.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Treasurer','Brgy Lupon']
  ],
  [
    'id'    => 'adminRequest',
    'label' => 'Document Request',
    'icon'  => 'description',
    'href'  => 'adminRequest.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Treasurer']
  ],
  [
    'id'    => 'adminEquipmentBorrowing',
    'label' => 'Equipment Borrowing',
    'icon'  => 'inventory_2',
    'href'  => 'adminEquipmentBorrowing.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ],
  [
    'id'    => 'adminComplaints',
    'label' => 'Blotter & Complaints',
    'icon'  => 'gavel',
    'href'  => 'adminComplaints.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Treasurer','Brgy Lupon']
  ],
  [
    'id'    => 'adminResidents',
    'label' => 'Residents',
    'icon'  => 'people',
    'href'  => 'adminResidents.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ],
  [
    'id'    => 'adminVerifications',
    'label' => 'Account Verifications',
    'icon'  => 'verified_user',
    'href'  => 'adminVerifications.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ],
  [
    'id'    => 'adminHistory',
    'label' => 'Transaction History',
    'icon'  => 'history',
    'href'  => 'adminHistory.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Treasurer']
  ],
  [
    'id'    => 'adminTransactions',
    'label' => 'Generate Reports',
    'icon'  => 'bar_chart',
    'href'  => 'adminTransactions.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Treasurer']
  ],
  [
    'id'    => 'adminWebsite',
    'label' => 'Website Configuration',
    'icon'  => 'web',
    'href'  => 'adminWebsite.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ],
  [
    'id'    => 'adminDeviceStatus',
    'label' => 'Device Status',
    'icon'  => 'devices',
    'href'  => 'adminDeviceStatus.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ],
  [
    'id'    => 'adminLogs',
    'label' => 'Activity Logs',
    'icon'  => 'receipt_long',
    'href'  => 'adminLogs.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ],
//   [
//     'id'    => 'settings',
//     'label' => 'Settings',
//     'icon'  => 'settings',
//     'href'  => 'adminSettings.php',
//     'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
//   ],
];

$currentRole = $_SESSION['loggedInUserRole'] ?? '';
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
        <?php foreach ($menuItems as $item): ?>
            <?php if (in_array($currentRole, $item['roles'], true)): ?>
                <li>
                <a href="adminPanel.php?page=<?= urlencode($item['id']) ?>" class="nav-link d-flex align-items-center <?= ($currentPage === $item['id']) ? 'active' : '' ?>">
                    <span class="material-symbols-outlined me-2"><?= htmlspecialchars($item['icon']) ?></span>
                    <?= htmlspecialchars($item['label']) ?>
                </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <!-- <li>
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
        </li> -->
    </ul>
</nav>

<!-- Hamburger Button -->
<button id="hamburger-btn" class="btn position-fixed top-0 start-0 m-2 d-md-none">
    <span class="material-symbols-outlined">menu</span>
</button>
