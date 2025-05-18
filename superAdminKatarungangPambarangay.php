<?php
require 'functions/dbconn.php';
$userId  = (int) $_SESSION['loggedInUserID'];
// $newCase = $_GET['transaction_id'] ?? '';

// ——— Filters & pagination setup ——————————————————————————————————————
$status  = $_GET['status'] ?? 'all';
$search  = trim($_GET['search'] ?? '');
$page    = max((int)($_GET['page_num'] ?? 1), 1);
$limit   = 10;
$offset  = ($page - 1) * $limit;

// ——— Build WHERE clauses —————————————————————————————————————————————
$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

// status filter
if ($status !== 'all') {
    $whereClauses[]  = 'kp.status = ?';
    $bindTypes      .= 's';
    $bindParams[]    = $status;
}

// global search
if ($search !== '') {
    $whereClauses[]  = '(kp.case_no LIKE ? OR s.transaction_id LIKE ? OR b.transaction_id LIKE ? OR kp.subject LIKE ? OR kp.status LIKE ?)';
    $bindTypes      .= 'sssss';
    $term            = "%{$search}%";
    $bindParams     = array_merge($bindParams, array_fill(0, 5, $term));
}

$whereSQL = $whereClauses
          ? 'WHERE ' . implode(' AND ', $whereClauses)
          : '';

// ——— Count total rows ————————————————————————————————————————————————
$countSQL = "
    SELECT COUNT(*) AS total
      FROM katarungang_pambarangay AS kp
      JOIN summon_records        AS s ON kp.smn_id = s.id
      JOIN blotter_records       AS b ON kp.blt_id = b.id
    {$whereSQL}
";
$countStmt = $conn->prepare($countSQL);
if ($whereClauses) {
    $refs = [];
    foreach ($bindParams as $i => $v) {
        $refs[$i] = & $bindParams[$i];
    }
    array_unshift($refs, $bindTypes);
    call_user_func_array([$countStmt, 'bind_param'], $refs);
}
$countStmt->execute();
$totalRows   = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages  = (int) ceil($totalRows / $limit);
$countStmt->close();

// ——— Fetch page of cases —————————————————————————————————————————————
$dataSQL = "
    SELECT
      kp.case_no,
      s.transaction_id AS smn_id,
      b.transaction_id AS blt_id,
      kp.subject,
      kp.status
    FROM katarungang_pambarangay AS kp
    JOIN summon_records        AS s ON kp.smn_id = s.id
    JOIN blotter_records       AS b ON kp.blt_id = b.id
    {$whereSQL}
    ORDER BY kp.id ASC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($dataSQL);

// bind filters + pagination
$types       = $bindTypes . 'ii';
$bindParams[] = $limit;
$bindParams[] = $offset;
$refs = [];
foreach ($bindParams as $i => $v) {
    $refs[$i] = & $bindParams[$i];
}
array_unshift($refs, $types);
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$cases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container py-3">
  <!-- <?php if ($newCase): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      New case <strong><?= htmlspecialchars($newCase) ?></strong> added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?> -->

  <div class="card shadow-sm p-3">
    <!-- Header: New Case button + Filters + Search -->
    <div class="d-flex align-items-center mb-3">
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          Filter
        </button>
        <div class="dropdown-menu p-3" aria-labelledby="filterDropdown" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
          <form method="get" class="mb-0" id="filterForm">
            <!-- reset to first page on filter change -->
            <input type="hidden" name="page"     value="superAdminKatarungangPambarangay">
            <input type="hidden" name="page_num" value="1">
            <!-- preserve existing search term -->
            <?php if ($search !== ''): ?>
              <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>

            <!-- Status Filter -->
            <div class="mb-2">
              <label class="form-label mb-1">Status</label>
              <select name="status" class="form-select form-select-sm" style="font-size:.75rem;">
                <option value="all" <?= $status==='all' ? 'selected':'' ?>>All</option>
                <option value="Punong Barangay"       <?= $status==='Punong Barangay'       ? 'selected':'' ?>>Punong Barangay</option>
                <option value="Unang Patawag"         <?= $status==='Unang Patawag'         ? 'selected':'' ?>>Unang Patawag</option>
                <option value="Pangalawang Patawag"   <?= $status==='Pangalawang Patawag'   ? 'selected':'' ?>>Pangalawang Patawag</option>
                <option value="Pangatlong Patawag"    <?= $status==='Pangatlong Patawag'    ? 'selected':'' ?>>Pangatlong Patawag</option>
                <option value="Cleared"               <?= $status==='Cleared'               ? 'selected':'' ?>>Cleared</option>
              </select>
            </div>

            <div class="d-flex">
              <a href="?page=superAdminKatarungangPambarangay&page_num=1" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
              <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
            </div>
          </form>
        </div>
      </div>

      <form method="get" id="searchForm" class="d-flex ms-auto me-2">
        <input type="hidden" name="page"     value="superAdminKatarungangPambarangay">
        <input type="hidden" name="page_num" value="1">
        <?php if ($status !== 'all'): ?>
          <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <?php endif; ?>
        <div class="input-group input-group-sm">
          <input id="searchInput" name="search" type="text" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
          <button id="searchBtn" class="btn btn-outline-secondary" type="button">
            <span class="material-symbols-outlined" id="searchIcon">
              <?= $search ? 'close' : 'search' ?>
            </span>
          </button>
        </div>
      </form>
    </div>

    <!-- Table of Cases -->
    <div class="table-responsive admin-table" style="height:500px;overflow-y:auto;">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Case No.</th>
            <th>Summon ID</th>
            <th>Blotter ID</th>
            <th>Subject</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($cases): ?>
            <?php foreach ($cases as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['case_no']) ?></td>
              <td><?= htmlspecialchars($c['smn_id'])   ?></td>
              <td><?= htmlspecialchars($c['blt_id'])   ?></td>
              <td><?= htmlspecialchars($c['subject'])  ?></td>
              <td>
                <span class="badge bg-info text-dark">
                  <?= htmlspecialchars($c['status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" class="text-center">No records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination justify-content-center pagination-sm">
        <li class="page-item<?= $page<=1?' disabled':'' ?>">
          <a class="page-link"
             href="?<?= http_build_query([
               'page'=>'superAdminKatarungangPambarangay',
               'status'=>$status,
               'search'=>$search,
               'page_num'=>$page-1
             ]) ?>">Previous</a>
        </li>
        <?php
        $range = 2; $dots = false;
        for ($i = 1; $i <= $totalPages; $i++) {
          if ($i===1 || $i===$totalPages || ($i>=$page-$range && $i<=$page+$range)) {
            $active = $i===$page ? ' active' : '';
            echo "<li class='page-item{$active}'>
                    <a class='page-link' href='?".
                    http_build_query([
                      'page'=>'superAdminKatarungangPambarangay',
                      'status'=>$status,
                      'search'=>$search,
                      'page_num'=>$i
                    ]).
                    "'>{$i}</a>
                  </li>";
            $dots = true;
          } elseif ($dots) {
            echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
            $dots = false;
          }
        }
        ?>
        <li class="page-item<?= $page>=$totalPages?' disabled':'' ?>">
          <a class="page-link"
             href="?<?= http_build_query([
               'page'=>'superAdminKatarungangPambarangay',
               'status'=>$status,
               'search'=>$search,
               'page_num'=>$page+1
             ]) ?>">Next</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>

<?php
$conn->close();
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form      = document.getElementById('searchForm');
  const input     = document.getElementById('searchInput');
  const btn       = document.getElementById('searchBtn');
  const icon      = document.getElementById('searchIcon');
  let hasSearch   = <?= $search !== '' ? 'true' : 'false' ?>;

  btn.addEventListener('click', () => {
    // if there’s already a search term, clear it and flip icon
    if (hasSearch) {
      input.value = '';
      icon.textContent = 'search';
      hasSearch = false;
    }
    // submit the form (GET)
    form.submit();
  });
});
</script>
