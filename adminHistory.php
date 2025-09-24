<?php
require 'functions/dbconn.php';
$userId = (int)($_SESSION['loggedInUserID'] ?? 0);

// Which pane/tab is selected
$tab = $_GET['tab'] ?? 'documents';

// Helper: build current query string (preserve other GET params) for tab links
function qs_with($overrides = []) {
  $qs = $_GET;
  foreach ($overrides as $k => $v) $qs[$k] = $v;
  return http_build_query($qs);
}
?>

<title>eBarangay Mo | Transaction History</title>

<div class="container p-3">
  <!-- TABS -->
  <ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
      <a class="nav-link <?= $tab === 'documents' ? 'active' : '' ?>"
        href="?<?= qs_with(['tab' => 'documents']) ?>">
        Document Requests
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?= $tab === 'equipments' ? 'active' : '' ?>"
        href="?<?= qs_with(['tab' => 'equipments']) ?>">
        Equipment Borrowing
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?= $tab === 'complaints' ? 'active' : '' ?>"
        href="?<?= qs_with(['tab' => 'complaints']) ?>">
        Complaint Requests
      </a>
    </li>
  </ul>
</div>

