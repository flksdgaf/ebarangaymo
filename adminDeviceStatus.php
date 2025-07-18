<?php 
// adminDeviceStatus.php
include 'functions/dbconn.php';

// 1) Fetch coin counts for this device
$deviceName = 'IOTPS-Magang-01';
$stmt = $conn->prepare("
  SELECT one_peso, five_peso, ten_peso, twenty_peso,
         twenty_bill, fifty_bill, one_hundred_bill, two_hundred_bill
    FROM collection_table
   WHERE device_id = ?
   LIMIT 1
");
$stmt->bind_param('s', $deviceName);
$stmt->execute();
$stmt->bind_result($c1, $c5, $c10, $c20, $b20, $b50, $b100, $b200);
$stmt->fetch();
$stmt->close();

// 2) Compute totals
$amount1   = $c1   * 1;
$amount5   = $c5   * 5;
$amount10  = $c10  * 10;
$amount20  = $c20  * 20;
$amountB20  = $b20  * 20;
$amountB50  = $b50  * 50;
$amountB100 = $b100 * 100;
$amountB200 = $b200 * 200;

$totalAmount = $amount1 + $amount5 + $amount10 + $amount20 + $amountB20 + $amountB50 + $amountB100 + $amountB200;

$conn->close();
?>

<title>eBarangay Mo | Device Status</title>

<div class="container-fluid p-3">
  <div class="row g-3">
    <div class="col-12">
      <div class="card p-3 shadow-sm mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Main Status</h5>
          <small id="status-clock" class="text-muted">As of --/--/---- --:--:--</small>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
          <!-- Device ID -->
          <div class="col-md-4 col-sm-6">
            <div class="card shadow-sm text-center p-3">
              <span class="material-symbols-outlined fs-1 text-success">devices</span>
              <h2 class="fw-bold text-success">IOTPS-Magang-01</h2>
              <p class="text-muted mb-0">Device ID</p>
            </div>
          </div>

          <!-- Turned On/Off (AJAX‐updated) -->
          <div class="col-md-4 col-sm-6">
            <div class="card shadow-sm text-center p-3">
              <span id="status-icon" class="material-symbols-outlined fs-1 text-danger">power</span>
              <h2 id="status-text" class="fw-bold text-danger">Off</h2>
              <p class="text-muted mb-0">Power Status</p>
            </div>
          </div>    

          <!-- Total collected -->
          <div class="col-md-4 col-sm-6">
            <div class="card shadow-sm text-center p-3">
              <span class="material-symbols-outlined fs-1 text-warning">payments</span>
              <h2 id="total-amount" class="fw-bold text-warning">
                Php <?= number_format($totalAmount, 2) ?>
              </h2>
              <p class="text-muted mb-0">Total Amount Collected</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Coin Counter -->
    <div class="col-md-6">
      <div class="card p-3 shadow-sm mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Coin Counter</h5>
          <small id="coin-clock" class="text-muted">As of --/--/---- --:--:--</small>
        </div>

        <!-- First row: 1 & 5 Peso -->
        <div class="row g-3 mb-3">
          <div class="col-6">
            <div class="card shadow-sm text-center p-3">
              <span class="material-symbols-outlined fs-1 text-warning">monetization_on</span>
              <h2 id="count-1" class="fw-bold text-warning"><?= $c1 > 0 ? $c1 : '0' ?></h2>
              <p class="text-muted mb-0">₱1 Coin</p>
            </div>
          </div>
          <div class="col-6">
            <div class="card shadow-sm text-center p-3">
              <span class="material-symbols-outlined fs-1 text-warning">monetization_on</span>
              <h2 id="count-5" class="fw-bold text-warning"><?= $c5 > 0 ? $c5 : '0' ?></h2>
              <p class="text-muted mb-0">₱5 Coin</p>
            </div>
          </div>
        </div>

        <!-- Second row: 10 & 20 Peso -->
        <div class="row g-3">
          <div class="col-6">
            <div class="card shadow-sm text-center p-3">
              <span class="material-symbols-outlined fs-1 text-warning">monetization_on</span>
              <h2 id="count-10" class="fw-bold text-warning"><?= $c10 > 0 ? $c10 : '0' ?></h2>
              <p class="text-muted mb-0">₱10 Coin</p>
            </div>
          </div>
          <div class="col-6">
            <div class="card shadow-sm text-center p-3">
              <span class="material-symbols-outlined fs-1 text-warning">monetization_on</span>
              <h2 id="count-20" class="fw-bold text-warning"><?= $c20 > 0 ? $c20 : '0' ?></h2>
              <p class="text-muted mb-0">₱20 Coin</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Paper Bills Counter -->
    <div class="col-md-6">
      <div class="card p-3 shadow-sm mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Paper Bills Counter</h5>
          <small id="bill-clock" class="text-muted">As of --/--/---- --:--:--</small>
        </div>

        <!-- First row: 20 & 50 Bill -->
        <div class="row g-3 mb-3">
          <div class="col-6">
            <div class="card shadow-sm text-center p-3">
              <span class="material-symbols-outlined fs-1 text-success">payments</span>
              <h2 id="bill-20" class="fw-bold text-success"><?= $b20 > 0 ? $b20 : '0' ?></h2>
              <p class="text-muted mb-0">₱20 Bill</p>
            </div>
          </div>
          <div class="col-6">
            <div class="card shadow-sm text-center p-3">
              <span class="material-symbols-outlined fs-1 text-success">payments</span>
              <h2 id="bill-50" class="fw-bold text-success"><?= $b50 > 0 ? $b50 : '0' ?></h2>
              <p class="text-muted mb-0">₱50 Bill</p>
            </div>
          </div>
        </div>

        <!-- Second row: 100 & 200 Bill -->
        <div class="row g-3">
          <div class="col-6">
            <div class="card shadow-sm text-center p-3">
              <span class="material-symbols-outlined fs-1 text-success">payments</span>
              <h2 id="bill-100" class="fw-bold text-success"><?= $b100 > 0 ? $b100 : '0' ?></h2>
              <p class="text-muted mb-0">₱100 Bill</p>
            </div>
          </div>
          <div class="col-6">
            <div class="card shadow-sm text-center p-3">
              <span class="material-symbols-outlined fs-1 text-success">payments</span>
              <h2 id="bill-200" class="fw-bold text-success"><?= $b200 > 0 ? $b200 : '0' ?></h2>
              <p class="text-muted mb-0">₱200 Bill</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <!-- Collections Table -->
  <div class="row g-3">
    <div class="col-12">
      <div class="card p-3 shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Collection History</h5>
          <button class="btn btn-success btn-sm">Filter</button>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle text-start">
            <thead class="table-light">
              <tr>
                <th>Device Name</th>
                <th>User</th>
                <th>Timestamp</th>
                <th>Amount</th>
                <th class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <!-- Example Row -->
              <tr>
                <td>IOTPS-Magang-01</td>
                <td>John Doe</td>
                <td>01-01-2025 10:00 AM</td>
                <td>Php 100.00</td>
                <td class="text-center">
                  <button class="btn btn-primary btn-sm me-2">View</button>
                  <button class="btn btn-success btn-sm">Edit</button>
                </td>
              </tr>
              <!-- More rows if needed -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
const API = 'functions/status_api.php?device_name=IOTPS-Magang';

async function refreshStatus() {
  try {
    const res  = await fetch(API);
    const data = await res.json();

    // — Heartbeat updates (as before) —
    document.getElementById('status-icon').className =
      `material-symbols-outlined fs-1 ${data.iconClass}`;
    const st = document.getElementById('status-text');
    st.className   = `fw-bold ${data.statusClass}`;
    st.textContent = data.statusText;
    document.getElementById('status-clock')
      .textContent = `As of ${data.timestamp}`;

    // — Total Amount —
    const totalEl = document.getElementById('total-amount');
    totalEl.textContent = `Php ${data.total_amount}`;
    document.getElementById('total-clock')
      .textContent = `As of ${new Date().toLocaleString()}`;

    // — Coin counts only —
    const coinMapping = {
      '1': 'one_peso',
      '5': 'five_peso',
      '10': 'ten_peso',
      '20': 'twenty_peso'
    };

    Object.entries(coinMapping).forEach(([val, key]) => {
      const el = document.getElementById(`count-${val}`);
      if (el && data[key] !== undefined) {
        el.textContent = data[key];
      }
    });

    // — Paper bill counts —
    const billMapping = {
      '20': 'twenty_bill',
      '50': 'fifty_bill',
      '100': 'one_hundred_bill',
      '200': 'two_hundred_bill'
    };

    Object.entries(billMapping).forEach(([val, key]) => {
      const el = document.getElementById(`bill-${val}`);
      if (el && data[key] !== undefined) {
        el.textContent = data[key];
      }
    });

  } catch (e) {
    console.error('Refresh failed:', e);
  }
}

// initial load + repeat every 3 seconds
refreshStatus();
setInterval(refreshStatus, 3000);

// Returns "MM-DD-YYYY hh:mm:ss"
function nowAsOf() {
  const d = new Date();
  const pad = n => String(n).padStart(2,'0');
  const M = pad(d.getMonth()+1);
  const D = pad(d.getDate());
  const Y = d.getFullYear();
  const h = pad(d.getHours());
  const m = pad(d.getMinutes());
  const s = pad(d.getSeconds());
  return `As of ${M}-${D}-${Y} ${h}:${m}:${s}`;
}

// Update every second
function startStatusClock() {
  const el = document.getElementById('status-clock');
  if (!el) return;
  el.textContent = nowAsOf();
  setInterval(() => {
    el.textContent = nowAsOf();
  }, 1000);
}

// update the coin counter clock
function startCoinClock() {
  const el = document.getElementById('coin-clock');
  if (!el) return;
  el.textContent = nowAsOf();
  setInterval(() => { el.textContent = nowAsOf(); }, 1000);
}

// update the paper bills counter clock
function startBillClock() {
  const el = document.getElementById('bill-clock');
  if (!el) return;
  el.textContent = nowAsOf();
  setInterval(() => { el.textContent = nowAsOf(); }, 1000);
}

document.addEventListener('DOMContentLoaded', () => {
  startStatusClock();
  startCoinClock();
  startBillClock(); // added call to bill clock
});
</script>


