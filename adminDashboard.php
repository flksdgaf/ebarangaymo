<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];

// PAGINATION SETUP
$page_num = isset($_GET['page_num']) && is_numeric($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$limit    = 10; 
$offset   = ($page_num - 1) * $limit;

// PULL IN FILTERS
$request_type = $_GET['request_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$claim_from = $_GET['claim_from'] ?? '';
$claim_to = $_GET['claim_to'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$document_status = $_GET['document_status'] ?? '';
$search = trim($_GET['search'] ?? '');

// BUILD WHERE CLAUSES
$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

// GLOBAL FULL-TEXT SEARCH
if ($search !== '') {
  $whereClauses[] = "(
    transaction_id LIKE ? OR
    full_name        LIKE ? OR
    request_type     LIKE ? OR
    payment_method   LIKE ? OR
    payment_status   LIKE ? OR
    document_status  LIKE ?
  )";
  $bindTypes .= 'ssssss';
  $term = "%{$search}%";
  $bindParams = array_merge($bindParams, array_fill(0, 6, $term));
}

// INDIVIDUAL FILTERS
if ($request_type) {
  $whereClauses[] = 'request_type = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $request_type;
}
if ($payment_method) {
  $whereClauses[] = 'payment_method = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $payment_method;
}
if ($payment_status) {
  $whereClauses[] = 'payment_status = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $payment_status;
}
if ($document_status) {
  $whereClauses[] = 'document_status = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $document_status;
}

$whereClauses[] = "NOT (payment_status = 'Paid' AND document_status = 'Released')";

// DATE RANGE
if ($date_from && $date_to) {
  $whereClauses[] = 'DATE(created_at) BETWEEN ? AND ?';
  $bindTypes    .= 'ss';
  $bindParams[]  = $date_from;
  $bindParams[]  = $date_to;
} elseif ($date_from) {
  $whereClauses[] = 'DATE(created_at) >= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $date_from;
} elseif ($date_to) {
  $whereClauses[] = 'DATE(created_at) <= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $date_to;
}

// CLAIM DATE RANGE
if ($claim_from && $claim_to) {
  $whereClauses[] = 'DATE(claim_date) BETWEEN ? AND ?';
  $bindTypes    .= 'ss';
  $bindParams[]  = $claim_from;
  $bindParams[]  = $claim_to;
} elseif ($claim_from) {
  $whereClauses[] = 'DATE(claim_date) >= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $claim_from;
} elseif ($claim_to) {
  $whereClauses[] = 'DATE(claim_date) <= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $claim_to;
}

$whereSQL = $whereClauses
  ? 'WHERE ' . implode(' AND ', $whereClauses)
  : '';
  
// COUNT TOTAL WITH FILTERS
$countSql = "SELECT COUNT(*) FROM view_general_requests {$whereSQL}";
$cst = $conn->prepare($countSql);
if (! empty($bindTypes)) {
  $cst->bind_param($bindTypes, ...$bindParams);
}

// if ($whereClauses) {
//   $cst->bind_param($bindTypes, ...$bindParams);
// }

$cst->execute();
$total = $cst->get_result()->fetch_row()[0];
$cst->close();

$pages = max(1, ceil($total / $limit));

// FETCH PAGE WITH FILTERS
$sql = "
    SELECT transaction_id, full_name, request_type, DATE_FORMAT(created_at, '%M %d, %Y') AS formatted_date, claim_date, payment_method, payment_status, document_status
    FROM view_general_requests {$whereSQL} ORDER BY created_at DESC LIMIT ? OFFSET ?
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

<!-- MAIN CONTENT -->
<div class="container-fluid p-3">
  <?php
  // Fetch live counts for dashboard stats
  // 1. Total registered users
  $userCount = $conn->query("SELECT COUNT(*) FROM user_accounts")->fetch_row()[0] ?? 0;

  // 2. Total service requests across multiple tables
  $serviceTables = [
      'barangay_id_requests',
      'blotter_records',
      'business_permit_requests',
      'good_moral_requests',
      'guardianship_requests',
      'indigency_requests',
      'residency_requests',
      'solo_parent_requests',
      'summon_records'
  ];
  $serviceCount = 0;
  foreach ($serviceTables as $tbl) {
      $cnt = $conn->query("SELECT COUNT(*) FROM {$tbl}")->fetch_row()[0] ?? 0;
      $serviceCount += $cnt;
  }

  // 3. Total pending account requests
  $pendingRequests = $conn->query("SELECT COUNT(*) FROM pending_accounts")->fetch_row()[0] ?? 0;

  // 4. Total residents across purok RBI tables
  $purokTables = [
      'purok1_rbi',
      'purok2_rbi',
      'purok3_rbi',
      'purok4_rbi',
      'purok5_rbi',
      'purok6_rbi'
  ];
  $residentsCount = 0;
  foreach ($purokTables as $tbl) {
      $cnt = $conn->query("SELECT COUNT(*) FROM {$tbl}")->fetch_row()[0] ?? 0;
      $residentsCount += $cnt;
  }

  // Build stats array dynamically
  $stats = [
      ['icon' => 'group',       'label' => 'Users',            'count' => $userCount],
      ['icon' => 'description', 'label' => 'Service Requests', 'count' => $serviceCount],
      ['icon' => 'apartment',  'label' => 'Residents',        'count' => $residentsCount],
      ['icon' => 'person_add',  'label' => 'Account Requests', 'count' => $pendingRequests],
  ];
  ?>
  
  <div class="row g-3 mb-4">
    <?php foreach ($stats as $stat): ?>
      <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm text-center p-3">
          <span class="material-symbols-outlined fs-1 text-success"><?= $stat['icon'] ?></span>
          <h2 class="fw-bold"><?= number_format($stat['count']) ?></h2>
          <p class="text-muted"><?= htmlspecialchars($stat['label']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Recent Requests Table -->
  <div class="col-12">
    <div class="card p-3 shadow-sm">
      <div class="d-flex align-items-center mb-3">
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
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
                  <option <?= $request_type==='Good Moral'?'selected':''?> value="Good Moral">Good Moral</option>
                  <option <?= $request_type==='Guardianship'?'selected':''?> value="Guardianship">Guardianship</option>
                  <option <?= $request_type==='Indigency'?'selected':''?> value="Indigency">Indigency</option>
                  <option <?= $request_type==='Residency'?'selected':''?> value="Residency">Residency</option>
                  <option <?= $request_type==='Solo Parent'?'selected':''?> value="Solo Parent">Solo Parent</option>
                </select>
              </div>

              <!-- Date Created -->
              <div class="mb-2">
                <label class="form-label mb-1">Date Created</label>
                <div class="d-flex gap-1">
                  <div class="flex-grow-1">
                    <small class="text-muted">From</small>
                    <input type="date" name="date_from" class="form-control form-control-sm" style="font-size:.75rem;" value="<?= htmlspecialchars($date_from) ?>">
                  </div>
                  <div class="flex-grow-1">
                    <small class="text-muted">To</small>
                    <input type="date" name="date_to" class="form-control form-control-sm" style="font-size:.75rem;" value="<?= htmlspecialchars($date_to) ?>">
                  </div>
                </div>
              </div>

              <!-- Claim Date -->
              <div class="mb-2">
                <label class="form-label mb-1">Claim Date</label>
                <div class="d-flex gap-1">
                  <div class="flex-grow-1">
                    <small class="text-muted">From</small>
                    <input type="date" name="claim_from" class="form-control form-control-sm me-1" style="font-size:.75rem;" value="<?= htmlspecialchars($claim_from) ?>">
                  </div>
                  <div class="flex-grow-1">
                    <small class="text-muted">To</small>
                    <input type="date" name="claim_to" class="form-control form-control-sm" style="font-size:.75rem;" value="<?= htmlspecialchars($claim_to) ?>">
                  </div>
                </div>
              </div>

              <!-- Payment Method -->
              <div class="mb-2">
                <label class="form-label mb-1">Payment Method</label>
                <select name="payment_method" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <?= $payment_method ==='GCash'?'selected':'' ?> value="GCash">GCash</option>
                  <option <?= $payment_method ==='Brgy Payment Device'?'selected':'' ?> value="Brgy Payment Device">Brgy Payment Device</option>
                  <option <?= $payment_method ==='Over-the-Counter'?'selected':'' ?> value="Over-the-Counter">Over-the-Counter</option>
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
                  <option <?= $document_status ==='Ready To Release'?'selected':'' ?> value="Ready To Release">Ready To Release</option>
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

        <form method="get" id="searchForm" class="d-flex ms-auto me-2">
          <input type="hidden" name="page_num" value="1">
          <?php foreach ([
            'request_type','date_from','date_to',
            'claim_from','claim_to',
            'payment_method','payment_status','document_status'
          ] as $f):
              if (!empty($_GET[$f])): ?>
            <input type="hidden"
                  name="<?= $f ?>"
                  value="<?= htmlspecialchars($_GET[$f]) ?>">
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
              <th>Transaction No.</th>
              <th>Name</th>
              <th>Request</th>
              <th>Payment Method</th>
              <th>Payment Status</th>
              <th>Document Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): 
                // extract and escape
                $txn   = htmlspecialchars($row['transaction_id']);
                $name  = htmlspecialchars($row['full_name']);
                $req   = htmlspecialchars($row['request_type']);
                $pm    = htmlspecialchars($row['payment_method']);
                $ps    = htmlspecialchars($row['payment_status']);
                $ds    = htmlspecialchars($row['document_status']);
                
                // badge classes
                $payClass = $ps === 'Paid' ? 'paid-status' : 'unpaid-status';
                switch ($ds) {
                  case 'For Verification': $docClass = 'for-verification-status'; break;
                  case 'Processing': $docClass = 'processing-status'; break;
                  case 'Ready To Release': $docClass = 'ready-to-release-status'; break;
                  case 'Released': $docClass = 'released-status'; break;
                  case 'Rejected': $docClass = 'rejected-status'; break;
                  default: $docClass = ''; break;
                }
              ?>
              <tr class="clickable-row"
                  data-transaction_id="<?= $txn?>"
                  data-full_name="<?= $name?>"
                  data-request_type="<?= $req?>"
                  data-created_at="<?= htmlspecialchars($row['formatted_date'])?>"
                  data-claim_date="<?= htmlspecialchars($row['claim_date'] ?: '—')?>"
                  data-payment_method="<?= $pm?>"
                  data-payment_status="<?= $ps?>"
                  data-document_status="<?= $ds?>"
                  style="cursor:pointer">
                <td><?= $txn ?></td>
                <td><?= $name ?></td>
                <td><?= $req ?></td>
                <td><?= $pm ?></td>
                <td><span class="badge <?= $payClass ?>"><?= $ps ?></span></td>
                <td><span class="badge <?= $docClass ?>"><?= $ds ?></span></td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center">No records found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Details Modal -->
      <div class="modal fade" id="rowModal" tabindex="-1" aria-labelledby="rowModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content shadow-lg">
            <div class="modal-header bg-dark text-white">
              <h5 class="modal-title" id="rowModalLabel">
                <i class="bi bi-card-list me-2"></i>Request Details
              </h5>
            </div>
            <div class="modal-body">
              <!-- Section: User & Request -->
              <div class="mb-4">
                <h6 class="fw-bold fs-5">Basic Information</h6>
                <dl class="row">
                  <dt class="col-sm-4">Transaction No.</dt>
                  <dd class="col-sm-8" id="modal-transaction_id">—</dd>

                  <dt class="col-sm-4">Name</dt>
                  <dd class="col-sm-8" id="modal-full_name">—</dd>

                  <dt class="col-sm-4">Request Type</dt>
                  <dd class="col-sm-8" id="modal-request_type">—</dd>
                </dl>
              </div>
              <!-- Section: Dates -->
              <div class="mb-4">
                <h6 class="fw-bold fs-5">Dates</h6>
                <dl class="row">
                  <dt class="col-sm-4">Date Created</dt>
                  <dd class="col-sm-8" id="modal-created_at">—</dd>

                  <dt class="col-sm-4">Claim Date</dt>
                  <dd class="col-sm-8" id="modal-claim_date">—</dd>
                </dl>
              </div>
              <!-- Section: Payment & Status -->
              <div>
                <h6 class="fw-bold fs-5">Payment & Status</h6>
                <dl class="row">
                  <dt class="col-sm-4">Payment Method</dt>
                  <dd class="col-sm-8" id="modal-payment_method">—</dd>

                  <dt class="col-sm-4">Payment Status</dt>
                  <dd class="col-sm-8" id="modal-payment_status">—</dd>

                  <dt class="col-sm-4">Document Status</dt>
                  <dd class="col-sm-8" id="modal-document_status">—</dd>
                </dl>
              </div>
            </div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">
                Close
              </button>
            </div>
          </div>
        </div>
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
          $end   = min($pages, $page_num+2);
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
  const form       = document.getElementById('searchForm');
  const input      = document.getElementById('searchInput');
  const btn        = document.getElementById('searchBtn');
  const hasSearch  = <?= $search !== '' ? 'true' : 'false' ?>;
  btn.addEventListener('click', () => {
    if (hasSearch) input.value = '';
    form.submit();
  });

  // Modal populator
  const modalEl      = document.getElementById('rowModal');
  const bsModal      = new bootstrap.Modal(modalEl);
  const dataKeys     = ['transaction_id','full_name','request_type','created_at','claim_date','payment_method','payment_status','document_status'];
  const getDlElement = key => document.getElementById(`modal-${key}`);

  document.querySelector('tbody').addEventListener('click', e => {
    const tr = e.target.closest('tr.clickable-row');
    if (!tr) return;

    dataKeys.forEach(key => {
      const el = getDlElement(key);
      el.textContent = tr.dataset[key] || '—';
    });

    bsModal.show();
  });
});
</script>