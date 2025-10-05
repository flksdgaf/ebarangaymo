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

// pagination for equipments list
$limit = 8;
$equip_page = max((int)($_GET['equip_page'] ?? 1), 1);
$offset = ($equip_page - 1) * $limit;

// pagination for borrow requests list
$borrow_limit = 8;
$borrow_page = max((int)($_GET['borrow_page'] ?? 1), 1);
$borrow_offset = ($borrow_page - 1) * $borrow_limit;

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

// // --- Build filtered equipment query (applies search + name filter) ---
// $equipSql = "SELECT * FROM equipment_list";
// $whereParts = [];
// $params = [];
// $types = '';

// if ($filter_name !== '') {
//   $whereParts[] = "name = ?";
//   $params[] = $filter_name;
//   $types .= 's';
// }

// if ($search !== '') {
//   $whereParts[] = "(equipment_sn LIKE ? OR name LIKE ? OR description LIKE ?)";
//   $like = '%' . $search . '%';
//   $params[] = $like;
//   $params[] = $like;
//   $params[] = $like;
//   $types .= 'sss';
// }

// if ($whereParts) {
//   $equipSql .= ' WHERE ' . implode(' AND ', $whereParts);
// }
// $equipSql .= ' ORDER BY id';

// $equipments = [];
// $eqStmt = $conn->prepare($equipSql);
// if (!$eqStmt) {
//   error_log("Prepare failed (equipSql): " . $conn->error . " -- SQL: " . $equipSql);
//   $equipments = [];
// } else {
//   $equipments = stmt_bind_execute_fetch($eqStmt, $types, $params);
//   $eqStmt->close();
// }

// --- Build filtered equipment query (applies search + name filter) ---
$equipBase = "FROM equipment_list";
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

$whereSQL = $whereParts ? ' WHERE ' . implode(' AND ', $whereParts) : '';

// 1) Get total count to compute total pages
$countSql = "SELECT COUNT(*) AS total " . $equipBase . $whereSQL;
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
  $countRows = stmt_bind_execute_fetch($countStmt, $types, $params);
  $countStmt->close();
  $totalRows = $countRows ? (int)$countRows[0]['total'] : 0;
  $totalPages = $totalRows ? (int)ceil($totalRows / $limit) : 0;
} else {
  error_log("Prepare failed (countStmt): " . $conn->error . " -- SQL: " . $countSql);
  $totalRows = 0;
  $totalPages = 0;
}

// 2) Fetch paginated rows
$equipSql = "SELECT * " . $equipBase . $whereSQL . " ORDER BY id LIMIT ? OFFSET ?";
$eqStmt = $conn->prepare($equipSql);
if (!$eqStmt) {
  error_log("Prepare failed (equipSql): " . $conn->error . " -- SQL: " . $equipSql);
  $equipments = [];
} else {
  $typesWithLimit = $types . 'ii';
  $paramsWithLimit = array_merge($params, [$limit, $offset]);
  $equipments = stmt_bind_execute_fetch($eqStmt, $typesWithLimit, $paramsWithLimit);
  $eqStmt->close();
}

// --- Build filtered borrow requests query ---
// We'll LEFT JOIN equipment_list to allow searching/filtering by equipment name
$borrowBase = "FROM borrow_requests br
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
    $borrowWhere[] = "br.borrow_date_from >= ?";
    $bParams[] = $filter_date_from;
    $bTypes .= 's';
  } else {
    error_log("Invalid filter_date_from: " . $filter_date_from);
  }
}

if ($filter_date_to !== '') {
  if (valid_date($filter_date_to)) {
    $borrowWhere[] = "br.borrow_date_to <= ?";
    $bParams[] = $filter_date_to;
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
                     OR br.borrow_date_from LIKE ? 
                     OR br.borrow_date_to LIKE ? 
                     OR br.pudo LIKE ?)";
  $blike = '%' . $bsearch . '%';
  // 9 placeholders above
  for ($i = 0; $i < 9; $i++) {
    $bParams[] = $blike;
    $bTypes .= 's';
  }
}

$borrowWhereSQL = $borrowWhere ? ' WHERE ' . implode(' AND ', $borrowWhere) : '';

// 1) Get total count for borrow requests
$borrowCountSql = "SELECT COUNT(*) AS total " . $borrowBase . $borrowWhereSQL;
$borrowCountStmt = $conn->prepare($borrowCountSql);
if ($borrowCountStmt) {
  $borrowCountRows = stmt_bind_execute_fetch($borrowCountStmt, $bTypes, $bParams);
  $borrowCountStmt->close();
  $borrowTotalRows = $borrowCountRows ? (int)$borrowCountRows[0]['total'] : 0;
  $borrowTotalPages = $borrowTotalRows ? (int)ceil($borrowTotalRows / $borrow_limit) : 0;
} else {
  error_log("Prepare failed (borrowCountStmt): " . $conn->error . " -- SQL: " . $borrowCountSql);
  $borrowTotalRows = 0;
  $borrowTotalPages = 0;
}

// 2) Fetch paginated borrow requests
$borrowSql = "SELECT br.*, IFNULL(el.name, '') AS equipment_name " . $borrowBase . $borrowWhereSQL . " ORDER BY br.borrow_date_from ASC, br.id ASC LIMIT ? OFFSET ?";
$brStmt = $conn->prepare($borrowSql);
if (!$brStmt) {
  error_log("Prepare failed (borrowSql): " . $conn->error . " -- SQL: " . $borrowSql);
  $borrows = [];
} else {
  $borrowTypesWithLimit = $bTypes . 'ii';
  $borrowParamsWithLimit = array_merge($bParams, [$borrow_limit, $borrow_offset]);
  $borrows = stmt_bind_execute_fetch($brStmt, $borrowTypesWithLimit, $borrowParamsWithLimit);
  $brStmt->close();
}
?>

<title>eBarangay Mo | Equipment Borrowing</title>

<div class="container-fluid p-3">

  <!-- Alert for add -->
  <?php if ($added): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Equipment <strong><?= htmlspecialchars($added) ?></strong> added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- Alert for updates -->
  <?php if (($_GET['updated'] ?? '') === 'none'): ?>
    <div class="alert alert-secondary alert-dismissible fade show" role="alert">
      No changes were made.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (($_GET['updated'] ?? '') === 'partial'): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      Equipment was updated, but quantity was not changed because some items are currently borrowed.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (($_GET['updated'] ?? '') === 'partial_with_borrowed'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Equipment updated successfully. Available quantity was adjusted to account for currently borrowed items.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (($_GET['updated'] ?? '') === 'full'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Equipment updated successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (($_GET['updated'] ?? '') === 'error'): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      Update failed: <?= htmlspecialchars($_GET['error_msg'] ?? 'Unknown error') ?>
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
  <?php if (($_GET['borrowed'] ?? '') !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Borrow request <strong><?= htmlspecialchars($_GET['borrowed']) ?></strong> scheduled successfully! Equipment has been reserved.
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
        Equipment List
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
                <input type="hidden" name="equip_page" value="1">

                <!-- FILTER: Equipment Name (dynamic) -->
                <div class="mb-2">
                  <label for="filter_name" class="form-label form-label-sm">Equipment Name</label>
                  <select name="filter_name" id="filter_name" class="form-select form-select-sm">
                    <option value="">All</option>
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
            <input type="hidden" name="equip_page" value="1">

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

        <div class="table-responsive admin-table"> <!-- style="height:500px;overflow-y:hidden;" style="height:500px;overflow-y:auto;"  -->
          <table class="table table-hover align-middle text-start">
            <thead class="table-light">
              <tr>
                <th>Equipment ID</th>
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

        <?php if (!empty($totalPages) && $totalPages > 1): ?>
          <?php
            $bp = [
              'page' => 'adminEquipmentBorrowing',
              'tab' => 'equipments',
              'search' => $search,
              'filter_name' => $filter_name
            ];
          ?>
          <nav class="mt-3">
            <ul class="pagination justify-content-center pagination-sm">
              <li class="page-item <?= $equip_page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($bp, ['equip_page'=>$equip_page-1])) ?>">Previous</a>
              </li>

              <?php
              $range = 2;
              $dots = false;
              for ($i = 1; $i <= $totalPages; $i++) {
                if ($i == 1 || $i == $totalPages || ($i >= $equip_page - $range && $i <= $equip_page + $range)) {
                  $active = $i == $equip_page ? 'active' : '';
                  echo "<li class='page-item {$active}'><a class='page-link' href='?" . http_build_query(array_merge($bp, ['equip_page' => $i])) . "'>$i</a></li>";
                  $dots = true;
                } elseif ($dots) {
                  echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                  $dots = false;
                }
              }
              ?>

              <li class="page-item <?= $equip_page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($bp, ['equip_page' => $equip_page + 1])) ?>">Next</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>

      </div>
    </div>

    <!-- Borrow Requests Tab Pane -->
    <div class="tab-pane fade <?= (($_GET['tab'] ?? '') === 'borrows') ? 'show active' : '' ?>" id="tab-borrows" role="tabpanel" aria-labelledby="tab-borrows-btn">
      <div class="card shadow-sm p-3">
        <div class="d-flex align-items-center mb-3">
          <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addBorrowModal">
            <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">add</span>
            Borrow an Equipment
          </button>
          
          <!-- Calendar Navigation -->
          <div class="ms-auto d-flex align-items-center">
            <!-- View Toggle -->
            <div class="btn-group me-3" role="group">
              <input type="radio" class="btn-check" name="viewType" id="weekView" value="week" checked>
              <label class="btn btn-outline-success btn-sm" for="weekView">Week</label>
              
              <input type="radio" class="btn-check" name="viewType" id="monthView" value="month">
              <label class="btn btn-outline-success btn-sm" for="monthView">Month</label>
            </div>
            
            <button id="prevPeriod" class="btn btn-sm btn-outline-success me-2">
              <span class="material-symbols-outlined" style="font-size:1rem;">chevron_left</span>
            </button>
            <h5 id="currentPeriod" class="mb-0 mx-3"></h5>
            <button id="nextPeriod" class="btn btn-sm btn-outline-success ms-2">
              <span class="material-symbols-outlined" style="font-size:1rem;">chevron_right</span>
            </button>
          </div>
        </div>

        <!-- Calendar Container -->
        <div class="table-responsive">
          <table class="table table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th class="text-center p-2" style="width: 14.28%;">Sun</th>
                <th class="text-center p-2" style="width: 14.28%;">Mon</th>
                <th class="text-center p-2" style="width: 14.28%;">Tue</th>
                <th class="text-center p-2" style="width: 14.28%;">Wed</th>
                <th class="text-center p-2" style="width: 14.28%;">Thu</th>
                <th class="text-center p-2" style="width: 14.28%;">Fri</th>
                <th class="text-center p-2" style="width: 14.28%;">Sat</th>
              </tr>
            </thead>
            <tbody id="calendarBody">
              <!-- Calendar dates will be populated by JavaScript -->
            </tbody>
          </table>
        </div>

        <!-- Legend -->
        <div class="mt-3 text-center">
          <small class="text-muted">
            <span class="badge bg-warning me-2">Pending</span>
            <span class="badge bg-primary me-2">Borrowed</span>
            <span class="badge bg-success me-2">Returned</span>
            <span class="badge bg-danger me-2">Rejected</span>
          </small>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Equipment Modal -->
  <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
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
    <div class="modal-dialog modal-lg modal-dialog-centered">
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
          <button type="submit" class="btn btn-success">Update Equipment
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="confirmDeleteForm" method="POST" action="">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="deleteConfirmLabel">Delete Equipment</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <p id="confirmDeleteText" class="mb-0 fs-6">
              Are you sure you want to delete this equipment? This action cannot be undone.
            </p>
            <input type="hidden" name="id" id="delete-id" value="">
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Delete Permanently</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Add Borrow Modal -->
  <div class="modal fade" id="addBorrowModal" tabindex="-1" aria-labelledby="addBorrowLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <form class="modal-content" method="POST" action="functions/borrow_add.php">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="addBorrowLabel">New Borrow Request</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row gy-3">
            <!-- First Name -->
            <div class="col-md-3">
              <label for="borrow-first-name" class="form-label">First Name</label>
              <input type="text" id="borrow-first-name" name="first_name" class="form-control" required>
            </div>

            <!-- Middle Name (Optional) -->
            <div class="col-md-3">
              <label for="borrow-middle-name" class="form-label">Middle Name <small class="text-muted">(optional)</small></label>
              <input type="text" id="borrow-middle-name" name="middle_name" class="form-control">
            </div>

            <!-- Last Name -->
            <div class="col-md-3">
              <label for="borrow-last-name" class="form-label">Last Name</label>
              <input type="text" id="borrow-last-name" name="last_name" class="form-control" required>
            </div>

            <!-- Suffix (Optional) -->
            <div class="col-md-3">
              <label for="borrow-suffix" class="form-label">Suffix <small class="text-muted">(optional)</small></label>
              <input type="text" id="borrow-suffix" name="suffix" class="form-control" placeholder="Jr., Sr., III...">
            </div>

            <!-- Equipment Dropdown -->
            <div class="col-md-6">
              <label class="form-label">Equipment</label>
              <select id="borrowedEquipment" name="equipment_sn" class="form-select" required>
                <option value="">Select equipment...</option>
                <?php foreach ($allEquipments as $eq): ?>
                  <option value="<?= htmlspecialchars($eq['equipment_sn'], ENT_QUOTES) ?>" 
                          data-avail="<?= (int)$eq['available_qty'] ?>">
                    <?= htmlspecialchars($eq['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <!-- muted availability text -->
              <div class="form-text text-muted" id="esnAvailableText" style="margin-top:.25rem;">
                Available: —
              </div>
            </div>

            <div class="col-md-6">
              <label for="borrow-qty" class="form-label">Quantity</label>
              <input type="number" id="borrow-qty" name="qty" class="form-control" min="1" placeholder="" required>
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

            <!-- Borrow date range -->
            <?php $today = date('Y-m-d'); ?>
            <div class="col-md-3">
              <label for="borrow-date-from" class="form-label">Borrow From</label>
              <input type="date" id="borrow-date-from" name="borrow_date_from" class="form-control" value="<?= htmlspecialchars($today, ENT_QUOTES) ?>" min="<?= htmlspecialchars($today, ENT_QUOTES) ?>" required>
            </div>

            <div class="col-md-3">
              <label for="borrow-date-to" class="form-label">Borrow To</label>
              <input type="date" id="borrow-date-to" name="borrow_date_to" class="form-control" value="<?= htmlspecialchars($today, ENT_QUOTES) ?>" min="<?= htmlspecialchars($today, ENT_QUOTES) ?>" required>
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

  <!-- Edit Borrow Modal -->
  <div class="modal fade" id="editBorrowModal" tabindex="-1" aria-labelledby="editBorrowLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="editBorrowLabel">Borrow Request Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="editBorrowId" value="">

          <dl class="row mb-0">
            <dt class="col-sm-3">Transaction ID</dt>
            <dd class="col-sm-9" id="editTransaction"></dd>

            <dt class="col-sm-3">Resident's Name</dt>
            <dd class="col-sm-9" id="editResident"></dd>

            <dt class="col-sm-3">Equipment</dt>
            <dd class="col-sm-9" id="editEquipment"></dd>

            <dt class="col-sm-3">Quantity</dt>
            <dd class="col-sm-9" id="editQty"></dd>

            <dt class="col-sm-3">Location</dt>
            <dd class="col-sm-9" id="editLocation"></dd>

            <dt class="col-sm-3">Used For</dt>
            <dd class="col-sm-9" id="editUsedFor"></dd>

            <dt class="col-sm-3">Borrow Date</dt>
            <dd class="col-sm-9" id="editDates"></dd>

            <dt class="col-sm-3">Pick-Up / Drop-Off</dt>
            <dd class="col-sm-9" id="editPudo"></dd>

            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9">
              <select id="editStatus" class="form-select form-select-sm" style="max-width: 200px;">
                <option value="Pending">Pending</option>
                <option value="Borrowed">Borrowed</option>
                <option value="Returned">Returned</option>
                <option value="Rejected">Rejected</option>
              </select>
            </dd>
          </dl>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" id="saveStatusBtn" class="btn btn-success">Save Changes</button>
        </div>
      </div>
    </div>
  </div>

  <!-- View Borrow Modal (Read-only) -->
  <div class="modal fade" id="viewBorrowModal" tabindex="-1" aria-labelledby="viewBorrowLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="viewBorrowLabel">Borrow Request Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <dl class="row mb-0">
            <dt class="col-sm-3">Transaction ID</dt>
            <dd class="col-sm-9" id="viewTransaction"></dd>

            <dt class="col-sm-3">Resident's Name</dt>
            <dd class="col-sm-9" id="viewResident"></dd>

            <dt class="col-sm-3">Equipment</dt>
            <dd class="col-sm-9" id="viewEquipment"></dd>

            <dt class="col-sm-3">Quantity</dt>
            <dd class="col-sm-9" id="viewQty"></dd>

            <dt class="col-sm-3">Location</dt>
            <dd class="col-sm-9" id="viewLocation"></dd>

            <dt class="col-sm-3">Used For</dt>
            <dd class="col-sm-9" id="viewUsedFor"></dd>

            <dt class="col-sm-3">Borrow Date</dt>
            <dd class="col-sm-9" id="viewDates"></dd>

            <dt class="col-sm-3">Pick-Up / Drop-Off</dt>
            <dd class="col-sm-9" id="viewPudo"></dd>

            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9" id="viewStatus"></dd>
          </dl>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
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
        // trim icon text to avoid whitespace mismatches
        const iconText = (searchIconEquip.textContent || '').trim();

        // Only clear when the icon explicitly shows "close" (i.e. an existing search).
        // Otherwise, perform a normal search submit (even if the user typed text).
        if (iconText === 'close') {
          searchInputEquip.value = '';
        }

        // Submit the form
        searchFormEquip.submit();
      });

      // intentionally no input listener here — icon won't flip while typing
    }


    // Borrow search handlers (bsearch)
    const searchInputBorrow = document.getElementById('searchInputBorrow');
    const searchBtnBorrow = document.getElementById('searchBtnBorrow');
    const searchIconBorrow = document.getElementById('searchIconBorrow');
    const searchFormBorrow = document.getElementById('searchFormBorrow');

    if (searchBtnBorrow && searchInputBorrow && searchIconBorrow && searchFormBorrow) {
      searchBtnBorrow.addEventListener('click', () => {
        const iconText = (searchIconBorrow.textContent || '').trim();

        // Clear only when icon is 'close' (meaning active search). Otherwise submit.
        if (iconText === 'close') {
          searchInputBorrow.value = '';
        }

        searchFormBorrow.submit();
      });

      // intentionally no input listener here either
    }

    // ── Delete Equipment ───────────────────────────
    document.querySelectorAll('.delete-equipment-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const name = btn.dataset.name;

        // Update confirmation text
        document.getElementById('confirmDeleteText').textContent = `Are you sure you want to delete “${name}” from the equipment list? This action cannot be undone.`;

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

    // Reset Add Equipment form when the modal is fully hidden
    const addEquipModalEl = document.getElementById('addEquipmentModal');
    if (addEquipModalEl) {
      const addEquipForm = addEquipModalEl.querySelector('form');
      addEquipModalEl.addEventListener('hidden.bs.modal', () => {
        if (!addEquipForm) return;
        // Reset fields to initial values
        addEquipForm.reset();

        // Clear any validation state (if you use Bootstrap validation classes)
        addEquipForm.classList.remove('was-validated');

        // Ensure the total_qty default is 1 (in case the HTML default changed)
        const qty = addEquipForm.querySelector('input[name="total_qty"]');
        if (qty) qty.value = 1;
      });
    }

    // keep selected tab in the URL so refresh restores it
    (function () {
      const tabButtons = document.querySelectorAll('#equipBorrowTabs button[data-bs-toggle="tab"]');
      if (!tabButtons.length) return;

      tabButtons.forEach(btn => {
        btn.addEventListener('shown.bs.tab', (e) => {
          // e.target is the shown tab button
          const target = e.target.getAttribute('data-bs-target') || e.target.dataset.bsTarget;
          // map target -> tab value used by server
          const tabValue = (target === '#tab-borrows') ? 'borrows' : 'equipments';

          const url = new URL(window.location.href);
          url.searchParams.set('tab', tabValue);

          // reset equipment page to 1 when switching to equipments (optional)
          if (tabValue === 'equipments') {
            url.searchParams.set('equip_page', url.searchParams.get('equip_page') || '1');
          } else if (tabValue === 'borrows') {
            url.searchParams.set('borrow_page', url.searchParams.get('borrow_page') || '1');
          }

          // replace state (no navigation)
          history.replaceState(null, '', url.toString());
        });
      });
    })();

    // Equipment dropdown handler
    const equipmentSelect = document.getElementById('borrowedEquipment');
    const borrowedqtyIn = document.getElementById('borrow-qty');
    const availText = document.getElementById('esnAvailableText');

    if (equipmentSelect && borrowedqtyIn && availText) {
      equipmentSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (!selectedOption || !selectedOption.value) {
          // No equipment selected
          availText.textContent = 'Available: —';
          borrowedqtyIn.value = '';
          borrowedqtyIn.removeAttribute('max');
          borrowedqtyIn.placeholder = '';
          return;
        }
        
        const avail = parseInt(selectedOption.getAttribute('data-avail')) || 0;
        
        // Update availability text
        availText.textContent = 'Available: ' + avail;
        
        // Set quantity constraints
        if (avail > 0) {
          borrowedqtyIn.max = avail;
          borrowedqtyIn.value = 1;
          borrowedqtyIn.placeholder = `(max ${avail})`;
        } else {
          borrowedqtyIn.value = '';
          borrowedqtyIn.max = 0;
          borrowedqtyIn.placeholder = '(unavailable)';
        }
      });
    }

    // Reset form when modal is hidden
    const addBorrowModalEl = document.getElementById('addBorrowModal');
    if (addBorrowModalEl) {
      addBorrowModalEl.addEventListener('hidden.bs.modal', () => {
        if (equipmentSelect) equipmentSelect.value = '';
        if (availText) availText.textContent = 'Available: —';
        if (borrowedqtyIn) {
          borrowedqtyIn.value = '';
          borrowedqtyIn.removeAttribute('max');
          borrowedqtyIn.placeholder = '';
        }
      });
    }

    // Borrow date range
    const fromInput = document.getElementById('borrow-date-from');
    const toInput = document.getElementById('borrow-date-to');

    if (!fromInput || !toInput) return;

    // Keep to.min in sync with from.value
    function syncToMin() {
      if (!fromInput.value) return;
      toInput.min = fromInput.value;
      // if 'to' is earlier than 'from', set it to from
      if (toInput.value && (toInput.value < fromInput.value)) {
        toInput.value = fromInput.value;
      }
    }

    // When user changes from-date, update to-date min and auto-fill if needed
    fromInput.addEventListener('change', () => {
      syncToMin();
    });

    // If to < from on page load, fix it
    syncToMin();

    // Reset Add Equipment form when the modal is fully hidden
    const addBorrowModal = document.getElementById('addBorrowModal');
    if (addBorrowModal) {
      const addEquipForm = addBorrowModal.querySelector('form');
      addBorrowModal.addEventListener('hidden.bs.modal', () => {
        if (!addEquipForm) return;
        // Reset fields to initial values
        addEquipForm.reset();
      });
    }

    // helper: simple escaped text to avoid XSS
    function escapeHtml(s) {
      if (!s) return '';
      return s.replace(/[&<>"'\/]/g, function (c) {
        return {
          '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#47;'
        }[c];
      });
    }

    // ── Wire Edit button to show full details with editable status ─────────────────────────────
    document.querySelectorAll('.borrow-edit-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const tr = btn.closest('tr');
        if (!tr) return;

        const id = tr.dataset.id;
        
        // Populate modal with all details
        document.getElementById('editBorrowId').value = id;
        document.getElementById('editTransaction').textContent = tr.dataset.transactionId || '—';
        document.getElementById('editResident').textContent = tr.dataset.resident || '—';
        document.getElementById('editEquipment').textContent = tr.dataset.equipment || tr.dataset.esn || '—';
        document.getElementById('editQty').textContent = tr.dataset.qty || '—';
        document.getElementById('editLocation').textContent = tr.dataset.location || '—';
        document.getElementById('editUsedFor').textContent = tr.dataset.usedfor || '—';
        
        // Format dates
        const from = tr.dataset.borrowFrom || '';
        const to = tr.dataset.borrowTo || '';
        document.getElementById('editDates').textContent = (from && to)
          ? (from === to ? from : from + ' — ' + to)
          : '—';
        
        document.getElementById('editPudo').textContent = tr.dataset.pudo || '—';
        
        // Set current status in dropdown
        const currentStatus = tr.dataset.status || 'Pending';
        document.getElementById('editStatus').value = currentStatus;

        // Show modal
        const modalEl = document.getElementById('editBorrowModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
      });
    });

    // Save Status button handler
    const saveStatusBtn = document.getElementById('saveStatusBtn');
    if (saveStatusBtn) {
      saveStatusBtn.addEventListener('click', async (ev) => {
        const id = document.getElementById('editBorrowId').value;
        const newStatus = document.getElementById('editStatus').value;
        
        if (!id) {
          console.error('No ID found');
          return;
        }

        // Find the borrow record in our data
        const borrowIndex = borrowsData.findIndex(b => b.id == id);
        if (borrowIndex === -1) {
          console.error('Borrow record not found in borrowsData');
          showStatusAlert('Record not found', 'danger');
          return;
        }
        
        const currentStatus = borrowsData[borrowIndex].status;
        
        // If status hasn't changed, just close modal
        if (currentStatus === newStatus) {
          const modalEl = document.getElementById('editBorrowModal');
          bootstrap.Modal.getInstance(modalEl)?.hide();
          return;
        }

        saveStatusBtn.disabled = true;
        const originalTxt = saveStatusBtn.textContent;
        saveStatusBtn.textContent = 'Saving...';

        try {
          const body = new URLSearchParams();
          body.append('id', id);
          body.append('new_status', newStatus);
          body.append('old_status', currentStatus);

          console.log('Sending request:', { id, newStatus, currentStatus }); // Debug log

          const resp = await fetch('functions/borrow_update_status.php', {
            method: 'POST',
            body: body
          });

          console.log('Response status:', resp.status); // Debug log

          const json = await resp.json();
          console.log('Response data:', json); // Debug log

          if (json && json.success) {
            // Update borrowsData array
            borrowsData[borrowIndex].status = newStatus;
            
            // Update table row if it exists (for list view)
            const tr = document.querySelector(`tr[data-id="${id}"]`);
            if (tr) {
              tr.dataset.status = newStatus;
              
              const badge = tr.querySelector('.badge');
              if (badge) {
                badge.className = 'badge';
                switch(newStatus) {
                  case 'Pending':
                    badge.classList.add('bg-warning', 'text-dark');
                    break;
                  case 'Borrowed':
                    badge.classList.add('bg-primary');
                    break;
                  case 'Returned':
                    badge.classList.add('bg-success');
                    break;
                  case 'Rejected':
                    badge.classList.add('bg-danger');
                    break;
                }
                badge.textContent = newStatus;
              }
              
              // Remove edit button if status is Returned or Rejected
              if (newStatus === 'Returned' || newStatus === 'Rejected') {
                const editBtn = tr.querySelector('.borrow-edit-btn');
                if (editBtn) editBtn.remove();
              }
            }

            // *** NEW: Update equipment availability in real-time ***
            if (json.equipment_sn && json.new_available_qty !== undefined) {
              // Update the equipment table if visible
              const equipmentRows = document.querySelectorAll('.avail-qty');
              equipmentRows.forEach(cell => {
                const row = cell.closest('tr');
                if (row) {
                  const esnCell = row.querySelector('td:first-child');
                  if (esnCell && esnCell.textContent.trim() === json.equipment_sn) {
                    // Update the availability display
                    cell.textContent = json.new_available_qty;
                    
                    // Optional: Add a brief highlight animation
                    cell.style.transition = 'background-color 0.5s';
                    cell.style.backgroundColor = '#d4edda'; // light green
                    setTimeout(() => {
                      cell.style.backgroundColor = '';
                    }, 1500);
                  }
                }
              });
              
              // Update the esnMap for borrow form validation
              if (esnMap[json.equipment_sn] !== undefined) {
                esnMap[json.equipment_sn] = json.new_available_qty;
              }
              
              // Update the equipment dropdown in Add Borrow modal
              const equipSelect = document.getElementById('borrowedEquipment');
              if (equipSelect) {
                const option = equipSelect.querySelector(`option[value="${json.equipment_sn}"]`);
                if (option) {
                  option.setAttribute('data-avail', json.new_available_qty);
                }
              }
            }

            // Refresh calendar to show updated status
            updateCalendar();

            // Hide modal
            const modalEl = document.getElementById('editBorrowModal');
            bootstrap.Modal.getInstance(modalEl)?.hide();

            showStatusAlert('Status updated successfully', 'success');
          } else {
            const err = (json && json.error) ? json.error : 'Failed to update status';
            console.error('Update failed:', err);
            showStatusAlert(err, 'danger');
          }

        } catch (err) {
          console.error('Fetch error:', err);
          showStatusAlert(err.message || 'Request failed', 'danger');
        } finally {
          saveStatusBtn.disabled = false;
          saveStatusBtn.textContent = originalTxt;
        }
      });
    }

    // helper: show a bootstrap alert in #statusAlertPlaceholder
    function showStatusAlert(message, type='success', timeout=4000) {
      const placeholder = document.getElementById('statusAlertPlaceholder');
      if (!placeholder) return;
      const id = 'alert-' + Math.random().toString(36).slice(2,9);
      const wrapper = document.createElement('div');
      wrapper.innerHTML = `
        <div id="${id}" class="alert alert-${type} alert-dismissible fade show" role="alert">
          ${escapeHtml(message)}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
      placeholder.appendChild(wrapper.firstElementChild);
      if (timeout > 0) {
        setTimeout(() => {
          const el = document.getElementById(id);
          if (el) {
            el.classList.remove('show');
            el.classList.add('hide');
            // remove after fade
            setTimeout(() => el.remove(), 300);
          }
        }, timeout);
      }
    }

    // Calendar functionality
    let currentDate = new Date();
    let currentView = 'week'; // Default to week view
    let borrowsData = <?= json_encode($borrows, JSON_HEX_TAG) ?>;

    function formatDateForDisplay(dateStr) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function getWeekStart(date) {
      const d = new Date(date);
      const day = d.getDay();
      const diff = d.getDate() - day;
      return new Date(d.setDate(diff));
    }

    function getWeekEnd(date) {
      const d = new Date(date);
      const day = d.getDay();
      const diff = d.getDate() - day + 6;
      return new Date(d.setDate(diff));
    }

    function generateWeekView(date) {
      const weekStart = getWeekStart(new Date(date));
      const weekEnd = getWeekEnd(new Date(date));
      
      document.getElementById('currentPeriod').textContent = 
        `${formatDateForDisplay(weekStart.toISOString().split('T')[0])} - ${formatDateForDisplay(weekEnd.toISOString().split('T')[0])}`;
      
      const calendarBody = document.getElementById('calendarBody');
      calendarBody.innerHTML = '';
      
      // Create single row for week view
      const row = document.createElement('tr');
      
      for (let i = 0; i < 7; i++) {
        const currentDay = new Date(weekStart);
        currentDay.setDate(weekStart.getDate() + i);
        
        const cell = document.createElement('td');
        cell.className = 'p-2 align-top border';
        cell.style.height = '200px'; // Increased height for detailed cards
        cell.style.minWidth = '120px';
        
        const today = new Date();
        const isToday = (
          currentDay.getFullYear() === today.getFullYear() &&
          currentDay.getMonth() === today.getMonth() &&
          currentDay.getDate() === today.getDate()
        );
        
        const dateDiv = document.createElement('div');
        if (isToday) {
          dateDiv.className = 'fw-bold text-white bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-1';
          dateDiv.style.width = '24px';
          dateDiv.style.height = '24px';
          dateDiv.style.fontSize = '0.8rem';
        } else {
          dateDiv.className = 'fw-bold mb-1';
          dateDiv.style.fontSize = '0.9rem';
        }
        dateDiv.textContent = currentDay.getDate();
        cell.appendChild(dateDiv);
        
        // Add day name
        const dayNameDiv = document.createElement('div');
        dayNameDiv.className = 'text-muted mb-1';
        dayNameDiv.style.fontSize = '0.7rem';
        dayNameDiv.textContent = currentDay.toLocaleDateString('en-US', { weekday: 'short' });
        cell.appendChild(dayNameDiv);
        
        // Add borrow events for this date - use local date string to avoid timezone issues
        const year = currentDay.getFullYear();
        const month = String(currentDay.getMonth() + 1).padStart(2, '0');
        const day = String(currentDay.getDate()).padStart(2, '0');
        const currentDateStr = `${year}-${month}-${day}`;
        addBorrowEvents(cell, currentDateStr);
        
        row.appendChild(cell);
      }
      
      calendarBody.appendChild(row);
    }

    function generateMonthView(year, month) {
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const daysInMonth = lastDay.getDate();
      const startingDayOfWeek = firstDay.getDay();
      
      const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
      
      document.getElementById('currentPeriod').textContent = `${monthNames[month]} ${year}`;
      
      const calendarBody = document.getElementById('calendarBody');
      calendarBody.innerHTML = '';
      
      let date = 1;
      
      // Create 6 rows (weeks) for the calendar
      for (let week = 0; week < 6; week++) {
        const row = document.createElement('tr');
        
        // Create 7 days for each week
        for (let day = 0; day < 7; day++) {
          const cell = document.createElement('td');
          cell.className = 'p-1 align-top border';
          cell.style.height = '120px';
          cell.style.minWidth = '120px';
          
          if (week === 0 && day < startingDayOfWeek) {
            // Previous month's days
            const prevMonth = month === 0 ? 11 : month - 1;
            const prevYear = month === 0 ? year - 1 : year;
            const prevMonthLastDay = new Date(prevYear, prevMonth + 1, 0).getDate();
            const prevDate = prevMonthLastDay - startingDayOfWeek + day + 1;
            
            const dateDiv = document.createElement('div');
            dateDiv.className = 'fw-bold text-muted mb-1';
            dateDiv.style.fontSize = '0.9rem';
            dateDiv.textContent = prevDate;
            cell.appendChild(dateDiv);
          } else if (date > daysInMonth) {
            // Next month's days
            const nextDate = date - daysInMonth;
            const dateDiv = document.createElement('div');
            dateDiv.className = 'fw-bold text-muted mb-1';
            dateDiv.style.fontSize = '0.9rem';
            dateDiv.textContent = nextDate;
            cell.appendChild(dateDiv);
            date++;
          } else {
            // Current month's days
            const today = new Date();
            const isToday = (year === today.getFullYear() && month === today.getMonth() && date === today.getDate());
            
            const dateDiv = document.createElement('div');
            if (isToday) {
              dateDiv.className = 'fw-bold text-white bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-1';
              dateDiv.style.width = '24px';
              dateDiv.style.height = '24px';
              dateDiv.style.fontSize = '0.8rem';
            } else {
              dateDiv.className = 'fw-bold mb-1';
              dateDiv.style.fontSize = '0.9rem';
            }
            dateDiv.textContent = date;
            cell.appendChild(dateDiv);
            
            // Add borrow events for this date
            const currentDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
            addBorrowEvents(cell, currentDateStr);
            
            date++;
          }
          
          row.appendChild(cell);
        }
        
        calendarBody.appendChild(row);
        
        // Break if we've filled all days of the month and don't need more weeks
        if (date > daysInMonth) break;
      }
    }

    function addBorrowEvents(cell, dateStr) {
      const dayEvents = borrowsData.filter(borrow => {
        const fromDate = borrow.borrow_date_from;
        const toDate = borrow.borrow_date_to;
        return dateStr >= fromDate && dateStr <= toDate;
      });
      
      if (currentView === 'week') {
        // Week view - show simplified cards
        dayEvents.forEach(event => {
          const eventCard = document.createElement('div');
          eventCard.className = 'card mb-2';
          eventCard.style.fontSize = '0.75rem';
          eventCard.style.cursor = 'pointer';
          
          const status = (event.status || 'pending').toLowerCase();
          let statusClass = 'bg-secondary';
          switch(status) {
            case 'pending': statusClass = 'bg-warning text-dark'; break;
            case 'borrowed': statusClass = 'bg-primary'; break;
            case 'returned': statusClass = 'bg-success'; break;
            case 'rejected': statusClass = 'bg-danger'; break;
          }
          
          eventCard.innerHTML = `
            <div class="card-body p-2">
              <div class="mb-2">
                <div class="badge ${statusClass} d-flex justify-content-between align-items-center w-100" style="font-size: 0.65rem;">
                  <span>${event.equipment_name || event.equipment_sn || 'Unknown'}</span>
                  <span>${event.qty}pcs</span>
                </div>
              </div>
              <div class="mb-1" style="font-size: 0.7rem; font-weight: 500;">
                ${event.transaction_id || '—'}
              </div>
              <div class="mb-2" style="font-size: 0.7rem;">
                ${event.resident_name || '—'}
              </div>
              <div class="d-flex justify-content-end">
                ${event.status === 'Returned' || event.status === 'Rejected' ? `
                  <button class="btn btn-info btn-sm view-event-btn" style="font-size: 0.6rem; padding: 2px 6px;" data-id="${event.id}">
                    <span class="material-symbols-outlined" style="font-size: 0.7rem;">visibility</span>
                  </button>
                ` : `
                  <button class="btn btn-secondary btn-sm edit-event-btn" style="font-size: 0.6rem; padding: 2px 6px;" data-id="${event.id}">
                    <span class="material-symbols-outlined" style="font-size: 0.7rem;">edit</span>
                  </button>
                `}
              </div>
            </div>
          `;
          
          cell.appendChild(eventCard);
        });
        
      } else {
        // Month view - show compact list with status-colored backgrounds
        const visibleEvents = dayEvents.slice(0, 3);
        const hiddenEvents = dayEvents.slice(3);
        
        visibleEvents.forEach(event => {
          const eventDiv = document.createElement('div');
          eventDiv.className = 'mb-1 p-1 rounded';
          eventDiv.style.fontSize = '0.65rem';
          eventDiv.style.cursor = 'pointer';
          
          const status = (event.status || 'pending').toLowerCase();
          let backgroundColor = '#6c757d'; // secondary/default
          let textColor = 'white';
          
          switch(status) {
            case 'pending': 
              backgroundColor = '#ffc107'; // warning yellow
              textColor = 'black';
              break;
            case 'borrowed': 
              backgroundColor = '#0d6efd'; // primary blue
              textColor = 'white';
              break;
            case 'returned': 
              backgroundColor = '#198754'; // success green
              textColor = 'white';
              break;
            case 'rejected': 
              backgroundColor = '#dc3545'; // danger red
              textColor = 'white';
              break;
          }
          
          eventDiv.style.backgroundColor = backgroundColor;
          eventDiv.style.color = textColor;
          
          eventDiv.innerHTML = `
            <div class="fw-bold" style="opacity: 0.8; font-size: 0.6rem;">${event.transaction_id || 'No ID'}</div>
            <div class="fw-bold">${event.resident_name || 'Unknown'}</div>
            <div style="opacity: 0.9;">${event.equipment_name || event.equipment_sn || 'Unknown'} (${event.qty}pcs)</div>
          `;
          
          eventDiv.addEventListener('click', () => {
            const isViewOnly = event.status === 'Returned' || event.status === 'Rejected';
            
            if (isViewOnly) {
              // Populate view-only modal
              document.getElementById('viewTransaction').textContent = event.transaction_id || '—';
              document.getElementById('viewResident').textContent = event.resident_name || '—';
              document.getElementById('viewEquipment').textContent = event.equipment_name || event.equipment_sn || '—';
              document.getElementById('viewQty').textContent = event.qty || '—';
              document.getElementById('viewLocation').textContent = event.location || '—';
              document.getElementById('viewUsedFor').textContent = event.used_for || '—';
              
              const from = event.borrow_date_from || '';
              const to = event.borrow_date_to || '';
              document.getElementById('viewDates').textContent = (from && to)
                ? (from === to ? formatDateForDisplay(from) : formatDateForDisplay(from) + ' — ' + formatDateForDisplay(to))
                : '—';
              
              document.getElementById('viewPudo').textContent = event.pudo || '—';
              document.getElementById('viewStatus').textContent = event.status || 'Pending';
              
              // Show view-only modal
              const modal = new bootstrap.Modal(document.getElementById('viewBorrowModal'));
              modal.show();
            } else {
              // Populate editable modal
              document.getElementById('editBorrowId').value = event.id;
              document.getElementById('editTransaction').textContent = event.transaction_id || '—';
              document.getElementById('editResident').textContent = event.resident_name || '—';
              document.getElementById('editEquipment').textContent = event.equipment_name || event.equipment_sn || '—';
              document.getElementById('editQty').textContent = event.qty || '—';
              document.getElementById('editLocation').textContent = event.location || '—';
              document.getElementById('editUsedFor').textContent = event.used_for || '—';
              
              const from = event.borrow_date_from || '';
              const to = event.borrow_date_to || '';
              document.getElementById('editDates').textContent = (from && to)
                ? (from === to ? formatDateForDisplay(from) : formatDateForDisplay(from) + ' — ' + formatDateForDisplay(to))
                : '—';
              
              document.getElementById('editPudo').textContent = event.pudo || '—';
              document.getElementById('editStatus').value = event.status || 'Pending';
              
              // Show edit modal
              const modal = new bootstrap.Modal(document.getElementById('editBorrowModal'));
              modal.show();
            }
          });
          cell.appendChild(eventDiv);
        });
        
        // Add "..." dropdown for additional events
        if (hiddenEvents.length > 0) {
          const moreDiv = document.createElement('div');
          moreDiv.className = 'dropdown d-flex justify-content-end';  // Added d-flex and justify-content-end
          moreDiv.innerHTML = `
            <button class="btn btn-link p-0 text-muted dropdown-toggle" type="button" data-bs-toggle="dropdown" style="font-size: 0.7rem; text-decoration: none;">
              ...
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="font-size: 0.65rem; min-width: 180px;">
              ${hiddenEvents.map(event => {
                // Determine status color
                const status = (event.status || 'pending').toLowerCase();
                let bgColor = '#6c757d'; // secondary/default
                let textColor = 'white';
                
                switch(status) {
                  case 'pending': 
                    bgColor = '#ffc107'; // warning yellow
                    textColor = 'black';
                    break;
                  case 'borrowed': 
                    bgColor = '#0d6efd'; // primary blue
                    textColor = 'white';
                    break;
                  case 'returned': 
                    bgColor = '#198754'; // success green
                    textColor = 'white';
                    break;
                  case 'rejected': 
                    bgColor = '#dc3545'; // danger red
                    textColor = 'white';
                    break;
                }
                
                return `
                  <li><a class="dropdown-item show-details-item" href="#" data-event='${JSON.stringify(event)}' style="background-color: ${bgColor}; color: ${textColor}; margin-bottom: 2px;">
                    <div class="fw-bold">${event.resident_name || 'Unknown'}</div>
                    <div style="opacity: 0.9;">${event.equipment_name || event.equipment_sn || 'Unknown'} (${event.qty}pcs)</div>
                  </a></li>
                `;
              }).join('')}
            </ul>
          `;
          
          // Add click handlers for dropdown items
          const dropdownItems = moreDiv.querySelectorAll('.show-details-item');
          dropdownItems.forEach(item => {
            item.addEventListener('click', (e) => {
              e.preventDefault();
              const eventData = JSON.parse(item.dataset.event);
              
              const isViewOnly = eventData.status === 'Returned' || eventData.status === 'Rejected';
              
              if (isViewOnly) {
                // Populate view-only modal
                document.getElementById('viewTransaction').textContent = eventData.transaction_id || '—';
                document.getElementById('viewResident').textContent = eventData.resident_name || '—';
                document.getElementById('viewEquipment').textContent = eventData.equipment_name || eventData.equipment_sn || '—';
                document.getElementById('viewQty').textContent = eventData.qty || '—';
                document.getElementById('viewLocation').textContent = eventData.location || '—';
                document.getElementById('viewUsedFor').textContent = eventData.used_for || '—';
                
                const from = eventData.borrow_date_from || '';
                const to = eventData.borrow_date_to || '';
                document.getElementById('viewDates').textContent = (from && to)
                  ? (from === to ? formatDateForDisplay(from) : formatDateForDisplay(from) + ' — ' + formatDateForDisplay(to))
                  : '—';
                
                document.getElementById('viewPudo').textContent = eventData.pudo || '—';
                document.getElementById('viewStatus').textContent = eventData.status || 'Pending';
                
                // Show view-only modal
                const modal = new bootstrap.Modal(document.getElementById('viewBorrowModal'));
                modal.show();
              } else {
                // Populate editable modal
                document.getElementById('editBorrowId').value = eventData.id;
                document.getElementById('editTransaction').textContent = eventData.transaction_id || '—';
                document.getElementById('editResident').textContent = eventData.resident_name || '—';
                document.getElementById('editEquipment').textContent = eventData.equipment_name || eventData.equipment_sn || '—';
                document.getElementById('editQty').textContent = eventData.qty || '—';
                document.getElementById('editLocation').textContent = eventData.location || '—';
                document.getElementById('editUsedFor').textContent = eventData.used_for || '—';
                
                const from = eventData.borrow_date_from || '';
                const to = eventData.borrow_date_to || '';
                document.getElementById('editDates').textContent = (from && to)
                  ? (from === to ? formatDateForDisplay(from) : formatDateForDisplay(from) + ' — ' + formatDateForDisplay(to))
                  : '—';
                
                document.getElementById('editPudo').textContent = eventData.pudo || '—';
                document.getElementById('editStatus').value = eventData.status || 'Pending';
                
                // Show edit modal
                const modal = new bootstrap.Modal(document.getElementById('editBorrowModal'));
                modal.show();
              }
            });
          });
          
          cell.appendChild(moreDiv);
        }
      }
    }

    function updateCalendar() {
      if (currentView === 'week') {
        generateWeekView(currentDate);
      } else {
        generateMonthView(currentDate.getFullYear(), currentDate.getMonth());
      }
    }

    // Event delegation for dynamically created edit and view buttons
    document.addEventListener('click', function(e) {
      // Handle edit button
      if (e.target.closest('.edit-event-btn')) {
        const btn = e.target.closest('.edit-event-btn');
        const eventId = btn.dataset.id;
        const event = borrowsData.find(b => b.id == eventId);
        if (event) {
          // Populate edit modal with event data
          document.getElementById('editBorrowId').value = event.id;
          document.getElementById('editTransaction').textContent = event.transaction_id || '—';
          document.getElementById('editResident').textContent = event.resident_name || '—';
          document.getElementById('editEquipment').textContent = event.equipment_name || event.equipment_sn || '—';
          document.getElementById('editQty').textContent = event.qty || '—';
          document.getElementById('editLocation').textContent = event.location || '—';
          document.getElementById('editUsedFor').textContent = event.used_for || '—';
          
          const from = event.borrow_date_from || '';
          const to = event.borrow_date_to || '';
          document.getElementById('editDates').textContent = (from && to)
            ? (from === to ? formatDateForDisplay(from) : formatDateForDisplay(from) + ' — ' + formatDateForDisplay(to))
            : '—';
          
          document.getElementById('editPudo').textContent = event.pudo || '—';
          document.getElementById('editStatus').value = event.status || 'Pending';
          
          // Show modal
          const modal = new bootstrap.Modal(document.getElementById('editBorrowModal'));
          modal.show();
        }
      }
      
      // Handle view button
      if (e.target.closest('.view-event-btn')) {
        const btn = e.target.closest('.view-event-btn');
        const eventId = btn.dataset.id;
        const event = borrowsData.find(b => b.id == eventId);
        if (event) {
          // Populate view-only modal with event data
          document.getElementById('viewTransaction').textContent = event.transaction_id || '—';
          document.getElementById('viewResident').textContent = event.resident_name || '—';
          document.getElementById('viewEquipment').textContent = event.equipment_name || event.equipment_sn || '—';
          document.getElementById('viewQty').textContent = event.qty || '—';
          document.getElementById('viewLocation').textContent = event.location || '—';
          document.getElementById('viewUsedFor').textContent = event.used_for || '—';
          
          const from = event.borrow_date_from || '';
          const to = event.borrow_date_to || '';
          document.getElementById('viewDates').textContent = (from && to)
            ? (from === to ? formatDateForDisplay(from) : formatDateForDisplay(from) + ' — ' + formatDateForDisplay(to))
            : '—';
          
          document.getElementById('viewPudo').textContent = event.pudo || '—';
          document.getElementById('viewStatus').textContent = event.status || 'Pending';
          
          // Show view-only modal
          const modal = new bootstrap.Modal(document.getElementById('viewBorrowModal'));
          modal.show();
        }
      }
    });

    // View type change handlers
    document.getElementById('weekView').addEventListener('change', function() {
      if (this.checked) {
        currentView = 'week';
        updateCalendar();
      }
    });

    document.getElementById('monthView').addEventListener('change', function() {
      if (this.checked) {
        currentView = 'month';
        updateCalendar();
      }
    });

    // Navigation handlers
    document.getElementById('prevPeriod').addEventListener('click', () => {
      if (currentView === 'week') {
        currentDate.setDate(currentDate.getDate() - 7);
      } else {
        currentDate.setMonth(currentDate.getMonth() - 1);
      }
      updateCalendar();
    });

    document.getElementById('nextPeriod').addEventListener('click', () => {
      if (currentView === 'week') {
        currentDate.setDate(currentDate.getDate() + 7);
      } else {
        currentDate.setMonth(currentDate.getMonth() + 1);
      }
      updateCalendar();
    });

    // Initialize calendar when borrow requests tab is shown
    document.getElementById('tab-borrows-btn').addEventListener('click', () => {
      setTimeout(() => {
        updateCalendar();
      }, 100);
    });

    // Initialize calendar if borrow requests tab is active on page load
    if ((<?= json_encode($_GET['tab'] ?? '') ?>) === 'borrows') {
      updateCalendar();
    }
  });
</script>
<?php
$conn->close();
?>
