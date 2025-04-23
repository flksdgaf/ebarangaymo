<?php
$userId = (int) $_SESSION['loggedInUserID'];

// ── 1) DETAIL VIEW ───────────────────────────────────────────────────────────
if (isset($_GET['transaction_id'])) {
    $tx = $_GET['transaction_id'];

    // 1a) fetch the bare view row so we know its request_type
    $vsql = "SELECT * FROM view_general_requests WHERE transaction_id = ? LIMIT 1";
    $vst = $conn->prepare($vsql);
    $vst->bind_param('s', $tx);
    $vst->execute();
    $vrow = $vst->get_result()->fetch_assoc();
    $vst->close();

    if (!$vrow) {
        echo "<div class='alert alert-danger'>Request not found.</div>";
        exit();
    }

    // 1b) pick the right table to fetch full details
    switch ($vrow['request_type']) {
        case 'Barangay ID':
            $tbl = 'barangay_id_requests';
            break;
        case 'Business Permit':
            $tbl = 'business_permit_requests';
            break;
        case 'Certification':
            $tbl = 'certification_requests';
            break;
        default:
            $tbl = null;
    }

    echo "<div class='container py-3'>";
    echo "<div class='card shadow-sm p-4 mb-4'>";
    echo "<h5 class='fw-bold mb-3'>Full Details for {$tx}</h5>";

    if ($tbl) {
        // 1c) pull everything from that base table
        $dsql = "SELECT * FROM $tbl WHERE transaction_id = ? LIMIT 1";
        $dst = $conn->prepare($dsql);
        $dst->bind_param('s', $tx);
        $dst->execute();
        $drow = $dst->get_result()->fetch_assoc();
        $dst->close();

        if ($drow) {
            // 1) list of columns you DON’T want to show
            $exclude = [
                'id',
                'account_id',
                'transaction_id',  // shown in the header
                'created_at',      // if you prefer your formatted date only
            ];
    
            echo "<dl class='row'>";
            foreach ($drow as $col => $val) {
                // 2) skip both empty and excluded columns
                if ($val === null || in_array($col, $exclude, true)) {
                    continue;
                }
                // 3) pretty up the label
                $label = ucwords(str_replace('_',' ',$col));
                echo "<dt class='col-sm-3'>{$label}</dt>";
                echo "<dd class='col-sm-9'>" . htmlspecialchars($val) . "</dd>";
            }
            echo "</dl>";
        } else {
            echo "<p class='text-danger'>No detailed record in <code>$tbl</code>.</p>";
        }
    } else {
        echo "<p class='text-danger'>Unknown request type: <strong>" . htmlspecialchars($vrow['request_type']) . "</strong></p>";
    }

    // back button (keeps you on same page number)
    $backPage = isset($_GET['pagination']) ? (int)$_GET['pagination'] : 1;
    echo "<a href='?page=userRequest&pagination={$backPage}' class='btn btn-secondary mt-3'>← Back to list</a>";
    echo "</div></div>";
    exit();
}

// ── 2) LIST + PAGINATION ─────────────────────────────────────────────────────
$limit  = 10;
$page   = isset($_GET['pagination']) && is_numeric($_GET['pagination'])
          ? (int)$_GET['pagination'] : 1;
$offset = ($page - 1) * $limit;

// count total
$countSql = "
  SELECT COUNT(*) AS total
    FROM view_general_requests
   WHERE account_id = ?
     AND document_status <> 'Released'
";
$cst = $conn->prepare($countSql);
$cst->bind_param('i', $userId);
$cst->execute();
$totalRows = $cst->get_result()->fetch_assoc()['total'];
$cst->close();
$totalPages = ceil($totalRows / $limit);

// fetch page
$sql = "
  SELECT transaction_id,
         full_name,
         request_type,
         DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') AS formatted_date
    FROM view_general_requests
   WHERE account_id = ?
     AND document_status <> 'Released'
   ORDER BY created_at ASC
   LIMIT ? OFFSET ?
";
$st = $conn->prepare($sql);
$st->bind_param('iii', $userId, $limit, $offset);
$st->execute();
$result = $st->get_result();
?>

<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold">My Requests</h4>
  </div>

  <div class="card shadow-sm p-3">
    <div class="table-responsive" style="height:500px; overflow-y:auto;">
      <table class="table align-middle text-start table-hover">
        <thead class="table-light">
          <tr>
            <th>Transaction No.</th>
            <th>Name</th>
            <th>Request</th>
            <th>Date Created</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr style="cursor:pointer"
                  onclick="window.location.href='?page=userRequest&pagination=<?= $page ?>&transaction_id=<?= $row['transaction_id'] ?>'">
                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['request_type']) ?></td>
                <td><?= $row['formatted_date'] ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="4" class="text-center">No requests found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- smart Bootstrap pagination with ellipsis -->
    <?php if ($totalPages > 1): ?>
      <nav>
        <ul class="pagination justify-content-center">

          <!-- Previous -->
          <li class="page-item <?= $page<=1 ? 'disabled':'' ?>">
            <a class="page-link" href="?page=userRequest&pagination=<?= max(1,$page-1) ?>">
              Previous
            </a>
          </li>

          <?php
          $range = 2; $ell=false;
          for ($i=1; $i<=$totalPages; $i++) {
            if ($i==1 || $i==$totalPages || abs($i-$page)<=$range) {
              echo "<li class='page-item ".($i==$page?'active':'')."'>
                      <a class='page-link' href='?page=userRequest&pagination=$i'>$i</a>
                    </li>";
              $ell=false;
            } else {
              if (!$ell) {
                echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                $ell = true;
              }
            }
          }
          ?>

          <!-- Next -->
          <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="?page=userRequest&pagination=<?= min($totalPages,$page+1) ?>">
              Next
            </a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>

  </div>
</div>

<?php
$st->close();
$conn->close();
?>
