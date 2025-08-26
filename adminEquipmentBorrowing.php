<?php
// equipment.php
require 'functions/dbconn.php';

// GET params
$search = trim($_GET['search'] ?? '');
$filter_name = $_GET['filter_name'] ?? '';
$added = $_GET['added'] ?? null;

// GET params for borrows tab
$bsearch = trim($_GET['bsearch'] ?? '');
$filter_esn = $_GET['filter_esn'] ?? '';
$filter_date_from = $_GET['filter_date_from'] ?? '';
$filter_date_to = $_GET['filter_date_to'] ?? '';

// small utility to build reference arrays for call_user_func_array
function refValues($arr){
  $refs = [];
  foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
  return $refs;
}

/**
 * Fetch all rows from mysqli_stmt as associative array.
 * Supports both mysqlnd (get_result) and non-mysqlnd (bind_result fallback).
 */
function stmt_fetch_all_assoc(mysqli_stmt $stmt) : array {
  // prefer get_result if available
  if (method_exists($stmt, 'get_result')) {
    $res = $stmt->get_result();
    if (!$res) return [];
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
    return $rows ?: [];
  }

  // fallback for environments without mysqlnd
  $meta = $stmt->result_metadata();
  if (!$meta) return [];

  $fields = [];
  $row = [];
  while ($f = $meta->fetch_field()) {
    $row[$f->name] = null;
    $fields[] = &$row[$f->name];
  }
  $meta->free();

  // Bind result variables
  if (!call_user_func_array([$stmt, 'bind_result'], $fields)) {
    return [];
  }

  $out = [];
  while ($stmt->fetch()) {
    $r = [];
    foreach ($row as $k => $v) $r[$k] = $v;
    $out[] = $r;
  }
  return $out;
}

/** Strict YYYY-MM-DD validator (also rejects impossible dates) */
function valid_date(string $d): bool {
  $dt = DateTime::createFromFormat('Y-m-d', $d);
  return $dt && $dt->format('Y-m-d') === $d;
}

/**
 * Safe bind + execute + fetch wrapper for mysqli_stmt.
 * - $types: string of types for bind_param (may be empty if no params)
 * - $params: array of values to bind (in order)
 * Returns array of associative rows or [] on error.
 */
function stmt_bind_execute_fetch(mysqli_stmt $stmt, string $types = '', array $params = []): array {
  if (!$stmt) {
    error_log("stmt_bind_execute_fetch: null stmt");
    return [];
  }

  // Bind parameters if provided
  if ($types !== '') {
    $bind = array_merge([$types], $params);
    $refs = refValues($bind);
    $ok = @call_user_func_array([$stmt, 'bind_param'], $refs);
    if ($ok === false) {
      error_log("bind_param failed: " . $stmt->error);
      return [];
    }
  }

  // Execute
  if (!$stmt->execute()) {
    error_log("Statement execute failed: " . $stmt->error);
    return [];
  }

  // Fetch rows (handles mysqlnd/no-mysqlnd)
  $rows = stmt_fetch_all_assoc($stmt);
  return $rows;
}

// --- Fetch distinct equipment names for the filter dropdown (always from full table) ---
$nameStmt = $conn->prepare("SELECT DISTINCT name FROM equipment_list ORDER BY name");
if (!$nameStmt) {
  error_log("Prepare failed (nameStmt): " . $conn->error);
  $names = [];
} else {
  $rows = stmt_bind_execute_fetch($nameStmt, '', []);
  $nameStmt->close();
  $names = $rows ? array_column($rows, 'name') : [];
}

// --- Fetch full equipment list for JS autocomplete and borrow filter ---
$allEquipments = [];
$allEqStmt = $conn->prepare("SELECT * FROM equipment_list ORDER BY name, equipment_sn");
if (!$allEqStmt) {
  error_log("Prepare failed (allEqStmt): " . $conn->error);
  $allEquipments = [];
} else {
  $allEquipments = stmt_bind_execute_fetch($allEqStmt, '', []);
  $allEqStmt->close();
}

$jsMap = array_column($allEquipments, 'available_qty', 'equipment_sn');

// --- Build filtered equipment query (applies search + name filter) ---
$equipSql = "SELECT * FROM equipment_list";
$whereParts = [];
$params = [];
$types = '';

if ($filter_name !== '') {
  $whereParts[] = "name = ?";
  $params[] = $filter_name;
  $types .= 's';
}

if ($search !== '') {
  $whereParts[] = "(equipment_sn LIKE ? OR name LIKE ? OR description LIKE ?)";
  $like = '%' . $search . '%';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= 'sss';
}

if ($whereParts) {
  $equipSql .= ' WHERE ' . implode(' AND ', $whereParts);
}
$equipSql .= ' ORDER BY id';

$equipments = [];
$eqStmt = $conn->prepare($equipSql);
if (!$eqStmt) {
  error_log("Prepare failed (equipSql): " . $conn->error . " -- SQL: " . $equipSql);
  $equipments = [];
} else {
  $equipments = stmt_bind_execute_fetch($eqStmt, $types, $params);
  $eqStmt->close();
}

// --- Build filtered borrow requests query ---
// We'll LEFT JOIN equipment_list to allow searching/filtering by equipment name
$borrowSql = "SELECT br.*, IFNULL(el.name, '') AS equipment_name
              FROM borrow_requests br
              LEFT JOIN equipment_list el ON br.equipment_sn = el.equipment_sn";

$borrowWhere = [];
$bParams = [];
$bTypes = '';

if ($filter_esn !== '') {
  $borrowWhere[] = "br.equipment_sn = ?";
  $bParams[] = $filter_esn;
  $bTypes .= 's';
}

if ($filter_date_from !== '' ) {
  if (valid_date($filter_date_from)) {
    $borrowWhere[] = "br.date >= ?";
    $bParams[] = $filter_date_from . ' 00:00:00';
    $bTypes .= 's';
  } else {
    error_log("Invalid filter_date_from: " . $filter_date_from);
  }
}

if ($filter_date_to !== '') {
  if (valid_date($filter_date_to)) {
    $borrowWhere[] = "br.date <= ?";
    $bParams[] = $filter_date_to . ' 23:59:59';
    $bTypes .= 's';
  } else {
    error_log("Invalid filter_date_to: " . $filter_date_to);
  }
}

if ($bsearch !== '') {
  $borrowWhere[] = "(br.resident_name LIKE ? 
                     OR br.equipment_sn LIKE ? 
                     OR IFNULL(el.name,'') LIKE ? 
                     OR CAST(br.qty AS CHAR) LIKE ? 
                     OR br.location LIKE ? 
                     OR br.used_for LIKE ? 
                     OR br.date LIKE ? 
                     OR br.pudo LIKE ?)";
  $blike = '%' . $bsearch . '%';
  for ($i = 0; $i < 8; $i++) {
    $bParams[] = $blike;
    $bTypes .= 's';
  }
}

if ($borrowWhere) {
  $borrowSql .= ' WHERE ' . implode(' AND ', $borrowWhere);
}
$borrowSql .= ' ORDER BY br.date DESC, br.id DESC';

$borrows = [];
$brStmt = $conn->prepare($borrowSql);
if (!$brStmt) {
  error_log("Prepare failed (borrowSql): " . $conn->error . " -- SQL: " . $borrowSql);
  $borrows = [];
} else {
  $borrows = stmt_bind_execute_fetch($brStmt, $bTypes, $bParams);
  $brStmt->close();
}
?>

<div class="container-fluid p-3">

  <!-- Alert for add -->
  <?php if ($added): ?>
    <div class="container mt-3">
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        Equipment <strong><?= htmlspecialchars($added) ?></strong> added successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    </div>
  <?php endif; ?>

  <!-- Alert for updates -->
  <?php if (($_GET['updated'] ?? '') === 'none'): ?>
    <div class="alert alert-secondary alert-dismissible fade show" role="alert">
      No changes were made. Equipment was not updated.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (($_GET['updated'] ?? '') === 'partial'): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      Equipment was updated, but quantity was not changed because some items are currently borrowed.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (($_GET['updated'] ?? '') === 'full'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Equipment updated successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- Alert for delete -->
  <?php if (($_GET['deleted'] ?? '') === '1'): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      Equipment deleted permanently.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (($_GET['deleted'] ?? '') === '0' && ($_GET['delete_error'] ?? '') === 'borrowed'): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      Cannot delete this equipment because there are existing borrow requests.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- Alert for borrow -->
  <?php if (($_GET['borrowed'] ?? '') === '1'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Borrow request submitted successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php elseif (($_GET['borrow_error'] ?? '') === 'toomany'): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      Requested quantity exceeds availability.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Alert Placeholder -->
  <div id="statusAlertPlaceholder"></div>

  <!-- NAV TABS: Equipment / Borrow Requests -->
  <ul class="nav nav-tabs mb-3" id="equipBorrowTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= (($_GET['tab'] ?? '') === 'borrows') ? '' : 'active' ?>" id="tab-equipments-btn" data-bs-toggle="tab" data-bs-target="#tab-equipments" type="button" role="tab" aria-controls="tab-equipments" aria-selected="true">
        List of Equipments
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= (($_GET['tab'] ?? '') === 'borrows') ? 'active' : '' ?>" id="tab-borrows-btn" data-bs-toggle="tab" data-bs-target="#tab-borrows" type="button" role="tab" aria-controls="tab-borrows" aria-selected="false">
        Borrow Requests
      </button>
    </li>
  </ul>

  <div class="tab-content">
    <!-- Equipments Tab Pane -->
    <div class="tab-pane fade <?= (($_GET['tab'] ?? '') === 'borrows') ? '' : 'show active' ?>" id="tab-equipments" role="tabpanel" aria-labelledby="tab-equipments-btn">
      <div class="card shadow-sm p-3">
        <div class="d-flex align-items-center mb-3">
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdownEquip" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">filter_list</span>
              Filter
            </button>
            <div class="dropdown-menu p-3" aria-labelledby="filterDropdownEquip" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
              <form method="get" class="mb-0" id="filterFormEquip">
                <input type="hidden" name="page" value="adminEquipmentBorrowing">
                <input type="hidden" name="tab" value="equipments">

                <!-- FILTER: Equipment Name (dynamic) -->
                <div class="mb-2">
                  <label for="filter_name" class="form-label form-label-sm">Equipment Name</label>
                  <select name="filter_name" id="filter_name" class="form-select form-select-sm">
                    <option value="">All names</option>
                    <?php foreach ($names as $n): ?>
                      <option value="<?= htmlspecialchars($n, ENT_QUOTES) ?>" <?= $filter_name === $n ? 'selected' : '' ?>>
                        <?= htmlspecialchars($n) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="d-flex">
                  <a href="?page=adminEquipmentBorrowing&tab=equipments" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
                  <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
                </div>
              </form>
            </div>
          </div>

          <button class="btn btn-sm btn-success ms-3" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
            <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">add</span>
            Add New Equipment
          </button>

          <form method="get" id="searchFormEquip" class="d-flex ms-auto me-2">
            <input type="hidden" name="page" value="adminEquipmentBorrowing">
            <input type="hidden" name="tab" value="equipments">
            <input type="hidden" name="filter_name" value="<?= htmlspecialchars($filter_name, ENT_QUOTES) ?>">

            <div class="input-group input-group-sm">
              <input name="search" id="searchInputEquip" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
              <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" id="searchBtnEquip" title="Search / Clear">
                <span class="material-symbols-outlined" id="searchIconEquip">
                  <?= $search !== '' ? 'close' : 'search' ?>
                </span>
              </button>
            </div>
            <noscript>
              <button type="submit" class="btn btn-outline-secondary">Search</button>
            </noscript>
          </form>
        </div>

        <div class="table-responsive admin-table">
          <table class="table table-hover align-middle text-start">
            <thead class="table-light">
              <tr>
                <th>Equipment SN</th>
                <th>Name</th>
                <th>Description</th>
                <th>Avail Qty</th>
                <th>Total Qty</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($equipments)): ?>
                <tr><td colspan="6" class="text-center">No equipment found.</td></tr>
              <?php else: foreach($equipments as $eq): ?>
                <tr>
                  <td><?= htmlspecialchars($eq['equipment_sn']) ?></td>
                  <td><?= htmlspecialchars($eq['name']) ?></td>
                  <td><?= nl2br(htmlspecialchars($eq['description']))?: '—' ?></td>
                  <td class="avail-qty" data-id="<?= $eq['id'] ?>">
                    <?= (int)$eq['available_qty'] ?>
                  </td>
                  <td><?= (int)$eq['total_qty'] ?></td>
                  <td>
                    <button class="btn btn-sm btn-primary me-1 edit-equipment-btn" data-id="<?= $eq['id'] ?>" data-name="<?= htmlspecialchars($eq['name'], ENT_QUOTES) ?>" data-desc="<?= htmlspecialchars($eq['description'], ENT_QUOTES) ?>" data-total="<?= (int)$eq['total_qty'] ?>">
                      <span class="material-symbols-outlined" style="font-size:13px">stylus</span>
                    </button>
                    <button class="btn btn-sm btn-danger delete-equipment-btn" data-id="<?= $eq['id'] ?>" data-name="<?= htmlspecialchars($eq['name'], ENT_QUOTES) ?>">
                      <span class="material-symbols-outlined" style="font-size:13px">delete</span>
                    </button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Borrow Requests Tab Pane -->
    <div class="tab-pane fade <?= (($_GET['tab'] ?? '') === 'borrows') ? 'show active' : '' ?>" id="tab-borrows" role="tabpanel" aria-labelledby="tab-borrows-btn">
      <div class="card shadow-sm p-3">
        <div class="d-flex align-items-center mb-3">
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdownBorrow" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">filter_list</span>
              Filter
            </button>
            <div class="dropdown-menu p-3" aria-labelledby="filterDropdownBorrow" style="min-width:320px; --bs-body-font-size:.75rem; font-size:.75rem;">
              <form method="get" class="mb-0" id="filterFormBorrow">
                <input type="hidden" name="page" value="adminEquipmentBorrowing">
                <input type="hidden" name="tab" value="borrows">

                <!-- FILTER: Equipment (show name + esn) -->
                <div class="mb-2">
                  <label for="filter_esn" class="form-label form-label-sm">Equipment</label>
                  <select name="filter_esn" id="filter_esn" class="form-select form-select-sm">
                    <option value="">All equipments</option>
                    <?php foreach ($allEquipments as $ae): ?>
                      <option value="<?= htmlspecialchars($ae['equipment_sn'], ENT_QUOTES) ?>" <?= $filter_esn === $ae['equipment_sn'] ? 'selected' : ''?>>
                        <?= htmlspecialchars($ae['name']) ?> (<?= htmlspecialchars($ae['equipment_sn']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- FILTER: Date range -->
                <div class="mb-2">
                  <label class="form-label form-label-sm">Date range</label>
                  <div class="d-flex gap-2">
                    <input type="date" name="filter_date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_from, ENT_QUOTES) ?>" placeholder="From">
                    <input type="date" name="filter_date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_to, ENT_QUOTES) ?>" placeholder="To">
                  </div>
                </div>

                <div class="d-flex">
                  <a href="?page=adminEquipmentBorrowing&tab=borrows" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
                  <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
                </div>
              </form>
            </div>
          </div>

          <button class="btn btn-sm btn-success ms-3" data-bs-toggle="modal" data-bs-target="#addBorrowModal">
            <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">add</span>
            Borrow an Equipment
          </button>

          <!-- Borrow search: uses bsearch param to avoid colliding with equipments search -->
          <form method="get" id="searchFormBorrow" class="d-flex ms-auto me-2">
            <input type="hidden" name="page" value="adminEquipmentBorrowing">
            <input type="hidden" name="tab" value="borrows">
            <!-- preserve current borrow filters when searching -->
            <input type="hidden" name="filter_esn" value="<?= htmlspecialchars($filter_esn, ENT_QUOTES) ?>">
            <input type="hidden" name="filter_date_from" value="<?= htmlspecialchars($filter_date_from, ENT_QUOTES) ?>">
            <input type="hidden" name="filter_date_to" value="<?= htmlspecialchars($filter_date_to, ENT_QUOTES) ?>">

            <div class="input-group input-group-sm">
              <input name="bsearch" id="searchInputBorrow" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($bsearch, ENT_QUOTES) ?>">
              <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" id="searchBtnBorrow" title="Search">
                <span class="material-symbols-outlined" id="searchIconBorrow"><?= $bsearch !== '' ? 'close' : 'search' ?></span>
              </button>
            </div>
            <noscript>
              <button type="submit" class="btn btn-outline-secondary">Search</button>
            </noscript>
          </form>
        </div>
        
        <!-- simplified Borrow Requests table -->
        <div class="table-responsive admin-table">
          <table class="table table-hover align-middle text-start">
            <thead class="table-light">
              <tr>
                <th>Resident’s Name</th>
                <th>Equipment</th>
                <th>Qty</th>
                <th>Date Requested</th>
                <th>Status</th>
                <th class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($borrows)): ?>
                <tr><td colspan="5" class="text-center">No borrow requests.</td></tr>
              <?php else: foreach($borrows as $br):
                $status = $br['status'] ?? 'Pending';
              ?>
                <tr
                  data-id="<?= (int)$br['id'] ?>"
                  data-resident="<?= htmlspecialchars($br['resident_name'], ENT_QUOTES) ?>"
                  data-esn="<?= htmlspecialchars($br['equipment_sn'], ENT_QUOTES) ?>"
                  data-equipment="<?= htmlspecialchars($br['equipment_name'], ENT_QUOTES) ?>"
                  data-qty="<?= (int)$br['qty'] ?>"
                  data-location="<?= htmlspecialchars($br['location'], ENT_QUOTES) ?>"
                  data-usedfor="<?= htmlspecialchars($br['used_for'], ENT_QUOTES) ?>"
                  data-date="<?= htmlspecialchars($br['date'], ENT_QUOTES) ?>"
                  data-pudo="<?= htmlspecialchars($br['pudo'], ENT_QUOTES) ?>"
                  data-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>"
                >
                  <td><?= htmlspecialchars($br['resident_name']) ?></td>
                  <td><?= htmlspecialchars($br['equipment_name'] ?: $br['equipment_sn']) ?></td>
                  <td><?= (int)$br['qty'] ?></td>
                  <td><?= htmlspecialchars($br['date']) ?></td>
                  <td>
                    <?php
                      // compute badge class
                      $badgeClass = 'bg-secondary';
                      if ($status === 'Pending') $badgeClass = 'bg-info';
                      if ($status === 'Borrowed') $badgeClass = 'bg-success';
                      if ($status === 'Returned') $badgeClass = 'bg-primary';
                      if ($status === 'Rejected') $badgeClass = 'bg-danger';
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                  </td>

                  <td class="text-center">
                    <?php
                      $isStaff = in_array($currentRole, ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper'], true);
                    ?>

                    <!-- Always allow View for staff and treasurer (adjust as needed) -->
                    <?php if ($isStaff || $currentRole === 'Brgy Treasurer'): ?>
                      <button class="btn btn-sm btn-warning borrow-view-btn me-1" title="View" data-id="<?= (int)$br['id'] ?>">
                        <span class="material-symbols-outlined" style="font-size:12px;">visibility</span>
                      </button>
                    <?php endif; ?>

                    <!-- Pending: staff can Accept / Reject -->
                    <?php if ($status === 'Pending' && $isStaff): ?>
                      <button class="btn btn-sm btn-success borrow-accept-btn me-1" title="Accept" data-id="<?= (int)$br['id'] ?>">
                        <span class="material-symbols-outlined" style="font-size:12px;">check</span>
                      </button>
                      <button class="btn btn-sm btn-danger borrow-reject-btn" title="Reject" data-id="<?= (int)$br['id'] ?>">
                        <span class="material-symbols-outlined" style="font-size:12px;">close</span>
                      </button>

                    <!-- Borrowed: staff can Edit (change status to Returned etc.) -->
                    <?php elseif ($status === 'Borrowed' && $isStaff): ?>
                      <button class="btn btn-sm btn-primary borrow-edit-btn me-1" title="Edit" data-id="<?= (int)$br['id'] ?>" data-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>">
                        <span class="material-symbols-outlined" style="font-size:12px;">edit</span>
                      </button>

                    <!-- For other statuses (Returned / Rejected) we just show the badge (View already shown if allowed) -->
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Equipment Modal -->
  <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="POST" action="functions/equipment_add.php">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="addEquipmentLabel">Add New Equipment</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="new-equipment-name" class="form-label">Equipment Name</label>
            <input type="text" id="new-equipment-name" name="name" class="form-control" placeholder="e.g., Chairs, Tables, etc." autocomplete="off" required>
          </div>
          <div class="mb-3">
            <label for="new-equipment-desc" class="form-label">Description</label>
            <textarea id="new-equipment-desc" name="description" class="form-control" rows="2" placeholder="Briefly describe condition, brand, etc."></textarea>
          </div>
          <div class="mb-3">
            <label for="new-equipment-qty" class="form-label">Total Quantity</label>
            <input type="number" id="new-equipment-qty" name="total_qty" class="form-control" min="1" value="1" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Equipment</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Equipment Modal -->
  <div class="modal fade" id="editEquipmentModal" tabindex="-1" aria-labelledby="editEquipmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="POST" action="functions/equipment_edit.php">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="editEquipmentLabel">
            Edit Equipment
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="edit-id">

          <div class="mb-3">
            <label for="edit-name" class="form-label">Equipment Name</label>
            <input name="name" id="edit-name" type="text" class="form-control" placeholder="e.g., Chairs, Tables, etc." required>
          </div>

          <div class="mb-3">
            <label for="edit-desc" class="form-label">Description</label>
            <textarea name="description" id="edit-desc" class="form-control" rows="2" placeholder="Briefly describe condition, brand, etc."></textarea>
          </div>

          <div class="mb-3">
            <label for="edit-total" class="form-label">Total Quantity</label>
            <input name="total_qty" id="edit-total" type="number" min="1" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Equipment
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content shadow">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="deleteConfirmLabel">
            Confirm Deletion
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="confirmDeleteForm" method="POST">
          <div class="modal-body">
            <p id="confirmDeleteText" class="mb-0 fs-6 text-center fw-medium"></p>
            <input type="hidden" name="id" id="delete-id">
          </div>
          <div class="modal-footer d-flex justify-content-between px-4 pb-3">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Delete</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Add Borrow Modal -->
  <div class="modal fade" id="addBorrowModal" tabindex="-1" aria-labelledby="addBorrowLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <form class="modal-content" method="POST" action="functions/borrow_add.php">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="addBorrowLabel">New Borrow Request</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row gy-3">
            <div class="col-md-6">
              <label for="borrow-resident-name" class="form-label">Resident’s Name</label>
              <input type="text" id="borrow-resident-name" name="resident_name" class="form-control" placeholder="Lastname, Firstname M." required>
            </div>

            <div class="col-md-6 position-relative">
              <label class="form-label">Equipment SN</label> <!-- for="borrow-equipment-esn" -->
              <input type="text" id="borrowedEsn" name="equipment_sn" class="form-control" placeholder="Type or select ESN" autocomplete="off" required>
              <ul id="borrowedEsnList" class="list-group position-absolute w-100 shadow-sm bg-white" style="top:100%; left:0; max-height:150px; overflow-y:auto; display:none;">
              </ul>
            </div>

            <div class="col-md-6">
              <label for="borrow-qty" class="form-label">Quantity</label>
              <input type="number" id="borrow-qty" name="qty" class="form-control" min="1" placeholder="1" required>
            </div>
            <div class="col-md-6">
              <label for="borrow-location" class="form-label">Location</label>
              <input type="text" id="borrow-location" name="location" class="form-control" placeholder="Office / Home / Event Venue" required>
            </div>

            <div class="col-md-6">
              <label for="borrow-used-for" class="form-label">Used For</label>
              <input type="text" id="borrow-used-for" name="used_for" class="form-control" placeholder="e.g., Presentation, Workshop" required>
            </div>
            <div class="col-md-6">
              <label for="borrow-pudo" class="form-label">Pick-Up / Drop-Off</label>
              <select id="borrow-pudo" name="pudo" class="form-select" required>
                <option value="">Choose…</option>
                <option value="Pick Up">Pick Up</option>
                <option value="Drop Off">Drop Off</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Submit Request</button>
        </div>
      </form>
    </div>
  </div>

  <!-- View Borrow Modal -->
  <div class="modal fade" id="viewBorrowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header text-white" style="background-color:#13411F;">
          <h5 class="modal-title">Borrow Request Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2" id="viewBorrowFields">
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Resident</label>
              <input type="text" readonly id="vb_resident" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Equipment</label>
              <input type="text" readonly id="vb_equipment" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label fw-semibold">Quantity</label>
              <input type="text" readonly id="vb_qty" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label fw-semibold">Date</label>
              <input type="text" readonly id="vb_date" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Pick Up / Drop Off</label>
              <input type="text" readonly id="vb_pudo" class="form-control form-control-sm">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Location</label>
              <textarea readonly id="vb_location" class="form-control form-control-sm" rows="2"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Used For</label>
              <textarea readonly id="vb_usedfor" class="form-control form-control-sm" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Accept Borrow Modal -->
  <div class="modal fade" id="acceptBorrowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
      <div class="modal-content border-success">
        <form id="acceptBorrowForm">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title"><span class="material-symbols-outlined me-1">check_circle</span>Accept Borrow Request</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p id="acceptBorrowMessage">Are you sure you want to accept this borrow request?</p>
            <input type="hidden" name="id" id="acceptBorrowId" value="">
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-success">Confirm Accept</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reject Borrow Modal -->
  <div class="modal fade" id="rejectBorrowModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-md">
      <div class="modal-content border-danger">
        <form id="rejectBorrowForm">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title"><span class="material-symbols-outlined me-1">warning</span>Reject Borrow Request</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p id="rejectBorrowMessage">Please provide a reason for rejection:</p>
            <input type="hidden" name="id" id="rejectBorrowId">
            <div class="mb-3">
              <textarea name="remarks" id="rejectBorrowRemarks" class="form-control" rows="3" placeholder="Enter reason" required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-danger">Confirm Reject</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Borrow Modal -->
  <div class="modal fade" id="editBorrowModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-md">
      <div class="modal-content">
        <form id="editBorrowForm">
          <div class="modal-header text-white" style="background-color:#13411F;">
            <h5 class="modal-title"><span class="material-symbols-outlined me-1">settings</span>Edit Borrow Request</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="id" id="editBorrowId">
            <div class="mb-3">
              <label class="form-label fw-semibold">Status</label>
              <select name="status" id="editBorrowStatus" class="form-select form-select-sm" required>
                <option value="Borrowed">Borrowed</option>
                <option value="Returned">Returned</option>
              </select>
            </div>
            <div class="mb-2 text-muted small">Changing status to <strong>Returned</strong> will increment equipment available quantity.</div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>


</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const esnMap = <?= json_encode($jsMap, JSON_HEX_TAG) ?>;
    const esnOptions = Object.keys(esnMap);

    const input = document.getElementById('borrowedEsn');
    const list  = document.getElementById('borrowedEsnList');
    const qtyIn = document.getElementById('borrow-qty');

    function rebuildList(items) {
      list.innerHTML = '';
      items.forEach(esn => {
        const li = document.createElement('li');
        li.textContent = esn;
        li.className = 'list-group-item list-group-item-action py-1';
        li.style.cursor = 'pointer';
        li.addEventListener('mousedown', () => {
          input.value = esn;
          list.style.display = 'none';
          input.dispatchEvent(new Event('change'));
        });
        list.appendChild(li);
      });
      list.style.display = items.length ? 'block' : 'none';
    }

    /// show full list on focus/click
    if (input) {
      input.addEventListener('focus', () => rebuildList(esnOptions));
      input.addEventListener('click', () => rebuildList(esnOptions));

      // filter as user types
      input.addEventListener('input', () => {
        const v = input.value.trim().toLowerCase();
        const filtered = v
          ? esnOptions.filter(e => e.toLowerCase().includes(v))
          : esnOptions;
        rebuildList(filtered);
      });

      // hide after blur (small delay to catch clicks)
      input.addEventListener('blur', () => setTimeout(() => {
        list.style.display = 'none';
      }, 150));

      // cap qty on ESN change
      input.addEventListener('change', () => {
        const avail = esnMap[input.value] || 0;
        if (qtyIn) {
          qtyIn.max = avail;
          qtyIn.value = avail ? Math.min(qtyIn.value||1, avail) : '';
          qtyIn.placeholder = avail ? `(max ${avail})` : `(unknown ESN)`;
        }
      });
    }

    // Equipments search handlers
    const searchInputEquip = document.getElementById('searchInputEquip');
    const searchBtnEquip = document.getElementById('searchBtnEquip');
    const searchFormEquip = document.getElementById('searchFormEquip');
    const searchIconEquip = document.getElementById('searchIconEquip');

    if (searchBtnEquip && searchInputEquip && searchFormEquip && searchIconEquip) {
      searchBtnEquip.addEventListener('click', () => {
        if (searchInputEquip.value.trim() !== '') {
          searchInputEquip.value = '';
        }
        searchFormEquip.submit();
      });
      searchInputEquip.addEventListener('input', () => {
        searchIconEquip.textContent = searchInputEquip.value.trim() ? 'close' : 'search';
      });
    }

    // Borrow search handlers (bsearch)
    const searchInputBorrow = document.getElementById('searchInputBorrow');
    const searchBtnBorrow = document.getElementById('searchBtnBorrow');
    const searchIconBorrow = document.getElementById('searchIconBorrow');
    const searchFormBorrow = document.getElementById('searchFormBorrow');

    if (searchBtnBorrow && searchInputBorrow && searchIconBorrow && searchFormBorrow) {
      searchBtnBorrow.addEventListener('click', () => {
        if (searchInputBorrow.value.trim() !== '') {
          searchInputBorrow.value = '';
        }
        searchFormBorrow.submit();
      });
      searchInputBorrow.addEventListener('input', () => {
        searchIconBorrow.textContent = searchInputBorrow.value.trim() ? 'close' : 'search';
      });
    }

    // ---------- Borrow requests UI wiring ----------
    document.body.addEventListener('click', (evt) => {
      // VIEW
      const viewBtn = evt.target.closest('.borrow-view-btn');
      if (viewBtn) {
        const row = viewBtn.closest('tr');
        document.getElementById('vb_resident').value = row.dataset.resident || '';
        document.getElementById('vb_equipment').value = row.dataset.equipment || row.dataset.esn || '';
        document.getElementById('vb_qty').value = row.dataset.qty || '';
        document.getElementById('vb_date').value = row.dataset.date || '';
        document.getElementById('vb_pudo').value = row.dataset.pudo || '';
        document.getElementById('vb_location').value = row.dataset.location || '';
        document.getElementById('vb_usedfor').value = row.dataset.usedfor || '';
        new bootstrap.Modal(document.getElementById('viewBorrowModal')).show();
        return;
      }

      // ACCEPT (open modal)
      const acceptBtn = evt.target.closest('.borrow-accept-btn');
      if (acceptBtn) {
        const id = acceptBtn.dataset.id;
        document.getElementById('acceptBorrowId').value = id;
        const row = acceptBtn.closest('tr');
        const resName = row.dataset.resident || '';
        const eq = row.dataset.equipment || row.dataset.esn || '';
        document.getElementById('acceptBorrowMessage').textContent = `Accept borrow request by ${resName} for ${eq}? This will reduce available stock.`;
        new bootstrap.Modal(document.getElementById('acceptBorrowModal')).show();
        return;
      }

      // REJECT (open modal)
      const rejectBtn = evt.target.closest('.borrow-reject-btn');
      if (rejectBtn) {
        const id = rejectBtn.dataset.id;
        document.getElementById('rejectBorrowId').value = id;
        const row = rejectBtn.closest('tr');
        const resName = row.dataset.resident || '';
        const eq = row.dataset.equipment || row.dataset.esn || '';
        document.getElementById('rejectBorrowMessage').textContent = `Reject borrow request by ${resName} for ${eq}? Provide reason below.`;
        document.getElementById('rejectBorrowRemarks').value = '';
        new bootstrap.Modal(document.getElementById('rejectBorrowModal')).show();
        return;
      }
    });

    // Accept form submit => POST to functions/borrow_accept.php
    document.getElementById('acceptBorrowForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('acceptBorrowId').value;
      if (!id) return alert('Missing id');
      const btn = e.submitter || null;
      try {
        const fd = new FormData();
        fd.append('id', id);
        const res = await fetch('functions/borrow_accept.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await res.json();
        if (!j.success) throw new Error(j.message || 'Failed to accept');
        location.reload();
      } catch (err) {
        alert('Error: ' + (err.message || err));
      }
    });

    // Reject form submit => POST to functions/borrow_reject.php
    document.getElementById('rejectBorrowForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('rejectBorrowId').value;
      const remarks = document.getElementById('rejectBorrowRemarks').value.trim();
      if (!id) return alert('Missing id');
      if (!remarks) return alert('Please enter a reason');
      try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('remarks', remarks);
        const res = await fetch('functions/borrow_reject.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await res.json();
        if (!j.success) throw new Error(j.message || 'Failed to reject');
        location.reload();
      } catch (err) {
        alert('Error: ' + (err.message || err));
      }
    });

        // ------- EDIT button open -------
    document.body.addEventListener('click', (evt) => {
      const editBtn = evt.target.closest('.borrow-edit-btn');
      if (editBtn) {
        const id = editBtn.dataset.id;
        const status = editBtn.dataset.status || 'Borrowed';
        document.getElementById('editBorrowId').value = id;
        document.getElementById('editBorrowStatus').value = status;
        new bootstrap.Modal(document.getElementById('editBorrowModal')).show();
        return;
      }
    });

    // ------- Edit form submit (changes status via borrow_toggle_status.php) -------
    document.getElementById('editBorrowForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('editBorrowId').value;
      const status = document.getElementById('editBorrowStatus').value;
      if (!id || !status) return alert('Missing data');

      try {
        const body = new URLSearchParams();
        body.append('id', id);
        body.append('status', status);
        const res = await fetch('functions/borrow_toggle_status.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: body.toString()
        });
        const j = await res.json();
        if (j.error) throw new Error(j.error || 'Update failed');

        // update the row badge and available qty cell if available
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (row) {
          const badge = row.querySelector('td:nth-child(4) .badge'); // status col is now 4th column
          if (badge) {
            badge.className = 'badge ' + (j.newStatus === 'Borrowed' ? 'bg-success' : (j.newStatus === 'Returned' ? 'bg-primary' : 'bg-secondary'));
            badge.textContent = j.newStatus;
          }
        }
        // If server returned updated equipment availability, reflect on equipments list
        if (j.equipmentId && typeof j.availableQty !== 'undefined') {
          const eqCell = document.querySelector(`.avail-qty[data-id="${j.equipmentId}"]`);
          if (eqCell) eqCell.textContent = j.availableQty;
        }

        // hide modal and show a small alert
        bootstrap.Modal.getInstance(document.getElementById('editBorrowModal')).hide();
        const placeholder = document.getElementById('statusAlertPlaceholder');
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            Status updated to <strong>${j.newStatus}</strong>.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>`;
        placeholder.append(wrapper);
        setTimeout(() => {
          const alertNode = bootstrap.Alert.getOrCreateInstance(wrapper.querySelector('.alert'));
          alertNode.close();
        }, 3000);

      } catch (err) {
        alert('Error updating status: ' + (err.message || err));
      }
    });

  });

  // ── Delete Equipment ───────────────────────────
  document.querySelectorAll('.delete-equipment-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const name = btn.dataset.name;

      // Update confirmation text
      document.getElementById('confirmDeleteText').textContent = `Are you sure you want to delete “${name}”? This action cannot be undone.`;

      // Set hidden input value & form action
      document.getElementById('delete-id').value = id;
      document.getElementById('confirmDeleteForm').action = 'functions/equipment_delete.php';

      // Show modal
      const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
      modal.show();
    });
  });

  document.querySelectorAll('.edit-equipment-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const name = btn.dataset.name;
      const desc = btn.dataset.desc;
      const total = btn.dataset.total;

      document.getElementById('edit-id').value = id;
      document.getElementById('edit-name').value = name;
      document.getElementById('edit-desc').value = desc;
      document.getElementById('edit-total').value = total;

      // Open modal manually
      const modal = new bootstrap.Modal(document.getElementById('editEquipmentModal'));
      modal.show();
    });
  });

  // ── Status dropdown change (optional AJAX hook) ─
  document.querySelectorAll('.borrow-status').forEach(sel => {
    sel.addEventListener('change', () => {
      const id = sel.dataset.id;
      const status = sel.value;
      fetch('/functions/borrow_toggle_status.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `id=${id}&status=${encodeURIComponent(status)}`
      })
      .then(r => r.json())
      .then(j => {
        if (j.error) {
          alert('Error: ' + j.error);
          sel.value = sel.dataset.prev;
        } else {
          sel.dataset.prev = j.newStatus;
          const eqCell = document.querySelector(`.avail-qty[data-id="${j.equipmentId}"]`);
          if (eqCell) eqCell.textContent = j.availableQty;

          const placeholder = document.getElementById('statusAlertPlaceholder');
          const wrapper = document.createElement('div');
          wrapper.innerHTML = `
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              Status updated to <strong>${j.newStatus}</strong>.
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
          placeholder.append(wrapper);

           setTimeout(() => {
            const alertNode = bootstrap.Alert.getOrCreateInstance(wrapper.querySelector('.alert'));
            alertNode.close();
          }, 3000);
        }
      })
      .catch(err => {
        console.error(err);
        sel.value = sel.dataset.prev;
      });
    });
  });
</script>
<?php
$conn->close();
?>
