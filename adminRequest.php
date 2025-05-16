<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];

// ── 0) FILTER SETUP ──────────────────────────────────────────────────────────
$request_type    = $_GET['request_type']    ?? '';
$date_from       = $_GET['date_from']       ?? '';
$date_to         = $_GET['date_to']         ?? '';
$payment_method  = $_GET['payment_method']  ?? '';
$payment_status  = $_GET['payment_status']  ?? '';
$document_status = $_GET['document_status'] ?? '';

$whereClauses = [];
$bindTypes    = '';
$bindParams   = [];

if ($request_type) {
  $whereClauses[] = 'request_type = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $request_type;
}
if ($payment_method) {
  $whereClauses[] = 'payment_method = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $payment_method;
}
if ($payment_status) {
  $whereClauses[] = 'payment_status = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $payment_status;
}
if ($document_status) {
  $whereClauses[] = 'document_status = ?';
  $bindTypes    .= 's';
  $bindParams[]  = $document_status;
}
if ($date_from && $date_to) {
  $whereClauses[] = 'DATE(created_at) BETWEEN ? AND ?';
  $bindTypes    .= 'ss';
  $bindParams[]  = $date_from;
  $bindParams[]  = $date_to;
} elseif ($date_from) {
  $whereClauses[] = 'DATE(created_at) >= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $date_from;
} elseif ($date_to) {
  $whereClauses[] = 'DATE(created_at) <= ?';
  $bindTypes    .= 's';
  $bindParams[]  = $date_to;
}

$whereSQL = $whereClauses
  ? 'WHERE ' . implode(' AND ', $whereClauses)
  : '';

$limit = 10; // records per page
$page = isset($_GET['page_num']) ? max((int)$_GET['page_num'], 1) : 1;
$offset = ($page - 1) * $limit;
  
$countSQL = "SELECT COUNT(*) AS total FROM view_general_requests {$whereSQL}";
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
$countResult = $countStmt->get_result()->fetch_assoc();
$totalRows = $countResult['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

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
    echo "<div class='d-flex justify-content-between align-items-start '>";
    echo "  <h5 class='fw-bold'>Full Details for {$tx}</h5>";
    echo "    <a href='?page=adminRequest' class='btn btn-secondary'>";
    echo "      <span class='material-symbols-outlined'>close_small</span>";
    echo "    </a>";  
    echo "</div>";
    
    if ($tbl) {
        $dsql = "SELECT * FROM {$tbl} WHERE transaction_id = ? LIMIT 1";
        $dst  = $conn->prepare($dsql);
        $dst->bind_param('s', $tx);
        $dst->execute();
        $drow = $dst->get_result()->fetch_assoc();
        $dst->close();

        if ($drow) {
          $exclude = ['id','account_id','transaction_id','created_at'];
          echo "<form method='post'>";
          foreach ($drow as $col => $val) {
              if ($val === null || in_array($col, $exclude, true)) continue;
              $label   = ucwords(str_replace('_', ' ', $col));
              $safeVal = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
              echo "<div class='row mb-2'>";
              // fixed‐width label column
              echo "  <div class='col-sm-2 fw-bold'>";
              echo "    <label class='col-form-label' style='font-size:0.75rem;'>$label</label>";
              echo "  </div>";
              // fixed‐width input column (won't span full container)
              echo "  <div class='col-sm-7'>";
              if ($col === 'payment_status') {
                echo "<select class='form-select form-select-sm' style='font-size:0.75rem;' name='$col' disabled>";
                foreach (['Paid', 'Unpaid'] as $opt) {
                  $sel = $opt === $val ? 'selected' : '';
                  echo "<option value='$opt' $sel>$opt</option>";
                }
                echo "</select>";
              } elseif ($col === 'document_status') {
                echo "<select class='form-select form-select-sm' style='font-size:0.75rem;' name='$col' disabled>";
                foreach (['For Verification','Processing','Ready To Release','Released','Rejected'] as $opt) {
                  $sel = $opt === $val ? 'selected' : '';
                  echo "<option value='$opt' $sel>$opt</option>";
                }
                echo "</select>";
              } else {
                echo "<input type='text' name='$col' class='form-control' style='font-size:0.75rem;' value='$safeVal' readonly>";

              }
              echo "  </div>";
              echo "</div>";
          }
          echo "</form>";
      } else {
          echo "<p class='text-danger'>No record found in <code>{$tbl}</code>.</p>";
      }      
    } else {
        echo "<p class='text-danger'>Unknown request type: <strong>" . htmlspecialchars($vrow['request_type']) . "</strong></p>";
    }

    // — View mode buttons (shown by default) —
    echo "<div id='groupView' class='btn-group w-100 mt-3' role='group'>";
    echo "  <button id='deleteBtn' type='button' class='btn btn-outline-danger me-1'>Delete</button>";
    echo "  <button id='editBtn'   type='button' class='btn btn-outline-primary me-1'>Edit</button>";
    echo "  <button id='certBtn'   type='button' class='btn btn-outline-secondary'>Generate Certificate</button>";
    echo "</div>";

    // — Edit mode buttons (hidden initially) —
    echo "<div id='groupEdit' class='btn-group w-100 mt-3 d-none' role='group'>";
    echo "  <button id='cancelBtn' type='button' class='btn btn-outline-danger me-1'>Cancel</button>";
    echo "  <button id='saveBtn'   type='button' class='btn btn-primary'>Save</button>";
    echo "</div>";
    echo "  </div>";  // close detailsArea
    echo "</div>";    // close container

    ?>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
      const editBtn   = document.getElementById('editBtn');
      const deleteBtn = document.getElementById('deleteBtn');
      const certBtn   = document.getElementById('certBtn');
      const cancelBtn = document.getElementById('cancelBtn');
      const saveBtn   = document.getElementById('saveBtn');
      const groupView = document.getElementById('groupView');
      const groupEdit = document.getElementById('groupEdit');
      const form      = document.querySelector('#detailsArea form');
      const inputs    = form.querySelectorAll('input, select');

      // This object will hold the original values
      let originalValues = {};

      // Enter edit mode
      editBtn.addEventListener('click', () => {
        // Store originals
        inputs.forEach(i => {
          originalValues[i.name] = i.value;
          i.removeAttribute('readonly');
          i.removeAttribute('disabled');
        });
        groupView.classList.add('d-none');
        groupEdit.classList.remove('d-none');
      });

      // Cancel edit
      cancelBtn.addEventListener('click', () => {
        // Restore originals
        inputs.forEach(i => {
          if (originalValues.hasOwnProperty(i.name)) {
            i.value = originalValues[i.name];
          }
          // Then put back to read‐only/disabled
          if (i.tagName === 'INPUT') {
            i.setAttribute('readonly', '');
          } else {
            i.setAttribute('disabled', '');
          }
        });

        // Clear stored originals (optional)
        originalValues = {};

        groupEdit.classList.add('d-none');
        groupView.classList.remove('d-none');
      });

      // Save changes
      saveBtn.addEventListener('click', () => {
        form.submit();
      });

      // Delete action
      deleteBtn.addEventListener('click', () => {
        if (confirm('Really delete?')) {
          window.location = `?page=adminRequest&action=delete&transaction_id=<?=urlencode($tx)?>`;
        }
      });
    });
    </script>

    <?php
    exit();
}

// ── 2) LIST + FILTERED QUERY ─────────────────────────────────────────────────
$sql = "
  SELECT transaction_id,
         full_name,
         request_type,
         payment_method,
         payment_status,
         document_status,
         DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') AS formatted_date
    FROM view_general_requests
    {$whereSQL}
    ORDER BY created_at DESC
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
  <div class="d-flex justify-content-between align-items-center mb-3">
    <!-- modal trigger -->
    <!-- <button type="button"
            class="btn btn-success"
            data-bs-toggle="modal"
            data-bs-target="#newRequestModal">
      Add New Request
    </button>  -->
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
        <form method="get" class="mb-0" id="filterForm">
          <!-- preserve the page -->
          <input type="hidden" name="page" value="adminRequest">

          <!-- Request Type -->
          <div class="mb-2">
            <label class="form-label mb-1">Request Type</label>
            <select name="request_type" class="form-select form-select-sm" style="font-size:.75rem;">
              <option value="">All</option>
              <option <?= $request_type==='Barangay ID'?'selected':''?>      value="Barangay ID">Barangay ID</option>
              <option <?= $request_type==='Business Permit'?'selected':''?> value="Business Permit">Business Permit</option>
              <option <?= $request_type==='Certification'?'selected':''?>   value="Certification">Certification</option>
            </select>
          </div>

          <!-- Date Created -->
          <div class="mb-2">
            <label class="form-label mb-1">Date Created</label>
            <div class="d-flex">
              <input type="date" name="date_from" class="form-control form-control-sm me-1" style="font-size:.75rem;" value="<?=htmlspecialchars($date_from)?>">
              <input type="date" name="date_to"   class="form-control form-control-sm" style="font-size:.75rem;" value="<?=htmlspecialchars($date_to)?>">
            </div>
          </div>

          <!-- Payment Method -->
          <div class="mb-2">
            <label class="form-label mb-1">Payment Method</label>
            <select name="payment_method" class="form-select form-select-sm" style="font-size:.75rem;">
              <option value="">All</option>
              <option <?= $payment_method==='GCash'?'selected':''?>          value="GCash">GCash</option>
              <option <?= $payment_method==='Brgy Payment Device'?'selected':''?> value="Brgy Payment Device">Brgy Payment Device</option>
              <option <?= $payment_method==='Over-the-Counter'?'selected':''?>    value="Over-the-Counter">Over-the-Counter</option>
            </select>
          </div>

          <!-- Payment Status -->
          <div class="mb-2">
            <label class="form-label mb-1">Payment Status</label>
            <select name="payment_status" class="form-select form-select-sm" style="font-size:.75rem;">
              <option value="">All</option>
              <option <?= $payment_status==='Paid'?'selected':''?>   value="Paid">Paid</option>
              <option <?= $payment_status==='Unpaid'?'selected':''?> value="Unpaid">Unpaid</option>
            </select>
          </div>

          <!-- Document Status -->
          <div class="mb-2">
            <label class="form-label mb-1">Document Status</label>
            <select name="document_status" class="form-select form-select-sm" style="font-size:.75rem;">
              <option value="">All</option>
              <option <?= $document_status==='For Verification'?'selected':''?> value="For Verification">For Verification</option>
              <option <?= $document_status==='Processing'?'selected':''?>       value="Processing">Processing</option>
              <option <?= $document_status==='Ready To Release'?'selected':''?> value="Ready To Release">Ready To Release</option>
              <option <?= $document_status==='Released'?'selected':''?>         value="Released">Released</option>
              <option <?= $document_status==='Rejected'?'selected':''?>         value="Rejected">Rejected</option>
            </select>
          </div>

          <div class="d-flex">
            <a href="?page=adminRequest" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
            <button type="submit" class="btn btn-sm btn-success flex-grow-1">Apply</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- end of your filter/header row -->

  <!-- New Request Modal -->
  <div class="modal fade" id="newRequestModal" tabindex="-1" aria-labelledby="newRequestModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-2xl shadow-lg border-0">

      <!-- Header -->
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>

      <!-- Body: selection pills + dynamic form -->
      <div class="modal-body p-4">

        <!-- Request Type Pills -->
        <ul class="nav nav-pills nav-fill mb-4" id="requestTypeNav" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pill-barangay" data-bs-toggle="pill" data-bs-target="#pane-barangay" type="button" role="tab">
              <i class="bi bi-person-badge me-1"></i> Barangay ID
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="pill-business" data-bs-toggle="pill" data-bs-target="#pane-business" type="button" role="tab">
              <i class="bi bi-briefcase me-1"></i> Business Permit
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="pill-certification" data-bs-toggle="pill" data-bs-target="#pane-certification" type="button" role="tab">
              <i class="bi bi-file-earmark-check me-1"></i> Certification
            </button>
          </li>
        </ul>

        <div class="tab-content" id="requestTypeNavContent">
          <!-- Barangay ID Form Pane -->
          <div class="tab-pane fade show active" id="pane-barangay" role="tabpanel">
            <!-- placeholder: form will be injected here -->
            <div id="requestFormContainer"></div>
          </div>
          <!-- Other types can load their own forms later -->
          <div class="tab-pane fade" id="pane-business" role="tabpanel">
            <p class="text-muted text-center mt-5">Business Permit form coming soon...</p>
          </div>
          <div class="tab-pane fade" id="pane-certification" role="tabpanel">
            <p class="text-muted text-center mt-5">Certification form coming soon...</p>
          </div>
        </div>

      </div>

      <!-- Footer: Submit -->
      <div class="modal-footer bg-light p-3 rounded-bottom-2xl">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-1"></i> Close
        </button>
        <button type="submit" id="submitRequestBtn" class="btn btn-success" disabled form="barangayIDForm">
          Submit Request
        </button>
      </div>

    </div>
  </div>
</div>


  <div class="card shadow-sm p-3">
    <div class="table-responsive admin-table" style="height:500px; overflow-y:auto;">
      <table class="table table-hover align-middle text-start">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">Transaction No.</th>
            <th class="text-nowrap">Name</th>
            <th class="text-nowrap">Request</th>
            <th class="text-nowrap">Payment Method</th>
            <th class="text-nowrap">Payment Status</th>
            <th class="text-nowrap">Document Status</th>
            <th class="text-nowrap">Created At</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr style="cursor:pointer" onclick="window.location.href='?page=adminRequest&transaction_id=<?=urlencode($row['transaction_id'])?>'">
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

      <?php if ($totalPages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center pagination-sm">
          <!-- Prev Button -->
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_num' => $page - 1])) ?>">Previous</a>
          </li>

          <?php
          $range = 2;
          $dots = false;

          for ($i = 1; $i <= $totalPages; $i++) {
            if (
              $i == 1 ||
              $i == $totalPages ||
              ($i >= $page - $range && $i <= $page + $range)
            ) {
              $active = $i == $page ? 'active' : '';
              echo "<li class='page-item {$active}'>
                      <a class='page-link' href='?" . http_build_query(array_merge($_GET, ['page_num' => $i])) . "'>$i</a>
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

<!-- <script>
  document.addEventListener('DOMContentLoaded', () => {
    const selector  = document.getElementById('requestType');
    const container = document.getElementById('requestFormContainer');
    const submitBtn = document.getElementById('submitRequestBtn');

    // Load Barangay ID form into the active pane
    function loadBarangayForm() {
      fetch('functions/adminBarangayIDForm.php')
        .then(res => res.text())
        .then(html => {
          container.innerHTML = html;
          submitBtn.setAttribute('form', 'barangayIDForm');
          submitBtn.disabled = false;
        })
        .catch(err => console.error('Load failed:', err));
    }

    // Initial load
    loadBarangayForm();

    // Tab event to reload form when switching back
    const tabEl = document.getElementById('pill-barangay');
    tabEl.addEventListener('shown.bs.tab', loadBarangayForm);
  });
</script> -->
