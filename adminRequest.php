<?php
$userId = (int)$_SESSION['loggedInUserID'];

// ── 0) FILTER SETUP ──────────────────────────────────────────────────────────
$requestTypes = [
  'All'             => null,
  'Barangay ID'     => 'barangay_id_requests',
  'Business Permit' => 'business_permit_requests',
  'Certification'   => 'certification_requests'
];

$filter     = $_GET['filter']     ?? 'All';
$pagination = isset($_GET['pagination']) && is_numeric($_GET['pagination']) ? (int)$_GET['pagination'] : 1;

// ── 1) DETAIL VIEW ───────────────────────────────────────────────────────────
if (isset($_GET['transaction_id'])) {
    $tx = $_GET['transaction_id'];

    // fetch base view row
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

    // determine underlying table name
    switch ($vrow['request_type']) {
      case 'Barangay ID':      $tbl = 'barangay_id_requests';    break;
      case 'Business Permit':  $tbl = 'business_permit_requests';break;
      case 'Certification':    $tbl = 'certification_requests';  break;
      default:                 $tbl = null;
    }

    echo "<div class='container py-3'>";
    echo "  <div id='detailsArea' class='card shadow-sm p-4 mb-4'>";
    echo "    <div class='d-flex justify-content-end gap-2 mb-3'>";
    echo "      <button class='btn btn-success' data-bs-toggle='modal' data-bs-target='#editModal'"
       . " onclick=\"populateEditModal('" . htmlspecialchars($vrow['transaction_id']) . "','"
       . htmlspecialchars($tbl) . "','"
       . htmlspecialchars($vrow['payment_status']) . "','"
       . htmlspecialchars($vrow['document_status']) . "')\">Edit</button>";
    echo "      <button class='btn btn-primary' onclick='printDetails()'>Print</button>";
    echo "    </div>";
    echo "    <h5 class='fw-bold mb-3'>Full Details for {$tx}</h5>";

    if ($tbl) {
        $dsql = "SELECT * FROM {$tbl} WHERE transaction_id = ? LIMIT 1";
        $dst  = $conn->prepare($dsql);
        $dst->bind_param('s', $tx);
        $dst->execute();
        $drow = $dst->get_result()->fetch_assoc();
        $dst->close();

        if ($drow) {
            $exclude = ['id','account_id','transaction_id','created_at'];
            echo "<dl class='row'>";
            foreach ($drow as $col => $val) {
                if ($val === null || in_array($col,$exclude,true)) continue;
                $label = ucwords(str_replace('_',' ',$col));
                echo "<dt class='col-sm-3'>{$label}</dt>";
                echo "<dd class='col-sm-9'>" . htmlspecialchars($val) . "</dd>";
            }
            echo "</dl>";
        } else {
            echo "<p class='text-danger'>No record found in <code>{$tbl}</code>.</p>";
        }
    } else {
        echo "<p class='text-danger'>Unknown request type: <strong>" . htmlspecialchars($vrow['request_type']) . "</strong></p>";
    }

    echo "    <a href='?page=adminRequest&filter=" . urlencode($filter) . "&pagination={$pagination}' class='btn btn-secondary mt-3'>← Back to list</a>";
    echo "  </div>";  // close detailsArea
    echo "</div>";    // close container
    ?>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form id="editForm" method="POST" action="functions/update_data.php">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Edit Request</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="transaction_id" id="editTx">
              <input type="hidden" name="table_type" id="editTbl">
              <input type="hidden" name="current_filter" id="editFilter" value="<?= htmlspecialchars($filter) ?>">
              <input type="hidden" name="pagination" id="editPage" value="<?= $pagination ?>">
              <div class="mb-3">
                <label class="form-label">Payment Status</label>
                <select class="form-select" name="payment_status" id="editPayment">
                  <option>Unpaid</option>
                  <option>Paid</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Document Status</label>
                <select class="form-select" name="document_status" id="editDocument">
                  <option>For Verification</option>
                  <option>Rejected</option>
                  <option>Processing</option>
                  <option>Ready To Release</option>
                  <option>Released</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">OR Number</label>
                <input type="text" class="form-control" name="or_number" id="editOrNumber" readonly>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-success">Save Changes</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <script>
    // Edit modal populate
    function populateEditModal(tx, tbl, pay, doc) {
      document.getElementById('editTx').value = tx;
      document.getElementById('editTbl').value = tbl;
      document.getElementById('editPayment').value = pay;
      document.getElementById('editDocument').value = doc;
      document.getElementById('editOrNumber').value = '';
      toggleOrField();
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('editDocument').addEventListener('change', toggleOrField);
      toggleOrField();
    });

    function toggleOrField() {
      const docVal = document.getElementById('editDocument').value;
      const orFld = document.getElementById('editOrNumber');
      if (docVal === 'Released') {
        orFld.removeAttribute('readonly');
      } else {
        orFld.setAttribute('readonly','readonly');
        orFld.value = '';
      }
    }

    // Print details area only
    function printDetails() {
      const printContents = document.getElementById('detailsArea').innerHTML;
      const originalContents = document.body.innerHTML;
      document.body.innerHTML = printContents;
      window.print();
      document.body.innerHTML = originalContents;
      window.location.reload();
    }
    </script>

    <style>
    @media print {
      body * {
        visibility: hidden;
      }
      #detailsArea, #detailsArea * {
        visibility: visible;
      }
      #detailsArea {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
      }
    }
    </style>

    <?php
    exit();
}

// ── 2) LIST + PAGINATION + FILTER ────────────────────────────────────────────
$where = '';
$params = [];
if ($filter !== 'All') {
    $where = "WHERE request_type = ?";
    $params[] = $filter;
}

// count total
$countSql = "SELECT COUNT(*) AS total FROM view_general_requests {$where}";
$cst = $conn->prepare($countSql);
if ($where) {
    $cst->bind_param('s', $params[0]);
}
$cst->execute();
$totalRows = $cst->get_result()->fetch_assoc()['total'];
$cst->close();
$totalPages = ceil($totalRows / 10);

// fetch page
$limit = 10;
$offset = ($pagination - 1) * $limit;
$sql = "
  SELECT transaction_id,
         full_name,
         request_type,
         payment_method,
         payment_status,
         document_status,
         DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') AS formatted_date
    FROM view_general_requests
    {$where}
    ORDER BY created_at ASC
    LIMIT ? OFFSET ?
";
$st = $conn->prepare($sql);
if ($where) {
    $st->bind_param('sii', $params[0], $limit, $offset);
} else {
    $st->bind_param('ii', $limit, $offset);
}
$st->execute();
$result = $st->get_result();
?>

<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold">Requests</h4>
    <div class="dropdown">
      <button class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
        <?= htmlspecialchars($filter) ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach (array_keys($requestTypes) as $name): ?>
          <li><a class="dropdown-item" href="?page=adminRequest&filter=<?= urlencode($name) ?>"><?= htmlspecialchars($name) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div class="card shadow-sm p-3">
    <div class="table-responsive" style="height:500px; overflow-y:auto;">
      <table class="table table-hover align-middle text-start">
        <thead class="table-light">
          <tr>
            <th>Transaction No.</th>
            <th>Name</th>
            <th>Request</th>
            <th>Payment Method</th>
            <th>Payment Status</th>
            <th>Document Status</th>
            <th>Created At</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr style="cursor:pointer" onclick="window.location.href='?page=adminRequest&filter=<?= urlencode($filter) ?>&pagination=<?= $pagination ?>&transaction_id=<?= urlencode($row['transaction_id']) ?>'">
                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['request_type']) ?></td>
                <td><?= htmlspecialchars($row['payment_method']) ?></td>
                <td><?= htmlspecialchars($row['payment_status']) ?></td>
                <td><?= htmlspecialchars($row['document_status']) ?></td>
                <td><?= $row['formatted_date'] ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="text-center">No requests found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav>
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $pagination<=1?'disabled':'' ?>">
          <a class="page-link" href="?page=adminRequest&filter=<?= urlencode($filter) ?>&pagination=<?= max(1,$pagination-1) ?>">Previous</a>
        </li>
        <?php
        $range = 2; $ell = false;
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i==1||$i==$totalPages||abs($i-$pagination)<=$range) {
                echo "<li class='page-item" . ($i==$pagination?' active':'') . "'><a class='page-link' href='?page=adminRequest&filter=" . urlencode($filter) . "&pagination=$i'>$i</a></li>";
                $ell = false;
            } elseif (!$ell) {
                echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
                $ell = true;
            }
        }
        ?>
        <li class="page-item <?= $pagination>=$totalPages?'disabled':'' ?>">
          <a class="page-link" href="?page=adminRequest&filter=<?= urlencode($filter) ?>&pagination=<?= min($totalPages,$pagination+1) ?>">Next</a>
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