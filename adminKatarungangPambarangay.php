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
    $whereClauses[] = "(k.transaction_id LIKE ? OR ca.content LIKE ? OR ra.content LIKE ?)";
    $bindTypes .= str_repeat('s', 3);
    $term = "%{$search}%";
    $bindParams = array_merge($bindParams, [$term, $term, $term]);
}

// filter by date range
if ($date_from && $date_to) {
    $whereClauses[] = 'DATE(k.scheduled_at) BETWEEN ? AND ?';
    $bindTypes .= 'ss';
    $bindParams = array_merge($bindParams, [$date_from, $date_to]);
} elseif ($date_from) {
    $whereClauses[] = 'DATE(k.scheduled_at) >= ?';
    $bindTypes .= 's';
    $bindParams = array_merge($bindParams, [$date_from]);
} elseif ($date_to) {
    $whereClauses[] = 'DATE(k.scheduled_at) <= ?';
    $bindTypes .= 's';
    $bindParams = array_merge($bindParams, [$date_to]);
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// PAGINATION
$limit = 10;
$page = max((int)($_GET['katarungan_page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

// 1) total count
$countSQL = "
    SELECT COUNT(*) AS total
      FROM katarungang_pambarangay_records k
      LEFT JOIN affidavit_records ca 
        ON ca.katarungan_id = k.id AND ca.role = 'complainant'
      LEFT JOIN affidavit_records ra 
        ON ra.katarungan_id = k.id AND ra.role = 'respondent'
    {$whereSQL}
";
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
$totalRows  = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

// build base query string for pagination links
$bp = [
    'page'                  => 'adminComplaints',
    'katarungan_search'     => $search,
    'katarungan_date_from'  => $date_from,
    'katarungan_date_to'    => $date_to,
];

// 2) fetch page of rows with JOIN to fetch affidavits
$sql = "
     SELECT
      k.transaction_id,
      c.complainant_name,
      c.respondent_name,
      c.complaint_type AS subject_pb,
      c.complaint_status,
      ca.content AS complainant_affidavit,
      ra.content AS respondent_affidavit,
      DATE(k.scheduled_at) AS scheduled_date_pb_raw,
      TIME(k.scheduled_at) AS scheduled_time_pb_raw,
      DATE_FORMAT(k.scheduled_at, '%b %e, %Y %l:%i %p') AS formatted_sched,
      k.complaint_stage,
      k.appearance_status
    FROM katarungang_pambarangay_records k
    LEFT JOIN complaint_records c
      ON c.transaction_id = k.transaction_id
    LEFT JOIN affidavit_records ca 
      ON ca.katarungan_id = k.id AND ca.role = 'complainant'
    LEFT JOIN affidavit_records ra 
      ON ra.katarungan_id = k.id AND ra.role = 'respondent'
    {$whereSQL}
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

            <form method="POST" action="functions/process_clear_katarungan.php">
              <input type="hidden" name="transaction_id" id="edit_katarungan_tid">

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
                            Print Summon
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Add content for Ikalawang / Ikatlong Patawag tabs here if needed -->
                  <div class="tab-pane fade" id="tab1st">…</div>
                  <div class="tab-pane fade" id="tab2nd">…</div>
                  <div class="tab-pane fade" id="tab3rd">…</div>
                </div>
              </div>

              <div class="modal-footer justify-content">
                <button type="submit" name="clear_case" value="1" class="btn btn-outline-success">Cleared</button>
                <button type="submit" name="proceed_next" value="1" class="btn btn-success">Proceed to Next Patawag</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- VIEW Katarungan Modal -->
      <div class="modal fade" id="viewKatarunganModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="viewKatarunganModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 95vw;">
          <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #13411F;">
              <h5 class="modal-title" id="viewKatarunganModalLabel">KATARUNGANG PAMBARANGAY</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-4 py-3">
              <!-- Complaint Information -->
              <div class="border rounded bg-light p-3 mb-3">
                <h6 class="fw-bold mb-2">Complaint Information</h6>
                <div class="d-flex flex-wrap align-items-center text-muted small gap-2">
                  <div><strong id="view_case_id">Case No.</strong></div>
                  <span class="text-muted">|</span>
                  <div class="d-flex align-items-center gap-1 flex-wrap">
                    <span id="view_complainant_summary">Complainant</span>
                    <span class="fw-semibold text-dark">vs</span>
                    <span id="view_respondent_summary">Respondent</span>
                  </div>
                </div>
              </div>

              <!-- Summon Information -->
              <div class="mb-2">
                <h6 class="fw-bold mb-2">Summon Information</h6>
              </div>

              <!-- Tabbed Summons -->
              <ul class="nav nav-tabs mb-2" id="viewSummonTab" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#viewTabPB" type="button">Punong Barangay</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#viewTab1st" type="button">Unang Patawag</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#viewTab2nd" type="button">Ikalawang Patawag</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#viewTab3rd" type="button">Ikatlong Patawag</button></li>
              </ul>

              <div class="tab-content">
                <div class="tab-pane fade show active" id="viewTabPB">
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
                    </div>
                  </div>
                </div>

                <!-- Add content for other tabs as needed -->
                <div class="tab-pane fade" id="viewTab1st">
                  <div class="border rounded bg-light p-3 text-muted text-center small">No content.</div>
                </div>
                <div class="tab-pane fade" id="viewTab2nd">
                  <div class="border rounded bg-light p-3 text-muted text-center small">No content.</div>
                </div>
                <div class="tab-pane fade" id="viewTab3rd">
                  <div class="border rounded bg-light p-3 text-muted text-center small">No content.</div>
                </div>
              </div>
            </div>

            <div class="modal-footer justify-content-end">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
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
              $isCleared = ($row['complaint_status'] === 'Cleared');
            ?>
              <tr
                data-id="<?= $tid ?>"
                data-complainant-name="<?= htmlspecialchars($row['complainant_name']   ?? '') ?>"
                data-respondent-name="<?= htmlspecialchars($row['respondent_name']     ?? '') ?>"
                data-pb-subject="<?= htmlspecialchars($row['subject_pb']            ?? '') ?>"
                data-pb-date="<?= htmlspecialchars($row['scheduled_date_pb_raw']   ?? '') ?>"
                data-pb-time="<?= htmlspecialchars($row['scheduled_time_pb_raw']   ?? '') ?>"
                data-complaint-status="<?= htmlspecialchars($row['complaint_stage'] ?? '') ?>"
              >
                <td><?= $tid ?></td>
                <td><?= htmlspecialchars(substr($row['complainant_affidavit'], 0, 50)) ?: '—' ?></td>
                <td><?= htmlspecialchars(substr($row['respondent_affidavit'], 0, 50)) ?: '—' ?></td>
                <td><?= htmlspecialchars($row['formatted_sched']) ?></td>
                <td><?= htmlspecialchars($row['complaint_stage']) ?></td>
                <td class="text-center">
                  <?php if ($isCleared): ?>
                    <!-- View -->
                    <button class="btn btn-sm btn-warning view-katarungan-btn">
                      <span class="material-symbols-outlined" style="font-size: 12px;">visibility</span>
                    </button>
                  <?php else: ?>
                    <!-- Edit -->
                    <button class="btn btn-sm btn-success edit-katarungan-btn">
                      <span class="material-symbols-outlined" style="font-size: 12px;">stylus</span>
                    </button>
                  <?php endif; ?>

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

      // 4) set the Print button URL
      document.getElementById('printSummonBtn').href = `functions/print_summon.php?transaction_id=${encodeURIComponent(tid)}`;

      // 5) show modal
      new bootstrap.Modal(document.getElementById('editKatarunganModal')).show();
    });
  });

  // Delete modal wiring
  document.querySelectorAll('.delete-katarungan-btn').forEach(button => {
    button.addEventListener('click', () => {
      const tid = button.closest('tr').dataset.id;
      document.getElementById('deleteKatarunganId').value      = tid;
      document.getElementById('deleteKatarunganIdLabel').textContent = tid;
      new bootstrap.Modal(document.getElementById('deleteKatarunganModal')).show();
    });
  });

  document.querySelectorAll('.view-katarungan-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      const tid = tr.dataset.id;

      // Inject values into view modal
      document.getElementById('view_case_id').textContent = tid;
      document.getElementById('view_complainant_summary').textContent = tr.dataset.complainantName;
      document.getElementById('view_respondent_summary').textContent = tr.dataset.respondentName;

      document.querySelector('#viewKatarunganModal [name="subject_pb"]').value = tr.dataset.pbSubject || '';
      document.querySelector('#viewKatarunganModal [name="scheduled_date_pb"]').value = tr.dataset.pbDate || '';
      document.querySelector('#viewKatarunganModal [name="scheduled_time_pb"]').value = tr.dataset.pbTime || '';

      // Show view modal
      new bootstrap.Modal(document.getElementById('viewKatarunganModal')).show();
    });
  });
});
</script>
