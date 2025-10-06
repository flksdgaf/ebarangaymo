<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];

$currentRole = $_SESSION['loggedInUserRole'] ?? '';

$isCoreAdmin = in_array($currentRole, [
    'Brgy Captain',
    'Brgy Secretary',
    'Brgy Bookkeeper',
    'Brgy Kagawad'
  ], true);

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
  $whereClauses[] = "
    (transaction_id LIKE ? OR full_name LIKE ? OR request_type LIKE ? OR payment_method LIKE ? OR payment_status LIKE ? 
    OR document_status LIKE ?)
  ";
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
$sql = "
  SELECT transaction_id, full_name, request_type, payment_method, payment_status, document_status 
  FROM view_dashboard {$whereSQL} ORDER BY created_at DESC LIMIT ? OFFSET ?
";
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
    'barangay_clearance_requests',
    'business_clearance_requests',
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
  <?php if ($isCoreAdmin): ?>
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
                  
                  // Payment status badge color
                  switch ($ps) {
                    case 'Paid':
                    case 'Free of Charge':
                      $payClass = 'bg-success';
                      break;
                    case 'Unpaid':
                    case 'Failed':
                      $payClass = 'bg-danger';
                      break;
                    case 'Pending':
                      $payClass = 'bg-warning text-dark';
                      break;
                    default:
                      $payClass = 'bg-secondary';
                  }
                  
                  // Document status badge color
                  switch ($ds) {
                    case 'For Verification':
                      $docClass = 'bg-info text-dark';
                      break;
                    case 'Processing':
                      $docClass = 'bg-warning text-dark';
                      break;
                    case 'Ready to Release':
                      $docClass = 'bg-primary';
                      break;
                    case 'Released':
                      $docClass = 'bg-success';
                      break;
                    case 'Rejected':
                      $docClass = 'bg-danger';
                      break;
                    default:
                      $docClass = 'bg-secondary';
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

  <?php elseif ($currentRole === 'Brgy Treasurer'): ?>
    <?php
    // Get current date ranges
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    
    // TODAY'S COLLECTION
    $todayCollection = 0;
    $todayQuery = "
        SELECT COALESCE(SUM(amount_paid), 0) as total 
        FROM official_receipt_records 
        WHERE DATE(issued_date) = '{$today}'
    ";
    $todayResult = $conn->query($todayQuery);
    if ($todayResult) {
        $todayCollection = $todayResult->fetch_assoc()['total'];
    }
    
    // THIS MONTH'S COLLECTION
    $monthCollection = 0;
    $monthQuery = "
        SELECT COALESCE(SUM(amount_paid), 0) as total 
        FROM official_receipt_records 
        WHERE DATE(issued_date) BETWEEN '{$monthStart}' AND '{$monthEnd}'
    ";
    $monthResult = $conn->query($monthQuery);
    if ($monthResult) {
        $monthCollection = $monthResult->fetch_assoc()['total'];
    }
    
    // TOTAL COLLECTIONS (ALL TIME)
    $totalCollection = 0;
    $totalQuery = "
        SELECT COALESCE(SUM(amount_paid), 0) as total 
        FROM official_receipt_records
    ";
    $totalResult = $conn->query($totalQuery);
    if ($totalResult) {
        $totalCollection = $totalResult->fetch_assoc()['total'];
    }
    
    // CREATED ORs COUNT (NEW)
    $createdORsCount = 0;
    $createdORsQuery = "SELECT COUNT(*) as total FROM official_receipt_records";
    $createdORsResult = $conn->query($createdORsQuery);
    if ($createdORsResult) {
        $createdORsCount = $createdORsResult->fetch_assoc()['total'];
    }
    
    // PAYMENT METHOD COUNTS FOR BAR CHART (excluding free services)
    $paymentMethodCounts = [
        'GCash' => 0,
        'Brgy Payment Device' => 0,
        'Over-the-Counter' => 0
    ];
    
    $paymentMethodQuery = "
        SELECT payment_method, COUNT(*) as count 
        FROM official_receipt_records 
        WHERE payment_method IN ('GCash', 'Brgy Payment Device', 'Over-the-Counter')
        GROUP BY payment_method
    ";
    $paymentMethodResult = $conn->query($paymentMethodQuery);
    if ($paymentMethodResult) {
        while ($row = $paymentMethodResult->fetch_assoc()) {
            $paymentMethodCounts[$row['payment_method']] = $row['count'];
        }
    }
    
    // Get pending ORs for table (exclude Indigency and First Time Job Seeker)
    $pendingORsResult = $conn->query("
        SELECT v.transaction_id, v.full_name, v.request_type, v.payment_method, v.payment_status, v.amount 
        FROM view_dashboard v
        WHERE v.payment_status = 'Paid' 
        AND v.document_status = 'Processing'
        AND v.request_type NOT IN ('Indigency', 'First Time Job Seeker')
        AND v.transaction_id NOT IN (SELECT transaction_id FROM official_receipt_records)
        ORDER BY v.created_at DESC 
        LIMIT 10
    ");
    ?>
    
    <!-- Treasurer's Dashboard -->
    <div class="row g-4 mb-4">
        <!-- Stats Cards Row -->
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm text-center p-3">
                <span class="material-symbols-outlined fs-2 text-success">receipt_long</span>
                <h2 class="fw-bold"><?= number_format($createdORsCount) ?></h2>
                <p class="text-muted">Created ORs</p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm text-center p-3">
                <span class="material-symbols-outlined fs-2 text-success">today</span>
                <h2 class="fw-bold">₱<?= number_format($todayCollection, 2) ?></h2>
                <p class="text-muted">Today's Collection</p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm text-center p-3">
                <span class="material-symbols-outlined fs-2 text-success">calendar_month</span>
                <h2 class="fw-bold">₱<?= number_format($monthCollection, 2) ?></h2>
                <p class="text-muted">This Month</p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm text-center p-3">
                <span class="material-symbols-outlined fs-2 text-success">account_balance</span>
                <h2 class="fw-bold">₱<?= number_format($totalCollection, 2) ?></h2>
                <p class="text-muted">Total Collections</p>
            </div>
        </div>
    </div>
    
    <!-- Chart Section -->
    <!-- <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm p-4">
                <div class="mb-3">
                    <h6 class="text-success mb-0" style="font-size: 1.1rem; font-weight: 600;">Payment Methods Distribution</h6>
                    <small class="text-muted">Total ORs created by payment method</small>
                </div>
                
                <canvas id="paymentMethodsChart" style="height: 300px;"></canvas> -->
                
                <!-- Legend -->
                <!-- <div class="d-flex justify-content-center mt-3" style="gap: 2rem;">
                    <div class="d-flex align-items-center">
                        <div style="width: 12px; height: 12px; background-color: #28a745; border-radius: 2px; margin-right: 8px;"></div>
                        <small class="text-muted">GCash</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <div style="width: 12px; height: 12px; background-color: #20c997; border-radius: 2px; margin-right: 8px;"></div>
                        <small class="text-muted">Payment Device</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <div style="width: 12px; height: 12px; background-color: #343a40; border-radius: 2px; margin-right: 8px;"></div>
                        <small class="text-muted">Over-the-Counter</small>
                    </div>
                </div>
            </div>
        </div>
    </div> -->
    
    <!-- Pending ORs Table -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm p-4">
                <div class="mb-3">
                    <h6 class="text-success mb-0" style="font-size: 1.1rem; font-weight: 600;">Pending ORs</h6>
                    <small class="text-muted">Paid requests awaiting official receipt</small>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead style="background-color: #f8f9fa;">
                            <tr>
                                <th class="text-muted" style="font-size: 0.875rem; font-weight: 500;">Transaction No.</th>
                                <th class="text-muted" style="font-size: 0.875rem; font-weight: 500;">Name</th>
                                <th class="text-muted" style="font-size: 0.875rem; font-weight: 500;">Request</th>
                                <th class="text-muted" style="font-size: 0.875rem; font-weight: 500;">Payment Method</th>
                                <th class="text-muted" style="font-size: 0.875rem; font-weight: 500;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pendingORsResult && $pendingORsResult->num_rows > 0): ?>
                                <?php while ($row = $pendingORsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-size: 0.875rem;"><?= htmlspecialchars($row['transaction_id']) ?></td>
                                        <td style="font-size: 0.875rem;"><?= htmlspecialchars($row['full_name']) ?></td>
                                        <td style="font-size: 0.875rem;"><?= htmlspecialchars($row['request_type']) ?></td>
                                        <td style="font-size: 0.875rem;"><?= htmlspecialchars($row['payment_method']) ?></td>
                                        <td style="font-size: 0.875rem;">₱<?= number_format($row['amount'], 2) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted" style="padding: 2rem;">No pending ORs found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

  <?php elseif ($currentRole === 'Lupon Tagapamayapa'): ?>
   <?php
    // Get current month and year
    $currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
    $currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    
    // Validate month and year
    if ($currentMonth < 1 || $currentMonth > 12) $currentMonth = date('n');
    if ($currentYear < 2020 || $currentYear > 2030) $currentYear = date('Y');
    
    // Get month name
    $monthNames = [
        1 => 'JANUARY', 2 => 'FEBRUARY', 3 => 'MARCH', 4 => 'APRIL',
        5 => 'MAY', 6 => 'JUNE', 7 => 'JULY', 8 => 'AUGUST',
        9 => 'SEPTEMBER', 10 => 'OCTOBER', 11 => 'NOVEMBER', 12 => 'DECEMBER'
    ];
    
    // Get first day of month and number of days
    $firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
    $firstDayWeek = date('w', $firstDay); // 0 = Sunday, 1 = Monday, etc.
    $daysInMonth = date('t', $firstDay);
    
    // Sample meeting data - you can replace this with actual database queries
    $meetings = [
        '2025-04-04' => [
            ['time' => '8:00 AM', 'title' => 'Yamada vs Beltran', 'type' => 'katarungan', 'status' => 'scheduled'],
            ['time' => '9:30 AM', 'title' => 'Yamada vs Beltran', 'type' => 'katarungan', 'status' => 'scheduled'],
            ['time' => '1:00 PM', 'title' => 'Yamada vs Beltran', 'type' => 'katarungan', 'status' => 'scheduled'],
            ['time' => '3:00 PM', 'title' => 'Yamada vs Beltran', 'type' => 'katarungan', 'status' => 'scheduled'],
        ],
        // Add more sample data as needed
    ];
    
    // Get today's date for highlighting
    $today = date('Y-m-d');
    $todayFormatted = date('Y-n-j');
    
    // Navigation URLs
    $prevMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
    $prevYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;
    $nextMonth = $currentMonth == 12 ? 1 : $currentMonth + 1;
    $nextYear = $currentMonth == 12 ? $currentYear + 1 : $currentYear;
    
    $prevUrl = "?month={$prevMonth}&year={$prevYear}";
    $nextUrl = "?month={$nextMonth}&year={$nextYear}";
    ?>
    
    <!-- Lupon Tagapamayapa Dashboard -->
    <div class="row g-4">
        <!-- Calendar Section -->
        <div class="col-md-8">
            <div class="card shadow-sm p-4">
                <!-- Calendar Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="text-success mb-0 fw-bold">Meeting Schedule</h5>
                    <div class="d-flex align-items-center">
                        <a href="<?= $prevUrl ?>" class="btn btn-sm btn-outline-secondary me-2">
                            <span class="material-symbols-outlined" style="font-size: 1rem;">chevron_left</span>
                        </a>
                        <h6 class="mb-0 mx-3 text-uppercase fw-bold"><?= $monthNames[$currentMonth] ?> | <?= $currentYear ?></h6>
                        <a href="<?= $nextUrl ?>" class="btn btn-sm btn-outline-secondary ms-2">
                            <span class="material-symbols-outlined" style="font-size: 1rem;">chevron_right</span>
                        </a>
                    </div>
                </div>
                
                <!-- Calendar Grid -->
                <div class="table-responsive">
                    <table class="table table-bordered" style="table-layout: fixed;">
                        <thead>
                            <tr style="height: 40px;">
                                <th class="text-center fw-bold text-muted" style="font-size: 0.875rem;">SUN</th>
                                <th class="text-center fw-bold text-muted" style="font-size: 0.875rem;">MON</th>
                                <th class="text-center fw-bold text-muted" style="font-size: 0.875rem;">TUE</th>
                                <th class="text-center fw-bold text-muted" style="font-size: 0.875rem;">WED</th>
                                <th class="text-center fw-bold text-muted" style="font-size: 0.875rem;">THU</th>
                                <th class="text-center fw-bold text-muted" style="font-size: 0.875rem;">FRI</th>
                                <th class="text-center fw-bold text-muted" style="font-size: 0.875rem;">SAT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $dayCounter = 1;
                            $weekCount = 0;
                            
                            // Calculate total weeks needed
                            $totalCells = $firstDayWeek + $daysInMonth;
                            $weeksNeeded = ceil($totalCells / 7);
                            
                            for ($week = 0; $week < $weeksNeeded; $week++):
                            ?>
                            <tr style="height: 70px;">
                                <?php for ($day = 0; $day < 7; $day++): ?>
                                    <td class="align-top p-2" style="width: 14.28%; position: relative;">
                                        <?php
                                        $cellNumber = ($week * 7) + $day + 1;
                                        $dayNumber = $cellNumber - $firstDayWeek;
                                        
                                        if ($dayNumber >= 1 && $dayNumber <= $daysInMonth):
                                            $currentDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $dayNumber);
                                            $isToday = $currentDate === $today;
                                            $hasMeetings = isset($meetings[$currentDate]);
                                        ?>
                                            <!-- Day Number -->
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <span class="fw-bold <?= $isToday ? 'text-white bg-success rounded-circle px-2 py-1' : 'text-dark' ?>" 
                                                      style="font-size: 0.875rem; min-width: 24px; text-align: center;">
                                                    <?= $dayNumber ?>
                                                </span>
                                            </div>
                                            
                                            <!-- Meeting Indicators -->
                                            <?php if ($hasMeetings): ?>
                                                <div style="font-size: 0.75rem;">
                                                    <?php foreach (array_slice($meetings[$currentDate], 0, 3) as $meeting): ?>
                                                        <div class="mb-1">
                                                            <?php if ($meeting['type'] === 'katarungan'): ?>
                                                                <div class="badge bg-success text-white w-100 text-start p-1" style="font-size: 0.7rem;">
                                                                    Katarungan Meeting
                                                                </div>
                                                            <?php elseif ($meeting['type'] === 'mediation'): ?>
                                                                <div class="badge bg-primary text-white w-100 text-start p-1" style="font-size: 0.7rem;">
                                                                    Mediation Session  
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="badge bg-secondary text-white w-100 text-start p-1" style="font-size: 0.7rem;">
                                                                    General Meeting
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Legend -->
                <div class="d-flex justify-content-center mt-3" style="gap: 2rem;">
                    <div class="d-flex align-items-center">
                        <div class="badge bg-success me-2">●</div>
                        <small class="text-muted">Unang Patawag</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="badge bg-primary me-2">●</div>
                        <small class="text-muted">Ikalawang Patawag</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="badge bg-dark me-2">●</div>
                        <small class="text-muted">Ikatlong Patawag</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Today's Schedule Sidebar -->
        <div class="col-md-4">
            <?php
            $todayMeetings = $meetings[$today] ?? [];
            $sidebarDate = date('F j, Y');
            $sidebarDay = strtoupper(date('D'));
            ?>
            
            <div class="card shadow-sm p-4">
                <div class="text-center mb-4">
                    <h6 class="text-muted mb-1"><?= $sidebarDate ?></h6>
                    <h5 class="fw-bold"><?= $sidebarDay ?></h5>
                </div>
                
                <div class="schedule-list">
                    <?php if (!empty($todayMeetings)): ?>
                        <?php foreach ($todayMeetings as $meeting): ?>
                            <div class="d-flex align-items-start mb-3">
                                <div class="badge bg-success rounded-circle me-3 mt-1" style="width: 12px; height: 12px;"></div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-success" style="font-size: 0.875rem;">
                                        <?= htmlspecialchars($meeting['time']) ?> 
                                        <?= htmlspecialchars($meeting['title']) ?>
                                    </div>
                                    <small class="text-muted">Katarungang Pambarangay</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <span class="material-symbols-outlined fs-2 mb-2 d-block">event_available</span>
                            <p>No meetings scheduled for today</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($todayMeetings) && count($todayMeetings) > 3): ?>
                    <div class="text-center mt-3">
                        <button class="btn btn-sm btn-outline-success">View All</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Search/clear logic (for Core Admin)
  const form = document.getElementById('searchForm');
  const input = document.getElementById('searchInput');
  const btn = document.getElementById('searchBtn');
  const hasSearch = <?= $search !== '' ? 'true' : 'false' ?>;
  
  if (form && input && btn) {
    btn.addEventListener('click', () => {
      if (hasSearch) input.value = '';
      form.submit();
    });
  }

  // Age Pie Chart (Core Admin Dashboard)
  if (document.getElementById('agePieChart')) {
    const pieCtx = document.getElementById('agePieChart').getContext('2d');
    new Chart(pieCtx, {
      type: 'pie',
      data: {
        labels: <?= json_encode(array_keys($ageGroups)) ?>,
        datasets: [{
          data: <?= json_encode(array_values($ageGroups)) ?>,
          backgroundColor: [
            '#198754',  
            '#20c997',  
            '#28a745' 
          ]
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { 
            callbacks: { 
              label: ctx => ctx.label + ': ' + ctx.formattedValue 
            } 
          }
        }
      }
    });
  }

  // Payment Methods Bar Chart (Treasurer Dashboard)
  if (document.getElementById('paymentMethodsChart')) {
    const barCtx = document.getElementById('paymentMethodsChart').getContext('2d');
    
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: ['GCash', 'Brgy Payment Device', 'Over-the-Counter'],
            datasets: [{
                label: 'Number of ORs',
                data: <?= json_encode(array_values($paymentMethodCounts)) ?>,
                backgroundColor: [
                    '#28a745',  // GCash - green
                    '#20c997',  // Payment Device - teal
                    '#343a40'   // Over-the-Counter - dark
                ],
                borderWidth: 0,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'ORs Created: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#6c757d',
                        font: {
                            size: 12
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        color: '#6c757d',
                        font: {
                            size: 12
                        }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            }
        }
    });
  }
});
</script>