<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];
?>

<div class="container-fluid p-3">
  <!-- Pane selectors -->
  <div class="d-flex mb-4">
    <button id="pane-blotter" class="btn btn-sm btn-outline-success me-2" type="button">Blotter</button>
    <button id="pane-summon" class="btn btn-sm btn-outline-success me-2" type="button">Summon</button>
    <button id="pane-katarungang" class="btn btn-sm btn-outline-success" type="button">Katarungang Pambarangay</button>
  </div>

  <!-- Pane content containers -->
  <div id="content-blotter" class="complaint-pane mb-3">
    <!-- <div class="card p-3 shadow-sm">
      <p class="mb-0 text-muted">Blotter content will go here.</p>
    </div> -->
    <?php include 'examples.php' ?>
  </div>
  <div id="content-summon" class="complaint-pane mb-3 d-none">
    <div class="card p-3 shadow-sm">
      <p class="mb-0 text-muted">Summon content will go here.</p>
    </div>
  </div>
  <div id="content-katarungang" class="complaint-pane mb-3 d-none">
    <div class="card p-3 shadow-sm">
      <p class="mb-0 text-muted">Katarungang Pambarangay content will go here.</p>
    </div>
  </div>
</div>

<script>
// simple tab switching
document.querySelectorAll('[id^="pane-"]').forEach(btn => {
  btn.addEventListener('click', () => {
    // deactivate buttons
    document.querySelectorAll('[id^="pane-"]').forEach(b => b.classList.remove('btn-success'));
    document.querySelectorAll('[id^="pane-"]').forEach(b => b.classList.add('btn-outline-success'));
    // hide all panes
    document.querySelectorAll('.complaint-pane').forEach(p => p.classList.add('d-none'));

    // activate this button + show its pane
    btn.classList.remove('btn-outline-success');
    btn.classList.add('btn-success');
    const suffix = btn.id.replace('pane-','');
    document.getElementById('content-' + suffix).classList.remove('d-none');
  });
});

// initialize first pane
document.getElementById('pane-blotter').click();
</script>
