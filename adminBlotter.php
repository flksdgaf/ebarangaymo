<?php
require 'functions/dbconn.php';

$userId = (int)$_SESSION['loggedInUserID'];

// ── 0) FILTER SETUP ──────────────────────────────────────────────────────────
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to']   ?? '';

$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

if ($date_from && $date_to) {
  $whereClauses[]  = 'DATE(date_occurred) BETWEEN ? AND ?';
  $bindTypes      .= 'ss';
  $bindParams[]    = $date_from;
  $bindParams[]    = $date_to;
} elseif ($date_from) {
  $whereClauses[]  = 'DATE(date_occurred) >= ?';
  $bindTypes      .= 's';
  $bindParams[]    = $date_from;
} elseif ($date_to) {
  $whereClauses[]  = 'DATE(date_occurred) <= ?';
  $bindTypes      .= 's';
  $bindParams[]    = $date_to;
}

$whereSQL = $whereClauses
  ? 'WHERE ' . implode(' AND ', $whereClauses)
  : '';

$limit  = 10;
$page   = isset($_GET['page_num']) ? max((int)$_GET['page_num'], 1) : 1;
$offset = ($page - 1) * $limit;

// ── COUNT FOR PAGINATION ─────────────────────────────────────────────────────
$countSQL  = "SELECT COUNT(*) AS total FROM blotter_records {$whereSQL}";
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
$totalRows  = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

// ── 1) FETCH LIST ────────────────────────────────────────────────────────────
$sql = "
  SELECT
    id,
    account_id,
    transaction_id,
    complainants,
    respondents,
    DATE_FORMAT(date_filed,    '%M %d, %Y %h:%i %p') AS date_filed_fmt,
    DATE_FORMAT(date_occurred, '%M %d, %Y')             AS date_occurred_fmt,
    complaint_nature,
    complaint_description,
    payment_method,
    OR_number,
    DATE_FORMAT(OR_issued_date,'%M %d, %Y')             AS OR_issued_fmt
  FROM blotter_records
  {$whereSQL}
  ORDER BY transaction_id DESC
  LIMIT ? OFFSET ?
";
$st = $conn->prepare($sql);
$types = $bindTypes . 'ii';
$bindParams[] = $limit;
$bindParams[] = $offset;
$refs = [];
foreach ($bindParams as $i => $v) {
  $refs[$i] = & $bindParams[$i];
}
array_unshift($refs, $types);
call_user_func_array([$st, 'bind_param'], $refs);
$st->execute();
$result = $st->get_result();
?>

<div class="container py-3">
  <!-- New Blotter & Filter -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <button class="btn btn-success"
            data-bs-toggle="modal"
            data-bs-target="#newBlotterModal">
      New Blotter
    </button>
    <div class="dropdown">
      <button class="btn btn-sm btn-outline-success dropdown-toggle"
              type="button"
              id="filterDropdown"
              data-bs-toggle="dropdown"
              aria-expanded="false">
        Filter
      </button>
      <div class="dropdown-menu dropdown-menu-end p-3"
           aria-labelledby="filterDropdown"
           style="min-width:260px; --bs-body-font-size:.75rem; font-size:.75rem;">
        <form method="get" class="mb-0" action="adminPanel.php">
        <!-- tell adminPanel to render the blotter page -->
        <input type="hidden" name="page" value="adminBlotter">
          <div class="mb-2">
            <label class="form-label mb-1">Date Occurred</label>
            <div class="d-flex">
              <input type="date" name="date_from" class="form-control form-control-sm me-1" style="font-size:.75rem;" value="<?=htmlspecialchars($date_from)?>">
              <input type="date" name="date_to"   class="form-control form-control-sm" style="font-size:.75rem;" value="<?=htmlspecialchars($date_to)?>">
            </div>
          </div>
          <div class="d-flex">
            <a href="adminPanel.php?page=adminBlotter" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
            <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- New Blotter Modal -->
  <div class="modal fade" id="newBlotterModal" tabindex="-1" aria-labelledby="newBlotterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content rounded-2xl shadow-lg border-0">
        <div class="modal-header">
          <h5 class="modal-title" id="newBlotterModalLabel">Record New Blotter</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
  <!-- point at your handler and let it know we came from admin -->
        <form action="functions/create_blotter.php" method="POST" id="newBlotterForm">
          <input type="hidden" name="adminRedirect" value="1">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Complainants</label>
              <input type="text" name="complainants" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Respondents</label>
              <input type="text" name="respondents" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Date Occurred</label>
              <input type="date" name="date_occurred" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Complaint Nature</label>
              <input type="text" name="complaint_nature" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Complaint Description</label>
              <textarea name="complaint_description" class="form-control" rows="3" required></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Payment Method</label>
              <input type="text" name="payment_method" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">OR Number</label>
              <input type="text" name="OR_number" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">OR Issued Date</label>
              <input type="date" name="OR_issued_date" class="form-control">
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-success">Save Blotter</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Blotter Records Table -->
  <div class="card shadow-sm p-3">
  <div class="table-responsive admin-table" style="height:500px; overflow-y:auto;">
      <table class="table table-hover align-middle text-start">
      <thead class="table-light">
          <tr>
            <th>Transaction No.</th>
            <th>Complainants</th>
            <th>Date Occurred</th>
            <th>Nature of Complaint</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($r = $result->fetch_assoc()): ?>
              <tr style="cursor:pointer"
                  data-bs-toggle="modal"
                  data-bs-target="#viewModal-<?= htmlspecialchars($r['id']) ?>">
                <td><?= htmlspecialchars($r['transaction_id']) ?></td>
                <td><?= htmlspecialchars($r['complainants']) ?></td>
                <td><?= $r['date_occurred_fmt'] ?></td>
                <td><?= htmlspecialchars($r['complaint_nature']) ?></td>
              </tr>

              <!-- View Modal -->
              <div class="modal fade" id="viewModal-<?= $r['id'] ?>" tabindex="-1" aria-labelledby="viewModalLabel-<?= $r['id'] ?>" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <?php
        // re-fetch record so we can render the full detail form
        $m = $conn->prepare("SELECT * FROM blotter_records WHERE id = ? LIMIT 1");
        $m->bind_param('i', $r['id']);
        $m->execute();
        $detail = $m->get_result()->fetch_assoc();
        $m->close();
      ?>
      <div class="modal-header">
        <h5 class="modal-title" id="viewModalLabel-<?= $r['id'] ?>">
          Blotter #<?= htmlspecialchars($detail['transaction_id']) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="detailForm-<?= $r['id'] ?>" method="post">
          <?php
            $exclude = ['id','account_id'];
            foreach ($detail as $col => $val) {
              if (in_array($col, $exclude, true)) continue;
              $label = ucwords(str_replace('_',' ',$col));
              // formatted fields already have `_fmt` alias
              if (strpos($col, '_fmt') !== false) {
                echo "<p><strong>{$label}:</strong> {$val}</p>";
              } else {
                echo "<div class='mb-3'>";
                echo "  <label class='form-label'>{$label}</label>";
                if ($col === 'complaint_description') {
                  echo "<textarea name='{$col}' class='form-control' rows='3' readonly>".
                        htmlspecialchars($val).
                        "</textarea>";
                } else {
                  echo "<input type='text' name='{$col}' value='".
                        htmlspecialchars($val).
                        "' class='form-control' readonly>";
                }
                echo "</div>";
              }
            }
            // keep the ID for editing
            echo "<input type='hidden' name='id' value='{$detail['id']}'>";
          ?>
        </form>
      </div>
      <!-- view‐mode buttons -->
      <div id="groupView-<?= $r['id'] ?>" class="btn-group w-100 p-3" role="group">
        <button type="button"
                class="btn btn-outline-danger me-1"
                onclick="if(confirm('Really delete?')) location.href='adminPanel.php?page=adminBlotter&action=delete&id=<?= $r['id'] ?>'">
          Delete
        </button>
        <button type="button"
                class="btn btn-outline-primary me-1"
                id="editBtn-<?= $r['id'] ?>">
          Edit
        </button>
        <button type="button"
                class="btn btn-outline-secondary"
                onclick="location.href='adminPanel.php?page=adminBlotter&action=certificate&id=<?= $r['id'] ?>'">
          Generate Certificate
        </button>
      </div>
      <!-- edit‐mode buttons (hidden initially) -->
      <div id="groupEdit-<?= $r['id'] ?>" class="btn-group w-100 p-3 d-none" role="group">
        <button type="button"
                class="btn btn-outline-danger me-1"
                id="cancelBtn-<?= $r['id'] ?>">
          Cancel
        </button>
        <button type="button"
                class="btn btn-primary"
                id="saveBtn-<?= $r['id'] ?>">
          Save
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const id        = <?= json_encode($r['id']) ?>;
    const editBtn   = document.getElementById(`editBtn-${id}`);
    const cancelBtn = document.getElementById(`cancelBtn-${id}`);
    const saveBtn   = document.getElementById(`saveBtn-${id}`);
    const viewGrp   = document.getElementById(`groupView-${id}`);
    const editGrp   = document.getElementById(`groupEdit-${id}`);
    const form      = document.getElementById(`detailForm-${id}`);
    const inputs    = form.querySelectorAll('input, textarea');

    // Enter Edit
    editBtn.addEventListener('click', () => {
      inputs.forEach(i => i.removeAttribute('readonly'));
      viewGrp.classList.add('d-none');
      editGrp.classList.remove('d-none');
    });

    // Cancel Edit
    cancelBtn.addEventListener('click', () => {
      // just reload the modal contents
      location.reload();
    });

    // Save
    saveBtn.addEventListener('click', () => {
      form.action = 'functions/edit_blotter.php';
      form.submit();
    });
  });
</script>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="4" class="text-center">No blotter records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center pagination-sm">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_num' => $page - 1])) ?>">Previous</a>
          </li>
          <?php
            $range = 2; $dots = false;
            for ($i = 1; $i <= $totalPages; $i++) {
              if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                $active = $i == $page ? 'active' : '';
                echo "<li class='page-item {$active}'>
                        <a class='page-link' href='?".http_build_query(array_merge($_GET, ['page_num'=>$i]))."'>$i</a>
                      </li>";
                $dots = true;
              } elseif ($dots) {
                echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                $dots = false;
              }
            }
          ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_num' => $page + 1])) ?>">Next</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php
$st->close();
$conn->close();
?>
