<?php
require 'functions/dbconn.php';
$userId = (int) ($_SESSION['loggedInUserID'] ?? 0);

// FILTER & SEARCH SETUP
$search = trim($_GET['katarungan_search'] ?? '');
$date_from = $_GET['katarungan_date_from'] ?? '';
$date_to = $_GET['katarungan_date_to'] ?? '';

// build query filters
$whereClauses = [];
$bindTypes = '';
$bindParams = [];

// global search on transaction_id or affidavit content
if ($search !== '') {
  $term = "%{$search}%";
  $whereClauses[] = "(k.transaction_id LIKE ? OR c.complaint_affidavit LIKE ? OR k.complainant_affidavit_unang_patawag LIKE ? OR k.complainant_affidavit_ikalawang_patawag LIKE ? OR k.complainant_affidavit_ikatlong_patawag LIKE ? OR k.respondent_affidavit_unang_patawag LIKE ? OR k.respondent_affidavit_ikalawang_patawag LIKE ? OR k.respondent_affidavit_ikatlong_patawag LIKE ?)";
  $bindTypes  .= str_repeat('s', 8);
  $bindParams = array_merge($bindParams, array_fill(0, 8, $term));
}

// (B) Date-range filter on the *same* CASE expression you use in SELECT
$dateExpr = "CASE k.complaint_stage WHEN 'Punong Barangay' THEN k.schedule_punong_barangay WHEN 'Unang Patawag' THEN k.schedule_unang_patawag WHEN 'Ikalawang Patawag' THEN k.schedule_ikalawang_patawag ELSE k.schedule_ikatlong_patawag END";

// filter by date range
if ($date_from && $date_to) {
    $whereClauses[] = "(DATE($dateExpr) BETWEEN ? AND ?)";
    $bindTypes .= 'ss';
    $bindParams = array_merge($bindParams, [$date_from, $date_to]);
} elseif ($date_from) {
    $whereClauses[] = "(DATE($dateExpr) >= ?)";
    $bindTypes .= 's';
    $bindParams = array_merge($bindParams, [$date_from]);
} elseif ($date_to) {
    $whereClauses[] = "(DATE($dateExpr) <= ?)";
    $bindTypes .= 's';
    $bindParams = array_merge($bindParams, [$date_to]);
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// PAGINATION
$limit = 10;
$page = max((int)($_GET['katarungan_page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

// 1) total count
$countSQL = " SELECT COUNT(*) AS total FROM katarungang_pambarangay_records k LEFT JOIN complaint_records c ON c.transaction_id = k.transaction_id $whereSQL";

// SELECT COUNT(*) AS total FROM katarungang_pambarangay_records k $whereSQL
$countStmt = $conn->prepare($countSQL);
if ($whereClauses) {
    // Bind dynamically
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

// build base query string for pagination links
$bp = [
    'page' => 'adminComplaints',
    'katarungan_search' => $search,
    'katarungan_date_from' => $date_from,
    'katarungan_date_to' => $date_to,
];

// 2) fetch page of rows with JOIN to fetch affidavits
// $sql = "
//   SELECT
//      k.transaction_id,
//      c.complainant_name,
//      c.respondent_name,
//      k.complaint_type       AS subject_pb,
//      c.complaint_status,
//      CASE k.complaint_stage
//        WHEN 'Punong Barangay'    THEN k.schedule_punong_barangay
//        WHEN 'Unang Patawag'      THEN k.schedule_unang_patawag
//        WHEN 'Ikalawang Patawag'  THEN k.schedule_ikalawang_patawag
//        ELSE k.schedule_ikatlong_patawag
//      END AS scheduled_at,
//      CASE k.complaint_stage
//        WHEN 'Unang Patawag'      THEN k.complainant_affidavit_unang_patawag
//        WHEN 'Ikalawang Patawag'  THEN k.complainant_affidavit_ikalawang_patawag
//        WHEN 'Ikatlong Patawag'   THEN k.complainant_affidavit_ikatlong_patawag
//        ELSE NULL
//      END AS complainant_affidavit,
//      CASE k.complaint_stage
//        WHEN 'Unang Patawag'      THEN k.respondent_affidavit_unang_patawag
//        WHEN 'Ikalawang Patawag'  THEN k.respondent_affidavit_ikalawang_patawag
//        WHEN 'Ikatlong Patawag'   THEN k.respondent_affidavit_ikatlong_patawag
//        ELSE NULL
//      END AS respondent_affidavit,
//      k.complaint_stage,
//      DATE_FORMAT(
//        CASE k.complaint_stage
//          WHEN 'Punong Barangay'   THEN k.schedule_punong_barangay
//          WHEN 'Unang Patawag'     THEN k.schedule_unang_patawag
//          WHEN 'Ikalawang Patawag' THEN k.schedule_ikalawang_patawag
//          ELSE k.schedule_ikatlong_patawag
//        END,
//        '%b %e, %Y %l:%i %p'
//      ) AS formatted_sched
//    FROM katarungang_pambarangay_records k
//    LEFT JOIN complaint_records c 
//      ON c.transaction_id = k.transaction_id
//    $whereSQL
//    ORDER BY k.transaction_id ASC
//    LIMIT ? OFFSET ?
// ";

// $sql = "
//   SELECT
//     k.transaction_id,
//     c.complainant_name,
//     c.respondent_name,
//     k.complaint_type AS subject_pb,
//     c.complaint_status,
//     $dateExpr AS scheduled_at,
//     CASE k.complaint_stage
//       WHEN 'Unang Patawag' THEN k.complainant_affidavit_unang_patawag
//       WHEN 'Ikalawang Patawag' THEN k.complainant_affidavit_ikalawang_patawag
//       WHEN 'Ikatlong Patawag' THEN k.complainant_affidavit_ikatlong_patawag
//       ELSE NULL
//     END AS complainant_affidavit,
  
//     CASE k.complaint_stage
//       WHEN 'Unang Patawag' THEN k.respondent_affidavit_unang_patawag
//       WHEN 'Ikalawang Patawag' THEN k.respondent_affidavit_ikalawang_patawag
//       WHEN 'Ikatlong Patawag' THEN k.respondent_affidavit_ikatlong_patawag
//       ELSE NULL
//     END AS respondent_affidavit,
  
//     k.complaint_stage,
//     DATE_FORMAT($dateExpr,'%b %e, %Y %l:%i %p') AS formatted_sched

//   FROM katarungang_pambarangay_records k
//   LEFT JOIN complaint_records c ON c.transaction_id = k.transaction_id
//   $whereSQL
//   ORDER BY k.transaction_id ASC
//   LIMIT ? OFFSET ?
// ";

$sql = "
  SELECT
    k.transaction_id,
    c.complainant_name,
    c.respondent_name,
    k.complaint_type             AS subject_pb,
    c.complaint_status,
    
    k.schedule_punong_barangay   AS sched_pb,
    k.schedule_unang_patawag     AS sched_1st,
    k.schedule_ikalawang_patawag AS sched_2nd,
    k.schedule_ikatlong_patawag  AS sched_3rd,

    k.complainant_affidavit_unang_patawag     AS aff_1st,
    k.complainant_affidavit_ikalawang_patawag AS aff_2nd,
    k.complainant_affidavit_ikatlong_patawag  AS aff_3rd,

    k.respondent_affidavit_unang_patawag     AS affr_1st,
    k.respondent_affidavit_ikalawang_patawag AS affr_2nd,
    k.respondent_affidavit_ikatlong_patawag  AS affr_3rd,

    k.complaint_stage,

    $dateExpr AS scheduled_at,

    DATE_FORMAT($dateExpr, '%b %e, %Y %l:%i %p') AS formatted_sched,

    CASE k.complaint_stage
      WHEN 'Unang Patawag'     THEN k.complainant_affidavit_unang_patawag
      WHEN 'Ikalawang Patawag' THEN k.complainant_affidavit_ikalawang_patawag
      WHEN 'Ikatlong Patawag'  THEN k.complainant_affidavit_ikatlong_patawag
      ELSE NULL
    END AS complainant_affidavit,

    CASE k.complaint_stage
      WHEN 'Unang Patawag'     THEN k.respondent_affidavit_unang_patawag
      WHEN 'Ikalawang Patawag' THEN k.respondent_affidavit_ikalawang_patawag
      WHEN 'Ikatlong Patawag'  THEN k.respondent_affidavit_ikatlong_patawag
      ELSE NULL
    END AS respondent_affidavit

  FROM katarungang_pambarangay_records k
  LEFT JOIN complaint_records c
    ON c.transaction_id = k.transaction_id
  $whereSQL
  ORDER BY k.transaction_id ASC
  LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

// bind params + pagination
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
  <?php if (isset($_GET['katarungan_deleted'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      Scheduled case <strong><?= htmlspecialchars($_GET['katarungan_deleted']) ?></strong> deleted.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($_GET['cleared_tid'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Case <strong><?= htmlspecialchars($_GET['cleared_tid']) ?></strong> has been cleared.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm p-3">
    <div class="d-flex align-items-center mb-3">
      <!-- Filter dropdown -->
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">filter_list</span>
          Filter
        </button>
        <div class="dropdown-menu p-3" style="min-width:260px; font-size:.75rem;">
          <form method="get" action="?page=adminComplaints" id="katarunganfilterForm">
            <input type="hidden" name="page" value="adminComplaints">
            <input type="hidden" name="katarungan_search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="katarungan_date_from" value="<?= htmlspecialchars($date_from) ?>">
            <input type="hidden" name="katarungan_date_to" value="<?= htmlspecialchars($date_to) ?>">
            <input type="hidden" name="katarungan_page"  value="1">

            <div class="mb-2">
              <label class="form-label mb-1">Scheduled Date</label>
              <div class="d-flex gap-1">
                <div class="flex-grow-1">
                  <small class="text-muted">From</small>
                  <input type="date" name="katarungan_date_from" class="form-control form-control-sm" value="<?=htmlspecialchars($date_from)?>">
                </div>
                <div class="flex-grow-1">
                  <small class="text-muted">To</small>
                  <input type="date" name="katarungan_date_to" class="form-control form-control-sm" value="<?=htmlspecialchars($date_to)?>">
                </div>
              </div>
            </div>

            <div class="d-flex">
              <a href="?page=adminComplaints&katarungan_page=1" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
              <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Search form -->
      <form method="get" action="?page=adminComplaints" id="searchFormKatarungan" class="d-flex ms-auto me-2">
        <input type="hidden" name="page" value="adminComplaints">
        <input type="hidden" name="katarungan_date_from" value="<?= htmlspecialchars($date_from) ?>">
        <input type="hidden" name="katarungan_date_to" value="<?= htmlspecialchars($date_to) ?>">
        <input type="hidden" name="katarungan_page" value="1">

        <div class="input-group input-group-sm">
          <input name="katarungan_search" id="searchInputKatarungan" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
          <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" id="searchBtnKatarungan">
            <span class="material-symbols-outlined" id="searchIcon">
              <?= !empty($search) ? 'close' : 'search' ?>
            </span>
          </button>
        </div>
      </form>

      <!-- Edit Katarungan Modal -->
      <div class="modal fade" id="editKatarunganModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editKatarunganModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 95vw;">
          <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #13411F;">
              <h5 class="modal-title" id="editKatarunganModalLabel">KATARUNGANG PAMBARANGAY</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form id="katarunganForm" method="POST" action="functions/process_save_affidavit.php">
              <input type="hidden" name="transaction_id" id="edit_katarungan_tid">
              <input type="hidden" name="action_type" id="actionType">
              <input type="hidden" name="stage" id="affidavit_stage">

              <div class="modal-body px-4 py-3">
                <!-- Complaint Information -->
                <div class="border rounded bg-light p-3 mb-3">
                  <h6 class="fw-bold mb-2">Complaint Information</h6>
                  <div class="d-flex flex-wrap align-items-center text-muted small gap-2">
                    <div><strong id="edit_case_id">Case No.</strong></div>
                    <span class="text-muted">|</span>
                    <div class="d-flex align-items-center gap-1 flex-wrap">
                      <span id="edit_complainant_summary">Complainant</span>
                      <span class="fw-semibold text-dark">vs</span>
                      <span id="edit_respondent_summary">Respondent</span>
                    </div>
                  </div>
                </div>

                <!-- Summon Information -->
                <div class="mb-2">
                  <h6 class="fw-bold mb-2">Summon Information</h6>
                </div>
                
                <!-- Tabbed Summons -->
                <ul class="nav nav-tabs mb-2" id="summonTab" role="tablist">
                  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPB" type="button">Punong Barangay</button></li>
                  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab1st" type="button">Unang Patawag</button></li>
                  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab2nd" type="button">Ikalawang Patawag</button></li>
                  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab3rd" type="button">Ikatlong Patawag</button></li>
                </ul>

                <div class="tab-content">
                  <div class="tab-pane fade show active" id="tabPB">
                    <div class="border rounded bg-light p-3">
                      <div class="row g-3">
                        <div class="col-md-6 me-1">
                          <label class="form-label">Subject</label>
                          <input type="text" name="subject_pb" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Summon Scheduled Date</label>
                          <input type="date" name="scheduled_date_pb" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Summon Scheduled Time</label>
                          <input type="time" name="scheduled_time_pb" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                          <a href="#" id="printSummonBtn" target="_blank" class="btn btn-sm btn-primary">
                            Print Complaint & Summon
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Add content for Ikalawang / Ikatlong Patawag tabs here if needed -->
                  <div class="tab-pane fade" id="tab1st">
                    <div class="border rounded bg-light p-3">
                      <div class="row g-3">
                        <div class="col-md-3">
                          <label class="form-label">Scheduled Date</label>
                          <input type="date" name="scheduled_date_1st" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 me-1">
                          <label class="form-label">Scheduled Time</label>
                          <input type="time" name="scheduled_time_1st" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Complainant Affidavit</label>
                          <!-- <textarea name="complainant_affidavit_1st" rows="2" class="form-control form-control-sm"></textarea> -->
                          <textarea name="complainant_affidavit_1st" rows="2" class="form-control form-control-sm" disabled></textarea>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Respondent Affidavit</label>
                          <!-- <textarea name="respondent_affidavit_1st" rows="2" class="form-control form-control-sm"></textarea> -->
                        <textarea name="respondent_affidavit_1st" rows="2" class="form-control form-control-sm" disabled></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                          <button type="button" class="btn btn-sm btn-outline-secondary edit-affidavit-btn" data-stage="1st">Edit</button>
                          <button type="submit" class="btn btn-sm btn-success save-affidavit-btn d-none" data-stage="1st">Save</button>
                          <button type="button" class="btn btn-sm btn-danger cancel-affidavit-btn d-none" data-stage="1st">Cancel</button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="tab-pane fade" id="tab2nd">
                    <div class="border rounded bg-light p-3">
                      <div class="row g-3">
                        <div class="col-md-3">
                          <label class="form-label">Scheduled Date</label>
                          <input type="date" name="scheduled_date_2nd" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 me-1">
                          <label class="form-label">Scheduled Time</label>
                          <input type="time" name="scheduled_time_2nd" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Complainant Affidavit</label>
                          <textarea name="complainant_affidavit_2nd" rows="2" class="form-control form-control-sm" disabled></textarea>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Respondent Affidavit</label>
                          <textarea name="respondent_affidavit_2nd" rows="2" class="form-control form-control-sm" disabled></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                          <button type="button" class="btn btn-sm btn-outline-secondary edit-affidavit-btn" data-stage="2nd">Edit</button>
                          <button type="submit" class="btn btn-sm btn-success save-affidavit-btn d-none" data-stage="2nd">Save</button>
                          <button type="button" class="btn btn-sm btn-danger cancel-affidavit-btn d-none" data-stage="2nd">Cancel</button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="tab-pane fade" id="tab3rd">
                    <div class="border rounded bg-light p-3">
                      <div class="row g-3">
                        <div class="col-md-3">
                          <label class="form-label">Scheduled Date</label>
                          <input type="date" name="scheduled_date_3rd" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 me-1">
                          <label class="form-label">Scheduled Time</label>
                          <input type="time" name="scheduled_time_3rd" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Complainant Affidavit</label>
                          <textarea name="complainant_affidavit_3rd" rows="2" class="form-control form-control-sm" disabled></textarea>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Respondent Affidavit</label>
                          <textarea name="respondent_affidavit_3rd" rows="2" class="form-control form-control-sm" disabled></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                          <button type="button" class="btn btn-sm btn-outline-secondary edit-affidavit-btn" data-stage="3rd">Edit</button>
                          <button type="submit" class="btn btn-sm btn-success save-affidavit-btn d-none" data-stage="3rd">Save</button>
                          <button type="button" class="btn btn-sm btn-danger cancel-affidavit-btn d-none" data-stage="3rd">Cancel</button>
                        </div>
                      </div>
                    </div>
                  </div>

                </div>
              </div>

              <div class="modal-footer justify-content">
                <button type="submit" id="clearBtn" class="btn btn-outline-success">Cleared</button>
                <button type="button" id="proceedBtn" class="btn btn-success">Proceed to Next Patawag</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Schedule Next Patawag Modal -->
      <div class="modal fade" id="scheduleNextModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form id="scheduleNextForm" class="modal-content" method="POST" action="functions/process_schedule_katarungang_pambarangay.php">
            <input type="hidden" name="transaction_id" id="sched_txn">
            <input type="hidden" name="current_stage" id="sched_current_stage">
            <div class="modal-header">
              <h5 class="modal-title">Schedule Next Patawag</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p id="sched_prompt">When should the next Patawag be?</p>
              <div class="mb-3">
                <label class="form-label">Date</label>
                <input type="date" name="next_date" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Time</label>
                <input type="time" name="next_time" class="form-control" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-success">Save Schedule</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Modal -->
      <div class="modal fade" id="deleteKatarunganModal" tabindex="-1" aria-labelledby="deleteKatarunganModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form id="deleteKatarunganForm" class="modal-content" action="functions/delete_katarungan.php" method="POST">
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title" id="deleteKatarunganModalLabel">Confirm Deletion</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              Are you sure you want to permanently delete schedule for transaction <strong id="deleteKatarunganIdLabel"></strong>?
              <input type="hidden" name="transaction_id" id="deleteKatarunganId">
              <input type="hidden" name="katarungan_page" value="<?= $page ?>">
              <input type="hidden" name="katarungan_search" value="<?= htmlspecialchars($search) ?>">
              <input type="hidden" name="katarungan_date_from" value="<?= htmlspecialchars($date_from) ?>">
              <input type="hidden" name="katarungan_date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger">Delete</button>
            </div>
          </form>
        </div>
      </div>

    </div>

    <!-- CASES TABLE -->
    <div class="table-responsive admin-table">
      <table class="table table-hover align-middle text-start">
        <thead class="table-light">
          <tr>
            <th>Case No.</th>
            <th>Complainant Affidavit</th>
            <th>Respondent Affidavit</th>
            <th>Scheduled At</th>
            <th>Status</th>
            <th class="text-nowrap text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): 
              $tid = htmlspecialchars($row['transaction_id']);
            ?>
              <tr
                data-id="<?= $tid ?>"
                data-complainant-name="<?= htmlspecialchars($row['complainant_name']   ?? '') ?>"
                data-respondent-name="<?= htmlspecialchars($row['respondent_name']     ?? '') ?>"
                data-pb-subject="<?= htmlspecialchars($row['subject_pb']            ?? '') ?>"
                data-pb-date="<?= htmlspecialchars(date('Y-m-d', strtotime($row['sched_pb']))) ?>"
                data-pb-time="<?= htmlspecialchars(date('H:i',   strtotime($row['sched_pb']))) ?>"

                data-1st-dt="<?= htmlspecialchars($row['sched_1st']) ?>"
                data-1st-aff="<?= htmlspecialchars($row['aff_1st']) ?>"
                data-1st-affr="<?= htmlspecialchars($row['affr_1st']) ?>"

                data-2nd-dt="<?= htmlspecialchars($row['sched_2nd']) ?>"
                data-2nd-aff="<?= htmlspecialchars($row['aff_2nd']) ?>"
                data-2nd-affr="<?= htmlspecialchars($row['affr_2nd']) ?>"

                data-3rd-dt="<?= htmlspecialchars($row['sched_3rd']) ?>"
                data-3rd-aff="<?= htmlspecialchars($row['aff_3rd']) ?>"
                data-3rd-affr="<?= htmlspecialchars($row['affr_3rd']) ?>" 
                
                data-complaint-status="<?= htmlspecialchars($row['complaint_stage'] ?? '') ?>"
              >
                <td><?= $tid ?></td>
                <td><?= htmlspecialchars(substr($row['complainant_affidavit'], 0, 50)) ?: '—' ?></td>
                <td><?= htmlspecialchars(substr($row['respondent_affidavit'], 0, 50)) ?: '—' ?></td>
                <td><?= $row['formatted_sched'] ? htmlspecialchars($row['formatted_sched']) : '—' ?></td>
                <td><?= htmlspecialchars($row['complaint_stage']) ?></td>
                <td class="text-center">
                  <!-- Edit -->
                  <button class="btn btn-sm btn-primary edit-katarungan-btn">
                    <span class="material-symbols-outlined" style="font-size: 12px;">stylus</span>
                  </button>

                  <!-- Delete -->
                  <button class="btn btn-sm btn-danger delete-katarungan-btn">
                    <span class="material-symbols-outlined" style="font-size: 12px;">delete</span>
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="text-center">No records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center pagination-sm">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($bp, ['katarungan_page' => $page - 1])) ?>">Previous</a>
          </li>
          <?php
            $range = 2; $dots = false;
            for ($i = 1; $i <= $totalPages; $i++) {
              if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                $active = $i == $page ? 'active' : '';
                echo "<li class='page-item {$active}'><a class='page-link' href='?" . http_build_query(array_merge($bp, ['katarungan_page' => $i])) . "'>$i</a></li>";
                $dots = true;
              } elseif ($dots) {
                echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                $dots = false;
              }
            }
          ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($bp, ['katarungan_page' => $page + 1])) ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Search reset/clear
  const form = document.getElementById('searchFormKatarungan');
  const input = document.getElementById('searchInputKatarungan');
  const btn   = document.getElementById('searchBtnKatarungan');
  const hasSearch = <?= json_encode($search !== '') ?>;

  btn.addEventListener('click', () => {
    if (hasSearch) input.value = '';
    form.submit();
  });

  // Edit modal wiring
  document.querySelectorAll('.edit-katarungan-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      const tid = tr.dataset.id;

      // ——— NEW: lock/unlock tabs by complaint_status ———
      const status = tr.dataset.complaintStatus; 
      const order  = ['Punong Barangay', 'Unang Patawag', 'Ikalawang Patawag', 'Ikatlong Patawag'];
      const maxTab = order.indexOf(status);
      document.querySelectorAll('#summonTab .nav-link').forEach((tabBtn, idx) => {
        if (idx <= maxTab) {
          tabBtn.classList.remove('disabled');
          tabBtn.removeAttribute('aria-disabled');
        } else {
          tabBtn.classList.add('disabled');
          tabBtn.setAttribute('aria-disabled','true');
        }
      });
      // ———————————————————————————————

      // 1) inject transaction_id
      document.getElementById('edit_katarungan_tid').value = tid;
      
      // 2) case summary (assuming you can reconstruct from your data model)
      // you may need to fetch complainant/respondent names via AJAX
      // or embed data attributes on the <tr>
      document.getElementById('edit_case_id').textContent = tid;
      document.getElementById('edit_complainant_summary').textContent = tr.dataset.complainantName;
      document.getElementById('edit_respondent_summary').textContent = tr.dataset.respondentName;

      // 3) populate Punong Barangay fields (if you have them as data attributes)
      document.querySelector('[name="subject_pb"]').value = tr.dataset.pbSubject || '';
      document.querySelector('[name="scheduled_date_pb"]').value = tr.dataset.pbDate || '';
      document.querySelector('[name="scheduled_time_pb"]').value = tr.dataset.pbTime || '';

      // grab the raw ISO datetime strings
      const dt1 = tr.dataset['1stDt'];
      const dt2 = tr.dataset['2ndDt'];
      const dt3 = tr.dataset['3rdDt'];

      // helper to split "YYYY-MM-DD hh:mm:ss" → [ "YYYY-MM-DD", "hh:mm" ]
      function splitDateTime(dt) {
        if (!dt) return ['', ''];
        const [d, t] = dt.split(' ');
        return [ d, t.slice(0,5) ];
      }

      // — 1st Patawag — 
      const [d1, t1] = splitDateTime(dt1);
      const dInp1 = document.querySelector('#tab1st [name="scheduled_date_1st"]');
      const tInp1 = document.querySelector('#tab1st [name="scheduled_time_1st"]');
      dInp1.value = d1; tInp1.value = t1;
      if (dt1) { dInp1.disabled = tInp1.disabled = true; }

      // — 2nd Patawag — 
      const [d2, t2] = splitDateTime(dt2);
      const dInp2 = document.querySelector('#tab2nd [name="scheduled_date_2nd"]');
      const tInp2 = document.querySelector('#tab2nd [name="scheduled_time_2nd"]');
      dInp2.value = d2; tInp2.value = t2;
      if (dt2) { dInp2.disabled = tInp2.disabled = true; }

      // — 3rd Patawag — 
      const [d3, t3] = splitDateTime(dt3);
      const dInp3 = document.querySelector('#tab3rd [name="scheduled_date_3rd"]');
      const tInp3 = document.querySelector('#tab3rd [name="scheduled_time_3rd"]');
      dInp3.value = d3; tInp3.value = t3;
      if (dt3) { dInp3.disabled = tInp3.disabled = true; }

      // ─── NOW POPULATE THE AFFIDAVITS ───
      document.querySelector('#tab1st [name="complainant_affidavit_1st"]').value = tr.dataset['1stAff']  || '';
      document.querySelector('#tab1st [name="respondent_affidavit_1st"]').value  = tr.dataset['1stAffr'] || '';
      document.querySelector('#tab2nd [name="complainant_affidavit_2nd"]').value = tr.dataset['2ndAff']  || '';
      document.querySelector('#tab2nd [name="respondent_affidavit_2nd"]').value  = tr.dataset['2ndAffr'] || '';
      document.querySelector('#tab3rd [name="complainant_affidavit_3rd"]').value = tr.dataset['3rdAff']  || '';
      document.querySelector('#tab3rd [name="respondent_affidavit_3rd"]').value  = tr.dataset['3rdAffr'] || '';

      // 4) set the Print button URL
      document.getElementById('printSummonBtn').href = `functions/print_complaint.php?transaction_id=${encodeURIComponent(tid)}`;

       // ── **NEW**: activate the tab that matches the current stage ────────────────
      const tabButtons = Array.from(document.querySelectorAll('#summonTab .nav-link'));
      tabButtons.forEach(tabBtn => {
        if (tabBtn.textContent.trim() === status) {
          new bootstrap.Tab(tabBtn).show();
        }
      });
      
      // 5) show modal
      new bootstrap.Modal(document.getElementById('editKatarunganModal')).show();
    });
  });

  // map each stage to its next stage label
  const nextStageMap = {
    'Punong Barangay': 'Unang Patawag',
    'Unang Patawag':   'Ikalawang Patawag',
    'Ikalawang Patawag':'Ikatlong Patawag'
  };

  document.getElementById('proceedBtn').addEventListener('click', e => {
    e.preventDefault();

    // find currently active tab
    const activeBtn = document.querySelector('#summonTab .nav-link.active');
    const currStage = activeBtn.textContent.trim();
    const nextStage = nextStageMap[currStage];
    if (!nextStage) return; // no next stage

    // grab the row’s txn from hidden input
    const txn = document.getElementById('edit_katarungan_tid').value;

    // set up modal
    document.getElementById('sched_txn').value = txn;
    document.getElementById('sched_current_stage').value = currStage;
    document.getElementById('sched_prompt').textContent = 
      `Schedule "${nextStage}" for Case ${txn}:`;

    // show modal
    new bootstrap.Modal(document.getElementById('scheduleNextModal')).show();
  });

  document.getElementById('clearBtn').addEventListener('click', function() {
    document.getElementById('actionType').value = 'clear';
  });

  // Delete modal wiring
  // document.querySelectorAll('.delete-katarungan-btn').forEach(button => {
  //   button.addEventListener('click', () => {
  //     const tid = button.closest('tr').dataset.id;
  //     document.getElementById('deleteKatarunganId').value      = tid;
  //     document.getElementById('deleteKatarunganIdLabel').textContent = tid;
  //     new bootstrap.Modal(document.getElementById('deleteKatarunganModal')).show();
  //   });
  // });
  
  // Save button for affidavit: set stage + action_type before form submit
  document.querySelectorAll('.save-affidavit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const stage = btn.dataset.stage;
      document.getElementById('affidavit_stage').value = stage;
      document.getElementById('actionType').value = 'clear'; // marks that we're saving affidavit edits
    });
  });

  function toggleAffidavitEditMode(stage, editing) {
    const aff1 = document.querySelector(`[name="complainant_affidavit_${stage}"]`);
    const aff2 = document.querySelector(`[name="respondent_affidavit_${stage}"]`);

    const editBtn   = document.querySelector(`.edit-affidavit-btn[data-stage="${stage}"]`);
    const saveBtn   = document.querySelector(`.save-affidavit-btn[data-stage="${stage}"]`);
    const cancelBtn = document.querySelector(`.cancel-affidavit-btn[data-stage="${stage}"]`);

    if (editing) {
      aff1.removeAttribute('disabled');
      aff2.removeAttribute('disabled');
      editBtn.classList.add('d-none');
      saveBtn.classList.remove('d-none');
      cancelBtn.classList.remove('d-none');

      // store original values to restore if cancelled
      aff1.dataset.original = aff1.value;
      aff2.dataset.original = aff2.value;
    } else {
      aff1.setAttribute('disabled', 'true');
      aff2.setAttribute('disabled', 'true');
      editBtn.classList.remove('d-none');
      saveBtn.classList.add('d-none');
      cancelBtn.classList.add('d-none');
    }
  }

  // Edit button click
  document.querySelectorAll('.edit-affidavit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const stage = btn.dataset.stage;
      toggleAffidavitEditMode(stage, true);
    });
  });

  // Cancel button click
  document.querySelectorAll('.cancel-affidavit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const stage = btn.dataset.stage;
      const aff1 = document.querySelector(`[name="complainant_affidavit_${stage}"]`);
      const aff2 = document.querySelector(`[name="respondent_affidavit_${stage}"]`);

      aff1.value = aff1.dataset.original || '';
      aff2.value = aff2.dataset.original || '';

      toggleAffidavitEditMode(stage, false);
    });
  });

});
</script>
