<!-- Sidebar -->
<aside id="sidebar" class="hidden">
  <div class="sidebar-top">
    <div class="logo">
      <img src="images/good_governance_logo.png" alt="Good Governance Logo" style="width: 50px;">
      <img src="images/magang_logo.png" alt="Barangay Magang Logo" style="width: 50px;">
      <h2>eBarangay Mo</h2>
      <p>BARANGAY SERVICES PORTAL OF BRGY. MAGANG, DAET, CAMARINES NORTE</p>
    </div>
    
    <div class="close" id="close-btn" style="cursor: pointer;">
      <span class="material-symbols-outlined">close</span>
    </div>
  </div>

  <?php $current = isset($_GET['page']) ? $_GET['page'] : 'adminDashboard'; ?>

  <div class="sidebar">
    <a href="adminpanel.php?page=adminDashboard" class="sidebar-nav-link <?php echo ($current === 'adminDashboard') ? 'sidebar-active' : ''; ?>">
      <span class="material-symbols-outlined">dashboard</span>
      <h3>Dashboard</h3>
    </a>

    <a href="adminpanel.php?page=adminRequest" class="sidebar-nav-link <?php echo ($current === 'adminRequest') ? 'sidebar-active' : ''; ?>">
    <span class="material-symbols-outlined">description</span>
      <h3>Request</h3>
    </a>
    
    <a href="adminpanel.php?page=adminBlotter" class="sidebar-nav-link <?php echo ($current === 'adminBlotter') ? 'sidebar-active' : ''; ?>">
        <span class="material-symbols-outlined">edit_document</span>
        <h3>Blotter Record</h3>
    </a>

    <a href="adminpanel.php?page=adminResidents" class="sidebar-nav-link <?php echo ($current === 'adminResidents') ? 'sidebar-active' : ''; ?>">
        <span class="material-symbols-outlined">folder_shared</span>
        <h3>Residents</h3>
    </a>

    <a href="adminpanel.php?page=adminWebsite" class="sidebar-nav-link <?php echo ($current === 'adminWebsite') ? 'sidebar-active' : ''; ?>">
        <span class="material-symbols-outlined">web</span>
        <h3>eBarangay Mo - Website</h3>
    </a>

    <a href="adminpanel.php?page=adminUsers" class="sidebar-nav-link <?php echo ($current === 'adminUsers') ? 'sidebar-active' : ''; ?>">
        <span class="material-symbols-outlined">group</span>
        <h3>Users</h3>
    </a>

    <a href="adminpanel.php?page=adminTransaction" class="sidebar-nav-link <?php echo ($current === 'adminTransaction') ? 'sidebar-active' : ''; ?>">
        <span class="material-symbols-outlined">receipt_long</span>
        <h3>Transaction History</h3>
    </a>

    <a href="adminpanel.php?page=adminLogs" class="sidebar-nav-link <?php echo ($current === 'adminLogs') ? 'sidebar-active' : ''; ?>">
    <span class="material-symbols-outlined">badge</span>
        <h3>Logs</h3>
    </a>

    <a href="adminpanel.php?page=adminAccount" class="sidebar-nav-link <?php echo ($current === 'adminAccount') ? 'sidebar-active' : ''; ?>">
    <span class="material-symbols-outlined">verified</span>
        <h3>Account Verifications</h3>
    </a>

    <a href="adminpanel.php?page=adminSettings" class="sidebar-nav-link <?php echo ($current === 'adminSettings') ? 'sidebar-active' : ''; ?>">
    <span class="material-symbols-outlined">settings</span>
        <h3>Admin Settings</h3>
    </a>
  </div>

  <div class="logout-btn">
    <a href="#">
      <span class="material-symbols-outlined">logout</span>
      <h3>Sign out</h3>
    </a>
  </div>
</aside>

<!-- Toggle Button (Hamburger) -->
<span class="material-symbols-outlined toggle-btn" id="hamburger-btn" style="cursor: pointer;">menu</span>