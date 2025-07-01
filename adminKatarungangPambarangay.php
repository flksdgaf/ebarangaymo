<?php
require 'functions/dbconn.php';
$userId = (int) $_SESSION['loggedInUserID'];
$newCase = $_GET['transaction_id'] ?? '';

// FILTER & SEARCH SETUP
$search = trim($_GET['katarungan_search'] ?? '');
$date_from = $_GET['katarungan_date_from'] ?? '';
$date_to = $_GET['katarungan_date_to'] ?? '';

// build a Katarungan-only query array
$bp = [
  'page' => 'adminComplaints',
  'katarungan_search' => $search,
  'katarungan_date_from' => $date_from,
  'katarungan_date_to' => $date_to,
];

$whereClauses = [];
$bindTypes = '';
$bindParams = [];

// global search on transaction_id, complainant_affidavit, respondent_affidavit
if ($search !== '') {
  $whereClauses[] = "(transaction_id LIKE ? OR complainant_affidavit LIKE ? OR respondent_affidavit LIKE ?)";
  $bindTypes .= str_repeat('s', 3);
  $term = "%{$search}%";
  $bindParams = array_merge($bindParams, array_fill(0, 3, $term));
}

// filter by scheduled_at date range
if ($date_from && $date_to) {
  $whereClauses[] = 'DATE(scheduled_at) BETWEEN ? AND ?';
  $bindTypes .= 'ss';
  $bindParams[] = $date_from;
  $bindParams[] = $date_to;
} elseif ($date_from) {
  $whereClauses[] = 'DATE(scheduled_at) >= ?';
  $bindTypes .= 's';
  $bindParams[] = $date_from;
} elseif ($date_to) {
  $whereClauses[] = 'DATE(scheduled_at) <= ?';
  $bindTypes .= 's';
  $bindParams[] = $date_to;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// PAGINATION
$limit = 10;
$page = max((int)($_GET['katarungan_page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

// 1) total count
$countSQL = "SELECT COUNT(*) AS total FROM katarungang_pambarangay_records {$whereSQL}";
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
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

// build base query string for pagination links
$qs = $_GET;
unset($qs['katarungan_page']);
$baseQS = http_build_query($qs);
if ($baseQS) {
  $baseQS .= '&';
}

// 2) fetch page of rows
$sql = "SELECT transaction_id, complainant_affidavit, respondent_affidavit, DATE_FORMAT(scheduled_at, '%b %e, %Y %l:%i %p') AS formatted_sched FROM katarungang_pambarangay_records {$whereSQL} ORDER BY transaction_id ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

// bind params + pagination
$types = $bindTypes . 'ii';
$params = array_merge($bindParams, [$limit, $offset]);
$refs = [];
foreach ($params as $i => $v) {
  $refs[$i] = & $params[$i];
}
array_unshift($refs, $types);
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<div>
  <?php if ($newCase): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      New case <strong><?= htmlspecialchars($newCase) ?></strong> added successfully!
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
        <div class="dropdown-menu p-3" aria-labelledby="filterDropdown" style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
          <form method="get" action="?page=adminComplaints" id="katarunganfilterForm" class="mb-0">
            <!-- preserve search -->
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
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): 
              $tid = htmlspecialchars($row['transaction_id']);
            ?>
              <tr data-id="<?= $tid ?>">
                <td><?= $tid ?></td>
                <td><?= htmlspecialchars(substr($row['complainant_affidavit'],0,50)) ?></td>
                <td><?= htmlspecialchars(substr($row['respondent_affidavit'],0,50)) ?></td>
                <td><?= htmlspecialchars($row['formatted_sched']) ?></td>
                <td class="text-center">
                 <!-- Edit -->
                  <button class="btn btn-sm btn-success editBtn">
                    <span class="material-symbols-outlined" style="font-size: 12px;">
                      stylus
                    </span>
                  </button>

                  <!-- Delete -->
                  <!-- <button class="btn btn-sm btn-danger deleteBtn">
                    <span class="material-symbols-outlined" style="font-size: 12px;">
                      delete
                    </span>
                  </button> -->
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
          <!-- Prev Button -->
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($bp, ['katarungan_page' => $page - 1])) ?>">Previous</a>
          </li>

          <?php
          $range = 2;
          $dots  = false;

          for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
              $active = $i == $page ? 'active' : '';
              echo "<li class='page-item {$active}'>
                      <a class='page-link' href='?" . http_build_query(array_merge($bp, ['katarungan_page' => $i])) . "'>$i</a>
                    </li>";
              $dots = true;
            } elseif ($dots) {
              echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
              $dots = false;
            }
          }
          ?>

          <!-- Next Button -->
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
  const form = document.getElementById('searchFormKatarungan');
  const input = document.getElementById('searchInputKatarungan');
  const btn = document.getElementById('searchBtnKatarungan');
  const hasSearch = <?= json_encode(!empty($search)) ?>;

  btn.addEventListener('click', () => {
    if (hasSearch) input.value = '';
    form.submit();
  });
});
</script>
