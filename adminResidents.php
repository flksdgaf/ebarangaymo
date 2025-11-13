<?php
// adminResidents.php (updated)
require_once 'functions/dbconn.php';

// Determine purok (default=1)
$purokNum = isset($_GET['purok']) && in_array((int)$_GET['purok'], [1,2,3,4,5,6]) ? (int)$_GET['purok'] : 1;

// --- Search setup ---
$search = trim($_GET['search'] ?? '');

// Build WHERE clauses
$where = [];
$params = [];
$types = '';

// columns you want to search
$searchCols = [
  'r.account_ID',
  'r.full_name',
  'r.house_number',
  'r.relationship_to_head',
  'r.registry_number',
  'r.total_population',
  'ua.role',
  'r.remarks'
];


// Global search 
if ($search !== '') {
    // build a placeholder for each column
    $likes = [];
    foreach ($searchCols as $col) {
        $likes[] = "$col LIKE ?";
        $types .= 's';
        $params[] = "%{$search}%";
    }
    $where[] = '(' . implode(' OR ', $likes) . ')';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';            
    
$tableName = "purok{$purokNum}_rbi";

// --- Pagination setup ---
$limit = 8;
$page_num = max((int)($_GET['page_num'] ?? 1), 1);
$offset = ($page_num - 1) * $limit;

// 1) get total count
$countSQL = "SELECT COUNT(*) AS total FROM `{$tableName}` AS r LEFT JOIN user_accounts AS ua ON r.account_ID = ua.account_id {$whereSQL}";
$countStmt = $conn->prepare($countSQL);
if ($countStmt === false) {
  die("Prepare failed (count): " . htmlspecialchars($conn->error));
}
if ($whereSQL) {
    // bind params (need references for call_user_func_array)
    $refs = [];
    foreach ($params as $i => $v) {
        $refs[$i] = & $params[$i];
    }
    array_unshift($refs, $types);
    call_user_func_array([$countStmt, 'bind_param'], $refs);
}
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);

// if no rows, treat as single page (keeps UI consistent)
if ($totalPages < 1) $totalPages = 1;

// if requested page is past total pages, clamp it
if ($page_num > $totalPages) {
    $page_num = $totalPages;
}

$offset = ($page_num - 1) * $limit;

// --- total residents for the selected purok (ignore search) ---
$totalResidentsPurok = 0;
$resCountSQL = "SELECT COUNT(*) AS purok_total FROM `{$tableName}`";
$resCountStmt = $conn->prepare($resCountSQL);
if ($resCountStmt !== false) {
  $resCountStmt->execute();
  $totalResidentsPurok = $resCountStmt->get_result()->fetch_assoc()['purok_total'] ?? 0;
  $resCountStmt->close();
}

// Build base query string for pagination links (preserve search & purok but not page_num)
$qs = $_GET;
unset($qs['page_num']);
$baseQS = http_build_query($qs);
if ($baseQS) $baseQS .= '&';

// 2) fetch the actual rows with LIMIT/OFFSET
$sql = "SELECT r.*, ua.role FROM `{$tableName}` AS r LEFT JOIN user_accounts AS ua ON r.account_ID = ua.account_id {$whereSQL} ORDER BY r.full_name ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
  die("Prepare failed (select): " . htmlspecialchars($conn->error));
}

// Build types and params for binding (include limit & offset)
$typesWithLimit = $types . 'ii';
$paramsWithLimit = array_merge($params, [$limit, $offset]);

// Build refs array for call_user_func_array
$refs = [];
foreach ($paramsWithLimit as $i => $v) {
  $refs[$i] = & $paramsWithLimit[$i];
}
array_unshift($refs, $typesWithLimit);
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$result = $stmt->get_result();

// Build PHP array for JS
$allRows = [];
while ($row = $result->fetch_assoc()) {
    $row['purok'] = $purokNum;
    $allRows[] = $row;
}
$stmt->close();

// starting row number for this page
$startRowNo = $offset + 1;

// --- small paging counters for the footer ---
$shownCount   = count($allRows);                             // how many rows are on this page
$startDisplay = $totalResidentsPurok > 0 ? ($offset + 1) : 0; // 1-based start index (or 0 when empty)
$endDisplay   = $offset + $shownCount;                       // 1-based end index

?>

<title>eBarangay Mo | Residents</title>

<div class="container-fluid p-3">
  <div id="alertContainer"></div>
  <!-- <div class="card shadow-sm p-3"> -->
  <div class="card shadow-sm p-3 position-relative">
    <!-- Filter -->
    <div class="d-flex justify-content-end mb-3">
      <select id="purokFilter" class="form-select form-select-sm w-auto">
        <?php for ($i = 1; $i <= 6; $i++): ?>
          <option value="<?= $i ?>" <?= $i === $purokNum ? 'selected' : '' ?>>
            Purok <?= $i ?>
          </option>
        <?php endfor; ?>
      </select>

      <button type="button" class="btn btn-sm btn-success ms-2" data-bs-toggle="modal" data-bs-target="#importCSVModal">
        <span class="material-symbols-outlined" style="font-size:1rem; vertical-align:middle;">upload_file</span>
        Import CSV
      </button>

      <button type="button" class="btn btn-sm btn-primary ms-2" id="addResidentBtn">
        <span class="material-symbols-outlined" style="font-size:1rem; vertical-align:middle;">person_add</span>
        Add Resident
      </button>

      <!-- Search Form -->
      <form id="searchForm" method="get" class="d-flex ms-auto me-2">
        <input type="hidden" name="page" value="adminResidents">
        <input type="hidden" name="purok" value="<?= $purokNum ?>">
        <input type="hidden" name="page_num" value="1">
        <div class="input-group input-group-sm w-100">
          <input name="search" id="searchInput" type="text" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
          <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" id="searchBtn">
            <span class="material-symbols-outlined" id="searchIcon">
              <?= !empty($search) ? 'close' : 'search' ?>
            </span>
          </button>
        </div>
      </form>
    </div>

    <div class="table-responsive admin-table" style="height:500px;overflow-y:hidden;"><!-- style="height:500px;overflow-y:auto;"  -->
      <table class="table table-hover align-middle resident-table">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">No.</th>
            <th class="text-nowrap">Full Name</th>
            <th class="text-nowrap">Account Role</th>
            <th class="text-nowrap">Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allRows)): ?>
            <tr><td colspan="4" class="text-center">No data for Purok <?= $purokNum ?></td></tr>
          <?php else: ?>
            <?php $no = $startRowNo; foreach ($allRows as $row):
              // map enum to CSS color
              switch($row['remarks']) {
                case 'On Hold': $bgColor = 'yellow'; break;
                case 'Transferred': $bgColor = 'orange'; break;
                case 'Deceased': $bgColor = 'red';    break;
                default: $bgColor = '';
              }
              $cellStyle = $bgColor ? "background-color:{$bgColor}!important;" : '';
              $escapedName = htmlspecialchars($row['full_name'], ENT_QUOTES);
              $acctId = htmlspecialchars($row['account_ID'] ?? '');
            ?>
              <tr class="resident-row" data-name="<?= $escapedName ?>" data-role="<?= htmlspecialchars($row['role'] ?? '', ENT_QUOTES) ?>" data-account="<?= $acctId ?>">
                <td style="<?= $cellStyle ?>"><?= $no ?></td>
                <td style="<?= $cellStyle ?>">
                  <div class="d-flex flex-column">
                    <span><?= $escapedName ?></span>
                    <small class="text-muted">Account ID: <?= $acctId ?></small>
                  </div>
                </td>
                <td style="<?= $cellStyle ?>">
                  <?php if ($row['role'] !== null): ?>
                    <select class="form-select form-select-sm role-select" style="width:137px; background-image: none; padding-right: 0.5rem;">
                      <?php 
                      $roles = ['Resident','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad', 'Brgy Treasurer', 'Lupon Tagapamayapa'];
                      foreach ($roles as $r): ?>
                        <option value="<?= $r ?>"
                          <?= $row['role'] === $r ? 'selected' : '' ?>>
                          <?= $r ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php else: ?>
                    — 
                  <?php endif; ?>
                </td>
                <td style="<?= $cellStyle ?>">
                  <select class="form-select form-select-sm remarks-select" style="width:101px; background-image: none; padding-right: 0.5rem;">
                    <option value="">None</option>
                    <option value="On Hold" <?= $row['remarks']==='On Hold' ? 'selected' : '' ?>>On Hold</option>
                    <option value="Transferred" <?= $row['remarks']==='Transferred' ? 'selected' : '' ?>>Transferred</option>
                    <option value="Deceased" <?= $row['remarks']==='Deceased' ? 'selected' : '' ?>>Deceased</option>
                  </select>
                </td>
              </tr>
            <?php $no++; endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center pagination-sm">
          <!-- Prev Button -->
          <li class="page-item <?= $page_num <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= $baseQS . http_build_query(array_merge($_GET, ['page_num' => $page_num - 1])) ?>">Previous</a>
          </li>

          <?php
          $range = 2;
          $dots = false;
          for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == 1 || $i == $totalPages || ($i >= $page_num - $range && $i <= $page_num + $range)) {
              $active = $i == $page_num ? 'active' : '';
              $query = $baseQS . http_build_query(array_merge($_GET, ['page_num' => $i]));
              echo "<li class='page-item {$active}'><a class='page-link' href='?{$query}'>$i</a></li>";
              $dots = true;
            } elseif ($dots) {
              echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
              $dots = false;
            }
          }
          ?>

          <!-- Next Button -->
          <li class="page-item <?= $page_num >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= $baseQS . http_build_query(array_merge($_GET, ['page_num' => $page_num + 1])) ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>

    <div id="purokTotalText"
     class="position-absolute end-0 bottom-0 pe-3 pb-2 text-muted user-select-none pointer-events-none">
      <small class="d-block fs-6">
        <?php if ($totalResidentsPurok > 0): ?>
          <!-- <span class="mx-2">•</span> -->
          <span class="text-muted small">
            Showing <strong><?= $startDisplay ?></strong>–<strong><?= $endDisplay ?></strong> of <strong><?= (int)$totalResidentsPurok ?></strong> Residents
          </span>
        <?php else: ?>
          <!-- <span class="mx-2">•</span> -->
          <span class="text-muted small">No Residents Found</span>
        <?php endif; ?>
      </small>
    </div>

  </div>

  <!-- Import CSV Modal -->
  <div class="modal fade" id="importCSVModal" tabindex="-1" aria-labelledby="importCSVLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header text-white" style="background-color:#13411F;">
          <h5 class="modal-title" id="importCSVLabel">Import Residents from CSV</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="importCSVForm" enctype="multipart/form-data">
            <div class="mb-3">
              <label for="purokSelect" class="form-label">Select Purok</label>
              <select id="purokSelect" name="purok" class="form-select" required>
                <option value="">Choose Purok...</option>
                <option value="1">Purok 1</option>
                <option value="2">Purok 2</option>
                <option value="3">Purok 3</option>
                <option value="4">Purok 4</option>
                <option value="5">Purok 5</option>
                <option value="6">Purok 6</option>
              </select>
            </div>
            
            <div class="mb-3">
              <label for="csvFile" class="form-label">CSV File</label>
              <input type="file" class="form-control" id="csvFile" name="csv_file" accept=".csv,.xlsx,.xls" required>
              <div class="form-text">
                Accepts: CSV, Excel (.xlsx, .xls)<br>
                Expected columns: No#, Relationship to Head, Fullname, Date of Birth, Gender, Civil Status, Blood Type, Birth Registration #, Highest Educational Attainment, Occupation, Reg#, Total Population
              </div>
            </div>

            <div class="alert alert-info" role="alert">
              <strong>Note:</strong> The CSV will be imported into the selected Purok table. Make sure your data matches the expected format.
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="importCSVBtn">Import</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Resident Modal -->
  <div class="modal fade" id="addResidentModal" tabindex="-1" aria-labelledby="addResidentLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header text-white" style="background-color:#13411F;">
          <h5 class="modal-title" id="addResidentLabel">Add New Resident</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3" style="max-height:68vh; overflow-y:auto;">
          <form id="addResidentForm" class="row g-2">
            
            <!-- Personal Info Section -->
            <div class="col-12">
              <h6 class="fw-bold fs-5" style="color:#13411F;">Personal Information</h6>
              <hr class="my-2">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
              <input type="text" name="first_name" id="first_name" class="form-control form-control-sm" required>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label fw-bold">Middle Name</label>
              <input type="text" name="middle_name" id="middle_name" class="form-control form-control-sm">
            </div>
            
            <div class="col-12 col-md-4">
              <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
              <input type="text" name="last_name" id="last_name" class="form-control form-control-sm" required>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label fw-bold">Birthdate <span class="text-danger">*</span></label>
              <input type="date" name="birthdate" class="form-control form-control-sm" required>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label fw-bold">Sex <span class="text-danger">*</span></label>
              <select name="sex" class="form-select form-select-sm" required>
                <option value="">Select...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Prefer not to say">Prefer not to say</option>
                <option value="Unknown">Unknown</option>
              </select>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label fw-bold">Civil Status <span class="text-danger">*</span></label>
              <select name="civil_status" class="form-select form-select-sm" required>
                <option value="">Select...</option>
                <option value="Single">Single</option>
                <option value="Married">Married</option>
                <option value="Widowed">Widowed</option>
                <option value="Separated">Separated</option>
                <option value="Divorced">Divorced</option>
                <option value="Unknown">Unknown</option>
              </select>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label fw-bold">Blood Type</label>
              <select name="blood_type" class="form-select form-select-sm">
                <option value="">Select...</option>
                <option value="A+">A+</option>
                <option value="A-">A-</option>
                <option value="B+">B+</option>
                <option value="B-">B-</option>
                <option value="AB+">AB+</option>
                <option value="AB-">AB-</option>
                <option value="O+">O+</option>
                <option value="O-">O-</option>
                <option value="Unknown">Unknown</option>
              </select>
            </div>

            <!-- Address / Household Section -->
            <div class="col-12 mt-3">
              <h6 class="fw-bold fs-5" style="color:#13411F;">Address & Household</h6>
              <hr class="my-2">
            </div>

            <div class="col-12 col-md-3">
              <label class="form-label fw-bold">Purok <span class="text-danger">*</span></label>
              <select name="purok" id="addPurokSelect" class="form-select form-select-sm" required>
                <option value="">Select...</option>
                <option value="1">Purok 1</option>
                <option value="2">Purok 2</option>
                <option value="3">Purok 3</option>
                <option value="4">Purok 4</option>
                <option value="5">Purok 5</option>
                <option value="6">Purok 6</option>
              </select>
            </div>

            <div class="col-12 col-md-3">
              <label class="form-label fw-bold">House No.</label>
              <input type="number" name="house_number" class="form-control form-control-sm">
            </div>

            <div class="col-12 col-md-3">
              <label class="form-label fw-bold">Relationship to Head</label>
              <input type="text" name="relationship_to_head" class="form-control form-control-sm" placeholder="e.g. Head, Spouse, Child">
            </div>

            <div class="col-12 col-md-3">
              <label class="form-label fw-bold">Total Population</label>
              <input type="number" name="total_population" class="form-control form-control-sm">
            </div>

            <!-- Other Info Section -->
            <div class="col-12 mt-3">
              <h6 class="fw-bold fs-5" style="color:#13411F;">Other Information</h6>
              <hr class="my-2">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label fw-bold">Birth Registration No.</label>
              <input type="text" name="birth_registration_number" class="form-control form-control-sm">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label fw-bold">Highest Educational Attainment</label>
              <select name="highest_educational_attainment" class="form-select form-select-sm">
                <option value="">Select...</option>
                <option value="Kindergarten">Kindergarten</option>
                <option value="Elementary">Elementary</option>
                <option value="High School">High School</option>
                <option value="Senior High School">Senior High School</option>
                <option value="Undergraduate">Undergraduate</option>
                <option value="College Graduate">College Graduate</option>
                <option value="Post-Graduate">Post-Graduate</option>
                <option value="Vocational">Vocational</option>
                <option value="None">None</option>
                <option value="Unknown">Unknown</option>
              </select>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label fw-bold">Occupation</label>
              <input type="text" name="occupation" class="form-control form-control-sm" placeholder="e.g. Teacher, Farmer">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-bold">Registry No.</label>
              <input type="number" name="registry_number" class="form-control form-control-sm">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label fw-bold">Remarks</label>
              <select name="remarks" class="form-select form-select-sm">
                <option value="">None</option>
                <option value="On Hold">On Hold</option>
                <option value="Transferred">Transferred</option>
                <option value="Deceased">Deceased</option>
              </select>
            </div>

          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="saveNewResidentBtn">Save Resident</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Resident Details Modal -->
<div class="modal fade" id="residentDetailsModal" tabindex="-1" aria-labelledby="residentDetailsLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered"> <!-- changed to modal-xl -->
    <div class="modal-content">
      <div class="modal-header text-white" style="background-color:#13411F;">
        <h5 class="modal-title" id="residentDetailsLabel">Resident Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" style="max-height:68vh; overflow-y:auto;">
        <form id="residentDetailsForm" class="row g-2">
          <!-- buildForm() will inject here -->
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" id="detailsEditSaveBtn">Edit</button>
      </div>
    </div>
  </div>
</div>

<!-- Add this confirmation modal right after your existing modal -->
<div class="modal fade" id="confirmSaveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Changes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to save these changes?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmSaveBtn">Yes, Save</button>
      </div>
    </div>
  </div>
</div>

<script>
  function showBootstrapAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alertContainer');
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    `;
    alertContainer.appendChild(wrapper);

    // Optional: auto-dismiss after 5s
    setTimeout(() => {
      wrapper.querySelector('.alert').classList.remove('show');
      wrapper.querySelector('.alert').classList.add('fade');
      setTimeout(() => wrapper.remove(), 500);
    }, 5000);
  }

  // Pass data to JS
  const residents = <?= json_encode($allRows, JSON_HEX_TAG) ?>;
  const purokNum = <?= $purokNum ?>;
  window.loggedInUserRole = <?= json_encode($_SESSION['loggedInUserRole'] ?? '') ?>;

  // map remarks to colors in JS
  const remarkColor = {
    'On Hold': 'yellow',
    'Transferred': 'orange',
    'Deceased': 'red'
  };

  document.addEventListener('DOMContentLoaded', () => {
    const canEdit = window.loggedInUserRole !== 'Brgy Kagawad';

    if (!canEdit) {
    // disable the remarks & role dropdowns
    document.querySelectorAll('.remarks-select, .role-select')
      .forEach(s => s.disabled = true);

    // remove the row‐click handler links to edit
    document.querySelectorAll('.resident-row').forEach(row => {
      row.style.cursor = 'default';
      row.replaceWith(row.cloneNode(true));  
      // (cloneNode strips off any event listeners)
    });

    // hide the “Edit” button in the modal
    document.getElementById('detailsEditSaveBtn').style.display = 'none';
  }
  
    // --- Filter Purok
    document.getElementById('purokFilter').addEventListener('change', function() {
      const url = new URL(window.location.href);
      url.searchParams.set('purok', this.value);
      url.searchParams.set('page_num', '1');
      window.location.href = url;
    });

    // Search handler
    const Sform = document.getElementById('searchForm');
    const input = document.getElementById('searchInput');
    const btn = document.getElementById('searchBtn');
    let hasSearch = <?= json_encode($search !== '') ?>;
    btn.addEventListener('click', () => {
      if (hasSearch) input.value = '';
      Sform.submit();
    });

    document.querySelectorAll('.remarks-select').forEach(sel => {
      sel.addEventListener('change', async function () {
        const row = this.closest('tr');
        const name = row.dataset.name;
        const newRemark = this.value;
        const color = remarkColor[newRemark] || '';

        // Check if resident has pending blotter
        const res = await fetch('functions/check_pending.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ full_name: name })
        });
        const json = await res.json();

        // If pending and admin tries to change to something else
        if (json.has_pending && newRemark !== 'On Hold') {
          showBootstrapAlert(`<strong>${name}</strong> has a pending complaint case. Remarks must remain as <strong>On Hold</strong>.`, 'danger');
          this.value = 'On Hold'; // revert back
          return;
        }

        // update row color
        row.querySelectorAll('td').forEach(td => td.style.backgroundColor = color);

        // persist
        await fetch('functions/update_remarks.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ full_name: name, purok: purokNum, remarks: newRemark })
        });
      });
    });

    // --- Role dropdown handler (replacement)
    document.querySelectorAll('.role-select').forEach(sel => {
      // store current value baseline so we can revert on failure
      sel.dataset.original = sel.value;

      sel.addEventListener('change', async function() {
        const tr = sel.closest('tr');
        const acct = (tr && tr.dataset && tr.dataset.account) ? tr.dataset.account.trim() : '';
        const newRole = sel.value;
        const oldRole = sel.dataset.original || tr.dataset.role || '';
        const residentName = tr.dataset.name || 'this resident';

        if (!acct) {
          showBootstrapAlert('<i class="bi bi-exclamation-triangle-fill"></i> <strong>Error:</strong> Account ID not found for this row.', 'danger');
          sel.value = oldRole;
          return;
        }

        if (newRole === oldRole) return;

        // Disable dropdown while updating
        sel.disabled = true;

        try {
          const res = await fetch('functions/update_role.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ account_id: acct, role: newRole })
          });

          let json;
          try { 
            json = await res.json(); 
          } catch (e) {
            sel.value = oldRole;
            showBootstrapAlert('<strong>Error:</strong> Invalid server response while updating role.', 'danger');
            return;
          }

          if (!json.success) {
            sel.value = oldRole;
            
            // Format error message based on type
            let errorMsg = json.error || 'Failed to update role';
            
            if (errorMsg.includes('permission')) {
              showBootstrapAlert(`<strong>Permission Denied:</strong> ${errorMsg}`, 'warning');
            } else if (errorMsg.includes('limit')) {
              showBootstrapAlert(`<strong>Role Limit Reached:</strong> ${errorMsg}`, 'warning');
            } else if (errorMsg.includes('cannot update your own role')) {
              showBootstrapAlert(`<strong>Not Allowed:</strong> You cannot update your own role.`, 'warning');
            } else if (errorMsg.includes('Brgy Captain')) {
              showBootstrapAlert(`<strong>Protected Role:</strong> ${errorMsg}`, 'warning');
            } else {
              showBootstrapAlert(`<strong>Error:</strong> ${errorMsg}`, 'danger');
            }
          } else {
            // Success: update dataset & baseline
            tr.dataset.role = newRole;
            sel.dataset.original = newRole;
            
            // Show success message
            const msg = json.message || `Role updated successfully for <strong>${residentName}</strong>`;
            showBootstrapAlert(msg, 'success');
          }
        } catch (err) {
          sel.value = oldRole;
          showBootstrapAlert('<strong>Network Error:</strong> Unable to connect to server. Please check your connection and try again.', 'danger');
          console.error(err);
        } finally {
          sel.disabled = false;
        }
      });
    });

    // --- Details / Edit Modal setup ---
    const modalEl = document.getElementById('residentDetailsModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('residentDetailsForm');
    const editSaveBtn = document.getElementById('detailsEditSaveBtn');
    let currentData = null;
    let isEditing = false;

    // Confirmation modal
    const confirmSaveModalEl = document.getElementById('confirmSaveModal');
    const confirmSaveModal = new bootstrap.Modal(confirmSaveModalEl);
    const confirmSaveBtn = document.getElementById('confirmSaveBtn');

    // Build form fields (readonly by default)
    function buildForm(data) {
      currentData = data;
      isEditing = false;
      editSaveBtn.textContent = 'Edit';
      
      form.innerHTML = '';

      // Profile picture at the top
      if (data.profile_picture) {
        const picDiv = document.createElement('div');
        picDiv.className = 'text-center mb-4';
        picDiv.innerHTML = `
          <img src="profilePictures/${data.profile_picture}" 
              class="rounded-circle" 
              style="width:120px;height:120px;object-fit:cover;">
        `;
        form.appendChild(picDiv);
      }

      // Helper to build a grid field
      function gridField(label, key, type = 'text', opts = [], colSize = 'col-12 col-md-4') {
        const val = data[key] ?? '';
        let fieldHtml = '';

        if (type === 'select') {
          fieldHtml = `<select id="field_${key}" name="${key}" class="form-select form-select-sm" disabled>
            ${opts.map(opt => `<option value="${opt}" ${String(val) === opt ? 'selected' : ''}>${opt}</option>`).join('')}
          </select>`;
        } else {
          fieldHtml = `<input id="field_${key}" name="${key}" type="${type}" 
                      class="form-control form-control-sm" 
                      value="${val}" disabled>`;
        }

        return `
          <div class="${colSize}">
            <label class="form-label fw-bold">${label}</label>
            ${fieldHtml}
          </div>
        `;
      }

      // Build sections in a grid layout
      form.innerHTML += `
        <!-- Personal Info -->
        <div class="col-12">
          <h6 class="fw-bold fs-5" style="color:#13411F;">Personal Information</h6>
          <hr class="my-2">
        </div>
        ${gridField('Full Name', 'full_name')}
        ${gridField('Birthdate', 'birthdate', 'date')}
        ${gridField('Sex', 'sex', 'select', ['Male','Female','Prefer not to say','Unknown'])}
        ${gridField('Civil Status', 'civil_status', 'select', ['Single','Married','Widowed','Separated','Divorced','Unknown'])}
        ${gridField('Blood Type', 'blood_type', 'select', ['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown'])}

        <!-- Address / Household -->
        <div class="col-12 mt-3">
          <h6 class="fw-bold fs-5" style="color:#13411F;">Address & Household</h6>
          <hr class="my-2">
        </div>
        ${gridField('Purok', 'purok', 'select', ['1','2','3','4','5','6'], 'col-12 col-md-3')}
        ${gridField('House No.', 'house_number', 'number', [], 'col-12 col-md-3')}
        ${gridField('Relationship to Head', 'relationship_to_head', 'text', [], 'col-12 col-md-3')}
        ${gridField('Total Population', 'total_population', 'number', [], 'col-12 col-md-3')}

        <!-- Other Info -->
        <div class="col-12 mt-3">
          <h6 class="fw-bold fs-5" style="color:#13411F;">Other Information</h6>
          <hr class="my-2">
        </div>
        ${gridField('Birth Reg. No.', 'birth_registration_number')}
        ${gridField('Highest Educational Attainment', 'highest_educational_attainment', 'select', ['Kindergarten','Elementary','High School','Senior High School','Undergraduate','College Graduate','Post-Graduate','Vocational','None','Unknown'])}
        ${gridField('Occupation', 'occupation')}
        ${gridField('Registry No.', 'registry_number', 'number')}
        ${gridField('Role', 'role')}
        ${gridField('Remarks', 'remarks')}
      `;
    }

    // Toggle Edit ↔ Save
    editSaveBtn.addEventListener('click', () => {
      if (!isEditing) {
        // switch to edit mode
        isEditing = true;
        editSaveBtn.textContent = 'Save';

        // enable only editable controls
       ['purok','full_name','house_number','relationship_to_head','registry_number','total_population',
        'sex','civil_status','blood_type', 'birth_registration_number','highest_educational_attainment', 'occupation'
       ].forEach(k => {
         const el = document.getElementById(`field_${k}`);
         if (el) el.disabled = false;
       });
      }
      else {
        // ask for confirmation
        confirmSaveModal.show();
      }
    });

    // actual save once confirmed
    confirmSaveBtn.addEventListener('click', async () => {
      confirmSaveModal.hide();

      // gather payload
      const originalPurok = currentData.purok;
      const newPurok = document.getElementById('field_purok').value;
      const payload = new URLSearchParams({ 
        account_id: currentData.account_ID,
        original_purok: originalPurok,
        new_purok: newPurok
      });
      
      if (currentData.profile_picture) {
        payload.append('profile_picture', currentData.profile_picture);
      }

      ['full_name','birthdate','sex','civil_status','blood_type',
      'birth_registration_number','highest_educational_attainment',
      'occupation','house_number','relationship_to_head',
      'registry_number','total_population'
      ].forEach(k => {
        const el = document.getElementById(`field_${k}`);
        payload.append(k, el.tagName==='SELECT' ? el.value : el.value);
      });

      // send to your update_resident.php
      const resp = await fetch('functions/update_resident.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: payload
      });

      let text = await resp.text();
      console.log('Raw response:', text);
      let json;
      try {
        json = JSON.parse(text);
      } catch(e) {
        return alert('Invalid JSON response, see console for raw output');
      }

      if (!json.success) {
        showBootstrapAlert('Save failed: ' + (json.error||'unknown'), 'danger');
        return;
      }

      // Close modal
      modal.hide();
      
      // Show success message
      showBootstrapAlert('Resident details updated successfully!', 'success');
      
      // Reload page to show updated data
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    });

    // Row click opens the modal
    document.querySelectorAll('.resident-row').forEach(row => {
      row.addEventListener('click', e => {
        if (e.target.closest('.remarks-select') || e.target.closest('.role-select')) return;
        const name = row.dataset.name;
        const data = residents.find(r => r.full_name === name);
        if (!data) return;
        buildForm(data);
        modal.show();
      });
    });

    // --- Add Resident Handler ---
    const addResidentBtn = document.getElementById('addResidentBtn');
    const addResidentModal = new bootstrap.Modal(document.getElementById('addResidentModal'));
    const addResidentForm = document.getElementById('addResidentForm');
    const saveNewResidentBtn = document.getElementById('saveNewResidentBtn');
    const addPurokSelect = document.getElementById('addPurokSelect');

    // Open modal and pre-select current purok
    addResidentBtn.addEventListener('click', () => {
      addResidentForm.reset();
      addPurokSelect.value = purokNum.toString();
      addResidentModal.show();
    });

    // Save new resident
    saveNewResidentBtn.addEventListener('click', async () => {
      // Validate required fields
      if (!addResidentForm.checkValidity()) {
        addResidentForm.reportValidity();
        return;
      }

      // Gather form data
      const formData = new FormData(addResidentForm);

      // Disable button while saving
      saveNewResidentBtn.disabled = true;
      saveNewResidentBtn.textContent = 'Saving...';

      try {
        const response = await fetch('functions/add_resident.php', {
          method: 'POST',
          body: formData
        });

        const text = await response.text();
        console.log('Raw response:', text);

        let json;
        try {
          json = JSON.parse(text);
        } catch (e) {
          showBootstrapAlert('Server error: Invalid response format. Check console for details.', 'danger');
          console.error('Parse error:', e, 'Response:', text);
          return;
        }

        if (json.success) {
          addResidentModal.hide();
          const lastName = formData.get('last_name');
          const firstName = formData.get('first_name');
          const middleName = formData.get('middle_name');
          const displayName = middleName 
            ? `${lastName}, ${firstName} ${middleName}` 
            : `${lastName}, ${firstName}`;
          showBootstrapAlert(`Resident <strong>${displayName}</strong> added successfully!`, 'success');
          
          // Reload if added to current purok, otherwise show info message
          const addedToPurok = parseInt(formData.get('purok'));
          if (addedToPurok === purokNum) {
            setTimeout(() => window.location.reload(), 2000);
          } else {
            setTimeout(() => {
              showBootstrapAlert(`Resident added to Purok ${addedToPurok}. Switch to that purok to view.`, 'info');
            }, 500);
          }
        } else {
          showBootstrapAlert(`Failed to add resident: ${json.error}`, 'danger');
        }
      } catch (error) {
        showBootstrapAlert('Network error: Unable to add resident', 'danger');
        console.error(error);
      } finally {
        saveNewResidentBtn.disabled = false;
        saveNewResidentBtn.textContent = 'Save Resident';
      }
    });
  });

  // Replace the existing CSV import button click handler with this:
  importCSVBtn.addEventListener('click', async () => {
    const purokSelect = document.getElementById('purokSelect');
    const csvFile = document.getElementById('csvFile');

    if (!purokSelect.value) {
      showBootstrapAlert('Please select a Purok', 'warning');
      return;
    }

    if (!csvFile.files[0]) {
      showBootstrapAlert('Please select a CSV file', 'warning');
      return;
    }

    const formData = new FormData();
    formData.append('purok', purokSelect.value);
    formData.append('csv_file', csvFile.files[0]);

    importCSVBtn.disabled = true;
    importCSVBtn.textContent = 'Importing...';

    try {
      const response = await fetch('functions/import_residents_csv.php', {
        method: 'POST',
        body: formData
      });

      const text = await response.text();
      console.log('Raw response:', text);
      
      let json;
      try {
        json = JSON.parse(text);
      } catch (e) {
        showBootstrapAlert('Server error: Invalid response format. Check console for details.', 'danger');
        console.error('Parse error:', e, 'Response:', text);
        return;
      }

      if (json.success) {
        let message = `Successfully imported ${json.imported_count} of ${json.total_rows} residents!`;
        if (json.skipped_count > 0) {
          message += `<br><small>${json.skipped_count} rows were skipped.</small>`;
        }
        if (json.errors && json.errors.length > 0) {
          message += `<br><details><summary>View Errors (${json.errors.length})</summary><ul>`;
          json.errors.slice(0, 10).forEach(err => {
            message += `<li>${err}</li>`;
          });
          if (json.errors.length > 10) {
            message += `<li>...and ${json.errors.length - 10} more</li>`;
          }
          message += `</ul></details>`;
        }
        
        showBootstrapAlert(message, json.errors && json.errors.length > 0 ? 'warning' : 'success');
        
        // Close modal and reset form
        const importModal = bootstrap.Modal.getInstance(document.getElementById('importCSVModal'));
        importModal.hide();
        importCSVForm.reset();
        
        // Only reload if importing to the currently selected purok
        if (parseInt(purokSelect.value) === purokNum) {
          // Always reload to show updated data
          setTimeout(() => {
            const url = new URL(window.location.href);
            url.searchParams.set('purok', purokSelect.value);
            url.searchParams.set('page_num', '1');
            window.location.href = url;
          }, 2000);
        } else {
          // Show message that data was imported to different purok
          setTimeout(() => {
            showBootstrapAlert(`Data imported to Purok ${purokSelect.value}. Switch to that purok to view the new residents.`, 'info');
          }, 500);
        }
      } else {
        showBootstrapAlert(`Import failed: ${json.error}`, 'danger');
        if (json.trace) {
          console.error('Stack trace:', json.trace);
        }
      }
    } catch (error) {
      showBootstrapAlert('Network error: Unable to import CSV', 'danger');
      console.error(error);
    } finally {
      importCSVBtn.disabled = false;
      importCSVBtn.textContent = 'Import';
    }
  });
</script>
