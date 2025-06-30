<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];

// Fetch distinct roles & actions for the filters
$rolesRs   = $conn->query("SELECT DISTINCT role FROM activity_logs ORDER BY role ASC");
$actionsRs = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");

// Now grab the filter inputs
$search = trim($_GET['search'] ?? '');
$role = $_GET['role'] ?? '';
$action = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// 2) Build WHERE clauses
$where = [];
$types = '';
$params = [];

// If the user entered a search term, match it against description
if ($search !== '') {
    $where[] = "(id LIKE ? OR role LIKE ? OR action LIKE ? OR description LIKE ?)";
    $types .= 'ssss';
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

// Filter by role (partial match)
if ($role !== '') {
    $where[] = "role LIKE ?";
    $types .= 's';
    $params[] = "%{$role}%";
}

// Filter by action (partial match)
if ($action !== '') {
    $where[] = "action LIKE ?";
    $types .= 's';
    $params[] = "%{$action}%";
}

// Filter by date range
if ($date_from !== '') {
    $where[] = "DATE(created_at) >= ?";
    $types .= 's';
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where[] = "DATE(created_at) <= ?";
    $types .= 's';
    $params[] = $date_to;
}

// PAGINATION SETUP
$limit = 10;
$page = max((int)($_GET['page_num'] ?? 1), 1);
$offset = ($page - 1) * $limit;

// COUNT total rows (for pagination)
$countSQL = "SELECT COUNT(*) AS total FROM activity_logs";
if ($where) $countSQL .= " WHERE " . implode(' AND ', $where);

$countStmt = $conn->prepare($countSQL);
if ($where) {
  $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

// 3) Assemble SQL
$sql = "SELECT role, action, description, DATE_FORMAT(created_at, '%b %e, %Y') AS formatted_date FROM activity_logs";
if ($where) {
  $sql .= "\n WHERE " . implode(' AND ', $where);
}
$sql .= "\n ORDER BY id ASC LIMIT ? OFFSET ?";

// Add pagination bindings
$types .= 'ii';
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!-- MAIN CONTENT -->
<div class="container-fluid p-3">
  <div class="card p-3 shadow-sm">
    <div class="d-flex align-items-center mb-3">
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="material-symbols-outlined me-1" style="font-size:1rem; vertical-align:middle;">filter_list</span>
          Filter
        </button>
        <div class="dropdown-menu p-3" aria-labelledby="filterDropdown" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
          <form method="get" id="filterForm" class="mb-0">
            <input type="hidden" name="page" value="superAdminLogs">

            <!-- Role Dropdown -->
            <div class="mb-2">
              <label class="form-label mb-1">Role</label>
              <select name="role" class="form-select form-select-sm" style="font-size:.75rem;">
                <option value="" <?= $role===''?'selected':'' ?>>All</option>
                <?php while($r = $rolesRs->fetch_assoc()): ?>
                  <option
                    value="<?= htmlspecialchars($r['role']) ?>"
                    <?= $role === $r['role'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r['role']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <!-- Action Dropdown -->
            <div class="mb-2">
              <label class="form-label mb-1">Action</label>
              <select name="action" class="form-select form-select-sm" style="font-size:.75rem;">
                <option value="" <?= $action===''?'selected':'' ?>>All</option>
                <?php while($a = $actionsRs->fetch_assoc()): ?>
                  <option
                    value="<?= htmlspecialchars($a['action']) ?>"
                    <?= $action === $a['action'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['action']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <!-- Date From / To (unchanged) -->
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

            <div class="d-flex">
              <a href="?page=superAdminLogs" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
              <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
            </div>
          </form>
        </div>
      </div>

      <form method="get" id="searchForm" class="d-flex ms-auto me-2">
        <input type="hidden" name="page" value="superAdminLogs">
        <input type="hidden" name="page_num" value="1">

        <?php foreach (['role','action','date_from','date_to'] as $f): 
            if (isset($_GET[$f]) && $_GET[$f] !== ''): ?>
          <input type="hidden" name="<?= $f ?>" value="<?= htmlspecialchars($_GET[$f]) ?>">
        <?php endif; endforeach; ?>

        <div class="input-group input-group-sm">
          <input name="search" id="searchInput" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
          <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" id="searchBtn">
            <span class="material-symbols-outlined">
              <?= $search !== '' ? 'close' : 'search' ?>
            </span>
          </button>
        </div>
      </form>
    </div>

    <!-- TABLE -->
    <div class="table-responsive admin-table">
      <table class="table table-hover align-middle text-start">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">No.</th>
            <th class="text-nowrap">Role</th>
            <th class="text-nowrap">Action</th>
            <th class="text-nowrap">Description</th>
            <th class="text-nowrap">Date Created</th>
          </tr>
        </thead>
        <tbody>
          <?php if (isset($result) && $result->num_rows > 0): ?>
            <?php $i = $offset + 1; while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['role']) ?></td>
                <td><?= htmlspecialchars($row['action']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><?= htmlspecialchars($row['formatted_date']) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="text-center">No records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center pagination-sm">
          <!-- Prev -->
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_num' => $page - 1])) ?>">Previous</a>
          </li>

          <?php
          $range = 2;
          $dots = false;
          for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
              $active = $i == $page ? 'active' : '';
              echo "<li class='page-item $active'><a class='page-link' href='?" . http_build_query(array_merge($_GET, ['page_num' => $i])) . "'>$i</a></li>";
              $dots = true;
            } elseif ($dots) {
              echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
              $dots = false;
            }
          }
          ?>

          <!-- Next -->
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_num' => $page + 1])) ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchForm  = document.getElementById('searchForm');
  const searchInput = document.getElementById('searchInput');
  const searchBtn   = document.getElementById('searchBtn');
  const hasSearch = <?= json_encode(!empty($search)) ?>;

  searchBtn.addEventListener('click', () => {
    if (hasSearch) searchInput.value = '';
    searchForm.submit();
  });
});
</script>

