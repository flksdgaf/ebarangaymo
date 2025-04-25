<?php
require 'functions/dbconn.php';

// ── PAGINATION SETUP ─────────────────────────────────────────────────────────
$page_num = isset($_GET['page_num']) && is_numeric($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$limit    = 7; // entries per page
$offset   = ($page_num - 1) * $limit;

// ── 2) PULL IN FILTERS ────────────────────────────────────────────────────────
$request_type    = $_GET['request_type']    ?? '';
$date_from       = $_GET['date_from']       ?? '';
$date_to         = $_GET['date_to']         ?? '';
$payment_method  = $_GET['payment_method']  ?? '';
$payment_status  = $_GET['payment_status']  ?? '';
$document_status = $_GET['document_status'] ?? '';

// ── 3) BUILD WHERE CLAUSES ───────────────────────────────────────────────────
$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

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
// ── date range ────────────────────────────────────────────────
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

$whereSQL = $whereClauses
  ? 'WHERE ' . implode(' AND ', $whereClauses)
  : '';
  
// ── 4) COUNT TOTAL WITH FILTERS ──────────────────────────────────────────────
$countSql = "SELECT COUNT(*) FROM view_general_requests {$whereSQL}";
$cst = $conn->prepare($countSql);
if ($whereClauses) {
  // bind only the filter params
  $cst->bind_param($bindTypes, ...$bindParams);
}
$cst->execute();
$total = $cst->get_result()->fetch_row()[0];
$cst->close();

$pages = max(1, ceil($total / $limit));

// ── 5) FETCH PAGE WITH FILTERS ──────────────────────────────────────────────
$sql = "
  SELECT transaction_id, full_name, request_type,
         created_at, claim_date,
         payment_method, payment_status, document_status
    FROM view_general_requests
    {$whereSQL}
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";
$st = $conn->prepare($sql);

if ($whereClauses) {
  // stitch types + filters + ii
  $fullTypes = $bindTypes . 'ii';
  $allParams = [];
  $allParams[] = $fullTypes;
  // needs references for call_user_func_array
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

// ── 6) BUILD QUERYSTRING FOR PAGINATION LINKS ────────────────────────────────
$qs = $_GET;
unset($qs['page_num']);   // we’ll append a fresh one for each link
$queryString = http_build_query($qs);
if ($queryString) {
  $queryString .= '&';
}
?>

<div class="container-fluid p-3">
  <div class="row g-3 mb-4">
    <?php
      $stats = [
        ['icon' => 'group', 'label' => 'Users', 'count' => 3],
        ['icon' => 'description', 'label' => 'Service Requests', 'count' => 1],
        ['icon' => 'visibility', 'label' => 'Page Views', 'count' => 0],
        ['icon' => 'person_add', 'label' => 'Account Requests', 'count' => 0],
      ];

      foreach ($stats as $stat) {
        echo '
        <div class="col-md-3 col-sm-6">
          <div class="card shadow-sm text-center p-3">
            <span class="material-symbols-outlined fs-1 text-success">' . $stat['icon'] . '</span>
            <h2 class="fw-bold">' . $stat['count'] . '</h2>
            <p class="text-muted">' . $stat['label'] . '</p>
          </div>
        </div>';
      }
    ?>
  </div>

  <!-- Recent Requests Table -->
  <div class="col-12">
    <div class="card p-3 shadow-sm">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold ms-2 text-success">Recent Requests</h5>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            Filter
          </button>
          <div class="dropdown-menu dropdown-menu-end p-3" aria-labelledby="filterDropdown" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
            <form method="get" class="mb-0" id="filterForm">
              <!-- always reset back to page 1 on filter -->
              <input type="hidden" name="page_num" value="1">

              <!-- Request Type -->
              <div class="mb-2">
                <label class="form-label mb-1">Request Type</label>
                <select name="request_type" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <?= $request_type==='Barangay ID'?'selected':'' ?>      value="Barangay ID">Barangay ID</option>
                  <option <?= $request_type==='Barangay Clearance'?'selected':'' ?> value="Barangay Clearance">Barangay Clearance</option>
                  <option <?= $request_type==='Certificate'?'selected':'' ?>       value="Certification">Certification</option>
                  <option <?= $request_type==='Business Permit'?'selected':'' ?>    value="Business Permit">Business Permit</option>
                  <option <?= $request_type==='Katarungang Pambarangay'?'selected':'' ?> value="Katarungang Pambarangay">Katarungang Pambarangay</option>
                  <option <?= $request_type==='Environmental Services'?'selected':'' ?>  value="Environmental Services">Environmental Services</option>
                </select>
              </div>

              <!-- Date Created -->
              <div class="mb-2">
                <label class="form-label mb-1">Date Created</label>
                <div class="d-flex">
                  <input type="date" name="date_from" class="form-control form-control-sm me-1" style="font-size:.75rem;" value="<?= htmlspecialchars($date_from) ?>">
                  <input type="date" name="date_to" class="form-control form-control-sm" style="font-size:.75rem;" value="<?= htmlspecialchars($date_to) ?>">
                </div>
              </div>

              <!-- Payment Method -->
              <div class="mb-2">
                <label class="form-label mb-1">Payment Method</label>
                <select name="payment_method" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <?= $payment_method==='GCash'?'selected':'' ?>             value="GCash">GCash</option>
                  <option <?= $payment_method==='Brgy Payment Device'?'selected':'' ?> value="Brgy Payment Device">Brgy Payment Device</option>
                  <option <?= $payment_method==='Over-the-Counter'?'selected':'' ?>    value="Over-the-Counter">Over-the-Counter</option>
                </select>
              </div>

              <!-- Payment Status -->
              <div class="mb-2">
                <label class="form-label mb-1">Payment Status</label>
                <select name="payment_status" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <?= $payment_status==='Paid'?'selected':'' ?>   value="Paid">Paid</option>
                  <option <?= $payment_status==='Unpaid'?'selected':'' ?> value="Unpaid">Unpaid</option>
                </select>
              </div>

              <!-- Document Status -->
              <div class="mb-2">
                <label class="form-label mb-1">Document Status</label>
                <select name="document_status" class="form-select form-select-sm" style="font-size:.75rem;">
                  <option value="">All</option>
                  <option <?= $document_status==='For Verification'?'selected':'' ?> value="For Verification">For Verification</option>
                  <option <?= $document_status==='Processing'?'selected':'' ?>       value="Processing">Processing</option>
                  <option <?= $document_status==='Ready To Release'?'selected':'' ?> value="Ready To Release">Ready To Release</option>
                  <option <?= $document_status==='Released'?'selected':'' ?>         value="Released">Released</option>
                  <option <?= $document_status==='Rejected'?'selected':'' ?>         value="Rejected">Rejected</option>
                </select>
              </div>

              <div class="d-flex">
                <a href="?page_num=1" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
                <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="table-responsive admin-request-table">
        <table class="table table-hover align-middle text-start">
          <thead class="table-light">
            <tr>
              <th class="text-nowrap">Transaction No.</th>
              <th class="text-nowrap">Name</th>
              <th class="text-nowrap">Request</th>
              <th class="text-nowrap">Date Created</th>
              <th class="text-nowrap">Claim Date</th>
              <th class="text-nowrap">Payment Method</th>
              <th class="text-nowrap">Payment Status</th>
              <th class="text-nowrap">Document Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
              // use the filtered & paginated result we already ran above
              while ($row = $result->fetch_assoc()) {
                $txn            = $row['transaction_id'];
                $name           = $row['full_name'];
                $request        = $row['request_type'];
                $formattedDate  = date("F d, Y h:i A", strtotime($row['created_at']));
                $formattedClaim = $row['claim_date']
                                  ? date("F d, Y", strtotime($row['claim_date']))
                                  : '—';
                $pmethod        = $row['payment_method'];
                $pstatus        = $row['payment_status'];
                $dstatus        = $row['document_status'];

                // badge classes
                $payClass = $pstatus === 'Paid' ? 'paid-status' : 'unpaid-status';
                switch ($dstatus) {
                  case 'For Verification': $docClass='for-verification-status'; break;
                  case 'Processing':       $docClass='processing-status';        break;
                  case 'Ready To Release': $docClass='ready-to-release-status'; break;
                  case 'Released':         $docClass='released-status';          break;
                  case 'Rejected':         $docClass='rejected-status';          break;
                  default:                 $docClass='';                        break;
                }

                echo "<tr>
                        <td>{$txn}</td>
                        <td>{$name}</td>
                        <td>{$request}</td>
                        <td>{$formattedDate}</td>
                        <td>{$formattedClaim}</td>
                        <td>{$pmethod}</td>
                        <td><span class='badge {$payClass}'>{$pstatus}</span></td>
                        <td><span class='badge {$docClass}'>{$dstatus}</span></td>
                    </tr>";
              }
            ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination Controls -->
      <?php if ($pages > 1): ?>
      <nav aria-label="Request pagination">
        <ul class="pagination justify-content-center flex-wrap mt-3">
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

<!-- Full Calendar -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<!-- Chart.js (Make sure it's included in admin_header.php or load below) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Full Calendar
  document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');

    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      height: 350,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: ''
      },
      events: [
        {
          title: 'Barangay Meeting',
          start: '2025-04-16',
          description: 'Monthly barangay council meeting',
          color: '#2e7d32'
        },
        {
          title: 'Vaccination Drive',
          start: '2025-04-20',
          end: '2025-04-22',
          description: 'Barangay health initiative',
          color: '#2e7d32'
        }
      ],
      eventClick: function(info) {
        alert(info.event.title + "\n" + info.event.extendedProps.description);
      }
    });

    calendar.render();
  });

  // Chart
  const ctx = document.getElementById('servicePieChart');
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: ['Brgy Clearance', 'Certification', 'Business Permit', 'Brgy ID'],
      datasets: [{
        label: 'Request Types',
        data: [45, 25, 10, 20],
        backgroundColor: ['#1e7e34', '#28a745', '#ffc107', '#20c997'],
        borderColor: '#fff',
        borderWidth: 1
      }]
    },
    options: {
      plugins: {
        legend: {
          display: false
        }
      }
    }
  });
</script>