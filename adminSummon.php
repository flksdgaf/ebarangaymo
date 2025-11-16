<?php
require 'functions/dbconn.php';
$userId = (int) ($_SESSION['loggedInUserID'] ?? 0);
$currentRole = $_SESSION['loggedInUserRole'] ?? '';

// FILTER & SEARCH SETUP
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$action_taken = $_GET['action_taken'] ?? '';
$complaint_stage = $_GET['complaint_stage'] ?? '';

// build base query params
$bp = [
    'page' => 'adminComplaints',
    'search' => $search,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'action_taken' => $action_taken,
    'complaint_stage' => $complaint_stage,
];

// build query filters
$whereClauses = [];
$bindTypes = '';
$bindParams = [];

// global search
if ($search !== '') {
    $term = "%{$search}%";
    $whereClauses[] = "(transaction_id LIKE ? OR case_no LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ? OR complaint_title LIKE ?)";
    $bindTypes .= str_repeat('s', 5);
    $bindParams = array_merge($bindParams, array_fill(0, 5, $term));
}

// date range filter on date_filed
if ($date_from && $date_to) {
    $whereClauses[] = "(DATE(date_filed) BETWEEN ? AND ?)";
    $bindTypes .= 'ss';
    $bindParams = array_merge($bindParams, [$date_from, $date_to]);
} elseif ($date_from) {
    $whereClauses[] = "(DATE(date_filed) >= ?)";
    $bindTypes .= 's';
    $bindParams[] = $date_from;
} elseif ($date_to) {
    $whereClauses[] = "(DATE(date_filed) <= ?)";
    $bindTypes .= 's';
    $bindParams[] = $date_to;
}

// filter by Action Taken
if ($action_taken !== '') {
    $whereClauses[] = 'action_taken = ?';
    $bindTypes .= 's';
    $bindParams[] = $action_taken;
}

// filter by Complaint Stage
if ($complaint_stage !== '') {
    $whereClauses[] = 'complaint_stage = ?';
    $bindTypes .= 's';
    $bindParams[] = $complaint_stage;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// PAGINATION
$limit = 10;
$page = max((int)($_GET['page_num'] ?? 1), 1);
$offset = ($page - 1) * $limit;

// 1) total count
$countSQL = "SELECT COUNT(*) AS total FROM barangay_complaints $whereSQL";
$countStmt = $conn->prepare($countSQL);
if ($whereClauses) {
    $refs = [];
    foreach ($bindParams as $i => &$val) {
        $refs[$i] = &$val;
    }
    array_unshift($refs, $bindTypes);
    call_user_func_array([$countStmt, 'bind_param'], $refs);
}
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

// 2) fetch page of rows
$sql = "
  SELECT
    id,
    transaction_id,
    case_no,
    complainant_name,
    complainant_address,
    respondent_name,
    respondent_address,
    complaint_title,
    nature_of_case,
    complaint_affidavit,
    pleading_statement,
    date_filed,
    date_initial_hearing,
    date_settlement,
    date_cfa_issued,
    action_taken,
    complaint_stage,
    chosen_pangkat,
    
    schedule_pb_first,
    schedule_pb_second,
    schedule_pb_third,
    schedule_unang_patawag,
    schedule_ikalawang_patawag,
    schedule_ikatlong_patawag,
    
    complainant_affidavit_pb_first,
    complainant_affidavit_pb_second,
    complainant_affidavit_pb_third,
    respondent_affidavit_pb_first,
    respondent_affidavit_pb_second,
    respondent_affidavit_pb_third,
    
    complainant_affidavit_unang_patawag,
    complainant_affidavit_ikalawang_patawag,
    complainant_affidavit_ikatlong_patawag,
    respondent_affidavit_unang_patawag,
    respondent_affidavit_ikalawang_patawag,
    respondent_affidavit_ikatlong_patawag,
    
    DATE_FORMAT(date_filed, '%b %e, %Y') AS formatted_date_filed,
    payment_status

  FROM barangay_complaints
  $whereSQL
  ORDER BY date_filed ASC, id ASC
  LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$types  = $bindTypes . 'ii';
$params = array_merge($bindParams, [$limit, $offset]);
$refs   = [];
foreach ($params as $i => &$val) {
    $refs[$i] = &$val;
}
array_unshift($refs, $types);
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<div>
  <!-- SUCCESS/ERROR ALERTS -->
  <?php if (isset($_GET['new_complaint_id'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      New complaint <strong><?= htmlspecialchars($_GET['new_complaint_id']) ?></strong> created successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['updated_complaint_id'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Complaint <strong><?= htmlspecialchars($_GET['updated_complaint_id']) ?></strong> updated successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['deleted_complaint_id'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      Complaint <strong><?= htmlspecialchars($_GET['deleted_complaint_id']) ?></strong> deleted.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cancelled_complaint_id'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      Case <strong><?= htmlspecialchars($_GET['cancelled_complaint_id']) ?></strong> has been cancelled.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- DYNAMIC JAVASCRIPT ALERTS -->
  <div id="js-alert-container"></div>

  <div class="card shadow-sm p-3">
    <div class="d-flex align-items-center mb-3">
      <!-- Filter dropdown -->
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown">
          <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">filter_list</span>
          Filter
        </button>
        <div class="dropdown-menu p-3" style="min-width:280px; font-size:.85rem;">
          <form method="get" action="?page=adminComplaints">
            <input type="hidden" name="page" value="adminComplaints">
            <input type="hidden" name="page_num" value="1">

            <!-- Action Taken -->
            <div class="mb-2">
              <label class="form-label mb-1 fw-bold">Action Taken</label>
              <select name="action_taken" class="form-select form-select-sm">
                <option value="">All</option>
                <option <?= $action_taken==='Pending' ? 'selected' : '' ?>>Pending</option>
                <option <?= $action_taken==='On-Going' ? 'selected' : '' ?>>On-Going</option>
                <option <?= $action_taken==='Mediated' ? 'selected' : '' ?>>Mediated</option>
                <option <?= $action_taken==='Conciliated' ? 'selected' : '' ?>>Conciliated</option>
                <option <?= $action_taken==='Dismissed' ? 'selected' : '' ?>>Dismissed</option>
                <option <?= $action_taken==='Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option <?= $action_taken==='CFA' ? 'selected' : '' ?>>CFA</option>
                <option <?= $action_taken==='Withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                <option <?= $action_taken==='Incoming' ? 'selected' : '' ?>>Incoming</option>
                <option <?= $action_taken==='Arbitrated' ? 'selected' : '' ?>>Arbitrated</option>
              </select>
            </div>

            <!-- Complaint Stage -->
            <div class="mb-2">
              <label class="form-label mb-1 fw-bold">Complaint Stage</label>
              <select name="complaint_stage" class="form-select form-select-sm">
                <option value="">All</option>
                <option <?= $complaint_stage==='Filing' ? 'selected' : '' ?>>Filing</option>
                <option <?= $complaint_stage==='Punong Barangay - 1st' ? 'selected' : '' ?>>Punong Barangay - 1st</option>
                <option <?= $complaint_stage==='Punong Barangay - 2nd' ? 'selected' : '' ?>>Punong Barangay - 2nd</option>
                <option <?= $complaint_stage==='Punong Barangay - 3rd' ? 'selected' : '' ?>>Punong Barangay - 3rd</option>
                <option <?= $complaint_stage==='Unang Patawag' ? 'selected' : '' ?>>Unang Patawag</option>
                <option <?= $complaint_stage==='Ikalawang Patawag' ? 'selected' : '' ?>>Ikalawang Patawag</option>
                <option <?= $complaint_stage==='Ikatlong Patawag' ? 'selected' : '' ?>>Ikatlong Patawag</option>
                <option <?= $complaint_stage==='Closed' ? 'selected' : '' ?>>Closed</option>
              </select>
            </div>

            <!-- Date Range -->
            <div class="mb-2">
              <label class="form-label mb-1 fw-bold">Date Filed</label>
              <div class="d-flex gap-1">
                <div class="flex-grow-1">
                  <small class="text-muted">From</small>
                  <input type="date" name="date_from" class="form-control form-control-sm" value="<?=htmlspecialchars($date_from)?>">
                </div>
                <div class="flex-grow-1">
                  <small class="text-muted">To</small>
                  <input type="date" name="date_to" class="form-control form-control-sm" value="<?=htmlspecialchars($date_to)?>">
                </div>
              </div>
            </div>

            <div class="d-flex gap-2">
              <a href="?page=adminComplaints" class="btn btn-sm btn-outline-secondary flex-grow-1">Reset</a>
              <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Add New Complaint -->
      <?php if ($currentRole !== 'Brgy Kagawad'): ?>
        <button class="btn btn-sm btn-success ms-3" data-bs-toggle="modal" data-bs-target="#addComplaintModal">
          <span class="material-symbols-outlined" style="font-size:1rem; vertical-align:middle;">add</span> 
          Add New Complaint
        </button>
      <?php endif; ?>

      <!-- Search form -->
      <form method="get" action="?page=adminComplaints" class="d-flex ms-auto">
        <input type="hidden" name="page" value="adminComplaints">
        <input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        <input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        <input type="hidden" name="action_taken" value="<?= htmlspecialchars($action_taken) ?>">
        <input type="hidden" name="complaint_stage" value="<?= htmlspecialchars($complaint_stage) ?>">
        <input type="hidden" name="page_num" value="1">

        <div class="input-group input-group-sm">
          <input name="search" id="searchInput" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
          <button type="button" class="btn btn-outline-secondary" id="searchBtn">
            <span class="material-symbols-outlined"><?= !empty($search) ? 'close' : 'search' ?></span>
          </button>
        </div>
      </form>
    </div>

    <!-- COMPLAINTS TABLE -->
    <div class="table-responsive admin-table"> <!--  style="max-height:600px; overflow-y:auto;" -->
      <table class="table table-hover align-middle text-start">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">Case No.</th>
            <th class="text-nowrap">Complainant</th>
            <th class="text-nowrap">Respondent</th>
            <th class="text-nowrap">Complaint Title</th>
            <th class="text-nowrap">Nature of Case</th>
            <th class="text-nowrap">Date Filed</th>
            <th class="text-nowrap">Action Taken</th>
            <?php if ($currentRole !== 'Brgy Kagawad'): ?>
              <th class="text-center text-nowrap">Action</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): 
              $tid = htmlspecialchars($row['transaction_id']);
              $caseNo = htmlspecialchars($row['case_no'] ?? $tid);
              
              // Badge color based on action_taken
              $badgeMap = [
                'Pending' => 'bg-warning text-dark',
                'On-Going' => 'bg-primary',
                'Mediated' => 'bg-success',
                'Conciliated' => 'bg-success',
                'Dismissed' => 'bg-danger',
                'Cancelled' => 'bg-secondary',
                'CFA' => 'bg-info',
                'Withdrawn' => 'bg-secondary',
                'Incoming' => 'bg-light text-dark',
                'Arbitrated' => 'bg-dark',
              ];
              $badgeClass = $badgeMap[$row['action_taken']] ?? 'bg-secondary';
            ?>
              <tr 
                class="complaint-row"
                data-id="<?= $tid ?>"
                data-case-no="<?= htmlspecialchars($row['case_no'] ?? '') ?>"
                data-complainant-name="<?= htmlspecialchars($row['complainant_name']) ?>"
                data-complainant-address="<?= htmlspecialchars($row['complainant_address']) ?>"
                data-respondent-name="<?= htmlspecialchars($row['respondent_name']) ?>"
                data-respondent-address="<?= htmlspecialchars($row['respondent_address']) ?>"
                data-complaint-title="<?= htmlspecialchars($row['complaint_title']) ?>"
                data-nature-of-case="<?= htmlspecialchars($row['nature_of_case']) ?>"
                data-complaint-affidavit="<?= htmlspecialchars($row['complaint_affidavit']) ?>"
                data-pleading-statement="<?= htmlspecialchars($row['pleading_statement']) ?>"
                data-action-taken="<?= htmlspecialchars($row['action_taken']) ?>"
                data-complaint-stage="<?= htmlspecialchars($row['complaint_stage']) ?>"
                data-json='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'
              >
                <td><?= $caseNo ?></td>
                <td><?= htmlspecialchars($row['complainant_name']) ?></td>
                <td><?= htmlspecialchars($row['respondent_name']) ?></td>
                <td><?= htmlspecialchars($row['complaint_title']) ?></td>
                <td><?= htmlspecialchars($row['nature_of_case']) ?></td>
                <td><?= htmlspecialchars($row['formatted_date_filed']) ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['action_taken']) ?></span></td>
                <?php if ($currentRole !== 'Brgy Kagawad'): ?>
                  <td class="text-center text-nowrap">
                    <button class="btn btn-sm btn-info view-btn" title="View Details">
                      <span class="material-symbols-outlined" style="font-size: 14px;">visibility</span>
                    </button>
                    <?php 
                    // Hide edit and cancel buttons if case is closed OR payment is pending
                    $finalStatuses = ['Cancelled', 'Mediated', 'Conciliated', 'Dismissed', 'CFA', 'Withdrawn', 'Arbitrated'];
                    $isClosed = in_array($row['action_taken'], $finalStatuses) || $row['complaint_stage'] === 'Closed';
                    $paymentPending = ($row['payment_status'] === 'Pending');
                    
                    if (!$isClosed && !$paymentPending): 
                    ?>
                      <button class="btn btn-sm btn-success edit-btn" title="Manage Case">
                        <span class="material-symbols-outlined" style="font-size: 14px;">edit</span>
                      </button>
                      <button class="btn btn-sm btn-warning cancel-btn" title="Cancel Case">
                        <span class="material-symbols-outlined" style="font-size: 14px;">cancel</span>
                      </button>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="<?= $currentRole !== 'Brgy Kagawad' ? '8' : '7' ?>" class="text-center">No complaints found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center pagination-sm">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($bp, ['page_num' => $page - 1])) ?>">Previous</a>
          </li>
          <?php
            $range = 2;
            $dots = false;
            for ($i = 1; $i <= $totalPages; $i++) {
              if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                $active = $i == $page ? 'active' : '';
                echo "<li class='page-item {$active}'><a class='page-link' href='?" . http_build_query(array_merge($bp, ['page_num' => $i])) . "'>$i</a></li>";
                $dots = true;
              } elseif ($dots) {
                echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                $dots = false;
              }
            }
          ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($bp, ['page_num' => $page + 1])) ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<!-- ADD NEW COMPLAINT MODAL -->
<div class="modal fade" id="addComplaintModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header text-white" style="background-color: #13411F;">
        <h5 class="modal-title">New Complaint Record</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="addComplaintForm" method="POST" action="functions/process_new_complaint.php">
        <div class="modal-body">
          <input type="hidden" name="account_id" value="<?= $userId ?>">
          
          <div class="row g-3">
            <!-- Complainant Details -->
            <div class="col-12"><h6 class="fw-bold text-success">Complainant Details</h6><hr class="mt-1"></div>
            <div class="col-md-4">
              <label class="form-label">First Name *</label>
              <input name="complainant_first_name" type="text" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Middle Name</label>
              <input name="complainant_middle_name" type="text" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <label class="form-label">Last Name *</label>
              <input name="complainant_last_name" type="text" class="form-control form-control-sm" required>
            </div>
            <div class="col-12">
              <label class="form-label">Address *</label>
              <input name="complainant_address" type="text" class="form-control form-control-sm" required>
            </div>

            <!-- Respondent Details -->
            <div class="col-12 mt-3"><h6 class="fw-bold text-success">Respondent Details</h6><hr class="mt-1"></div>
            <div class="col-md-4">
              <label class="form-label">First Name *</label>
              <input name="respondent_first_name" type="text" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Middle Name</label>
              <input name="respondent_middle_name" type="text" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <label class="form-label">Last Name *</label>
              <input name="respondent_last_name" type="text" class="form-control form-control-sm" required>
            </div>
            <div class="col-12">
              <label class="form-label">Address *</label>
              <input name="respondent_address" type="text" class="form-control form-control-sm" required>
            </div>

            <!-- Complaint Details -->
            <div class="col-12 mt-3"><h6 class="fw-bold text-success">Complaint Details</h6><hr class="mt-1"></div>
            <div class="col-md-6">
              <label class="form-label">Complaint Title *</label>
              <input name="complaint_title" type="text" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nature of Case *</label>
              <select name="nature_of_case" class="form-select form-select-sm" required>
                <option value="">Select...</option>
                <option value="Criminal">Criminal</option>
                <option value="Civil">Civil</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Complaint Affidavit *</label>
              <textarea name="complaint_affidavit" class="form-control form-control-sm" rows="3" required></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Pleading Statement *</label>
              <textarea name="pleading_statement" class="form-control form-control-sm" rows="3" required></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Create Complaint</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- VIEW CASE MODAL (Read-Only) -->
<div class="modal fade" id="viewCaseModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 90vw;">
    <div class="modal-content">
      <div class="modal-header text-white" style="background-color: #13411F;">
        <h5 class="modal-title">View Case: <span id="view_case_no"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      
      <div class="modal-body p-4">
        <!-- Case Summary -->
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label fw-bold text-muted">Complainant</label>
            <div class="border rounded p-2 bg-light" id="view_complainant_full"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold text-muted">Respondent</label>
            <div class="border rounded p-2 bg-light" id="view_respondent_full"></div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold text-muted">Complaint Title</label>
            <div class="border rounded p-2 bg-light" id="view_complaint_title"></div>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-bold text-muted">Nature of Case</label>
            <div class="border rounded p-2 bg-light" id="view_nature_of_case"></div>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-bold text-muted">Date Filed</label>
            <div class="border rounded p-2 bg-light" id="view_date_filed"></div>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-bold text-muted">Stage</label>
            <div class="border rounded p-2 bg-light" id="view_stage"></div>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-bold text-muted">Status</label>
            <div class="border rounded p-2 bg-light" id="view_action"></div>
          </div>
          <div class="col-12">
            <label class="form-label fw-bold text-muted">Complaint Affidavit</label>
            <div class="border rounded p-2 bg-light" style="min-height:60px; white-space:pre-wrap;" id="view_complaint_affidavit"></div>
          </div>
          <div class="col-12">
            <label class="form-label fw-bold text-muted">Pleading Statement</label>
            <div class="border rounded p-2 bg-light" style="min-height:60px; white-space:pre-wrap;" id="view_pleading_statement"></div>
          </div>
        </div>

        <!-- Punong Barangay Meetings -->
        <div class="mb-3" id="view_pb_section">
          <h6 class="fw-bold text-success">Punong Barangay Meetings</h6>
          <hr class="mt-1">
          <div class="row g-2">
            <div class="col-md-4" id="view_pb_1st_card" style="display:none;">
              <div class="card">
                <div class="card-body">
                  <h6 class="card-subtitle mb-2 text-muted">1st Meeting</h6>
                  <p class="mb-1"><strong>Schedule:</strong> <span id="view_pb_1st_sched"></span></p>
                  <p class="mb-1"><strong>Complainant Affidavit:</strong></p>
                  <p class="small" id="view_pb_1st_comp"></p>
                  <p class="mb-1"><strong>Respondent Affidavit:</strong></p>
                  <p class="small" id="view_pb_1st_resp"></p>
                </div>
              </div>
            </div>
            <div class="col-md-4" id="view_pb_2nd_card" style="display:none;">
              <div class="card">
                <div class="card-body">
                  <h6 class="card-subtitle mb-2 text-muted">2nd Meeting</h6>
                  <p class="mb-1"><strong>Schedule:</strong> <span id="view_pb_2nd_sched"></span></p>
                  <p class="mb-1"><strong>Complainant Affidavit:</strong></p>
                  <p class="small" id="view_pb_2nd_comp"></p>
                  <p class="mb-1"><strong>Respondent Affidavit:</strong></p>
                  <p class="small" id="view_pb_2nd_resp"></p>
                </div>
              </div>
            </div>
            <div class="col-md-4" id="view_pb_3rd_card" style="display:none;">
              <div class="card">
                <div class="card-body">
                  <h6 class="card-subtitle mb-2 text-muted">3rd Meeting</h6>
                  <p class="mb-1"><strong>Schedule:</strong> <span id="view_pb_3rd_sched"></span></p>
                  <p class="mb-1"><strong>Complainant Affidavit:</strong></p>
                  <p class="small" id="view_pb_3rd_comp"></p>
                  <p class="mb-1"><strong>Respondent Affidavit:</strong></p>
                  <p class="small" id="view_pb_3rd_resp"></p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Lupon Hearings -->
        <div class="mb-3" id="view_lupon_section">
          <h6 class="fw-bold text-success">Lupon Tagapamayapa Hearings</h6>
          <hr class="mt-1">
          <div class="row g-2">
            <div class="col-md-4" id="view_lupon_1st_card" style="display:none;">
              <div class="card">
                <div class="card-body">
                  <h6 class="card-subtitle mb-2 text-muted">Unang Patawag</h6>
                  <p class="mb-1"><strong>Schedule:</strong> <span id="view_lupon_1st_sched"></span></p>
                  <p class="mb-1"><strong>Complainant Affidavit:</strong></p>
                  <p class="small" id="view_lupon_1st_comp"></p>
                  <p class="mb-1"><strong>Respondent Affidavit:</strong></p>
                  <p class="small" id="view_lupon_1st_resp"></p>
                </div>
              </div>
            </div>
            <div class="col-md-4" id="view_lupon_2nd_card" style="display:none;">
              <div class="card">
                <div class="card-body">
                  <h6 class="card-subtitle mb-2 text-muted">Ikalawang Patawag</h6>
                  <p class="mb-1"><strong>Schedule:</strong> <span id="view_lupon_2nd_sched"></span></p>
                  <p class="mb-1"><strong>Complainant Affidavit:</strong></p>
                  <p class="small" id="view_lupon_2nd_comp"></p>
                  <p class="mb-1"><strong>Respondent Affidavit:</strong></p>
                  <p class="small" id="view_lupon_2nd_resp"></p>
                </div>
              </div>
            </div>
            <div class="col-md-4" id="view_lupon_3rd_card" style="display:none;">
              <div class="card">
                <div class="card-body">
                  <h6 class="card-subtitle mb-2 text-muted">Ikatlong Patawag</h6>
                  <p class="mb-1"><strong>Schedule:</strong> <span id="view_lupon_3rd_sched"></span></p>
                  <p class="mb-1"><strong>Complainant Affidavit:</strong></p>
                  <p class="small" id="view_lupon_3rd_comp"></p>
                  <p class="mb-1"><strong>Respondent Affidavit:</strong></p>
                  <p class="small" id="view_lupon_3rd_resp"></p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Settlement Details -->
        <div class="row g-3" id="view_settlement_section" style="display:none;">
          <div class="col-12">
            <h6 class="fw-bold text-success">Settlement Details</h6>
            <hr class="mt-1">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold text-muted">Date of Settlement</label>
            <div class="border rounded p-2 bg-light" id="view_date_settlement"></div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold text-muted">Date CFA Issued</label>
            <div class="border rounded p-2 bg-light" id="view_date_cfa"></div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold text-muted">Chosen Pangkat</label>
            <div class="border rounded p-2 bg-light" id="view_chosen_pangkat"></div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- MANAGE/EDIT CASE MODAL -->
<div class="modal fade" id="manageCaseModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 90vw;">
    <div class="modal-content">
      <div class="modal-header text-white" style="background-color: #13411F;">
        <h5 class="modal-title">Manage Case: <span id="modal_case_no"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      
      <div class="modal-body p-4">
        <input type="hidden" id="current_transaction_id">
        
        <!-- Case Summary Banner -->
        <div class="alert alert-light border mb-3">
          <div class="row">
            <div class="col-md-4">
              <small class="text-muted">Complainant</small>
              <div class="fw-bold" id="summary_complainant"></div>
            </div>
            <div class="col-md-4">
              <small class="text-muted">Respondent</small>
              <div class="fw-bold" id="summary_respondent"></div>
            </div>
            <div class="col-md-2">
              <small class="text-muted">Stage</small>
              <div><span class="badge bg-info" id="summary_stage"></span></div>
            </div>
            <div class="col-md-2">
              <small class="text-muted">Status</small>
              <div><span class="badge bg-primary" id="summary_action"></span></div>
            </div>
          </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs" id="caseTab" role="tablist">
          <li class="nav-item">
            <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#tab-details">Case Details</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" id="pb-tab" data-bs-toggle="tab" data-bs-target="#tab-pb">Punong Barangay</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" id="lupon-tab" data-bs-toggle="tab" data-bs-target="#tab-lupon">Lupon Tagapamayapa</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" id="actions-tab" data-bs-toggle="tab" data-bs-target="#tab-actions">Case Actions</button>
          </li>
        </ul>

        <div class="tab-content mt-3" id="caseTabContent">
          
          <!-- TAB 1: CASE DETAILS -->
          <div class="tab-pane fade show active" id="tab-details">
            <form id="editDetailsForm">
              <input type="hidden" name="transaction_id" id="edit_transaction_id">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">Complaint Title</label>
                  <input name="complaint_title" id="edit_complaint_title" type="text" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Nature of Case</label>
                  <select name="nature_of_case" id="edit_nature_of_case" class="form-select form-select-sm" required>
                    <option value="Criminal">Criminal</option>
                    <option value="Civil">Civil</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label fw-bold">Complaint Affidavit</label>
                  <textarea name="complaint_affidavit" id="edit_complaint_affidavit" class="form-control form-control-sm" rows="3" required></textarea>
                </div>
                <div class="col-12">
                  <label class="form-label fw-bold">Pleading Statement</label>
                  <textarea name="pleading_statement" id="edit_pleading_statement" class="form-control form-control-sm" rows="3" required></textarea>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
              </div>
            </form>
          </div>

          <!-- TAB 2: PUNONG BARANGAY -->
          <div class="tab-pane fade" id="tab-pb">
            <ul class="nav nav-pills mb-3" id="pbSubTab">
              <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pb-1st">1st Meeting</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pb-2nd">2nd Meeting</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pb-3rd">3rd Meeting</button></li>
            </ul>
            
            <div class="tab-content">
              <!-- PB 1st Meeting -->
              <div class="tab-pane fade show active" id="pb-1st">
                <!-- Schedule Form -->
                <form method="POST" action="functions/process_schedule_pb.php" id="form-pb-1st-schedule">
                  <input type="hidden" name="transaction_id" class="pb-tid">
                  <input type="hidden" name="meeting_number" value="first">
                  <div class="row g-3">
                    <div class="col-md-3">
                      <label class="form-label">Schedule Date *</label>
                      <input type="date" name="schedule_date" id="pb_1st_date" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Schedule Time *</label>
                      <input type="time" name="schedule_time" id="pb_1st_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
                      <button type="submit" class="btn btn-primary btn-sm" id="btn-save-schedule-pb-1st">Save Schedule</button>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-edit-schedule-pb-1st" style="display:none;">Edit Schedule</button>
                      <button type="button" class="btn btn-success btn-sm" id="print-pb-1st" style="display:none;">Print Summon</button>
                    </div>
                  </div>
                </form>

                <!-- Affidavit Form -->
                <form method="POST" action="functions/process_schedule_pb.php" id="form-pb-1st-affidavit" class="mt-3">
                  <input type="hidden" name="transaction_id" class="pb-tid">
                  <input type="hidden" name="meeting_number" value="first">
                  <input type="hidden" name="affidavit_only" value="1">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Complainant Affidavit</label>
                      <textarea name="complainant_affidavit" id="pb_1st_comp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Respondent Affidavit</label>
                      <textarea name="respondent_affidavit" id="pb_1st_resp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                      <button type="submit" class="btn btn-primary btn-sm" id="btn-save-affidavit-pb-1st" style="display:none;">Save Affidavits</button>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-edit-affidavit-pb-1st" style="display:none;">Edit Affidavits</button>
                    </div>
                  </div>
                </form>
                
                <!-- Action buttons shown only after meeting is scheduled -->
                <div class="d-flex justify-content-end gap-2 mt-3" id="pb-1st-actions" style="display:none;">
                  <button type="button" class="btn btn-outline-primary btn-sm" id="proceed-to-pb-2nd" disabled>
                    Proceed to 2nd Meeting
                  </button>
                  <button type="button" class="btn btn-outline-warning btn-sm" id="skip-to-lupon-from-1st" disabled>
                    Skip to Lupon
                  </button>
                </div>
              </div>

              <!-- PB 2nd Meeting -->
              <div class="tab-pane fade" id="pb-2nd">
                <!-- Schedule Form -->
                <form method="POST" action="functions/process_schedule_pb.php" id="form-pb-2nd-schedule">
                  <input type="hidden" name="transaction_id" class="pb-tid">
                  <input type="hidden" name="meeting_number" value="second">
                  <div class="row g-3">
                    <div class="col-md-3">
                      <label class="form-label">Schedule Date *</label>
                      <input type="date" name="schedule_date" id="pb_2nd_date" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Schedule Time *</label>
                      <input type="time" name="schedule_time" id="pb_2nd_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
                      <button type="submit" class="btn btn-primary btn-sm" id="btn-save-schedule-pb-2nd">Save Schedule</button>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-edit-schedule-pb-2nd" style="display:none;">Edit Schedule</button>
                      <button type="button" class="btn btn-success btn-sm" id="print-pb-2nd" style="display:none;">Print Summon</button>
                    </div>
                  </div>
                </form>

                <!-- Affidavit Form -->
                <form method="POST" action="functions/process_schedule_pb.php" id="form-pb-2nd-affidavit" class="mt-3">
                  <input type="hidden" name="transaction_id" class="pb-tid">
                  <input type="hidden" name="meeting_number" value="second">
                  <input type="hidden" name="affidavit_only" value="1">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Complainant Affidavit</label>
                      <textarea name="complainant_affidavit" id="pb_2nd_comp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Respondent Affidavit</label>
                      <textarea name="respondent_affidavit" id="pb_2nd_resp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                      <button type="submit" class="btn btn-primary btn-sm" id="btn-save-affidavit-pb-2nd" style="display:none;">Save Affidavits</button>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-edit-affidavit-pb-2nd" style="display:none;">Edit Affidavits</button>
                    </div>
                  </div>
                </form>
                
                <!-- Action buttons -->
                <div class="d-flex justify-content-end gap-2 mt-3" id="pb-2nd-actions" style="display:none;">
                  <button type="button" class="btn btn-outline-primary btn-sm" id="proceed-to-pb-3rd" disabled>
                    Proceed to 3rd Meeting
                  </button>
                  <button type="button" class="btn btn-outline-warning btn-sm" id="skip-to-lupon-from-2nd" disabled>
                    Skip to Lupon
                  </button>
                </div>
              </div>

              <!-- PB 3rd Meeting -->
              <div class="tab-pane fade" id="pb-3rd">
                <!-- Schedule Form -->
                <form method="POST" action="functions/process_schedule_pb.php" id="form-pb-3rd-schedule">
                  <input type="hidden" name="transaction_id" class="pb-tid">
                  <input type="hidden" name="meeting_number" value="third">
                  <div class="row g-3">
                    <div class="col-md-3">
                      <label class="form-label">Schedule Date *</label>
                      <input type="date" name="schedule_date" id="pb_3rd_date" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Schedule Time *</label>
                      <input type="time" name="schedule_time" id="pb_3rd_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
                      <button type="submit" class="btn btn-primary btn-sm" id="btn-save-schedule-pb-3rd">Save Schedule</button>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-edit-schedule-pb-3rd" style="display:none;">Edit Schedule</button>
                      <button type="button" class="btn btn-success btn-sm" id="print-pb-3rd" style="display:none;">Print Summon</button>
                    </div>
                  </div>
                </form>

                <!-- Affidavit Form -->
                <form method="POST" action="functions/process_schedule_pb.php" id="form-pb-3rd-affidavit" class="mt-3">
                  <input type="hidden" name="transaction_id" class="pb-tid">
                  <input type="hidden" name="meeting_number" value="third">
                  <input type="hidden" name="affidavit_only" value="1">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Complainant Affidavit</label>
                      <textarea name="complainant_affidavit" id="pb_3rd_comp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Respondent Affidavit</label>
                      <textarea name="respondent_affidavit" id="pb_3rd_resp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                      <button type="submit" class="btn btn-primary btn-sm" id="btn-save-affidavit-pb-3rd" style="display:none;">Save Affidavits</button>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-edit-affidavit-pb-3rd" style="display:none;">Edit Affidavits</button>
                    </div>
                  </div>
                </form>
                
                <!-- Action buttons -->
                <div class="d-flex justify-content-end mt-3" id="pb-3rd-actions" style="display:none;">
                  <button type="button" class="btn btn-success btn-sm" id="proceed-to-lupon" disabled>
                    Proceed to Lupon
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 3: LUPON TAGAPAMAYAPA -->
          <div class="tab-pane fade" id="tab-lupon">
            <h6 class="fw-bold text-success">Lupon Hearings</h6>
            <hr class="mt-1">
            
            <ul class="nav nav-pills mb-3" id="luponSubTab">
              <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#lupon-1st">Unang Patawag</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#lupon-2nd">Ikalawang Patawag</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#lupon-3rd">Ikatlong Patawag</button></li>
            </ul>
            
            <div class="tab-content">
              <!-- Unang Patawag -->
              <div class="tab-pane fade show active" id="lupon-1st">
                <!-- Schedule Form -->
                <form method="POST" action="functions/process_schedule_lupon.php" id="form-lupon-1st-schedule">
                  <input type="hidden" name="transaction_id" class="lupon-tid">
                  <input type="hidden" name="hearing_number" value="unang">
                  <input type="hidden" name="chosen_pangkat" id="chosen_pangkat_hidden">
                  
                  <div class="row g-3">
                    <div class="col-md-3">
                      <label class="form-label">Schedule Date *</label>
                      <input type="date" name="schedule_date" id="lupon_1st_date" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Schedule Time *</label>
                      <input type="time" name="schedule_time" id="lupon_1st_time" class="form-control form-control-sm" required>
                    </div>
                    
                    <!-- Pangkat Members Selection -->
                    <div class="col-12 mt-3">
                      <h6 class="fw-bold text-success">Pangkat Tagapagkasundo Members</h6>
                      <hr class="mt-1">
                    </div>
                    <div class="col-12">
                      <label class="form-label">Choose Pangkat Members (Minimum: 3) *</label>
                      <select id="pangkat_members_dropdown" name="pangkat_members[]" class="form-select form-select-sm" multiple style="height: 120px;" required>
                        <option value="" disabled>Loading Lupon members...</option>
                      </select>
                      <small class="text-muted">Hold Ctrl/Cmd to select multiple members. Minimum of 3 members required.</small>
                      <div class="mt-2 d-flex justify-content-between align-items-center">
                        <div>
                          <strong>Selected:</strong> <span id="selected_members_display" class="text-primary">None</span>
                        </div>
                        <div class="d-flex gap-2">
                          <button type="submit" class="btn btn-primary btn-sm" id="btn-save-schedule-lupon-1st">Save Schedule/Members</button>
                          <button type="button" class="btn btn-warning btn-sm" id="btn-edit-schedule-lupon-1st" style="display:none;">Edit Schedule/Members</button>
                          <button type="button" class="btn btn-success btn-sm" id="print-lupon-1st" style="display:none;">Print Summon</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </form>

                <!-- Affidavit Form -->
                <form method="POST" action="functions/process_schedule_lupon.php" id="form-lupon-1st-affidavit" class="mt-3">
                  <input type="hidden" name="transaction_id" class="lupon-tid">
                  <input type="hidden" name="hearing_number" value="unang">
                  <input type="hidden" name="affidavit_only" value="1">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Complainant Affidavit</label>
                      <textarea name="complainant_affidavit" id="lupon_1st_comp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Respondent Affidavit</label>
                      <textarea name="respondent_affidavit" id="lupon_1st_resp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                      <button type="submit" class="btn btn-primary btn-sm" id="btn-save-affidavit-lupon-1st" style="display:none;">Save Affidavits</button>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-edit-affidavit-lupon-1st" style="display:none;">Edit Affidavits</button>
                    </div>
                  </div>
                </form>

                <!-- Action buttons shown only after hearing is scheduled -->
                <div class="d-flex justify-content-end gap-2 mt-3" id="lupon-1st-actions" style="display:none;">
                  <button type="button" class="btn btn-outline-primary btn-sm" id="proceed-to-ikalawang" disabled>
                    Proceed to Ikalawang Patawag
                  </button>
                </div>
              </div>

              <!-- Ikalawang Patawag -->
              <div class="tab-pane fade" id="lupon-2nd">
                <!-- Schedule Form -->
                <form method="POST" action="functions/process_schedule_lupon.php" id="form-lupon-2nd-schedule">
                  <input type="hidden" name="transaction_id" class="lupon-tid">
                  <input type="hidden" name="hearing_number" value="ikalawang">
                  <div class="row g-3">
                    <div class="col-md-3">
                      <label class="form-label">Schedule Date *</label>
                      <input type="date" name="schedule_date" id="lupon_2nd_date" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Schedule Time *</label>
                      <input type="time" name="schedule_time" id="lupon_2nd_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
                      <button type="submit" class="btn btn-primary btn-sm" id="btn-save-schedule-lupon-2nd">Save Schedule</button>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-edit-schedule-lupon-2nd" style="display:none;">Edit Schedule</button>
                      <button type="button" class="btn btn-success btn-sm" id="print-lupon-2nd" style="display:none;">Print Summon</button>
                    </div>
                  </div>
                </form>

                <!-- Affidavit Form -->
                <form method="POST" action="functions/process_schedule_lupon.php" id="form-lupon-2nd-affidavit" class="mt-3">
                  <input type="hidden" name="transaction_id" class="lupon-tid">
                  <input type="hidden" name="hearing_number" value="ikalawang">
                  <input type="hidden" name="affidavit_only" value="1">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Complainant Affidavit</label>
                      <textarea name="complainant_affidavit" id="lupon_2nd_comp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Respondent Affidavit</label>
                      <textarea name="respondent_affidavit" id="lupon_2nd_resp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                      <button type="submit" class="btn btn-primary btn-sm" id="btn-save-affidavit-lupon-2nd" style="display:none;">Save Affidavits</button>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-edit-affidavit-lupon-2nd" style="display:none;">Edit Affidavits</button>
                    </div>
                  </div>
                </form>

                <!-- Action buttons shown only after hearing is scheduled -->
                <div class="d-flex justify-content-end gap-2 mt-3" id="lupon-2nd-actions" style="display:none;">
                  <button type="button" class="btn btn-outline-primary btn-sm" id="proceed-to-ikatlong" disabled>
                    Proceed to Ikatlong Patawag
                  </button>
                </div>
              </div>

              <!-- Ikatlong Patawag -->
              <div class="tab-pane fade" id="lupon-3rd">
                <!-- Schedule Form -->
                <form method="POST" action="functions/process_schedule_lupon.php" id="form-lupon-3rd-schedule">
                  <input type="hidden" name="transaction_id" class="lupon-tid">
                  <input type="hidden" name="hearing_number" value="ikatlong">
                  <div class="row g-3">
                    <div class="col-md-3">
                      <label class="form-label">Schedule Date *</label>
                      <input type="date" name="schedule_date" id="lupon_3rd_date" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Schedule Time *</label>
                      <input type="time" name="schedule_time" id="lupon_3rd_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
                      <button type="submit" class="btn btn-primary btn-sm" id="btn-save-schedule-lupon-3rd">Save Schedule</button>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-edit-schedule-lupon-3rd" style="display:none;">Edit Schedule</button>
                      <button type="button" class="btn btn-success btn-sm" id="print-lupon-3rd" style="display:none;">Print Summon</button>
                    </div>
                  </div>
                </form>

                <!-- Affidavit Form -->
                <form method="POST" action="functions/process_schedule_lupon.php" id="form-lupon-3rd-affidavit" class="mt-3">
                  <input type="hidden" name="transaction_id" class="lupon-tid">
                  <input type="hidden" name="hearing_number" value="ikatlong">
                  <input type="hidden" name="affidavit_only" value="1">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Complainant Affidavit</label>
                      <textarea name="complainant_affidavit" id="lupon_3rd_comp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Respondent Affidavit</label>
                      <textarea name="respondent_affidavit" id="lupon_3rd_resp_aff" class="form-control form-control-sm" rows="3" disabled></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                      <button type="submit" class="btn btn-primary btn-sm" id="btn-save-affidavit-lupon-3rd" style="display:none;">Save Affidavits</button>
                      <button type="button" class="btn btn-warning btn-sm" id="btn-edit-affidavit-lupon-3rd" style="display:none;">Edit Affidavits</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- TAB 4: CASE ACTIONS -->
          <div class="tab-pane fade" id="tab-actions">
            <!-- Respondent Hold Status Section -->
            <div class="card mb-4" id="hold-status-section">
              <div class="card-header bg-light">
                <h6 class="mb-0 fw-bold text-dark">Respondent Hold Status</h6>
              </div>
              <div class="card-body">
                <div id="hold-status-loading" class="text-center py-3">
                  <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                  <span class="ms-2">Checking respondent status...</span>
                </div>
                
                <div id="hold-status-content" style="display:none;">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <label class="form-label text-muted small">Respondent Name</label>
                      <div class="fw-bold" id="hold-respondent-name"></div>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label text-muted small">Found in Purok</label>
                      <div class="fw-bold" id="hold-purok-location"></div>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label text-muted small">Current Status</label>
                      <div id="hold-current-status"></div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                      <button type="button" class="btn btn-warning btn-sm w-100" id="btn-hold-respondent" style="display:none;">
                        Hold
                      </button>
                      <button type="button" class="btn btn-success btn-sm w-100" id="btn-release-hold" style="display:none;">
                        Clear Hold Status
                      </button>
                    </div>
                  </div>
                </div>
                
                <div id="hold-status-not-found" style="display:none;">
                  <div class="alert alert-info mb-0">
                    <strong>Note:</strong> Respondent <span class="fw-bold" id="hold-respondent-name-nf"></span> is not registered in any Purok RBI table.
                  </div>
                </div>
              </div>
            </div>

            <form method="POST" action="functions/process_case_action.php">
              <input type="hidden" name="transaction_id" class="action-tid">
              <input type="hidden" name="current_stage" id="current_stage_hidden">
              <div class="row g-3">
                <div class="col-12">
                  <h6 class="fw-bold">Additional Case Information</h6>
                  <hr class="mt-2 mb-3">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Action Taken *</label>
                  <select name="action_taken" id="close_action_taken" class="form-select form-select-sm" required>
                    <option value="">Select action...</option>
                  </select>
                  <small class="text-muted" id="action_taken_hint"></small>
                </div>
                <div class="col-12">
                  <button type="submit" name="action_type" value="close_case" class="btn btn-danger">Close Case</button>
                </div>
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- CANCEL CASE CONFIRMATION MODAL -->
<div class="modal fade" id="cancelModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title">
          Confirm Cancellation
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="functions/cancel_complaint.php">
        <div class="modal-body">
          <p class="mb-2"><strong>Are you sure you want to cancel case <span id="cancel_case_no"></span>?</strong></p>
          <p class="text-muted small mb-0">This will set the case status to "Cancelled" and close the case.</p>
          <input type="hidden" name="transaction_id" id="cancel_transaction_id">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Go Back</button>
          <button type="submit" class="btn btn-warning">Yes, Cancel Case</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- SKIP TO LUPON CONFIRMATION MODAL -->
<div class="modal fade" id="skipToLuponModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title">
          Skip to Lupon Tagapamayapa
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2"><strong>Are you sure you want to skip the remaining Punong Barangay meetings?</strong></p>
        <p class="text-muted small mb-0">This will proceed directly to the Lupon Tagapamayapa hearings.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="confirmSkipToLupon">
          Yes, Skip to Lupon
        </button>
      </div>
    </div>
  </div>
</div>

<!-- HOLD RESPONDENT CONFIRMATION MODAL -->
<div class="modal fade" id="holdRespondentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title">
          Confirm Hold Respondent
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2"><strong>Are you sure you want to place this respondent on hold?</strong></p>
        <div class="alert alert-warning mb-2">
          <small><strong>Respondent:</strong> <span id="hold-confirm-name"></span></small><br>
          <small><strong>Location:</strong> <span id="hold-confirm-purok"></span></small>
        </div>
        <p class="text-muted small mb-0">This will update the respondent's status in the RBI system to "On Hold".</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="confirmHoldRespondent">
          Yes, Place On Hold
        </button>
      </div>
    </div>
  </div>
</div>

<!-- REMOVE HOLD CONFIRMATION MODAL -->
<div class="modal fade" id="removeHoldModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">
          Clear Hold Status
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2"><strong>Are you sure you want to clear the hold status?</strong></p>
        <div class="alert alert-info mb-2">
          <small><strong>Respondent:</strong> <span id="remove-hold-confirm-name"></span></small><br>
          <small><strong>Location:</strong> <span id="remove-hold-confirm-purok"></span></small>
        </div>
        <p class="text-muted small mb-0">This will clear the "On Hold" status from the respondent's RBI record.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmRemoveHold">
          Yes, Clear Hold Status
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  // Dynamic Bootstrap Alert Function
  function showAlert(message, type = 'success') {
    const container = document.getElementById('js-alert-container');
    const alertDiv = document.createElement('div');
    
    // Map alert types to Bootstrap classes
    const alertClasses = {
      'success': 'alert-success',
      'danger': 'alert-danger',
      'info': 'alert-info'
    };
    
    const alertClass = alertClasses[type] || 'alert-info';
    
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    container.appendChild(alertDiv);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
      alertDiv.classList.remove('show');
      setTimeout(() => alertDiv.remove(), 150);
    }, 5000);
  }
  
  // Search functionality
  const searchBtn = document.getElementById('searchBtn');
  const searchInput = document.getElementById('searchInput');
  const hasSearch = <?= json_encode(!empty($search)) ?>;

  searchBtn.addEventListener('click', () => {
    if (hasSearch) searchInput.value = '';
    searchBtn.closest('form').submit();
  });

  // Add Complaint Modal - Reset form on close
  const addModal = document.getElementById('addComplaintModal');
  addModal.addEventListener('hidden.bs.modal', () => {
    document.getElementById('addComplaintForm').reset();
  });

  // View button - Open View Case Modal (Read-Only)
  document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const row = btn.closest('tr');
      const data = JSON.parse(row.dataset.json);
      
      // Populate view modal
      document.getElementById('view_case_no').textContent = data.case_no || data.transaction_id;
      document.getElementById('view_complainant_full').textContent = data.complainant_name + ' - ' + data.complainant_address;
      document.getElementById('view_respondent_full').textContent = data.respondent_name + ' - ' + data.respondent_address;
      document.getElementById('view_complaint_title').textContent = data.complaint_title;
      document.getElementById('view_nature_of_case').textContent = data.nature_of_case;
      document.getElementById('view_date_filed').textContent = data.formatted_date_filed || '';
      document.getElementById('view_stage').textContent = data.complaint_stage;
      document.getElementById('view_action').textContent = data.action_taken;
      document.getElementById('view_complaint_affidavit').textContent = data.complaint_affidavit;
      document.getElementById('view_pleading_statement').textContent = data.pleading_statement;
      
      // Show/hide PB meetings
      const pbCards = ['1st', '2nd', '3rd'];
      pbCards.forEach(num => {
        const card = document.getElementById(`view_pb_${num}_card`);
        const schedData = data[`schedule_pb_${num === '1st' ? 'first' : num === '2nd' ? 'second' : 'third'}`];
        if (schedData) {
          card.style.display = 'block';
          document.getElementById(`view_pb_${num}_sched`).textContent = schedData || 'Not scheduled';
          document.getElementById(`view_pb_${num}_comp`).textContent = data[`complainant_affidavit_pb_${num === '1st' ? 'first' : num === '2nd' ? 'second' : 'third'}`] || 'N/A';
          document.getElementById(`view_pb_${num}_resp`).textContent = data[`respondent_affidavit_pb_${num === '1st' ? 'first' : num === '2nd' ? 'second' : 'third'}`] || 'N/A';
        } else {
          card.style.display = 'none';
        }
      });
      
      // Show/hide Lupon hearings
      const luponCards = [
        {num: '1st', field: 'unang_patawag'},
        {num: '2nd', field: 'ikalawang_patawag'},
        {num: '3rd', field: 'ikatlong_patawag'}
      ];
      luponCards.forEach(item => {
        const card = document.getElementById(`view_lupon_${item.num}_card`);
        const schedData = data[`schedule_${item.field}`];
        if (schedData) {
          card.style.display = 'block';
          document.getElementById(`view_lupon_${item.num}_sched`).textContent = schedData || 'Not scheduled';
          document.getElementById(`view_lupon_${item.num}_comp`).textContent = data[`complainant_affidavit_${item.field}`] || 'N/A';
          document.getElementById(`view_lupon_${item.num}_resp`).textContent = data[`respondent_affidavit_${item.field}`] || 'N/A';
        } else {
          card.style.display = 'none';
        }
      });
      
      // Show settlement section if case is closed
      const settlementSection = document.getElementById('view_settlement_section');
      if (data.complaint_stage === 'Closed' || data.date_settlement || data.date_cfa_issued) {
        settlementSection.style.display = 'block';
        document.getElementById('view_date_settlement').textContent = data.date_settlement || 'N/A';
        document.getElementById('view_date_cfa').textContent = data.date_cfa_issued || 'N/A';
        document.getElementById('view_chosen_pangkat').textContent = data.chosen_pangkat || 'N/A';
      } else {
        settlementSection.style.display = 'none';
      }
      
      new bootstrap.Modal(document.getElementById('viewCaseModal')).show();
    });
  });

  // Helper function to setup edit/save schedule buttons
  function setupScheduleButtons(meeting, data) {
    const schedField = meeting === 'pb-1st' ? 'schedule_pb_first' : 
                       meeting === 'pb-2nd' ? 'schedule_pb_second' :
                       meeting === 'pb-3rd' ? 'schedule_pb_third' :
                       meeting === 'lupon-1st' ? 'schedule_unang_patawag' :
                       meeting === 'lupon-2nd' ? 'schedule_ikalawang_patawag' :
                       'schedule_ikatlong_patawag';
    
    const dateId = meeting.replace('-', '_') + '_date';
    const timeId = meeting.replace('-', '_') + '_time';
    const saveBtn = document.getElementById(`btn-save-schedule-${meeting}`);
    const editBtn = document.getElementById(`btn-edit-schedule-${meeting}`);
    const dateInput = document.getElementById(dateId);
    const timeInput = document.getElementById(timeId);
    
    // For Lupon 1st meeting, also handle pangkat dropdown
    const pangkatDropdown = meeting === 'lupon-1st' ? document.getElementById('pangkat_members_dropdown') : null;
    
    if (data[schedField]) {
      // Schedule exists - show edit button, hide save button, disable inputs
      saveBtn.style.display = 'none';
      editBtn.style.display = 'inline-block';
      dateInput.disabled = true;
      timeInput.disabled = true;
      
      // Disable pangkat dropdown if this is Lupon 1st meeting
      if (pangkatDropdown) {
        pangkatDropdown.disabled = true;
      }
      
      editBtn.onclick = function() {
        // Enable editing
        dateInput.disabled = false;
        timeInput.disabled = false;
        saveBtn.style.display = 'inline-block';
        editBtn.style.display = 'none';
        
        // Enable pangkat dropdown if this is Lupon 1st meeting
        if (pangkatDropdown) {
          pangkatDropdown.disabled = false;
        }
      };
    } else {
      // No schedule - show save button, hide edit button, enable inputs
      saveBtn.style.display = 'inline-block';
      editBtn.style.display = 'none';
      dateInput.disabled = false;
      timeInput.disabled = false;
      
      // Enable pangkat dropdown if this is Lupon 1st meeting
      if (pangkatDropdown) {
        pangkatDropdown.disabled = false;
      }
    }
  }

  // Helper function to setup edit/save affidavit buttons
  function setupAffidavitButtons(meeting, data) {
    const schedField = meeting === 'pb-1st' ? 'schedule_pb_first' : 
                       meeting === 'pb-2nd' ? 'schedule_pb_second' :
                       meeting === 'pb-3rd' ? 'schedule_pb_third' :
                       meeting === 'lupon-1st' ? 'schedule_unang_patawag' :
                       meeting === 'lupon-2nd' ? 'schedule_ikalawang_patawag' :
                       'schedule_ikatlong_patawag';
    
    const compId = meeting.replace('-', '_') + '_comp_aff';
    const respId = meeting.replace('-', '_') + '_resp_aff';
    const saveBtn = document.getElementById(`btn-save-affidavit-${meeting}`);
    const editBtn = document.getElementById(`btn-edit-affidavit-${meeting}`);
    const compInput = document.getElementById(compId);
    const respInput = document.getElementById(respId);
    
    if (data[schedField]) {
      // Schedule exists - enable affidavit fields and show edit button
      const hasAffidavits = compInput.value || respInput.value;
      
      if (hasAffidavits) {
        // Affidavits exist - show edit button, disable fields
        saveBtn.style.display = 'none';
        editBtn.style.display = 'inline-block';
        compInput.disabled = true;
        respInput.disabled = true;
        
        editBtn.onclick = function() {
          // Enable editing
          compInput.disabled = false;
          respInput.disabled = false;
          saveBtn.style.display = 'inline-block';
          editBtn.style.display = 'none';
        };
      } else {
        // No affidavits yet - show save button, enable fields
        saveBtn.style.display = 'inline-block';
        editBtn.style.display = 'none';
        compInput.disabled = false;
        respInput.disabled = false;
      }
    } else {
      // No schedule - hide both buttons, disable fields
      saveBtn.style.display = 'none';
      editBtn.style.display = 'none';
      compInput.disabled = true;
      respInput.disabled = true;
    }
  }

  // Helper function to enable/disable proceed buttons
  function updateProceedButtons(meeting, data) {
    if (meeting === 'pb-1st') {
      const proceedBtn = document.getElementById('proceed-to-pb-2nd');
      const skipBtn = document.getElementById('skip-to-lupon-from-1st');
      
      if (data.schedule_pb_first) {
        // Hide both buttons if Unang Patawag is already scheduled
        if (data.schedule_unang_patawag) {
          proceedBtn.style.display = 'none';
          skipBtn.style.display = 'none';
        } else {
          // Hide proceed button if already in 2nd or 3rd meeting stage
          if (data.complaint_stage === 'Punong Barangay - 2nd' || data.complaint_stage === 'Punong Barangay - 3rd') {
            proceedBtn.style.display = 'none';
          } else {
            proceedBtn.disabled = false;
            proceedBtn.style.display = 'inline-block';
          }
          
          // Hide skip button if 2nd meeting is scheduled
          if (data.schedule_pb_second) {
            skipBtn.style.display = 'none';
          } else {
            skipBtn.disabled = false;
            skipBtn.style.display = 'inline-block';
          }
        }
      }
    } else if (meeting === 'pb-2nd') {
      const proceedBtn = document.getElementById('proceed-to-pb-3rd');
      const skipBtn = document.getElementById('skip-to-lupon-from-2nd');
      
      if (data.schedule_pb_second) {
        // Hide both buttons if Unang Patawag is already scheduled
        if (data.schedule_unang_patawag) {
          proceedBtn.style.display = 'none';
          skipBtn.style.display = 'none';
        } else {
          // Hide proceed button if already in 3rd meeting stage
          if (data.complaint_stage === 'Punong Barangay - 3rd') {
            proceedBtn.style.display = 'none';
          } else {
            proceedBtn.disabled = false;
            proceedBtn.style.display = 'inline-block';
          }
          
          // Hide skip button if 3rd meeting is scheduled
          if (data.schedule_pb_third) {
            skipBtn.style.display = 'none';
          } else {
            skipBtn.disabled = false;
            skipBtn.style.display = 'inline-block';
          }
        }
      }
    } else if (meeting === 'pb-3rd') {
      const proceedBtn = document.getElementById('proceed-to-lupon');
      
      if (data.schedule_pb_third) {
        // Hide button if Unang Patawag is already scheduled
        if (data.schedule_unang_patawag) {
          proceedBtn.style.display = 'none';
        } else {
          proceedBtn.disabled = false;
          proceedBtn.style.display = 'inline-block';
        }
      }
    }
  }

  // Helper function to enable/disable Lupon proceed buttons
  function updateLuponProceedButtons(hearing, data) {
      if (hearing === 'lupon-1st') {
        const proceedBtn = document.getElementById('proceed-to-ikalawang');
        
        if (data.schedule_unang_patawag && proceedBtn) {
          // Hide button if Ikalawang is already scheduled OR if already in Ikalawang/Ikatlong stage
          if (data.schedule_ikalawang_patawag || data.complaint_stage === 'Ikalawang Patawag' || data.complaint_stage === 'Ikatlong Patawag') {
            proceedBtn.style.display = 'none';
          } else {
            proceedBtn.disabled = false;
            proceedBtn.style.display = 'inline-block';
          }
        }
      } else if (hearing === 'lupon-2nd') {
        const proceedBtn = document.getElementById('proceed-to-ikatlong');
        
        if (data.schedule_ikalawang_patawag && proceedBtn) {
          // Hide button if Ikatlong is already scheduled OR if already in Ikatlong stage
          if (data.schedule_ikatlong_patawag || data.complaint_stage === 'Ikatlong Patawag') {
            proceedBtn.style.display = 'none';
          } else {
            proceedBtn.disabled = false;
            proceedBtn.style.display = 'inline-block';
          }
        }
      }
    }

  // Helper function to populate dynamic action taken options
  function populateActionTaken(stage) {
    const select = document.getElementById('close_action_taken');
    const hint = document.getElementById('action_taken_hint');
    
    // Clear existing options
    select.innerHTML = '<option value="">Select action...</option>';
    
    // Always available options
    const alwaysOptions = [
      { value: 'Dismissed', text: 'Dismissed (Endorsed to Court)' },
      { value: 'Cancelled', text: 'Cancelled' },
      { value: 'CFA', text: 'CFA (Certificate of File Action)' },
      { value: 'Withdrawn', text: 'Withdrawn' }
    ];
    
    // Stage-specific options
    if (stage && stage.includes('Punong Barangay')) {
      // Add Mediated option
      const mediatedOption = document.createElement('option');
      mediatedOption.value = 'Mediated';
      mediatedOption.textContent = 'Mediated (Settled by Punong Barangay)';
      select.appendChild(mediatedOption);
      hint.textContent = 'Settlement date will be automatically set to today\'s date for Mediated cases';
    } else if (stage && stage.includes('Patawag')) {
      // Add Conciliated option
      const conciliatedOption = document.createElement('option');
      conciliatedOption.value = 'Conciliated';
      conciliatedOption.textContent = 'Conciliated (Settled by Lupon)';
      select.appendChild(conciliatedOption);
      hint.textContent = 'Settlement date will be automatically set to today\'s date for Conciliated cases';
    } else {
      hint.textContent = '';
    }
    
    // Add always available options
    alwaysOptions.forEach(opt => {
      const option = document.createElement('option');
      option.value = opt.value;
      option.textContent = opt.text;
      select.appendChild(option);
    });
  }

  // Edit button - Open Manage Case Modal
  document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const row = btn.closest('tr');
      const data = JSON.parse(row.dataset.json);
      
      // Populate modal with data
      document.getElementById('current_transaction_id').value = data.transaction_id;
      document.getElementById('modal_case_no').textContent = data.case_no || data.transaction_id;
      document.getElementById('summary_complainant').textContent = data.complainant_name;
      document.getElementById('summary_respondent').textContent = data.respondent_name;
      document.getElementById('summary_stage').textContent = data.complaint_stage;
      document.getElementById('summary_action').textContent = data.action_taken;
      
      // Case Details Tab
      document.getElementById('edit_transaction_id').value = data.transaction_id;
      document.getElementById('edit_complaint_title').value = data.complaint_title;
      document.getElementById('edit_nature_of_case').value = data.nature_of_case;
      document.getElementById('edit_complaint_affidavit').value = data.complaint_affidavit;
      document.getElementById('edit_pleading_statement').value = data.pleading_statement;
      
      // Populate all transaction_id hidden fields
      document.querySelectorAll('.pb-tid, .lupon-tid, .action-tid').forEach(el => {
        el.value = data.transaction_id;
      });
      
      // Populate chosen_pangkat in Lupon tab
      loadExistingPangkatMembers(data.chosen_pangkat || '');
      
      // Store current stage for action taken filtering
      document.getElementById('current_stage_hidden').value = data.complaint_stage;
      
      // Populate dynamic action taken options
      populateActionTaken(data.complaint_stage);
      
      // Function to get next available weekday (excluding weekends)
      function getNextWeekday(dateString) {
        const date = new Date(dateString);
        date.setDate(date.getDate() + 1); // Start from next day
        
        // Skip to Monday if it's Saturday or Sunday
        while (date.getDay() === 0 || date.getDay() === 6) {
          date.setDate(date.getDate() + 1);
        }
        
        return date.toISOString().split('T')[0];
      }
      
      // Function to disable weekends on date input
      function setupWeekdayOnly(inputId, minDate) {
        const input = document.getElementById(inputId);
        input.setAttribute('min', minDate);
        
        // Add change listener to prevent weekend selection
        input.addEventListener('input', function(e) {
          const selectedDate = new Date(e.target.value);
          const dayOfWeek = selectedDate.getDay();
          
          // If weekend selected, move to next Monday
          if (dayOfWeek === 0 || dayOfWeek === 6) {
            let nextDate = new Date(selectedDate);
            while (nextDate.getDay() === 0 || nextDate.getDay() === 6) {
              nextDate.setDate(nextDate.getDate() + 1);
            }
            e.target.value = nextDate.toISOString().split('T')[0];
          }
        });
      }
      
      // Set minimum dates based on previous meeting schedules
      const today = new Date().toISOString().split('T')[0];
      
      // PB 1st Meeting - minimum is today (if no schedule) or keep existing
      if (!data.schedule_pb_first) {
        setupWeekdayOnly('pb_1st_date', today);
      } else {
        setupWeekdayOnly('pb_1st_date', data.schedule_pb_first.split(' ')[0]);
      }
      
      // PB 2nd Meeting - minimum is day after 1st meeting
      if (data.schedule_pb_first) {
        const minDate2nd = getNextWeekday(data.schedule_pb_first.split(' ')[0]);
        setupWeekdayOnly('pb_2nd_date', minDate2nd);
      } else {
        setupWeekdayOnly('pb_2nd_date', today);
      }
      
      // PB 3rd Meeting - minimum is day after 2nd meeting (or 1st if 2nd not scheduled)
      if (data.schedule_pb_second) {
        const minDate3rd = getNextWeekday(data.schedule_pb_second.split(' ')[0]);
        setupWeekdayOnly('pb_3rd_date', minDate3rd);
      } else if (data.schedule_pb_first) {
        const minDate3rd = getNextWeekday(data.schedule_pb_first.split(' ')[0]);
        setupWeekdayOnly('pb_3rd_date', minDate3rd);
      } else {
        setupWeekdayOnly('pb_3rd_date', today);
      }
      
      // Lupon 1st - minimum is day after PB 3rd (or 2nd, or 1st, or today)
      if (data.schedule_pb_third) {
        const minDateLupon1st = getNextWeekday(data.schedule_pb_third.split(' ')[0]);
        setupWeekdayOnly('lupon_1st_date', minDateLupon1st);
      } else if (data.schedule_pb_second) {
        const minDateLupon1st = getNextWeekday(data.schedule_pb_second.split(' ')[0]);
        setupWeekdayOnly('lupon_1st_date', minDateLupon1st);
      } else if (data.schedule_pb_first) {
        const minDateLupon1st = getNextWeekday(data.schedule_pb_first.split(' ')[0]);
        setupWeekdayOnly('lupon_1st_date', minDateLupon1st);
      } else {
        setupWeekdayOnly('lupon_1st_date', today);
      }
      
      // Lupon 2nd - minimum is day after Lupon 1st
      if (data.schedule_unang_patawag) {
        const minDateLupon2nd = getNextWeekday(data.schedule_unang_patawag.split(' ')[0]);
        setupWeekdayOnly('lupon_2nd_date', minDateLupon2nd);
      } else {
        setupWeekdayOnly('lupon_2nd_date', today);
      }
      
      // Lupon 3rd - minimum is day after Lupon 2nd
      if (data.schedule_ikalawang_patawag) {
        const minDateLupon3rd = getNextWeekday(data.schedule_ikalawang_patawag.split(' ')[0]);
        setupWeekdayOnly('lupon_3rd_date', minDateLupon3rd);
      } else if (data.schedule_unang_patawag) {
        const minDateLupon3rd = getNextWeekday(data.schedule_unang_patawag.split(' ')[0]);
        setupWeekdayOnly('lupon_3rd_date', minDateLupon3rd);
      } else {
        setupWeekdayOnly('lupon_3rd_date', today);
      }
      
      // PB 1st Meeting
      if (data.schedule_pb_first) {
        const [d, t] = data.schedule_pb_first.split(' ');
        document.getElementById('pb_1st_date').value = d;
        document.getElementById('pb_1st_time').value = t.slice(0,5);
      }
      document.getElementById('pb_1st_comp_aff').value = data.complainant_affidavit_pb_first || '';
      document.getElementById('pb_1st_resp_aff').value = data.respondent_affidavit_pb_first || '';
      setupScheduleButtons('pb-1st', data);
      setupAffidavitButtons('pb-1st', data);
      updateProceedButtons('pb-1st', data);

      // Show print button and action buttons if 1st PB meeting is scheduled
      const printBtn1st = document.getElementById('print-pb-1st');
      const pb1stActions = document.getElementById('pb-1st-actions');
      if (data.schedule_pb_first) {
        printBtn1st.style.display = 'inline-block';
        printBtn1st.onclick = function() {
          const [schedDate, schedTime] = data.schedule_pb_first.split(' ');
          const printUrl = `functions/print_complaint.php?transaction_id=${data.transaction_id}&stage=Punong Barangay&date=${schedDate}&time=${schedTime}`;
          window.open(printUrl, '_blank');
        };
        pb1stActions.style.display = 'flex';
      } else {
        printBtn1st.style.display = 'none';
        pb1stActions.style.display = 'none';
      }
      
      // PB 2nd Meeting
      if (data.schedule_pb_second) {
        const [d, t] = data.schedule_pb_second.split(' ');
        document.getElementById('pb_2nd_date').value = d;
        document.getElementById('pb_2nd_time').value = t.slice(0,5);
      }
      document.getElementById('pb_2nd_comp_aff').value = data.complainant_affidavit_pb_second || '';
      document.getElementById('pb_2nd_resp_aff').value = data.respondent_affidavit_pb_second || '';
      setupScheduleButtons('pb-2nd', data);
      setupAffidavitButtons('pb-2nd', data);
      updateProceedButtons('pb-2nd', data);

      const printBtn2nd = document.getElementById('print-pb-2nd');
      const pb2ndActions = document.getElementById('pb-2nd-actions');
      if (data.schedule_pb_second) {
        printBtn2nd.style.display = 'inline-block';
        printBtn2nd.onclick = function() {
          const [schedDate, schedTime] = data.schedule_pb_second.split(' ');
          const printUrl = `functions/print_complaint.php?transaction_id=${data.transaction_id}&stage=Punong Barangay&date=${schedDate}&time=${schedTime}`;
          window.open(printUrl, '_blank');
        };
        pb2ndActions.style.display = 'flex';
      } else {
        printBtn2nd.style.display = 'none';
        pb2ndActions.style.display = 'none';
      }
      
      // PB 3rd Meeting
      if (data.schedule_pb_third) {
        const [d, t] = data.schedule_pb_third.split(' ');
        document.getElementById('pb_3rd_date').value = d;
        document.getElementById('pb_3rd_time').value = t.slice(0,5);
      }
      document.getElementById('pb_3rd_comp_aff').value = data.complainant_affidavit_pb_third || '';
      document.getElementById('pb_3rd_resp_aff').value = data.respondent_affidavit_pb_third || '';
      setupScheduleButtons('pb-3rd', data);
      setupAffidavitButtons('pb-3rd', data);
      updateProceedButtons('pb-3rd', data);

      const printBtn3rd = document.getElementById('print-pb-3rd');
      const pb3rdActions = document.getElementById('pb-3rd-actions');
      if (data.schedule_pb_third) {
        printBtn3rd.style.display = 'inline-block';
        printBtn3rd.onclick = function() {
          const [schedDate, schedTime] = data.schedule_pb_third.split(' ');
          const printUrl = `functions/print_complaint.php?transaction_id=${data.transaction_id}&stage=Punong Barangay&date=${schedDate}&time=${schedTime}`;
          window.open(printUrl, '_blank');
        };
        pb3rdActions.style.display = 'flex';
      } else {
        printBtn3rd.style.display = 'none';
        pb3rdActions.style.display = 'none';
      }
      
      // Lupon Unang Patawag
      if (data.schedule_unang_patawag) {
        const [d, t] = data.schedule_unang_patawag.split(' ');
        document.getElementById('lupon_1st_date').value = d;
        document.getElementById('lupon_1st_time').value = t.slice(0,5);
      }
      document.getElementById('lupon_1st_comp_aff').value = data.complainant_affidavit_unang_patawag || '';
      document.getElementById('lupon_1st_resp_aff').value = data.respondent_affidavit_unang_patawag || '';
      setupScheduleButtons('lupon-1st', data);
      setupAffidavitButtons('lupon-1st', data);
      updateLuponProceedButtons('lupon-1st', data);

      // Show print button if Unang Patawag is scheduled
      const printBtnUnang = document.getElementById('print-lupon-1st');
      const lupon1stActions = document.getElementById('lupon-1st-actions');
      if (data.schedule_unang_patawag) {
        printBtnUnang.style.display = 'inline-block';
        printBtnUnang.onclick = function() {
          const [schedDate, schedTime] = data.schedule_unang_patawag.split(' ');
          const printUrl = `functions/print_complaint.php?transaction_id=${data.transaction_id}&stage=Lupon Tagapamayapa&date=${schedDate}&time=${schedTime}`;
          window.open(printUrl, '_blank');
        };
        lupon1stActions.style.display = 'flex';
      } else {
        printBtnUnang.style.display = 'none';
        lupon1stActions.style.display = 'none';
      }
      
      // Lupon Ikalawang Patawag
      if (data.schedule_ikalawang_patawag) {
        const [d, t] = data.schedule_ikalawang_patawag.split(' ');
        document.getElementById('lupon_2nd_date').value = d;
        document.getElementById('lupon_2nd_time').value = t.slice(0,5);
      }
      document.getElementById('lupon_2nd_comp_aff').value = data.complainant_affidavit_ikalawang_patawag || '';
      document.getElementById('lupon_2nd_resp_aff').value = data.respondent_affidavit_ikalawang_patawag || '';
      setupScheduleButtons('lupon-2nd', data);
      setupAffidavitButtons('lupon-2nd', data);
      updateLuponProceedButtons('lupon-2nd', data);

      // Show print button if Ikalawang Patawag is scheduled
      const printBtnIkalawang = document.getElementById('print-lupon-2nd');
      const lupon2ndActions = document.getElementById('lupon-2nd-actions');
      if (data.schedule_ikalawang_patawag) {
        printBtnIkalawang.style.display = 'inline-block';
        printBtnIkalawang.onclick = function() {
          const [schedDate, schedTime] = data.schedule_ikalawang_patawag.split(' ');
          const printUrl = `functions/print_complaint.php?transaction_id=${data.transaction_id}&stage=Lupon Tagapamayapa&date=${schedDate}&time=${schedTime}`;
          window.open(printUrl, '_blank');
        };
        lupon2ndActions.style.display = 'flex';
      } else {
        printBtnIkalawang.style.display = 'none';
        lupon2ndActions.style.display = 'none';
      }
      
      // Lupon Ikatlong Patawag
      if (data.schedule_ikatlong_patawag) {
        const [d, t] = data.schedule_ikatlong_patawag.split(' ');
        document.getElementById('lupon_3rd_date').value = d;
        document.getElementById('lupon_3rd_time').value = t.slice(0,5);
      }
      document.getElementById('lupon_3rd_comp_aff').value = data.complainant_affidavit_ikatlong_patawag || '';
      document.getElementById('lupon_3rd_resp_aff').value = data.respondent_affidavit_ikatlong_patawag || '';
      setupScheduleButtons('lupon-3rd', data);
      setupAffidavitButtons('lupon-3rd', data);

      // Show print button if Ikatlong Patawag is scheduled
      const printBtnIkatlong = document.getElementById('print-lupon-3rd');
      if (data.schedule_ikatlong_patawag) {
        printBtnIkatlong.style.display = 'inline-block';
        printBtnIkatlong.onclick = function() {
          const [schedDate, schedTime] = data.schedule_ikatlong_patawag.split(' ');
          const printUrl = `functions/print_complaint.php?transaction_id=${data.transaction_id}&stage=Lupon Tagapamayapa&date=${schedDate}&time=${schedTime}`;
          window.open(printUrl, '_blank');
        };
      } else {
        printBtnIkatlong.style.display = 'none';
      }
      
      // DISABLE TABS BASED ON STAGE
      const pbTab = document.getElementById('pb-tab');
      const luponTab = document.getElementById('lupon-tab');
      const actionsTab = document.getElementById('actions-tab');

      // Reset all tabs
      [pbTab, luponTab, actionsTab].forEach(tab => {
        tab.classList.remove('disabled');
        tab.removeAttribute('disabled');
      });

      // Disable tabs based on current stage
      const stage = data.complaint_stage;

      // ALWAYS disable Case Actions tab if no 1st PB meeting scheduled
      // OR if case is already closed (action_taken is set to final status)
      const finalStatuses = ['Mediated', 'Conciliated', 'Dismissed', 'Cancelled', 'CFA', 'Withdrawn', 'Arbitrated'];
      if (!data.schedule_pb_first || finalStatuses.includes(data.action_taken)) {
        actionsTab.classList.add('disabled');
        actionsTab.setAttribute('disabled', 'true');
      }

      if (stage === 'Filing') {
        luponTab.classList.add('disabled');
        luponTab.setAttribute('disabled', 'true');
      } else if (stage.includes('Punong Barangay')) {
        luponTab.classList.add('disabled');
        luponTab.setAttribute('disabled', 'true');
      } else if (stage.includes('Patawag')) {
        // All tabs enabled
      } else if (stage === 'Closed') {
        pbTab.classList.add('disabled');
        pbTab.setAttribute('disabled', 'true');
        luponTab.classList.add('disabled');
        luponTab.setAttribute('disabled', 'true');
      }

      // DISABLE PB SUB-TABS
      const pb2ndTab = document.querySelector('[data-bs-target="#pb-2nd"]');
      const pb3rdTab = document.querySelector('[data-bs-target="#pb-3rd"]');

      pb2ndTab.classList.add('disabled');
      pb2ndTab.setAttribute('disabled', 'true');
      pb2ndTab.style.pointerEvents = 'none';
      pb2ndTab.style.opacity = '0.5';

      pb3rdTab.classList.add('disabled');
      pb3rdTab.setAttribute('disabled', 'true');
      pb3rdTab.style.pointerEvents = 'none';
      pb3rdTab.style.opacity = '0.5';

      if (data.schedule_pb_second) {
        pb2ndTab.classList.remove('disabled');
        pb2ndTab.removeAttribute('disabled');
        pb2ndTab.style.pointerEvents = '';
        pb2ndTab.style.opacity = '';
      }

      if (data.schedule_pb_third) {
        pb3rdTab.classList.remove('disabled');
        pb3rdTab.removeAttribute('disabled');
        pb3rdTab.style.pointerEvents = '';
        pb3rdTab.style.opacity = '';
      }

      // DISABLE LUPON SUB-TABS
      const luponUnangTab = document.querySelector('[data-bs-target="#lupon-1st"]');
      const luponIkalawangTab = document.querySelector('[data-bs-target="#lupon-2nd"]');
      const luponIkatlongTab = document.querySelector('[data-bs-target="#lupon-3rd"]');

      // Always enable Unang Patawag if Lupon tab is enabled
      luponUnangTab.classList.remove('disabled');
      luponUnangTab.removeAttribute('disabled');
      luponUnangTab.style.pointerEvents = '';
      luponUnangTab.style.opacity = '';

      // Disable Ikalawang and Ikatlong by default
      luponIkalawangTab.classList.add('disabled');
      luponIkalawangTab.setAttribute('disabled', 'true');
      luponIkalawangTab.style.pointerEvents = 'none';
      luponIkalawangTab.style.opacity = '0.5';

      luponIkatlongTab.classList.add('disabled');
      luponIkatlongTab.setAttribute('disabled', 'true');
      luponIkatlongTab.style.pointerEvents = 'none';
      luponIkatlongTab.style.opacity = '0.5';

      // Only enable tabs if there's ALREADY a schedule (not just enabled by proceed button)
      // This prevents tabs from being clickable until proceed button is actually clicked
      if (data.schedule_ikalawang_patawag) {
        luponIkalawangTab.classList.remove('disabled');
        luponIkalawangTab.removeAttribute('disabled');
        luponIkalawangTab.style.pointerEvents = '';
        luponIkalawangTab.style.opacity = '';
      }

      if (data.schedule_ikatlong_patawag) {
        luponIkatlongTab.classList.remove('disabled');
        luponIkatlongTab.removeAttribute('disabled');
        luponIkatlongTab.style.pointerEvents = '';
        luponIkatlongTab.style.opacity = '';
      }
      
      // Determine which tab to show based on current stage and schedules
      let targetTabId = 'details-tab'; // Default to Case Details
      let targetSubTabId = null;
      
      // Check if case is closed - if yes, ALWAYS show Case Details tab
      // const finalStatuses = ['Mediated', 'Conciliated', 'Dismissed', 'Cancelled', 'CFA', 'Withdrawn', 'Arbitrated'];
      const isCaseClosed = finalStatuses.includes(data.action_taken) || data.complaint_stage === 'Closed';
      
      if (!isCaseClosed) {
        // Only auto-navigate to stages if case is NOT closed
        // Check if there are any schedules - if yes, show the appropriate stage
        if (data.schedule_ikatlong_patawag) {
          targetTabId = 'lupon-tab';
          targetSubTabId = 'lupon-3rd';
        } else if (data.schedule_ikalawang_patawag) {
          targetTabId = 'lupon-tab';
          targetSubTabId = 'lupon-2nd';
        } else if (data.schedule_unang_patawag) {
          targetTabId = 'lupon-tab';
          targetSubTabId = 'lupon-1st';
        } else if (data.schedule_pb_third) {
          targetTabId = 'pb-tab';
          targetSubTabId = 'pb-3rd';
        } else if (data.schedule_pb_second) {
          targetTabId = 'pb-tab';
          targetSubTabId = 'pb-2nd';
        } else if (data.schedule_pb_first) {
          targetTabId = 'pb-tab';
          targetSubTabId = 'pb-1st';
        }
      }
      // If case is closed, stays on Case Details tab
      
      // Show modal
      const manageCaseModal = new bootstrap.Modal(document.getElementById('manageCaseModal'));
      manageCaseModal.show();

      // Check respondent hold status when Case Actions tab is clicked
      checkRespondentHoldStatus(data.transaction_id);
      
      // After modal is shown, activate the target tab
      setTimeout(() => {
        const targetTab = document.getElementById(targetTabId);
        if (targetTab) {
          const tabInstance = new bootstrap.Tab(targetTab);
          tabInstance.show();
          
          // If there's a sub-tab to show, activate it
          if (targetSubTabId) {
            setTimeout(() => {
              const subTab = document.querySelector(`[data-bs-target="#${targetSubTabId}"]`);
              if (subTab) {
                const subTabInstance = new bootstrap.Tab(subTab);
                subTabInstance.show();
              }
            }, 100);
          }
        }
      }, 100);
    });
  });

  // Cancel button
  document.querySelectorAll('.cancel-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const row = btn.closest('tr');
      const tid = row.dataset.id;
      const caseNo = row.dataset.caseNo || tid;
      
      document.getElementById('cancel_transaction_id').value = tid;
      document.getElementById('cancel_case_no').textContent = caseNo;
      
      new bootstrap.Modal(document.getElementById('cancelModal')).show();
    });
  });

  // Proceed to 2nd PB Meeting
  document.getElementById('proceed-to-pb-2nd').addEventListener('click', () => {
    const pb2ndTab = document.querySelector('[data-bs-target="#pb-2nd"]');
    pb2ndTab.classList.remove('disabled');
    pb2ndTab.removeAttribute('disabled');
    pb2ndTab.style.pointerEvents = '';
    pb2ndTab.style.opacity = '';
    bootstrap.Tab.getInstance(pb2ndTab)?.show() || new bootstrap.Tab(pb2ndTab).show();
  });

  // Skip to Lupon from 1st meeting
  document.getElementById('skip-to-lupon-from-1st').addEventListener('click', () => {
    const skipModal = new bootstrap.Modal(document.getElementById('skipToLuponModal'));
    skipModal.show();
    document.getElementById('confirmSkipToLupon').onclick = async function() {
      const tid = document.getElementById('current_transaction_id').value;
      
      try {
        const response = await fetch(`functions/process_skip_to_lupon.php?transaction_id=${tid}&ajax=1`);
        const result = await response.json();
        
        if (result.success) {
          // Enable Lupon tab
          const luponTab = document.getElementById('lupon-tab');
          luponTab.classList.remove('disabled');
          luponTab.removeAttribute('disabled');
          
          // Switch to Lupon tab
          bootstrap.Tab.getInstance(luponTab)?.show() || new bootstrap.Tab(luponTab).show();
          
          // Close skip modal
          skipModal.hide();
          
          const row = document.querySelector(`tr[data-id="${tid}"]`);
          const caseNo = row ? (row.dataset.caseNo || tid) : tid;
          showAlert(`Case <strong>${caseNo}</strong>: ${result.message}`, 'success');
        } else {
          showAlert('Error: ' + result.message, 'danger');
        }
      } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
      }
    };
  });

  // Proceed to 3rd PB Meeting
  document.getElementById('proceed-to-pb-3rd').addEventListener('click', () => {
    const pb3rdTab = document.querySelector('[data-bs-target="#pb-3rd"]');
    pb3rdTab.classList.remove('disabled');
    pb3rdTab.removeAttribute('disabled');
    pb3rdTab.style.pointerEvents = '';
    pb3rdTab.style.opacity = '';
    bootstrap.Tab.getInstance(pb3rdTab)?.show() || new bootstrap.Tab(pb3rdTab).show();
  });

  // Skip to Lupon from 2nd meeting
  document.getElementById('skip-to-lupon-from-2nd').addEventListener('click', () => {
    const skipModal = new bootstrap.Modal(document.getElementById('skipToLuponModal'));
    skipModal.show();
    document.getElementById('confirmSkipToLupon').onclick = async function() {
      const tid = document.getElementById('current_transaction_id').value;
      
      try {
        const response = await fetch(`functions/process_skip_to_lupon.php?transaction_id=${tid}&ajax=1`);
        const result = await response.json();
        
        if (result.success) {
          // Enable Lupon tab
          const luponTab = document.getElementById('lupon-tab');
          luponTab.classList.remove('disabled');
          luponTab.removeAttribute('disabled');
          
          // Switch to Lupon tab
          bootstrap.Tab.getInstance(luponTab)?.show() || new bootstrap.Tab(luponTab).show();
          
          // Close skip modal
          skipModal.hide();
          
          const row = document.querySelector(`tr[data-id="${tid}"]`);
          const caseNo = row ? (row.dataset.caseNo || tid) : tid;
          showAlert(`Case <strong>${caseNo}</strong>: ${result.message}`, 'success');
        } else {
          showAlert('Error: ' + result.message, 'danger');
        }
      } catch (error) {
        alert('Error: ' + error.message);
      }
    };
  });

  // Proceed to Lupon from 3rd meeting
  document.getElementById('proceed-to-lupon').addEventListener('click', () => {
    const skipModal = new bootstrap.Modal(document.getElementById('skipToLuponModal'));
    skipModal.show();
    document.getElementById('confirmSkipToLupon').onclick = async function() {
      const tid = document.getElementById('current_transaction_id').value;
      
      try {
        const response = await fetch(`functions/process_skip_to_lupon.php?transaction_id=${tid}&ajax=1`);
        const result = await response.json();
        
        if (result.success) {
          // Enable Lupon tab
          const luponTab = document.getElementById('lupon-tab');
          luponTab.classList.remove('disabled');
          luponTab.removeAttribute('disabled');
          
          // Switch to Lupon tab
          bootstrap.Tab.getInstance(luponTab)?.show() || new bootstrap.Tab(luponTab).show();
          
          // Close skip modal
          skipModal.hide();
          
          const row = document.querySelector(`tr[data-id="${tid}"]`);
          const caseNo = row ? (row.dataset.caseNo || tid) : tid;
          showAlert(`Case <strong>${caseNo}</strong>: ${result.message}`, 'success');
        } else {
          showAlert('Error: ' + result.message, 'danger');
        }
      } catch (error) {
        alert('Error: ' + error.message);
      }
    };
  });

  // Proceed to Ikalawang Patawag from Unang
  document.getElementById('proceed-to-ikalawang').addEventListener('click', () => {
    const lupon2ndTab = document.querySelector('[data-bs-target="#lupon-2nd"]');
    
    // Enable the tab
    lupon2ndTab.classList.remove('disabled');
    lupon2ndTab.removeAttribute('disabled');
    lupon2ndTab.style.pointerEvents = '';
    lupon2ndTab.style.opacity = '';
    
    // Switch to it
    bootstrap.Tab.getInstance(lupon2ndTab)?.show() || new bootstrap.Tab(lupon2ndTab).show();
  });

  // Proceed to Ikatlong Patawag from Ikalawang
  document.getElementById('proceed-to-ikatlong').addEventListener('click', () => {
    const lupon3rdTab = document.querySelector('[data-bs-target="#lupon-3rd"]');
    
    // Enable the tab
    lupon3rdTab.classList.remove('disabled');
    lupon3rdTab.removeAttribute('disabled');
    lupon3rdTab.style.pointerEvents = '';
    lupon3rdTab.style.opacity = '';
    
    // Switch to it
    bootstrap.Tab.getInstance(lupon3rdTab)?.show() || new bootstrap.Tab(lupon3rdTab).show();
  });

  // Fetch and populate Lupon members dropdown
  let luponMembersData = [];

  async function loadLuponMembers() {
    try {
      const response = await fetch('functions/fetch_lupon_members.php');
      const data = await response.json();
      luponMembersData = data;
      
      const dropdown = document.getElementById('pangkat_members_dropdown');
      dropdown.innerHTML = '';
      
      if (data.length === 0) {
        dropdown.innerHTML = '<option value="" disabled>No Lupon members found</option>';
      } else {
        data.forEach(member => {
          const option = document.createElement('option');
          option.value = member.full_name;
          option.textContent = member.full_name;
          dropdown.appendChild(option);
        });
      }
    } catch (error) {
      console.error('Error loading Lupon members:', error);
      document.getElementById('pangkat_members_dropdown').innerHTML = '<option value="" disabled>Error loading members</option>';
    }
  }

  // Update selected members display and hidden input
  document.getElementById('pangkat_members_dropdown').addEventListener('change', function() {
    const selectedOptions = Array.from(this.selectedOptions);
    const selectedNames = selectedOptions.map(opt => opt.value);
    
    // Update display
    const displaySpan = document.getElementById('selected_members_display');
    if (selectedNames.length === 0) {
      displaySpan.textContent = 'None';
    } else {
      displaySpan.textContent = selectedNames.join(', ');
    }
    
    // Update hidden input (comma-separated)
    document.getElementById('chosen_pangkat_hidden').value = selectedNames.join(', ');
  });

  // Load existing selected members when modal opens
  function loadExistingPangkatMembers(chosenPangkat) {
    const dropdown = document.getElementById('pangkat_members_dropdown');
    
    if (!chosenPangkat) {
      // Clear all selections
      Array.from(dropdown.options).forEach(opt => opt.selected = false);
      document.getElementById('selected_members_display').textContent = 'None';
      document.getElementById('chosen_pangkat_hidden').value = '';
      return;
    }
    
    // Split by comma and trim
    const existingMembers = chosenPangkat.split(',').map(name => name.trim());
    
    // Select matching options
    Array.from(dropdown.options).forEach(opt => {
      if (existingMembers.includes(opt.value)) {
        opt.selected = true;
      }
    });
    
    // Update display
    document.getElementById('selected_members_display').textContent = existingMembers.join(', ');
    document.getElementById('chosen_pangkat_hidden').value = chosenPangkat;
  }

  // Load Lupon members when page loads
  loadLuponMembers();

  // AJAX: Edit Details Form
  document.getElementById('editDetailsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // If transaction_id is not in formData, get it from the hidden input
    if (!formData.get('transaction_id')) {
      const tid = document.getElementById('edit_transaction_id').value;
      formData.append('transaction_id', tid);
    }
    
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Saving...';
    
    try {
      const response = await fetch('functions/process_edit_complaint.php', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.success) {

        // Update the row data in the table so reopening modal shows new data
        const tid = document.getElementById('edit_transaction_id').value;
        const row = document.querySelector(`tr[data-id="${tid}"]`);

        // Close the modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('manageCaseModal'));
        modal.hide();
        
        // Show alert after modal is closed (use type from response or default to 'success')
        const alertType = result.type || 'success';
        const caseNo = row ? (row.dataset.caseNo || tid) : tid;
        setTimeout(() => {
          showAlert(`Case <strong>${caseNo}</strong>: ${result.message}`, alertType);
        }, 300);
        
        if (row) {
          // Update the data attributes
          row.setAttribute('data-complaint-title', formData.get('complaint_title'));
          row.setAttribute('data-nature-of-case', formData.get('nature_of_case'));
          row.setAttribute('data-complaint-affidavit', formData.get('complaint_affidavit'));
          row.setAttribute('data-pleading-statement', formData.get('pleading_statement'));
          
          // Update the JSON data
          const jsonData = JSON.parse(row.dataset.json);
          jsonData.complaint_title = formData.get('complaint_title');
          jsonData.nature_of_case = formData.get('nature_of_case');
          jsonData.complaint_affidavit = formData.get('complaint_affidavit');
          jsonData.pleading_statement = formData.get('pleading_statement');
          row.setAttribute('data-json', JSON.stringify(jsonData));
          
          // Update the complaint title in the table cell (4th column)
          const titleCell = row.querySelector('td:nth-child(4)');
          if (titleCell) {
            titleCell.textContent = formData.get('complaint_title');
          }
          
          // Update the nature of case in the table cell (5th column)
          const natureCell = row.querySelector('td:nth-child(5)');
          if (natureCell) {
            natureCell.textContent = formData.get('nature_of_case');
          }
        }
      } else {
        showAlert('Error: ' + result.message, 'danger');
      }
    } catch (error) {
      showAlert('Error saving changes: ' + error.message, 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  });

  // AJAX: PB Schedule Forms (all 6 forms)
  ['form-pb-1st-schedule', 'form-pb-2nd-schedule', 'form-pb-3rd-schedule',
   'form-pb-1st-affidavit', 'form-pb-2nd-affidavit', 'form-pb-3rd-affidavit'].forEach(formId => {
    const form = document.getElementById(formId);
    if (form) {
      form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('ajax', '1');
        
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Saving...';
        
        try {
          const response = await fetch('functions/process_schedule_pb.php', {
            method: 'POST',
            body: formData
          });
          
          const result = await response.json();
          
          if (result.success) {
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('manageCaseModal'));
            modal.hide();
            
            // Get case number for alert
            const tid = formData.get('transaction_id');
            const row = document.querySelector(`tr[data-id="${tid}"]`);
            const caseNo = row ? (row.dataset.caseNo || tid) : tid;
            
            // Show success alert after modal is closed
            setTimeout(() => {
              showAlert(`Case <strong>${caseNo}</strong>: ${result.message}`, 'success');
              // Reload the page to refresh data after showing alert
              setTimeout(() => window.location.reload(), 1500);
            }, 300);
          } else {
            showAlert('Error: ' + result.message, 'danger');
          }
        } catch (error) {
          showAlert('Error saving: ' + error.message, 'danger');
        } finally {
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      });
    }
  });

  // AJAX: Lupon Schedule Forms (all 6 forms)
  ['form-lupon-1st-schedule', 'form-lupon-2nd-schedule', 'form-lupon-3rd-schedule',
   'form-lupon-1st-affidavit', 'form-lupon-2nd-affidavit', 'form-lupon-3rd-affidavit'].forEach(formId => {
    const form = document.getElementById(formId);
    if (form) {
      form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Special validation for Unang Patawag schedule form
        if (formId === 'form-lupon-1st-schedule') {
          const dropdown = document.getElementById('pangkat_members_dropdown');
          const selectedOptions = Array.from(dropdown.selectedOptions).filter(opt => opt.value);
          
          if (selectedOptions.length < 3) {
            showAlert('Please select at least 3 Pangkat members', 'danger');
            return;
          }
          
          // Update hidden field with selected members
          const selectedNames = selectedOptions.map(opt => opt.value);
          document.getElementById('chosen_pangkat_hidden').value = selectedNames.join(', ');
        }
        
        const formData = new FormData(this);
        formData.append('ajax', '1');
        
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Saving...';
        
        try {
          const response = await fetch('functions/process_schedule_lupon.php', {
            method: 'POST',
            body: formData
          });
          
          const result = await response.json();
          
          if (result.success) {
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('manageCaseModal'));
            modal.hide();
            
            // Get case number for alert
            const tid = formData.get('transaction_id');
            const row = document.querySelector(`tr[data-id="${tid}"]`);
            const caseNo = row ? (row.dataset.caseNo || tid) : tid;
            
            // Show success alert after modal is closed
            setTimeout(() => {
              showAlert(`Case <strong>${caseNo}</strong>: ${result.message}`, 'success');
              // Reload the page to refresh data after showing alert
              setTimeout(() => window.location.reload(), 1500);
            }, 300);
          } else {
            showAlert('Error: ' + result.message, 'danger');
          }
        } catch (error) {
          showAlert('Error saving: ' + error.message, 'danger');
        } finally {
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      });
    }
  });

  // Function to check and display respondent hold status
  async function checkRespondentHoldStatus(transactionId) {
    const loadingDiv = document.getElementById('hold-status-loading');
    const contentDiv = document.getElementById('hold-status-content');
    const notFoundDiv = document.getElementById('hold-status-not-found');
    
    loadingDiv.style.display = 'block';
    contentDiv.style.display = 'none';
    notFoundDiv.style.display = 'none';
    
    try {
      const formData = new FormData();
      formData.append('transaction_id', transactionId);
      formData.append('action', 'check');
      
      const response = await fetch('functions/check_respondent_status.php', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      loadingDiv.style.display = 'none';
      
      if (result.found) {
        contentDiv.style.display = 'block';
        document.getElementById('hold-respondent-name').textContent = result.respondent_name;
        document.getElementById('hold-purok-location').textContent = result.purok.replace('_rbi', '').toUpperCase();
        
        const statusDiv = document.getElementById('hold-current-status');
        const holdBtn = document.getElementById('btn-hold-respondent');
        const releaseBtn = document.getElementById('btn-release-hold');
        
        if (result.current_status === 'On Hold') {
          statusDiv.innerHTML = '<span class="badge bg-warning">On Hold</span>';
          holdBtn.style.display = 'none';
          releaseBtn.style.display = 'block';
        } else {
          statusDiv.innerHTML = '<span class="badge bg-secondary">' + (result.current_status || 'None') + '</span>';
          holdBtn.style.display = 'block';
          releaseBtn.style.display = 'none';
        }
      } else {
        notFoundDiv.style.display = 'block';
        document.getElementById('hold-respondent-name-nf').textContent = result.respondent_name;
      }
    } catch (error) {
      loadingDiv.style.display = 'none';
      console.error('Error checking respondent status:', error);
      showAlert('Error checking respondent status', 'danger');
    }
  }

  // Hold Respondent Button - Show confirmation modal
  document.getElementById('btn-hold-respondent').addEventListener('click', function() {
    const respondentName = document.getElementById('hold-respondent-name').textContent;
    const purokLocation = document.getElementById('hold-purok-location').textContent;
    
    document.getElementById('hold-confirm-name').textContent = respondentName;
    document.getElementById('hold-confirm-purok').textContent = purokLocation;
    
    const holdModal = new bootstrap.Modal(document.getElementById('holdRespondentModal'));
    holdModal.show();
  });

  // Confirm Hold Respondent - Actual processing
  document.getElementById('confirmHoldRespondent').addEventListener('click', async function() {
    const tid = document.getElementById('current_transaction_id').value;
    
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
    
    try {
      const formData = new FormData();
      formData.append('transaction_id', tid);
      formData.append('action', 'hold');
      
      const response = await fetch('functions/process_hold_respondent.php', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.success) {
        // Close the confirmation modal
        const holdModal = bootstrap.Modal.getInstance(document.getElementById('holdRespondentModal'));
        holdModal.hide();
        
        showAlert(result.message, 'success');
        // Refresh the hold status display
        await checkRespondentHoldStatus(tid);
      } else {
        showAlert('Error: ' + result.message, 'danger');
      }
    } catch (error) {
      showAlert('Error: ' + error.message, 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  });

  // Remove Hold Button - Show confirmation modal
  document.getElementById('btn-release-hold').addEventListener('click', function() {
    const respondentName = document.getElementById('hold-respondent-name').textContent;
    const purokLocation = document.getElementById('hold-purok-location').textContent;
    
    document.getElementById('remove-hold-confirm-name').textContent = respondentName;
    document.getElementById('remove-hold-confirm-purok').textContent = purokLocation;
    
    const removeHoldModal = new bootstrap.Modal(document.getElementById('removeHoldModal'));
    removeHoldModal.show();
  });

  // Confirm Remove Hold - Actual processing
  document.getElementById('confirmRemoveHold').addEventListener('click', async function() {
    const tid = document.getElementById('current_transaction_id').value;
    
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
    
    try {
      const formData = new FormData();
      formData.append('transaction_id', tid);
      formData.append('action', 'release');
      
      const response = await fetch('functions/process_hold_respondent.php', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.success) {
        // Close the confirmation modal
        const removeHoldModal = bootstrap.Modal.getInstance(document.getElementById('removeHoldModal'));
        removeHoldModal.hide();
        
        showAlert(result.message, 'success');
        // Refresh the hold status display
        await checkRespondentHoldStatus(tid);
      } else {
        showAlert('Error: ' + result.message, 'danger');
      }
    } catch (error) {
      showAlert('Error: ' + error.message, 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  });
});
</script>
