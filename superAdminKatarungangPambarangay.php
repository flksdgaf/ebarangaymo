<?php
require 'functions/dbconn.php';
$userId  = (int) $_SESSION['loggedInUserID'];
$newCase = $_GET['transaction_id'] ?? '';

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
    $whereClauses[]  = '(kp.case_no LIKE ? OR s.transaction_id LIKE ? OR b.transaction_id LIKE ? OR kp.subject LIKE ?)';
    $bindTypes      .= 'ssss';
    $term            = "%{$search}%";
    $bindParams     = array_merge($bindParams, array_fill(0, 4, $term));
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
    ORDER BY kp.id DESC
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
  <?php if ($newCase): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      New case <strong><?= htmlspecialchars($newCase) ?></strong> added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm p-3">
    <!-- Header: New Case button + Filters + Search -->
    <div class="d-flex align-items-center mb-3">
      <button class="btn btn-sm btn-outline-success me-2" id="addCaseBtn">
        <i class="bi bi-plus-lg me-1"></i> New Case
      </button>

      <div class="dropdown">
        <button class="btn btn-sm btn-outline-success dropdown-toggle"
                id="statusDropdown" data-bs-toggle="dropdown">
          <?= $status === 'all' ? 'All Statuses' : htmlspecialchars($status) ?>
        </button>
        <ul class="dropdown-menu" aria-labelledby="statusDropdown">
          <?php
            $statuses = [
              'all'                 => 'All',
              'Punong Barangay'     => 'Punong Barangay',
              'Unang Patawag'       => 'Unang Patawag',
              'Pangalawang Patawag' => 'Pangalawang Patawag',
              'Pangatlong Patawag'  => 'Pangatlong Patawag',
              'Cleared'             => 'Cleared',
            ];
            foreach ($statuses as $key => $label):
          ?>
          <li>
            <a class="dropdown-item<?= $status===$key?' active':''?>"
               href="?page=superAdminKatarungangPambarangay
                       &status=<?= urlencode($key) ?>
                       &search=<?= urlencode($search) ?>">
              <?= $label ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <form method="get" id="searchForm" class="d-flex ms-auto me-2">
        <!-- preserve page and reset to first page on new search -->
        <input type="hidden" name="page"     value="superAdminKatarungangPambarangay">
        <input type="hidden" name="page_num" value="1">

        <!-- preserve the status filter if set -->
        <?php if ($status !== 'all'): ?>
          <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <?php endif; ?>

        <div class="input-group input-group-sm">
          <input
            name="search"
            id="searchInput"
            type="text"
            class="form-control"
            placeholder="Search case…"
            value="<?= htmlspecialchars($search) ?>"
          >
          <button type="button" class="btn btn-outline-secondary" id="searchBtn">
            <span class="material-symbols-outlined" id="searchIcon">
              <?= !empty($search) ? 'close' : 'search' ?>
            </span>
          </button>
        </div>
      </form>
    </div>

    <!-- Table of Cases -->
    <div class="table-responsive admin-table" style="max-height:500px;overflow-y:auto;">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Case No.</th>
            <th>Summon ID</th>
            <th>Blotter ID</th>
            <th>Subject</th>
            <th>Status</th>
            <th class="text-center">Actions</th>
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
              <td class="text-center">
                <button class="btn btn-sm btn-outline-primary">
                  Edit
                </button>
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
document.getElementById('addCaseBtn').addEventListener('click', () => {
  // Replace with your own “add new case” modal trigger logic:
  // e.g. open a modal, inject fields, and point its form at serviceKP_submit.php
  // bootstrap.Modal.getOrCreateInstance(document.getElementById('addCaseModal')).show();
});
</script>
