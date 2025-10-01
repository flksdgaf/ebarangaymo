<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'functions/dbconn.php';

// Ensure timezone for "As of" timestamp (user's timezone; adjust if necessary)
date_default_timezone_set('Asia/Manila');

$userId = (int)($_SESSION['loggedInUserID'] ?? 0);

// Which pane/tab is selected
$tab = $_GET['tab'] ?? 'todays'; // default to Today's Collection

// Helper: build current query string (preserve other GET params) for tab links
function qs_with($overrides = []) {
  $qs = $_GET;
  foreach ($overrides as $k => $v) $qs[$k] = $v;
  return http_build_query($qs);
}

// Small helper for showing nullable DB values (show blank for NULL, show "0" if numeric 0)
function show_nullable($val) {
  if (!isset($val) || $val === null) return '';
  return htmlspecialchars((string)$val);
}
?>
<title>eBarangay Mo | Transaction History</title>

<style>
  /* modal + form styling (kept from your previous) */
  .modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
  }
  .modal.show { display: block; }
  .modal-dialog { position: relative; width: auto; max-width: 900px; margin: 1.75rem auto; }
  .modal-content { position: relative; background-color: #fff; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
  .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; background-color: #2d5f3f; color: white; border-radius: 8px 8px 0 0; }
  .modal-header h5 { margin: 0; font-size: 1.125rem; display:flex; gap:.5rem; align-items:center; }
  .modal-header .close { background:transparent; border:none; color:white; font-size:1.25rem; cursor:pointer; opacity:0.9; }
  .modal-body { padding: 1rem 1.5rem; }
  .modal-footer { padding: 0.75rem 1.5rem; display:flex; justify-content:flex-end; gap:.5rem; border-top:1px solid #e5e7eb; }
  .form-group { margin-bottom: 0.75rem; }
  .form-group label { display:block; margin-bottom:.35rem; font-weight:500; color:#1f2937; }
  .form-control { width:100%; padding:.5rem .6rem; border-radius:4px; border:1px solid #d1d5db; background:#f8fafb; }
  .form-row { display:flex; gap:.75rem; }
  .form-row .col { flex:1; }
  .btn { padding: .5rem .9rem; border-radius:6px; border:none; cursor:pointer; font-weight:500; }
  .btn-secondary { background:#6b7280; color:white; }
  .btn-success { background:#16a34a; color:white; }
  .btn-primary { background:#2d5f3f; color:white; }

  /* table styling to match adminTransactions.php */
  .card { border-radius: 8px; }
  .card-body { padding: 1rem; }
  .table-sm { font-size: .875rem; }
  .table-custom thead th { background: #f8fafc; border-bottom: 2px solid #e9ecef; }
  .table-custom tbody tr td, .table-custom thead tr th { vertical-align: middle; }
  .table-custom tbody tr:hover { background: rgba(45,95,63,0.03); }

  .table-accountable { overflow-x:auto; }

  /* actions below table */
  .below-table-actions { display:flex; justify-content:flex-start; align-items:center; gap:0.5rem; margin-top:1rem; }
  .as-of { margin-top:.75rem; color:#6b7280; font-size:.9rem; }
</style>

<div class="container-fluid p-3">
  <!-- TABS -->
  <ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
      <a class="nav-link <?= $tab === 'todays' ? 'active' : '' ?>" href="?<?= qs_with(['tab' => 'todays']) ?>">
        Today's Collection
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?= $tab === 'deposits' ? 'active' : '' ?>" href="?<?= qs_with(['tab' => 'deposits']) ?>">
        Deposits
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link <?= $tab === 'accountable' ? 'active' : '' ?>" href="?<?= qs_with(['tab' => 'accountable']) ?>">
        Accountable for Accounting Form
      </a>
    </li>
  </ul>

  <div class="tab-content">
    <!-- Today's Collection Pane -->
    <div class="tab-pane <?= $tab === 'todays' ? 'active' : '' ?>" id="todays">
      <div class="card mb-3">
        <div class="card-body shadow-sm p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
          </div>

          <?php
          // Fetch today's records
          $today = date('Y-m-d');

          $sql = "
            SELECT
              orr.issued_date,
              orr.or_number,
              orr.amount_paid,
              th.full_name,
              th.request_type
            FROM official_receipt_records AS orr
            INNER JOIN transaction_history AS th
              ON th.transaction_id = orr.transaction_id
            WHERE DATE(orr.issued_date) = ?
            ORDER BY orr.issued_date ASC
          ";

          $stmt = $conn->prepare($sql);
          if ($stmt === false) {
            echo '<div class="alert alert-danger">Database error: ' . htmlspecialchars($conn->error) . '</div>';
          } else {
            $stmt->bind_param('s', $today);
            $stmt->execute();
            $res = $stmt->get_result();

            echo '<div class="table-responsive">';
            echo '<table class="table table-custom table-striped table-sm table-hover align-middle mb-0">';
            echo '<thead class="table-light"><tr>';
            echo '<th>Date</th><th>OR Number</th><th>Name</th><th>Request Type</th><th class="text-end">Amount</th>';
            echo '</tr></thead><tbody>';

            $total = 0.00;
            if ($res->num_rows === 0) {
              echo '<tr><td colspan="5" class="text-center text-muted">No records for today (' . htmlspecialchars($today) . ').</td></tr>';
            } else {
              while ($row = $res->fetch_assoc()) {
                $issued = $row['issued_date'] ?? null;
                $issued_fmt = $issued ? date('m-d-Y H:i:s', strtotime($issued)) : '';
                $or_number = htmlspecialchars($row['or_number'] ?? '');
                $full_name = htmlspecialchars($row['full_name'] ?? '');
                $request_type = htmlspecialchars($row['request_type'] ?? '');
                $amount = (float)($row['amount_paid'] ?? 0);
                $total += $amount;
                $amount_fmt = number_format($amount, 2);

                echo '<tr>';
                echo '<td>' . $issued_fmt . '</td>';
                echo '<td>' . $or_number . '</td>';
                echo '<td>' . $full_name . '</td>';
                echo '<td>' . $request_type . '</td>';
                echo '<td class="text-end">' . $amount_fmt . '</td>';
                echo '</tr>';
              }
            }

            echo '</tbody>';
            echo '<tfoot>';
            echo '<tr class="table-secondary">';
            echo '<th colspan="4" class="text-end">Total</th>';
            echo '<th class="text-end">' . number_format($total, 2) . '</th>';
            echo '</tr>';
            echo '</tfoot>';
            echo '</table>';
            echo '</div>'; // table-responsive

            $stmt->close();
          }
          ?>

          <!-- As-of timestamp (ONLY in Today's Collection) -->
          <div class="as-of">As of <?= date('m-d-Y H:i:s') ?></div>
        </div>
      </div>
    </div>

    <!-- Deposits Pane -->
    <div class="tab-pane <?= $tab === 'deposits' ? 'active' : '' ?>" id="deposits">
      <div class="card mb-3">
        <div class="card-body shadow-sm p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
          </div>

          <?php
          // show success alert only when on deposits tab
          if ($tab === 'deposits' && isset($_GET['success']) && $_GET['success'] == '1') {
            echo '<div class="alert alert-success">Deposit saved successfully!</div>';
          } elseif ($tab === 'deposits' && isset($_GET['success']) && $_GET['success'] == '0') {
            echo '<div class="alert alert-danger">Failed to save deposit. Please try again.</div>';
          }

          $sql = "SELECT * FROM deposits ORDER BY deposit_date DESC, created_at DESC";
          $result = $conn->query($sql);

          echo '<div class="table-responsive">';
          echo '<table class="table table-custom table-striped table-sm table-hover align-middle mb-0">';
          echo '<thead class="table-light"><tr>';
          echo '<th>Bank/Branch</th><th>Date</th><th>Reference</th><th class="text-end">Amount</th>';
          echo '</tr></thead><tbody>';

          $total_deposits = 0.00;
          if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
              $bank_branch = htmlspecialchars($row['bank_branch'] ?? '');
              $deposit_date = $row['deposit_date'] ?? '';
              $deposit_date_fmt = $deposit_date ? date('m-d-Y', strtotime($deposit_date)) : '';
              $reference = htmlspecialchars($row['reference'] ?? '');
              $amount = (float)($row['amount'] ?? 0);
              $total_deposits += $amount;
              $amount_fmt = number_format($amount, 2);

              echo '<tr>';
              echo '<td>' . $bank_branch . '</td>';
              echo '<td>' . $deposit_date_fmt . '</td>';
              echo '<td>' . $reference . '</td>';
              echo '<td class="text-end">' . $amount_fmt . '</td>';
              echo '</tr>';
            }
          } else {
            echo '<tr><td colspan="4" class="text-center text-muted">No deposits recorded yet.</td></tr>';
          }

          echo '</tbody>';
          echo '<tfoot>';
          echo '<tr class="table-secondary">';
          echo '<th colspan="3" class="text-end">Total</th>';
          echo '<th class="text-end">' . number_format($total_deposits, 2) . '</th>';
          echo '</tr>';
          echo '</tfoot>';
          echo '</table>';
          echo '</div>'; // table-responsive
          ?>

          <!-- Add New button placed below the table and aligned to the lower-left -->
          <div class="below-table-actions">
            <button type="button" class="btn btn-primary" onclick="openDepositModal()">+ Add New</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Accountable for Accounting Form Pane -->
    <div class="tab-pane <?= $tab === 'accountable' ? 'active' : '' ?>" id="accountable">
      <div class="card mb-3">
        <div class="card-body shadow-sm p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
          </div>

          <?php
          // show success/failure alerts for accountable tab
          if ($tab === 'accountable' && isset($_GET['success'])) {
            if ($_GET['success'] == '1') {
              echo '<div class="alert alert-success">Accountable form saved successfully!</div>';
            } else {
              echo '<div class="alert alert-danger">Failed to save accountable form. Please try again.</div>';
            }
          }

          // Fetch accountables from accountable_forms table
          $sql = "SELECT * FROM accountable_forms ORDER BY created_at DESC";
          $res = $conn->query($sql);

          echo '<div class="table-accountable">';
          echo '<table class="table table-custom table-sm table-hover align-middle mb-0">';
          // header row 1
          echo '<thead class="table-light">';
          echo '<tr>';
          echo '<th rowspan="2">Name of Form and No.</th>';
          echo '<th rowspan="2">Form Type</th>';
          echo '<th colspan="3" class="text-center">Beginning Balance</th>';
          echo '<th colspan="3" class="text-center">Receipt</th>';
          echo '<th colspan="3" class="text-center">Issued</th>';
          echo '<th colspan="3" class="text-center">Ending Balance</th>';
          echo '</tr>';
          // header row 2 - subcolumns
          echo '<tr>';
          for ($i = 0; $i < 4; $i++) {
            echo '<th>Qty</th><th>From</th><th>To</th>';
          }
          echo '</tr>';
          echo '</thead>';
          echo '<tbody>';

          if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
              // Use real column names
              $form_name = htmlspecialchars($row['form_name'] ?? '');
              $form_type = htmlspecialchars($row['form_type'] ?? '');

              $b_qty = show_nullable($row['beginning_balance_quantity'] ?? null);
              $b_from = show_nullable($row['beginning_balance_from'] ?? null);
              $b_to  = show_nullable($row['beginning_balance_to'] ?? null);

              $r_qty = show_nullable($row['receipt_quantity'] ?? null);
              $r_from = show_nullable($row['receipt_from'] ?? null);
              $r_to  = show_nullable($row['receipt_to'] ?? null);

              $i_qty = show_nullable($row['issued_quantity'] ?? null);
              $i_from = show_nullable($row['issued_from'] ?? null);
              $i_to  = show_nullable($row['issued_to'] ?? null);

              $e_qty = show_nullable($row['ending_balance_quantity'] ?? null);
              $e_from = show_nullable($row['ending_balance_from'] ?? null);
              $e_to  = show_nullable($row['ending_balance_to'] ?? null);

              echo '<tr>';
              echo '<td>' . $form_name . '</td>';
              echo '<td>' . $form_type . '</td>';
              echo '<td>' . $b_qty . '</td><td>' . $b_from . '</td><td>' . $b_to . '</td>';
              echo '<td>' . $r_qty . '</td><td>' . $r_from . '</td><td>' . $r_to . '</td>';
              echo '<td>' . $i_qty . '</td><td>' . $i_from . '</td><td>' . $i_to . '</td>';
              echo '<td>' . $e_qty . '</td><td>' . $e_from . '</td><td>' . $e_to . '</td>';
              echo '</tr>';
            }
          } else {
            echo '<tr><td colspan="14" class="text-center text-muted">No Accountable for Accounting Form records found.</td></tr>';
          }

          echo '</tbody>';
          echo '</table>';
          echo '</div>'; // table-accountable
          ?>

          <!-- Add New button placed below the table and aligned to the lower-left -->
          <div class="below-table-actions">
            <button type="button" class="btn btn-primary" onclick="openAccountableModal()">+ Add New</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Deposit Modal (existing; posts to functions/deposits_add.php) -->
<div id="depositModal" class="modal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          Record Deposit
        </h5>
        <button type="button" class="close" onclick="closeDepositModal()">&times;</button>
      </div>
      <form method="POST" action="functions/deposits_add.php">
        <input type="hidden" name="action" value="save_deposit">
        <div class="modal-body">
          <div class="form-group">
            <label for="bank_branch">Bank/Branch</label>
            <input type="text" class="form-control" id="bank_branch" name="bank_branch" placeholder="Enter Bank/Branch" required>
          </div>
          <div class="form-row">
            <div class="col form-group">
              <label for="deposit_date">Date</label>
              <input type="date" class="form-control" id="deposit_date" name="deposit_date" required>
            </div>
            <div class="col form-group">
              <label for="reference">Reference</label>
              <input type="text" class="form-control" id="reference" name="reference" placeholder="Enter Reference" required>
            </div>
          </div>
          <div class="form-group">
            <label for="amount">Amount</label>
            <input type="number" step="0.01" class="form-control" id="amount" name="amount" placeholder="Enter Amount" required min="0.01">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeDepositModal()">Cancel</button>
          <button type="submit" class="btn btn-success">Save Deposit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Accountable Add Modal -->
<div id="accountableModal" class="modal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          Add Accountable Form
        </h5>
        <button type="button" class="close" onclick="closeAccountableModal()">&times;</button>
      </div>

      <form method="POST" action="functions/accountable_add.php">
        <div class="modal-body">
          <div class="form-group">
            <label for="form_name_no">Name of Form and No.</label>
            <input type="text" class="form-control" id="form_name_no" name="form_name_no" placeholder="e.g., Official Receipt Form No. ___" required>
          </div>

          <div class="form-group">
            <label>Form Type</label>
            <div>
              <label style="margin-right:1rem;">
                <input type="radio" name="form_type" value="With Money Value" checked> With Money Value
              </label>
              <label>
                <input type="radio" name="form_type" value="Without Money Value"> Without Money Value
              </label>
            </div>
          </div>

          <hr>
          <h6>Beginning Balance</h6>
          <div class="form-row">
            <div class="col form-group">
              <label for="beginning_qty">Qty</label>
              <input type="number" class="form-control" id="beginning_qty" name="beginning_qty" min="0" step="1">
            </div>
            <div class="col form-group">
              <label for="beginning_serial_from">Inclusive Serial No. From</label>
              <input type="text" class="form-control" id="beginning_serial_from" name="beginning_serial_from" placeholder="From">
            </div>
            <div class="col form-group">
              <label for="beginning_serial_to">Inclusive Serial No. To</label>
              <input type="text" class="form-control" id="beginning_serial_to" name="beginning_serial_to" placeholder="To">
            </div>
          </div>

          <hr>
          <h6>Receipt</h6>
          <div class="form-row">
            <div class="col form-group">
              <label for="receipt_qty">Qty</label>
              <input type="number" class="form-control" id="receipt_qty" name="receipt_qty" min="0" step="1">
            </div>
            <div class="col form-group">
              <label for="receipt_serial_from">Inclusive Serial No. From</label>
              <input type="text" class="form-control" id="receipt_serial_from" name="receipt_serial_from">
            </div>
            <div class="col form-group">
              <label for="receipt_serial_to">Inclusive Serial No. To</label>
              <input type="text" class="form-control" id="receipt_serial_to" name="receipt_serial_to">
            </div>
          </div>

          <hr>
          <h6>Issued</h6>
          <div class="form-row">
            <div class="col form-group">
              <label for="issued_qty">Qty</label>
              <input type="number" class="form-control" id="issued_qty" name="issued_qty" min="0" step="1">
            </div>
            <div class="col form-group">
              <label for="issued_serial_from">Inclusive Serial No. From</label>
              <input type="text" class="form-control" id="issued_serial_from" name="issued_serial_from">
            </div>
            <div class="col form-group">
              <label for="issued_serial_to">Inclusive Serial No. To</label>
              <input type="text" class="form-control" id="issued_serial_to" name="issued_serial_to">
            </div>
          </div>

          <hr>
          <h6>Ending Balance</h6>
          <div class="form-row">
            <div class="col form-group">
              <label for="ending_qty">Qty</label>
              <input type="number" class="form-control" id="ending_qty" name="ending_qty" min="0" step="1">
            </div>
            <div class="col form-group">
              <label for="ending_serial_from">Inclusive Serial No. From</label>
              <input type="text" class="form-control" id="ending_serial_from" name="ending_serial_from">
            </div>
            <div class="col form-group">
              <label for="ending_serial_to">Inclusive Serial No. To</label>
              <input type="text" class="form-control" id="ending_serial_to" name="ending_serial_to">
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeAccountableModal()">Cancel</button>
          <button type="submit" class="btn btn-success">Save</button>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
function openDepositModal() {
  document.getElementById('depositModal').classList.add('show');
}

function closeDepositModal() {
  document.getElementById('depositModal').classList.remove('show');
  const f = document.getElementById('depositModal').querySelector('form');
  if (f) f.reset();
}

function openAccountableModal() {
  document.getElementById('accountableModal').classList.add('show');
}
function closeAccountableModal() {
  const m = document.getElementById('accountableModal');
  m.classList.remove('show');
  const f = m.querySelector('form');
  if (f) f.reset();
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
  const depositModal = document.getElementById('depositModal');
  const accountableModal = document.getElementById('accountableModal');
  if (event.target === depositModal) closeDepositModal();
  if (event.target === accountableModal) closeAccountableModal();
});
</script>
