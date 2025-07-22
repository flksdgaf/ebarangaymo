<?php
require 'functions/dbconn.php';
$userId = (int) $_SESSION['loggedInUserID'];
?>

<title>eBarangay Mo | Complaints</title>

<div class="container-fluid p-3">
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
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const params = new URLSearchParams(window.location.search);

  // decide which pane had activity
  let pane = 'blotter';
  if (params.has('summon_search') || params.has('summon_page')) pane = 'summon';
  // if (params.has('katarungan_search') || params.has('katarungan_page')) pane = 'katarungan';

  // show that pane
  const trigger = document.getElementById(`${pane}-tab`);
  if (trigger) {
    bootstrap.Tab.getOrCreateInstance(trigger).show();
  }
});
</script>
