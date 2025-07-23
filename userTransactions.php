<?php
$userId = (int) $_SESSION['loggedInUserID'];

// ── 1) DETAIL VIEW ───────────────────────────────────────────────────────────
if (isset($_GET['transaction_id'])) {
    $tx = $_GET['transaction_id'];

    // fetch the view row to know its request_type (and ensure Released)
    $vsql = "
      SELECT * 
        FROM view_request 
       WHERE transaction_id = ? 
         AND payment_status = 'Paid'
         AND document_status = 'Released'
       LIMIT 1
    ";
    $vst = $conn->prepare($vsql);
    $vst->bind_param('s', $tx);
    $vst->execute();
    $vrow = $vst->get_result()->fetch_assoc();
    $vst->close();

    if (!$vrow) {
        echo "<div class='alert alert-danger'>Released request not found.</div>";
        exit();
    }

    // pick the correct base table
    switch ($vrow['request_type']) {
        case 'Barangay ID':
            $tbl = 'barangay_id_requests'; break;
        case 'Business Permit':
            $tbl = 'business_permit_requests'; break;
        case 'Certification':
            $tbl = 'certification_requests'; break;
        case 'Indigency':
            $tbl = 'indigency_requests'; break;
        case 'Residency':
            $tbl = 'residency_requests'; break;
        case 'Good Moral':
            $tbl = 'good_moral_requests'; break;
        case 'Solo Parent':
            $tbl = 'solo_parent_requests'; break;
        case 'Guardianship':
            $tbl = 'guardianship_requests'; break;
        default:
            $tbl = null;
    }

    echo "<div class='container py-3'>";
    echo "  <div class='card shadow-sm p-4 mb-4'>";
    echo "    <h5 class='fw-bold mb-3'>Transaction Details for {$tx}</h5>";

    if ($tbl) {
        $dsql = "SELECT * FROM {$tbl} WHERE transaction_id = ? LIMIT 1";
        $dst  = $conn->prepare($dsql);
        $dst->bind_param('s', $tx);
        $dst->execute();
        $drow = $dst->get_result()->fetch_assoc();
        $dst->close();

        if ($drow) {
            // hide these in detail
            $exclude = ['id','account_id','transaction_id','created_at'];
            echo "<dl class='row'>";
            foreach ($drow as $col => $val) {
                if ($val === null || in_array($col, $exclude, true)) {
                    continue;
                }
                $label = ucwords(str_replace('_',' ',$col));
                echo "<dt class='col-sm-3'>{$label}</dt>";
                echo "<dd class='col-sm-9'>" . htmlspecialchars($val) . "</dd>";
            }
            echo "</dl>";
        } else {
            echo "<p class='text-danger'>No detailed record found in <code>{$tbl}</code>.</p>";
        }
    } else {
        echo "<p class='text-danger'>Unknown request type: <strong>"
             . htmlspecialchars($vrow['request_type'])
             . "</strong></p>";
    }

    // back link preserves pagination
    $backPage = isset($_GET['pagination']) ? (int) $_GET['pagination'] : 1;
    echo "<a href='?page=userTransactions&pagination={$backPage}' 
               class='btn btn-secondary mt-3'>← Back to history</a>";
    echo "  </div>";
    echo "</div>";
    exit();
}

// ── 2) LIST + PAGINATION ─────────────────────────────────────────────────────
$limit  = 10;
$page   = isset($_GET['pagination']) && is_numeric($_GET['pagination'])
          ? (int) $_GET['pagination']
          : 1;
$offset = ($page - 1) * $limit;

// count only released
$countSql = "
  SELECT COUNT(*) AS total
    FROM view_request
   WHERE account_id = ?
     AND document_status = 'Released'
";
$cst = $conn->prepare($countSql);
$cst->bind_param('i', $userId);
$cst->execute();
$totalRows  = $cst->get_result()->fetch_assoc()['total'];
$cst->close();
$totalPages = ceil($totalRows / $limit);

// fetch page of released transactions
$sql = "
  SELECT transaction_id,
         full_name,
         request_type,
         DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') AS formatted_date
    FROM view_request
   WHERE account_id = ?
     AND document_status = 'Released'
   ORDER BY created_at DESC
   LIMIT ? OFFSET ?
";
$st = $conn->prepare($sql);
$st->bind_param('iii', $userId, $limit, $offset);
$st->execute();
$result = $st->get_result();
?>

<title>eBarangay Mo | Transactions</title>

<div class="container py-3">
  <div class="card shadow-sm p-3">
    <div class="table-responsive" style="height:500px; overflow-y:auto;">
      <table class="table align-middle text-start table-hover">
        <thead class="table-light">
          <tr>
            <th>Transaction No.</th>
            <th>Name</th>
            <th>Request</th>
            <th>Release Date</th>
            <!-- <th>OR No.</th> -->
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr style="cursor:pointer"
                  onclick="window.location.href='?page=userTransactions&pagination=<?= $page ?>&transaction_id=<?= urlencode($row['transaction_id']) ?>'">
                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['request_type']) ?></td>
                <td><?= $row['formatted_date'] ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="text-center">No released transactions found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- smart Bootstrap pagination -->
    <?php if ($totalPages > 1): ?>
      <nav>
        <ul class="pagination justify-content-center">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?page=userTransactions&pagination=<?= max(1, $page-1) ?>">
              Previous
            </a>
          </li>

          <?php
          $range = 2; $ell = false;
          for ($i = 1; $i <= $totalPages; $i++) {
              if ($i == 1 || $i == $totalPages || abs($i - $page) <= $range) {
                  echo "<li class='page-item".($i==$page?' active':'')."'>"
                     ."<a class='page-link' href='?page=userTransactions&pagination=$i'>$i</a>"
                     ."</li>";
                  $ell = false;
              } elseif (!$ell) {
                  echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                  $ell = true;
              }
          }
          ?>

          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?page=userTransactions&pagination=<?= min($totalPages, $page+1) ?>">
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
