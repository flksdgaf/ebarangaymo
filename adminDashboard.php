<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];

$currentRole = $_SESSION['loggedInUserRole'] ?? '';

// what each role is allowed to do on the request page
$rolePermissions = [
  'Brgy Captain' => [],
  'Brgy Secretary' => [],
  'Brgy Bookkeeper' => [],
  'Brgy Treasurer' => [], 
  'Brgy Kagawad' => [], 
  'Lupon Tagapamayapa' => [],
];

$perms = $rolePermissions[$currentRole] ?? [];

// PAGINATION SETUP
$page_num = isset($_GET['page_num']) && is_numeric($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$limit = 10; 
$offset = ($page_num - 1) * $limit;

// PULL IN FILTERS
$request_type = $_GET['request_type'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$document_status = $_GET['document_status'] ?? '';
$search = trim($_GET['search'] ?? '');

// BUILD WHERE CLAUSES
$whereClauses = [];
$bindTypes = '';
$bindParams = [];

// GLOBAL FULL-TEXT SEARCH
if ($search !== '') {
  $whereClauses[] = "(transaction_id LIKE ? OR full_name LIKE ? OR request_type LIKE ? OR payment_method LIKE ? OR payment_status LIKE ? OR document_status LIKE ?)";
  $bindTypes .= 'ssssss';
  $term = "%{$search}%";
  $bindParams = array_merge($bindParams, array_fill(0, 6, $term));
}

// INDIVIDUAL FILTERS
if ($request_type) {
  $whereClauses[] = 'request_type = ?';
  $bindTypes .= 's';
  $bindParams[] = $request_type;
}
if ($payment_method) {
  $whereClauses[] = 'payment_method = ?';
  $bindTypes .= 's';
  $bindParams[] = $payment_method;
}
if ($payment_status) {
  $whereClauses[] = 'payment_status = ?';
  $bindTypes .= 's';
  $bindParams[] = $payment_status;
}
if ($document_status) {
  $whereClauses[] = 'document_status = ?';
  $bindTypes .= 's';
  $bindParams[] = $document_status;
}

// Purok filter for pie chart
$allPuroks = ['purok1_rbi','purok2_rbi','purok3_rbi','purok4_rbi','purok5_rbi','purok6_rbi'];
$selectedPurok = $_GET['purok'] ?? ''; 
if (in_array($selectedPurok, $allPuroks)) {
  // only the selected purok
  $purokTables = [ $selectedPurok ];
} else {
  // default: all puroks
  $purokTables = $allPuroks;
}

// EXCLUDE PAID AND RELEASED (NOT FINAL)
// $whereClauses[] = "NOT (payment_status = 'Paid' AND document_status = 'Released')";

// Only show rows from the current week 
$whereClauses[] = "YEAR(created_at) = YEAR(CURDATE()) AND WEEK(created_at,1) = WEEK(CURDATE(),1)";

// BUILD WHERE CLAUSE
$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
  
// COUNT TOTAL WITH FILTERS
$countSql = "SELECT COUNT(*) FROM view_dashboard {$whereSQL}";
$cst = $conn->prepare($countSql);
if (! empty($bindTypes)) {
  $cst->bind_param($bindTypes, ...$bindParams);
}

$cst->execute();
$total = $cst->get_result()->fetch_row()[0];
$cst->close();

$pages = max(1, ceil($total / $limit));

// FETCH PAGE WITH FILTERS
$sql = "SELECT transaction_id, full_name, request_type, payment_method, payment_status, document_status FROM view_dashboard {$whereSQL} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$st = $conn->prepare($sql);

// BIND FILTERS & PAGINATION
if ($whereClauses) {
  $fullTypes = $bindTypes . 'ii';
  $allParams = [];
  $allParams[] = $fullTypes;

  foreach ($bindParams as $k => $v) {
    $allParams[] = & $bindParams[$k];
  }
  $allParams[] = & $limit;
  $allParams[] = & $offset;

  call_user_func_array([$st, 'bind_param'], $allParams);
} else {
  $st->bind_param('ii', $limit, $offset);
}

$st->execute();
$result = $st->get_result();

// BUILD QUERYSTRING FOR PAGINATION LINKS
$qs = $_GET;
unset($qs['page_num']); 
$queryString = http_build_query($qs);
if ($queryString) {
  $queryString .= '&';
}
?>

<title>eBarangay Mo | Dashboard</title>

<!-- MAIN CONTENT -->
<div class="container-fluid p-3">
  <?php
  // TOTAL USERS
  $userCount = $conn->query("SELECT COUNT(*) FROM user_accounts")->fetch_row()[0] ?? 0;

  // TOTAL SERVICES
  $serviceTables = [
    'barangay_id_requests',
    'business_permit_requests',
    'good_moral_requests',
    'guardianship_requests',
    'indigency_requests',
    'residency_requests',
    'solo_parent_requests',
    // 'blotter_records',
    // 'complaint_records',
    // 'katarungan_pambarangay_records',
  ];
  $serviceCount = 0;
  foreach ($serviceTables as $tbl) {
    $cnt = $conn->query("SELECT COUNT(*) FROM {$tbl}")->fetch_row()[0] ?? 0;
    $serviceCount += $cnt;
  }

  // 3. TOTAL PENDING ACCOUNT REQUESTS
  $pendingRequests = $conn->query("SELECT COUNT(*) FROM pending_accounts")->fetch_row()[0] ?? 0;

  // 4. TOTAL RESIDENTS (always show all)
  $residentsCount = 0;
  foreach ($allPuroks as $tbl) {
    $cnt = $conn->query("SELECT COUNT(*) FROM {$tbl}")->fetch_row()[0] ?? 0;
    $residentsCount += $cnt;
  }

  // AGE GROUP COUNTS
  $ageGroups = [
    'Children (<18)' => 0,
    'Adults (18–59)' => 0,
    'Senior Citizens (60+)' => 0,
  ];
  
  function getAge($birthdate) {
    $dob = new DateTime($birthdate);
    return $dob->diff(new DateTime())->y;
  }
  foreach ($purokTables as $tbl) {
    $res = $conn->query("SELECT birthdate FROM {$tbl}");
    while ($r = $res->fetch_assoc()) {
      $age = getAge($r['birthdate']);
      if ($age < 18) {
        $ageGroups['Children (<18)']++;
      } elseif ($age < 60) {
        $ageGroups['Adults (18–59)']++;
      } else {
        $ageGroups['Senior Citizens (60+)']++;
      }
    }
  }

  // BUILD STATS ARRAY FOR DASHBOARD
  $stats = [
    ['icon' => 'group', 'label' => 'Users', 'count' => $userCount],
    ['icon' => 'description', 'label' => 'Service Requests', 'count' => $serviceCount],
    ['icon' => 'diversity_3', 'label' => 'Residents', 'count' => $residentsCount],
    ['icon' => 'person_add', 'label' => 'Account Requests', 'count' => $pendingRequests],
  ];
  ?>
  
  <div class="row g-4 mb-4">
    <?php foreach ($stats as $stat): ?>
      <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm text-center p-3">
          <span class="material-symbols-outlined fs-2 text-success"><?= $stat['icon'] ?></span>
          <h2 class="fw-bold"><?= number_format($stat['count']) ?></h2>
          <p class="text-muted"><?= htmlspecialchars($stat['label']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- New Card #1: Pie Chart with Purok Filter -->
    <div class="col-md-6 col-sm-12">
      <div class="card shadow-sm p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="card-title mb-0 fs-5 text-muted">Residents by Age Group</h5>

          <form method="get" class="d-flex align-items-center" style="gap:.5rem">
            <?php 
              foreach ($_GET as $k => $v) {
                if ($k !== 'purok') {
                  echo "<input type='hidden' name='".htmlspecialchars($k)."' value='".htmlspecialchars($v)."'>";
                }
              }
            ?>

            <select name="purok" class="form-select form-select-sm" style="font-size:.875rem" onchange="this.form.submit()">
              <option value="">All</option>
              <option value="purok1_rbi" <?= $selectedPurok==='purok1_rbi'?'selected':'' ?>>Purok 1</option>
              <option value="purok2_rbi" <?= $selectedPurok==='purok2_rbi'?'selected':'' ?>>Purok 2</option>
              <option value="purok3_rbi" <?= $selectedPurok==='purok3_rbi'?'selected':'' ?>>Purok 3</option>
              <option value="purok4_rbi" <?= $selectedPurok==='purok4_rbi'?'selected':'' ?>>Purok 4</option>
              <option value="purok5_rbi" <?= $selectedPurok==='purok5_rbi'?'selected':'' ?>>Purok 5</option>
              <option value="purok6_rbi" <?= $selectedPurok==='purok6_rbi'?'selected':'' ?>>Purok 6</option>
            </select>
          </form>
        </div>

        <?php if (array_sum($ageGroups) > 0): ?>
          <canvas id="agePieChart" style="max-height:200px;"></canvas>
        <?php else: ?>
          <p class="text-center text-muted my-5">
            No resident data for <?= $selectedPurok ? preg_replace('/^purok(\d+)_rbi$/i','Purok $1',$selectedPurok) : 'any purok' ?> yet.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Requests Table -->
  <div class="col-12">
    <div class="card p-3 shadow-sm">
      <div class="d-flex align-items-center mb-3">
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">filter_list</span>
            Filter
          </button>
          <div class="dropdown-menu p-3" aria-labelledby="filterDropdown" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
            <form method="get" class="mb-0" id="filterForm">
              <input type="hidden" name="page_num" value="1">
              
              <!-- Request Type -->
              <div class="mb-2">
                <label class="form-label mb-1">Request Type</label>
                <select name="request_type" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <?= $request_type ==='Barangay ID'?'selected':'' ?> value="Barangay ID">Barangay ID</option>
                  <option <?= $request_type ==='Business Permit'?'selected':'' ?> value="Business Permit">Business Permit</option>
                  <option <?= $request_type ==='Good Moral'?'selected':''?> value="Good Moral">Good Moral</option>
                  <option <?= $request_type ==='Guardianship'?'selected':''?> value="Guardianship">Guardianship</option>
                  <option <?= $request_type ==='Indigency'?'selected':''?> value="Indigency">Indigency</option>
                  <option <?= $request_type ==='Residency'?'selected':''?> value="Residency">Residency</option>
                  <option <?= $request_type ==='Solo Parent'?'selected':''?> value="Solo Parent">Solo Parent</option>
                  </select>
              </div>

              <!-- Payment Method -->
              <!-- <div class="mb-2">
                <label class="form-label mb-1">Payment Method</label>
                <select name="payment_method" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <= $payment_method ==='GCash'?'selected':'' ?> value="GCash">GCash</option>
                  <option <= $payment_method ==='Brgy Payment Device'?'selected':'' ?> value="Brgy Payment Device">Brgy Payment Device</option>
                  <option <= $payment_method ==='Over-the-Counter'?'selected':'' ?> value="Over-the-Counter">Over-the-Counter</option>
                </select>
              </div> -->

              <!-- Payment Status -->
              <div class="mb-2">
                <label class="form-label mb-1">Payment Status</label>
                <select name="payment_status" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <?= $payment_status ==='Paid'?'selected':'' ?> value="Paid">Paid</option>
                  <option <?= $payment_status ==='Unpaid'?'selected':'' ?> value="Unpaid">Unpaid</option>
                </select>
              </div>

              <!-- Document Status -->
              <div class="mb-2">
                <label class="form-label mb-1">Document Status</label>
                <select name="document_status" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <?= $document_status ==='For Verification'?'selected':'' ?> value="For Verification">For Verification</option>
                  <option <?= $document_status ==='Processing'?'selected':'' ?> value="Processing">Processing</option>
                  <option <?= $document_status ==='Ready to Release'?'selected':'' ?> value="Ready to Release">Ready to Release</option>
                  <option <?= $document_status ==='Released'?'selected':'' ?> value="Released">Released</option>
                  <option <?= $document_status ==='Rejected'?'selected':'' ?> value="Rejected">Rejected</option>
                </select>
              </div>

              <div class="d-flex">
                <a href="?page_num=1" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
                <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
              </div>
            </form>
          </div>
        </div>

        <!-- NEW: Requests This Week title with count -->
        <h5 class="card-title mb-0 mx-3 fs-5 text-muted">
          Total Requests This Week (<?= number_format($total) ?>)
        </h5>

        <form method="get" id="searchForm" class="d-flex ms-auto me-2">
          <input type="hidden" name="page_num" value="1">
          <?php foreach ([
            'request_type','date_from','date_to',
            'claim_from','claim_to',
            'payment_method','payment_status','document_status'
          ] as $f):
              if (!empty($_GET[$f])): ?>
            <input type="hidden" name="<?= $f ?>" value="<?= htmlspecialchars($_GET[$f]) ?>">
          <?php endif; endforeach; ?>

          <div class="input-group input-group-sm">
            <input id="searchInput" name="search" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" type="button">
              <span class="material-symbols-outlined" id="searchIcon">
                <?= $search ? 'close' : 'search' ?>
              </span>
            </button>
          </div>
        </form>
      </div>

      <div class="table-responsive admin-table">
        <table class="table table-hover align-middle text-start">
          <thead class="table-light">
            <tr>
              <th class="text-nowrap">Transaction No.</th>
              <th class="text-nowrap">Name</th>
              <th class="text-nowrap">Request</th>
              <th class="text-nowrap">Payment Status</th>
              <th class="text-nowrap">Document Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): 
                // extract and escape
                $txn = htmlspecialchars($row['transaction_id']);
                $name = htmlspecialchars($row['full_name']);
                $req = htmlspecialchars($row['request_type']);
                $ps = htmlspecialchars($row['payment_status']);
                $ds = htmlspecialchars($row['document_status']);
                
                // badge classes
                $payClass = $ps === 'Paid' ? 'paid-status' : 'unpaid-status';
                switch ($ds) {
                  case 'For Verification': $docClass = 'for-verification-status'; break;
                  case 'Processing': $docClass = 'processing-status'; break;
                  case 'Ready to Release': $docClass = 'ready-to-release-status'; break;
                  case 'Released': $docClass = 'released-status'; break;
                  case 'Rejected': $docClass = 'rejected-status'; break;
                  default: $docClass = ''; break;
                }
              ?>
              <tr>
                <td class="text-nowrap"><?= $txn ?></td>
                <td class="text-nowrap"><?= $name ?></td>
                <td class="text-nowrap"><?= $req ?></td>
                <td class="text-nowrap"><span class="badge <?= $payClass ?>"><?= $ps ?></span></td>
                <td class="text-nowrap"><span class="badge <?= $docClass ?>"><?= $ds ?></span></td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center">No records found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination Controls -->
      <?php if ($pages > 1): ?>
      <nav aria-label="Request pagination">
        <ul class="pagination justify-content-center pagination-sm">
          <li class="page-item<?= $page_num<=1?' disabled':'' ?>">
            <a class="page-link" href="?<?= $queryString ?>page_num=<?= max(1,$page_num-1) ?>">Prev</a>
          </li>

          <?php if ($page_num>3): ?>
            <li class="page-item"><a class="page-link" href="?<?= $queryString ?>page_num=1">1</a></li>
            <?php if ($page_num>4): ?>
              <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
          <?php endif; ?>

          <?php
          $start = max(1, $page_num-2);
          $end = min($pages, $page_num+2);
          for ($i=$start; $i<=$end; $i++): ?>
            <li class="page-item<?= $i==$page_num?' active':'' ?>">
              <a class="page-link" href="?<?= $queryString ?>page_num=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>

          <?php if ($page_num<$pages-2): ?>
            <?php if ($page_num<$pages-3): ?>
              <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
            <li class="page-item"><a class="page-link" href="?<?= $queryString ?>page_num=<?= $pages ?>"><?= $pages ?></a></li>
          <?php endif; ?>

          <li class="page-item<?= $page_num>=$pages?' disabled':'' ?>">
            <a class="page-link" href="?<?= $queryString ?>page_num=<?= min($pages,$page_num+1) ?>">Next</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Search/clear logic
  const form = document.getElementById('searchForm');
  const input = document.getElementById('searchInput');
  const btn = document.getElementById('searchBtn');
  const hasSearch  = <?= $search !== '' ? 'true' : 'false' ?>;
  
  btn.addEventListener('click', () => {
    if (hasSearch) input.value = '';
    form.submit();
  });

  const ctx = document.getElementById('agePieChart').getContext('2d');
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: <?= json_encode(array_keys($ageGroups)) ?>,
      datasets: [{data: <?= json_encode(array_values($ageGroups)) ?>,
      backgroundColor: [
          '#198754',  
          '#20c997',  
          '#28a745' 
        ]
      }]
    },
    options: {responsive: true,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.formattedValue } }
      }
    }
  });
});
</script>