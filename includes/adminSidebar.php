<!-- Sidebar (Bootstrap-based) -->
<?php 
include 'functions/dbconn.php'; 

$info = $conn->query("SELECT logo, name, address FROM barangay_info WHERE id=1")->fetch_assoc();
$logoUrl = 'images/' . $info['logo'];

// 1. how many ONLINE requests awaiting verification?
  $newDocReqs = (int)$conn->query(
    "SELECT COUNT(*) FROM view_request 
     WHERE request_source = 'Online' 
       AND document_status = 'For Verification'"
  )->fetch_row()[0];

  // 2. how many account‐verification requests pending?
  //    (assumes you have a table like `account_requests` or `user_accounts`
  //     with status='Pending' for new sign-ups)
  $newAcctReqs = (int)$conn->query(
    "SELECT COUNT(*) FROM pending_accounts
     WHERE time_creation > NOW() - INTERVAL 1 DAY"
  )->fetch_row()[0];
  // 3. how many newly VERIFIED residents? (to show in Residents tab)
  //    if you want to alert “hey, new profiles have just gone live”,
  //    you might track `verified_at` vs. last time admin looked,
  //    but for simplicity here we’ll count those verified in the last 24h:
  $newResidents = (int)$conn->query(
    "SELECT COUNT(*) FROM user_accounts
     WHERE role = 'Approved'"
  )->fetch_row()[0];

  // map sidebar‐item IDs → badge counts
  $badges = [
    'adminRequest' => $newDocReqs,
    'adminVerifications' => $newAcctReqs,
    'adminResidents' => $newResidents,
    // leave the others at zero until you add them:
    'adminEquipmentBorrowing' => 0,
    'adminDeviceStatus' => 0,
    'adminCollections' => 0, // <-- added for Collection tab
    // etc.
  ];

$menuItems = [
  [
    'id' => 'adminDashboard',
    'label' => 'Dashboard',
    'icon' => 'home',
    'href' => 'adminDashboard.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Treasurer','Lupon Tagapamayapa']
  ],
  [
    'id' => 'adminRequest',
    'label' => 'Document Request',
    'icon' => 'description',
    'href' => 'adminRequest.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Treasurer']
  ],
  [
    'id' => 'adminEquipmentBorrowing',
    'label' => 'Equipment Borrowing',
    'icon' => 'inventory_2',
    'href' => 'adminEquipmentBorrowing.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ],
  [
    'id' => 'adminComplaints',
    // 'label' => 'Blotter & Complaints',
    'label' => ($_SESSION['loggedInUserRole'] === 'Brgy Treasurer') ? 'Complaint Transactions' : 'Blotter & Complaints',
    'icon' => 'gavel',
    'href' => 'adminComplaints.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Treasurer','Lupon Tagapamayapa']
  ],
  [
    'id' => 'adminResidents',
    'label' => 'Residents',
    'icon' => 'people',
    'href' => 'adminResidents.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ],
  [
    'id' => 'adminVerifications',
    'label' => 'Account Verifications',
    'icon' => 'verified_user',
    'href' => 'adminVerifications.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ],
  [
    'id' => 'adminHistory',
    'label' => 'Transaction History',
    'icon' => 'history',
    'href' => 'adminHistory.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad'] //,'Brgy Treasurer'
  ],

  // <-- NEW Collection tab
  [
    'id' => 'adminCollections',
    'label' => 'Collection',
    'icon' => 'receipt', // material icon name; change to preferred icon if you want
    'href' => 'adminCollections.php',
    'roles' => ['Brgy Treasurer','Brgy Bookkeeper'] // limited access as requested
  ],

  [
    'id' => 'adminTransactions',
    'label' => 'Generate Reports',
    'icon' => 'bar_chart',
    'href' => 'adminTransactions.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad'] //,'Brgy Treasurer'
  ],
  [
    'id' => 'adminWebsite',
    'label' => 'Website Configuration',
    'icon' => 'web',
    'href' => 'adminWebsite.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ],
  [
    'id' => 'adminDeviceStatus',
    'label' => 'Device Status',
    'icon' => 'devices',
    'href' => 'adminDeviceStatus.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ],
  [
    'id' => 'adminLogs',
    'label' => 'Activity Logs',
    'icon' => 'receipt_long',
    'href' => 'adminLogs.php',
    'roles' => ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad']
  ]
];

$currentRole = $_SESSION['loggedInUserRole'] ?? '';
?>

<nav id="sidebar" class="sidebar">
    <div class="text-center mb-4 mt-2">
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

      <!-- <php foreach ($menuItems as $item): ?>
        <php if (in_array($currentRole, $item['roles'], true)): ?>
          <php 
            $isActive = $currentPage === $item['id'] ? 'active' : '';
            // lookup badge count (default 0)
            $count = $badges[$item['id']] ?? 0;
          ?>
          <li>
            <a href="adminPanel.php?page=<= urlencode($item['id']) ?>" class="nav-link d-flex align-items-center <= $isActive ?>">
              <span class="material-symbols-outlined me-2"><= htmlspecialchars($item['icon']) ?></span>
              <= htmlspecialchars($item['label']) ?>
              <php if ($count > 0): ?>
                <span class="badge bg-danger rounded-pill"><= $count ?></span>
              <php endif; ?>
            </a>
          </li>
        <php endif; ?>
      <php endforeach; ?> -->
    </ul>
</nav>

<!-- Hamburger Button -->
<button id="hamburger-btn" class="btn position-fixed top-0 start-0 m-2 d-md-none">
    <span class="material-symbols-outlined">menu</span>
</button>
