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
  <?php if (($_GET['borrowed'] ?? '') !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Borrow request <strong><?= htmlspecialchars($_GET['borrowed']) ?></strong> submitted successfully!
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
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdownBorrow" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">filter_list</span>
              Filter
            </button>
            <div class="dropdown-menu p-3" aria-labelledby="filterDropdownBorrow" style="min-width:320px; --bs-body-font-size:.75rem; font-size:.75rem;">
              <form method="get" class="mb-0" id="filterFormBorrow">
                <input type="hidden" name="page" value="adminEquipmentBorrowing">
                <input type="hidden" name="borrow_page" value="1">
                <input type="hidden" name="tab" value="borrows">

                <!-- FILTER: Equipment (show name + esn) -->
                <div class="mb-2">
                  <label for="filter_esn" class="form-label form-label-sm">Equipment Name</label>
                  <select name="filter_esn" id="filter_esn" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($allEquipments as $ae): ?>
                      <option value="<?= htmlspecialchars($ae['equipment_sn'], ENT_QUOTES) ?>" <?= $filter_esn === $ae['equipment_sn'] ? 'selected' : ''?>>
                        <?= htmlspecialchars($ae['name']) ?> (<?= htmlspecialchars($ae['equipment_sn']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- FILTER: Date range -->
                <div class="mb-2">
                  <label class="form-label form-label-sm">Date Request Range</label>
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
            <input type="hidden" name="borrow_page" value="1">

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
        <div class="table-responsive admin-table"> <!-- style="height:500px;overflow-y:auto;" -->
          <table class="table table-hover align-middle text-start">
            <thead class="table-light">
              <tr>
                <th>Transaction ID</th>
                <th>Resident</th>
                <th>Equipment</th>
                <th>Quantity</th>
                <th>Borrow Date</th>
                <th>Status</th>
                <th class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($borrows)): ?>
                <tr><td colspan="7" class="text-center">No borrow requests found.</td></tr>
              <?php else: foreach($borrows as $br):
                $status = $br['status'] ?? 'Pending';
              ?>
                <tr
                  data-id="<?= (int)$br['id'] ?>"
                  data-transaction-id="<?= htmlspecialchars($br['transaction_id'], ENT_QUOTES) ?>"
                  data-resident="<?= htmlspecialchars($br['resident_name'], ENT_QUOTES) ?>"
                  data-esn="<?= htmlspecialchars($br['equipment_sn'], ENT_QUOTES) ?>"
                  data-equipment="<?= htmlspecialchars($br['equipment_name'], ENT_QUOTES) ?>"
                  data-qty="<?= (int)$br['qty'] ?>"
                  data-location="<?= htmlspecialchars($br['location'], ENT_QUOTES) ?>"
                  data-usedfor="<?= htmlspecialchars($br['used_for'], ENT_QUOTES) ?>"
                  data-borrow-from="<?= htmlspecialchars($br['borrow_date_from'] ?? '', ENT_QUOTES) ?>"
                  data-borrow-to="<?= htmlspecialchars($br['borrow_date_to'] ?? '', ENT_QUOTES) ?>"
                  data-pudo="<?= htmlspecialchars($br['pudo'], ENT_QUOTES) ?>"
                  data-status="<?= htmlspecialchars($br['status'], ENT_QUOTES) ?>"
                >
                  <td><?= htmlspecialchars($br['transaction_id']) ?></td>
                  <td><?= htmlspecialchars($br['resident_name']) ?></td>
                  <td><?= htmlspecialchars($br['equipment_name'] ?: $br['equipment_sn']) ?></td>
                  <td><?= (int)$br['qty'] ?></td>
                  <?php
                    // display a friendly range: if from == to show single date, otherwise show "from — to"
                    $from = $br['borrow_date_from'] ?? '';
                    $to = $br['borrow_date_to'] ?? '';
                    if ($from && $to) {
                      $displayDate = ($from === $to) ? $from : ($from . ' — ' . $to);
                    } else {
                      // fallback to empty or any pre-existing single-date field if present
                      $displayDate = htmlspecialchars($br['borrow_date_from'] ?? $br['borrow_date'] ?? '');
                    }
                  ?>
                  <td><?= htmlspecialchars($displayDate) ?></td>
                  <td>
                    <?php
                      // compute badge class
                      $badgeClass = 'bg-secondary';
                      if ($status === 'Pending') $badgeClass = 'bg-warning';
                      if ($status === 'Borrowed') $badgeClass = 'bg-primary';
                      if ($status === 'Returned') $badgeClass = 'bg-success';
                      if ($status === 'Rejected') $badgeClass = 'bg-danger';
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                  </td>

                  <td class="text-center">
                    <?php
                      $isStaff = in_array($currentRole, ['Brgy Captain','Brgy Secretary','Brgy Bookkeeper'], true);
                    ?>

                    <!-- Always allow View for staff and treasurer (adjust as needed) -->
                    <?php if ($isStaff): ?>
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

        <?php if (!empty($borrowTotalPages) && $borrowTotalPages > 1): ?>
          <?php
            $bbp = [
              'page' => 'adminEquipmentBorrowing',
              'tab' => 'borrows',
              'bsearch' => $bsearch,
              'filter_esn' => $filter_esn,
              'filter_date_from' => $filter_date_from,
              'filter_date_to' => $filter_date_to
            ];
          ?>
          <nav class="mt-3">
            <ul class="pagination justify-content-center pagination-sm">
              <li class="page-item <?= $borrow_page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($bbp, ['borrow_page'=>$borrow_page-1])) ?>">Previous</a>
              </li>

              <?php
              $range = 2;
              $dots = false;
              for ($i = 1; $i <= $borrowTotalPages; $i++) {
                if ($i == 1 || $i == $borrowTotalPages || ($i >= $borrow_page - $range && $i <= $borrow_page + $range)) {
                  $active = $i == $borrow_page ? 'active' : '';
                  echo "<li class='page-item {$active}'><a class='page-link' href='?" . http_build_query(array_merge($bbp, ['borrow_page' => $i])) . "'>$i</a></li>";
                  $dots = true;
                } elseif ($dots) {
                  echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                  $dots = false;
                }
              }
              ?>

              <li class="page-item <?= $borrow_page >= $borrowTotalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($bbp, ['borrow_page' => $borrow_page + 1])) ?>">Next</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>

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
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <form class="modal-content" method="POST" action="functions/borrow_add.php">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="addBorrowLabel">New Borrow Request</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row gy-3">
            <div class="col-md-6">
              <label for="borrow-resident-name" class="form-label">Name</label>
              <input type="text" id="borrow-resident-name" name="resident_name" class="form-control" placeholder="Lastname, Firstname M." required>
            </div>

            <!-- VISIBLE: Equipment NAME (for user) -->
            <div class="col-md-6 position-relative">
              <label class="form-label">Equipment</label>
              <input type="text" id="borrowedEquipment" class="form-control" placeholder="Type or select equipment name" autocomplete="off" required>
              <input type="hidden" id="borrowedEsn" name="equipment_sn" value="">
              <ul id="borrowedEquipmentList" class="list-group position-absolute w-100 shadow-sm bg-white" style="top:100%; left:0; max-height:180px; overflow-y:auto; display:none; z-index:1050;"></ul>

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
            <div class="col-md-6">
              <label for="borrow-date-from" class="form-label">Borrow From</label>
              <input type="date" id="borrow-date-from" name="borrow_date_from" class="form-control" value="<?= htmlspecialchars($today, ENT_QUOTES) ?>" min="<?= htmlspecialchars($today, ENT_QUOTES) ?>" required>
            </div>

            <div class="col-md-6">
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

  <!-- Borrow View Modal -->
  <div class="modal fade" id="viewBorrowModal" tabindex="-1" aria-labelledby="viewBorrowLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="viewBorrowLabel">Borrow Request Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <dl class="row mb-0">
            <dt class="col-sm-4">Transaction ID</dt>
            <dd class="col-sm-8" id="viewTransaction"></dd>

            <dt class="col-sm-4">Resident’s Name</dt>
            <dd class="col-sm-8" id="viewResident"></dd>

            <dt class="col-sm-4">Equipment</dt>
            <dd class="col-sm-8" id="viewEquipment"></dd>

            <dt class="col-sm-4">Quantity</dt>
            <dd class="col-sm-8" id="viewQty"></dd>

            <dt class="col-sm-4">Location</dt>
            <dd class="col-sm-8" id="viewLocation"></dd>

            <dt class="col-sm-4">Used For</dt>
            <dd class="col-sm-8" id="viewUsedFor"></dd>

            <dt class="col-sm-4">Borrow Date</dt>
            <dd class="col-sm-8" id="viewDates"></dd>

            <dt class="col-sm-4">Pick-Up / Drop-Off</dt>
            <dd class="col-sm-8" id="viewPudo"></dd>

            <dt class="col-sm-4">Status</dt>
            <dd class="col-sm-8" id="viewStatus"></dd>
          </dl>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Accept Borrow Modal -->
  <div class="modal fade" id="acceptBorrowModal" tabindex="-1" aria-labelledby="acceptBorrowLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="acceptBorrowLabel">Accept Borrow Request</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">
            Are you sure you want to <strong>accept</strong> this borrow request?<br>
            <small class="text-muted">Transaction ID: <span id="acceptTransactionId"></span></small>
          </p>

          <!-- hidden field to store borrow_request id (server id) -->
          <input type="hidden" id="acceptBorrowId" value="">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="confirmAcceptBtn">Accept</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Reject Borrow Modal -->
  <div class="modal fade" id="rejectBorrowModal" tabindex="-1" aria-labelledby="rejectBorrowLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="rejectBorrowLabel">Reject Borrow Request</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>
            You are rejecting request <small class="text-muted">(Transaction ID: <span id="rejectTransactionId"></span>)</small>
          </p>

          <!-- hidden server-side borrow id -->
          <input type="hidden" id="rejectBorrowId" value="">

          <div class="mb-3">
            <label for="rejectReason" class="form-label">Rejection Details</label>
            <textarea id="rejectReason" class="form-control" rows="3" placeholder="Explain the reason for rejecting this request..." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="confirmRejectBtn">Reject Request</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Borrow Modal -->
  <div class="modal fade" id="editBorrowModal" tabindex="-1" aria-labelledby="editBorrowLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="editBorrowLabel">Edit Borrow Request</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <!-- store id -->
          <input type="hidden" id="editBorrowId" value="">

          <!-- friendly info text -->
          <p id="editBorrowInfo" class="mb-3"></p>

          
        </div>

        <div class="modal-footer">
          <!-- Mark as returned button inside the body (as you requested) -->
          <div class="d-grid">
            <button type="button" id="markReturnedBtn" class="btn btn-success">Mark as Returned</button>
          </div>
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

    // supplies: server-side array of equipment objects (id, equipment_sn, name, available_qty, total_qty, description)
    const equipments = <?= json_encode($allEquipments, JSON_HEX_TAG) ?>;

    // DOM elements
    const visibleInput = document.getElementById('borrowedEquipment');
    const hiddenEsn = document.getElementById('borrowedEsn'); // this is the value submitted to server
    const borrowedList = document.getElementById('borrowedEquipmentList');
    const borrowedqtyIn = document.getElementById('borrow-qty');
    const availText = document.getElementById('esnAvailableText');

    // helper: show friendly availability text
    function setAvailText(n) {
      if (n === null || n === undefined || n === '') {
        availText.textContent = 'Available: —';
      } else {
        availText.textContent = 'Available: ' + (Number.isInteger(n) ? n : n);
      }
    }

    // build a filtered list of equipment entries and render as clickable items
    function rebuildList(items) {
      borrowedList.innerHTML = '';
      items.forEach(item => {
        const li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action py-1';
        li.style.cursor = 'pointer';
        // show name and ESN, click will store ESN and show name
        li.textContent = item.name; //+ ' (' + item.equipment_sn + ')';
        li.addEventListener('mousedown', (ev) => {
          // set visible to NAME (the user requested the NAME to show)
          visibleInput.value = item.name;
          // store ESN in hidden input for form submit
          hiddenEsn.value = item.equipment_sn;
          // update availability and cap qty
          const avail = parseInt(item.available_qty) || 0;
          setAvailText(avail);
          if (borrowedqtyIn) {
            borrowedqtyIn.max = avail;
            if (!borrowedqtyIn.value) borrowedqtyIn.value = avail ? 1 : '';
            else borrowedqtyIn.value = Math.min(parseInt(borrowedqtyIn.value || 0), avail || 0) || (avail ? 1 : '');
            borrowedqtyIn.placeholder = avail ? `(max ${avail})` : `(unknown ESN)`;
          }
          // hide list after selection
          borrowedList.style.display = 'none';
        });
        borrowedList.appendChild(li);
      });

      borrowedList.style.display = items.length ? 'block' : 'none';
    }

    // show full list on focus/click
    const options = equipments; // array
    if (visibleInput) {
      visibleInput.addEventListener('focus', () => rebuildList(options));
      visibleInput.addEventListener('click', () => rebuildList(options));

      // filter while typing
      visibleInput.addEventListener('input', () => {
        const v = (visibleInput.value || '').trim().toLowerCase();
        if (!v) {
          // no filter -> show all
          rebuildList(options);
          // clear hidden ESN & availability because user is typing a new value
          hiddenEsn.value = '';
          setAvailText(null);
          if (borrowedqtyIn) { borrowedqtyIn.removeAttribute('max'); borrowedqtyIn.placeholder = ''; }
          return;
        }
        const filtered = options.filter(e =>
          (e.name && e.name.toLowerCase().includes(v)) ||
          (e.equipment_sn && e.equipment_sn.toLowerCase().includes(v))
        );
        rebuildList(filtered);
        // clear hidden value until an exact selection is chosen
        hiddenEsn.value = '';
        setAvailText(null);
        if (borrowedqtyIn) { borrowedqtyIn.removeAttribute('max'); borrowedqtyIn.placeholder = ''; }
      });

      // hide after blur (small delay to catch clicks)
      visibleInput.addEventListener('blur', () => setTimeout(() => {
        borrowedList.style.display = 'none';
      }, 150));
    }

    // If user pastes or programmatically changes the hidden ESN elsewhere,
    // keep availability and qty cap in sync. Also allow the JS to react when
    // someone manually edits the hiddenEsn (rare).
    function updateFromHiddenEsn() {
      const esn = hiddenEsn.value || '';
      if (!esn) {
        setAvailText(null);
        if (borrowedqtyIn) { borrowedqtyIn.removeAttribute('max'); borrowedqtyIn.placeholder = ''; }
        return;
      }
      const item = options.find(e => e.equipment_sn === esn);
      if (!item) {
        setAvailText(null);
        if (borrowedqtyIn) { borrowedqtyIn.removeAttribute('max'); borrowedqtyIn.placeholder = ''; }
        return;
      }
      const avail = parseInt(item.available_qty) || 0;
      setAvailText(avail);
      if (borrowedqtyIn) {
        borrowedqtyIn.max = avail;
        borrowedqtyIn.value = avail ? Math.min(parseInt(borrowedqtyIn.value || 0) || 1, avail) : '';
        borrowedqtyIn.placeholder = avail ? `(max ${avail})` : `(unknown ESN)`;
      }
      // set visible text to name (in case hidden was changed externally)
      if (visibleInput && visibleInput.value !== item.name) visibleInput.value = item.name;
    }

    // If your code previously updated `borrowedEsn` directly, call updateFromHiddenEsn() after that change.
    // Add listener to hidden input in case of programmatic changes
    hiddenEsn.addEventListener('change', updateFromHiddenEsn);

    // Ensure when the modal is shown, availability reflects any pre-filled ESN
    const addBorrowModalEl = document.getElementById('addBorrowModal');
    if (addBorrowModalEl) {
      addBorrowModalEl.addEventListener('shown.bs.modal', () => {
        updateFromHiddenEsn();
      });
      // reset when hidden
      addBorrowModalEl.addEventListener('hidden.bs.modal', () => {
        if (visibleInput) visibleInput.value = '';
        hiddenEsn.value = '';
        setAvailText(null);
        if (borrowedqtyIn) { borrowedqtyIn.value = ''; borrowedqtyIn.removeAttribute('max'); borrowedqtyIn.placeholder = ''; }
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

    // ── Borrow View ─────────────────────────────
    document.querySelectorAll('.borrow-view-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const tr = btn.closest('tr');
        if (!tr) return;

        // Transaction ID
        document.getElementById('viewTransaction').textContent = tr.dataset.transactionId || '—';

        // Other fields
        document.getElementById('viewResident').textContent = tr.dataset.resident || '—';
        document.getElementById('viewEquipment').textContent = tr.dataset.equipment || tr.dataset.esn || '—';
        document.getElementById('viewQty').textContent = tr.dataset.qty || '—';
        document.getElementById('viewLocation').textContent = tr.dataset.location || '—';
        document.getElementById('viewUsedFor').textContent = tr.dataset.usedfor || '—';

        // Borrow dates
        const from = tr.dataset.borrowFrom || '';
        const to = tr.dataset.borrowTo || '';
        document.getElementById('viewDates').textContent = (from && to)
          ? (from === to ? from : from + ' — ' + to)
          : '—';

        document.getElementById('viewPudo').textContent = tr.dataset.pudo || '—';
        document.getElementById('viewStatus').textContent = tr.dataset.status || 'Pending';

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('viewBorrowModal'));
        modal.show();
      });
    });

    // ── Wire Accept button ─────────────────────────────
    document.querySelectorAll('.borrow-accept-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const tr = btn.closest('tr');
        if (!tr) return;

        // Get transaction id (for display) and numeric id (for server)
        const tid = tr.dataset.transactionId || '—';
        const brId = tr.dataset.id || '';

        // Put them inside modal
        document.getElementById('acceptTransactionId').textContent = tid;
        document.getElementById('acceptBorrowId').value = brId;

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('acceptBorrowModal'));
        modal.show();
      });
    });

    // Confirm Accept button — send request to server
    const confirmAcceptBtn = document.getElementById('confirmAcceptBtn');
    if (confirmAcceptBtn) {
      confirmAcceptBtn.addEventListener('click', async (ev) => {
        const id = document.getElementById('acceptBorrowId').value;
        if (!id) return;

        // disable to prevent double clicks
        confirmAcceptBtn.disabled = true;
        confirmAcceptBtn.textContent = 'Processing...';

        try {
          const form = new FormData();
          form.append('id', id);

          const resp = await fetch('functions/borrow_accept.php', {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
          });

          const data = await resp.json();

          if (data && data.success) {
            // optionally show a brief success alert then reload
            const placeholder = document.getElementById('statusAlertPlaceholder');
            if (placeholder) {
              placeholder.innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">Borrow request accepted. Reloading...<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
            // close modal then reload to reflect changes
            const mEl = document.getElementById('acceptBorrowModal');
            bootstrap.Modal.getInstance(mEl)?.hide();
            // small timeout so user sees the modal close animation / alert
            setTimeout(() => location.reload(), 300);
          } else {
            const err = (data && data.error) ? data.error : 'Unknown error';
            const placeholder = document.getElementById('statusAlertPlaceholder');
            if (placeholder) {
              placeholder.innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Accept failed: ' + String(err) + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            } else {
              alert('Accept failed: ' + err);
            }
            confirmAcceptBtn.disabled = false;
            confirmAcceptBtn.textContent = 'Accept';
          }
        } catch (err) {
          console.error(err);
          alert('Network or server error while accepting.');
          confirmAcceptBtn.disabled = false;
          confirmAcceptBtn.textContent = 'Accept';
        }
      });
    }

    // ── Wire Reject button ─────────────────────────────
    document.querySelectorAll('.borrow-reject-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const tr = btn.closest('tr');
        if (!tr) return;

        const tid = tr.dataset.transactionId || '—';
        const brId = tr.dataset.id || '';

        // Put into modal
        document.getElementById('rejectTransactionId').textContent = tid;
        document.getElementById('rejectBorrowId').value = brId;

        // Clear previous reason
        const reasonEl = document.getElementById('rejectReason');
        if (reasonEl) reasonEl.value = '';

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('rejectBorrowModal'));
        modal.show();
      });
    });

    // Confirm Reject button — send to server
    const confirmRejectBtn = document.getElementById('confirmRejectBtn');
    if (confirmRejectBtn) {
      confirmRejectBtn.addEventListener('click', async () => {
        const id = document.getElementById('rejectBorrowId').value;
        const reasonEl = document.getElementById('rejectReason');
        const reason = reasonEl ? reasonEl.value.trim() : '';

        if (!id) {
          alert('Missing borrow request id.');
          return;
        }
        if (!reason) {
          // simple client-side validation
          if (reasonEl) {
            reasonEl.focus();
          }
          alert('Please provide a rejection reason.');
          return;
        }

        confirmRejectBtn.disabled = true;
        confirmRejectBtn.textContent = 'Processing...';

        try {
          const form = new FormData();
          form.append('id', id);
          form.append('reason', reason);

          const resp = await fetch('functions/borrow_reject.php', {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
          });

          const data = await resp.json();

          if (data && data.success) {
            const placeholder = document.getElementById('statusAlertPlaceholder');
            if (placeholder) {
              placeholder.innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">Borrow request rejected. Reloading...<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
            // hide modal then reload so UI updates
            const mEl = document.getElementById('rejectBorrowModal');
            bootstrap.Modal.getInstance(mEl)?.hide();
            setTimeout(() => location.reload(), 300);
          } else {
            const err = (data && data.error) ? data.error : 'Unknown error';
            const placeholder = document.getElementById('statusAlertPlaceholder');
            if (placeholder) {
              placeholder.innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Reject failed: ' + String(err) + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            } else {
              alert('Reject failed: ' + err);
            }
            confirmRejectBtn.disabled = false;
            confirmRejectBtn.textContent = 'Reject Request';
          }
        } catch (err) {
          console.error(err);
          alert('Network or server error while rejecting.');
          confirmRejectBtn.disabled = false;
          confirmRejectBtn.textContent = 'Reject Request';
        }
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

        // ── Wire Edit (Mark returned) ─────────────────────────────
    document.querySelectorAll('.borrow-edit-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const tr = btn.closest('tr');
        if (!tr) return;

        const id = tr.dataset.id;
        const qty = tr.dataset.qty || '0';
        const equipment = tr.dataset.equipment || tr.dataset.esn || '—';

        // fill modal
        document.getElementById('editBorrowId').value = id;
        document.getElementById('editBorrowInfo').innerHTML =
          `<strong>${escapeHtml(qty)}</strong> <strong>${escapeHtml(equipment)}</strong> is currently borrowed. Mark as returned?`;

        // show modal
        const modalEl = document.getElementById('editBorrowModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
      });
    });

    // Mark as Returned button handler (inside modal body)
    const markReturnedBtn = document.getElementById('markReturnedBtn');
    if (markReturnedBtn) {
      markReturnedBtn.addEventListener('click', async (ev) => {
        const id = document.getElementById('editBorrowId').value;
        if (!id) return;

        markReturnedBtn.disabled = true;
        const originalTxt = markReturnedBtn.textContent;
        markReturnedBtn.textContent = 'Processing...';

        try {
          const body = new URLSearchParams();
          body.append('id', id);
          body.append('action', 'return');

          const resp = await fetch('functions/borrow_update.php', {
            method: 'POST',
            body: body
          });

          const json = await resp.json();
          if (json && json.success) {
            // Update table row: change badge, data-status, remove edit button
            const tr = document.querySelector(`tr[data-id="${id}"]`);
            if (tr) {
              tr.dataset.status = 'Returned';
              // update the badge element (assumes there's a .badge in the status cell)
              const badge = tr.querySelector('.badge');
              if (badge) {
                badge.className = 'badge bg-success';
                badge.textContent = 'Returned';
              }
              // remove edit button if present
              const editBtn = tr.querySelector('.borrow-edit-btn');
              if (editBtn) editBtn.remove();
            }

            // hide modal
            const modalEl = document.getElementById('editBorrowModal');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            showStatusAlert('Borrow request marked as returned', 'success');
          } else {
            const err = (json && json.error) ? json.error : 'Failed to mark as returned';
            showStatusAlert(err, 'danger');
          }

        } catch (err) {
          showStatusAlert(err.message || 'Request failed', 'danger');
        } finally {
          markReturnedBtn.disabled = false;
          markReturnedBtn.textContent = originalTxt;
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


  });
</script>
<?php
$conn->close();
?>
