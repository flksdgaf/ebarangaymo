<?php
require 'functions/dbconn.php';
$userId = (int) $_SESSION['loggedInUserID'];
$role = $_SESSION['loggedInUserRole'] ?? '';

?>

<title>eBarangay Mo | Complaints</title>

<div class="container-fluid p-3">
  <?php if ($role === 'Brgy Treasurer'): ?>
  <!-- Show only the Complaint Transactions tab for Treasurer -->
  <ul class="nav nav-tabs" id="complaintTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions-pane" type="button" role="tab">
        Complaint Transactions
      </button>
    </li>
  </ul>

  <div class="tab-content mt-3">
    <div class="tab-pane fade show active" id="transactions-pane" role="tabpanel">
      <?php include 'adminComplaintTransactions.php'; ?>
    </div>
  </div>

<?php else: ?>
  <!-- Show all other tabs for non-Treasurers -->
  <ul class="nav nav-tabs" id="complaintTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="blotter-tab" data-bs-toggle="tab" data-bs-target="#blotter-pane" type="button" role="tab">
        Blotter
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="summon-tab" data-bs-toggle="tab" data-bs-target="#summon-pane" type="button" role="tab">
        Complaint
      </button>
    </li>
    <!-- <li class="nav-item" role="presentation">
      <button class="nav-link" id="katarungan-tab" data-bs-toggle="tab" data-bs-target="#katarungan-pane" type="button" role="tab">
        Katarungang Pambarangay
      </button>
    </li> -->
  </ul>

  <div class="tab-content mt-3">
    <div class="tab-pane fade show active" id="blotter-pane" role="tabpanel">
      <?php include 'adminBlotter.php'; ?>
    </div>
    <div class="tab-pane fade" id="summon-pane" role="tabpanel">
      <?php include 'adminSummon.php'; ?>
    </div>
    <div class="tab-pane fade" id="katarungan-pane" role="tabpanel">
      <?php include 'adminKatarungangPambarangay.php'; ?>
    </div>
  </div>
<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const params = new URLSearchParams(window.location.search);
  
  // Check if we're navigating FROM another page (not a refresh)
  const isFromOtherPage = !sessionStorage.getItem('complaintTabVisited');
  
  // Mark that we've visited the complaint page
  sessionStorage.setItem('complaintTabVisited', 'true');
  
  // Check if there's a saved tab in sessionStorage
  let savedTab = sessionStorage.getItem('activeComplaintTab');
  
  // Decide which pane to show
  let pane = null;
  
  if (params.has('summon_search') || params.has('summon_page') || params.has('new_complaint_id') || params.has('updated_complaint_id') || params.has('deleted_complaint_id')) {
    pane = 'summon';
  } else if (params.has('katarungan_search') || params.has('katarungan_page')) {
    pane = 'katarungan';
  } else if (params.has('transactions_page')) {
    pane = 'transactions';
  } else if (isFromOtherPage) {
    // Coming from another page - always show blotter first
    pane = 'blotter';
    sessionStorage.removeItem('activeComplaintTab'); // Clear saved tab
  } else if (savedTab) {
    // Use saved tab (during refresh)
    pane = savedTab;
  } else {
    // Default to blotter
    pane = 'blotter';
  }

  // Show that pane
  const trigger = document.getElementById(`${pane}-tab`);
  if (trigger) {
    bootstrap.Tab.getOrCreateInstance(trigger).show();
  }

  // Save the active tab to sessionStorage when tabs are clicked
  document.querySelectorAll('#complaintTabs button[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function(e) {
      const tabId = e.target.id.replace('-tab', '');
      sessionStorage.setItem('activeComplaintTab', tabId);
    });
  });
  
  // Clear the 'visited' flag when navigating away from this page
  window.addEventListener('beforeunload', function(e) {
    // Don't clear if it's just a refresh (check if Ctrl+R or F5)
    if (!e.currentTarget.performance.navigation.type === 1) {
      sessionStorage.removeItem('complaintTabVisited');
    }
  });
});

// Clear complaint tab memory when navigating via sidebar
document.querySelectorAll('a[href*="adminPanel.php"]').forEach(link => {
  link.addEventListener('click', function(e) {
    const href = this.getAttribute('href');
    // If navigating to a different page (not adminComplaints), clear the memory
    if (href && !href.includes('page=adminComplaints')) {
      sessionStorage.removeItem('complaintTabVisited');
      sessionStorage.removeItem('activeComplaintTab');
    }
  });
});
</script>
