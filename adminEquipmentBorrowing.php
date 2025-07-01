<?php
// equipment.php
require 'functions/dbconn.php';

// 1) Load all equipment
$eqRes = $conn->query("SELECT * FROM equipment_list ORDER BY id");
$equipments = $eqRes->fetch_all(MYSQLI_ASSOC);

// 2) Load all borrow requests
$brRes = $conn->query("SELECT * FROM borrow_requests ORDER BY date DESC, id DESC");
$borrows = $brRes->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>eBarangay Mo | Equipment Borrowing</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    .readonly { cursor: not-allowed; }
  </style>
</head>
<body>
<div class="container py-4">

  <!-- ──────────────────────────────── -->
  <!-- Card #1: List of Equipment -->
  <!-- ──────────────────────────────── -->
  <div class="card shadow-sm mb-5">
    <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white p-3">
      <h5 class="mb-0"><i class="fas fa-tools me-2"></i>List of Equipments</h5>
    </div>
    <div class="card-body p-0">
      <div class="d-flex justify-content-end m-3">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
          <i class="fas fa-plus me-1"></i> Add New Equipment
        </button>
      </div>
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Equipment SN</th>
              <th>Name</th>
              <th>Description</th>
              <th>Avail Qty</th>
              <th>Total Qty</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($equipments)): ?>
              <tr><td colspan="6" class="text-center py-3">No equipment found.</td></tr>
            <?php else: foreach($equipments as $eq): ?>
              <tr>
                <td><?= htmlspecialchars($eq['equipment_sn']) ?></td>
                <td><?= htmlspecialchars($eq['name']) ?></td>
                <td><?= nl2br(htmlspecialchars($eq['description']))?: '—' ?></td>
                <td><?= (int)$eq['available_qty'] ?></td>
                <td><?= (int)$eq['total_qty'] ?></td>
                <td>
                  <button
                    class="btn btn-sm btn-primary me-1 edit-btn"
                    data-id="<?= $eq['id'] ?>"
                    data-name="<?= htmlspecialchars($eq['name'], ENT_QUOTES) ?>"
                    data-desc="<?= htmlspecialchars($eq['description'], ENT_QUOTES) ?>"
                    data-total="<?= (int)$eq['total_qty'] ?>"
                    data-avail="<?= (int)$eq['available_qty'] ?>">
                    Edit
                  </button>
                  <button
                    class="btn btn-sm btn-danger delete-equipment-btn"
                    data-id="<?= $eq['id'] ?>"
                    data-name="<?= htmlspecialchars($eq['name'], ENT_QUOTES) ?>">
                    Delete
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ──────────────────────────────── -->
  <!-- Card #2: Borrow Requests -->
  <!-- ──────────────────────────────── -->
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white p-3">
      <h5 class="mb-0"><i class="fas fa-book-reader me-2"></i>Borrow Requests</h5>
    </div>
    <div class="card-body p-0">
      <div class="d-flex justify-content-end m-3">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBorrowModal">
        <i class="fas fa-plus me-1"></i> Borrow an Equipment
      </button>
      </div>
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Resident’s Name</th>
              <th>Borrowed ESN</th>
              <th>Qty</th>
              <th>Location</th>
              <th>Used For</th>
              <th>Date</th>
              <th>PUDO</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($borrows)): ?>
              <tr><td colspan="9" class="text-center py-3">No borrow requests.</td></tr>
            <?php else: foreach($borrows as $br): ?>
              <tr>
                <td><?= htmlspecialchars($br['resident_name']) ?></td>
                <td><?= htmlspecialchars($br['equipment_sn']) ?></td>
                <td><?= (int)$br['qty'] ?></td>
                <td><?= htmlspecialchars($br['location']) ?></td>
                <td><?= htmlspecialchars($br['used_for']) ?></td>
                <td><?= htmlspecialchars($br['date']) ?></td>
                <td><?= htmlspecialchars($br['pudo']) ?></td>
                <td>
                  <select class="form-select form-select-sm borrow-status" 
                          data-id="<?= $br['id'] ?>">
                    <option <?= $br['status']==='Borrowed' ? 'selected':'' ?>>Borrowed</option>
                    <option <?= $br['status']==='Returned' ? 'selected':'' ?>>Returned</option>
                  </select>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- ──────────────────────────────── -->
<!-- Modals -->
<!-- ──────────────────────────────── -->

<!-- Add Equipment Modal -->
<div class="modal fade" id="addEquipmentModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="functions/equipment_add.php">
      <div class="modal-header">
        <h5 class="modal-title">Add Equipment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input name="name" type="text" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Total Quantity</label>
          <input name="total_qty" type="number" min="1" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Equipment Modal -->
<div class="modal fade" id="editEquipmentModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="functions/equipment_edit.php">
      <div class="modal-header">
        <h5 class="modal-title">Edit Equipment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="edit-id">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input name="name" id="edit-name" type="text" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit-desc" class="form-control" rows="3"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Total Quantity</label>
          <input name="total_qty" id="edit-total" type="number" min="1" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>


<!-- ──────────────────────────────── -->
<!-- Add Borrow Request Modal (wider) -->
<!-- ──────────────────────────────── -->
<div class="modal fade" id="addBorrowModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST" action="functions/borrow_add.php">
      <div class="modal-header">
        <h5 class="modal-title">New Borrow Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row gx-3">
          <!-- Row 1: name + ESN -->
          <div class="col-md-6 mb-3">
            <label class="form-label">Resident’s Name</label>
            <input name="resident_name" type="text" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3 position-relative">
            <label class="form-label">Equipment SN</label>
            <input
              type="text"
              id="borrowedEsn"
              name="equipment_sn"
              class="form-control"
              placeholder="Type or select ESN"
              autocomplete="off"
              required>
            <ul
             id="borrowedEsnList"
             class="list-group position-absolute w-100 shadow-sm bg-white"
             style="
               top: 100%;        /* sit just below the input */
               left: 0;
               z-index: 1050;    /* above the modal backdrop */
               max-height: 150px;
               overflow-y: auto;
               display: none;
             ">
            </ul>
          </div>

          <!-- Row 2: qty + location -->
          <div class="col-md-6 mb-3">
            <label class="form-label">Qty</label>
            <input name="qty" id="qtyInput" type="number" min="1" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Location</label>
            <input name="location" type="text" class="form-control" required>
          </div>

          <!-- Row 3: used_for + PUDO -->
          <div class="col-md-6 mb-3">
            <label class="form-label">Used For</label>
            <input name="used_for" type="text" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Pick‑Up / Drop‑Off</label>
            <select name="pudo" class="form-select" required>
              <option>Pick Up</option>
              <option>Drop Off</option>
            </select>
          </div>

          <!-- Row 4: date only -->
          <!-- <div class="col-md-6 mb-3">
            <label class="form-label">Date</label>
            <input name="date" type="date" class="form-control" required>
          </div> -->
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success">Save Request</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal (re-used) -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body text-center">
        <p id="confirmDeleteText"></p>
        <div class="d-flex justify-content-around">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <form id="confirmDeleteForm" method="POST">
            <button type="submit" class="btn btn-danger">Delete</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const esnMap = <?= json_encode($jsMap) ?>;

  (function(){
    const esnOptions = <?= json_encode(array_column($equipments,'equipment_sn')) ?>;
    const input      = document.getElementById('borrowedEsn');
    const list       = document.getElementById('borrowedEsnList');

    function rebuildList(items) {
        list.innerHTML = '';
        items.forEach(esn => {
        const li = document.createElement('li');
        li.textContent = esn;
        li.className   = 'list-group-item list-group-item-action py-1';
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

    // Show the full list on focus OR click
    input.addEventListener('focus', () => rebuildList(esnOptions));
    input.addEventListener('click', () => rebuildList(esnOptions));

    // Filter as the user types
    input.addEventListener('input', () => {
        const v = input.value.trim().toLowerCase();
        const filtered = v
        ? esnOptions.filter(e => e.toLowerCase().includes(v))
        : esnOptions;
        rebuildList(filtered);
    });

    // Hide after blur (small timeout to allow click on an <li>)
    input.addEventListener('blur', () => setTimeout(() => {
        list.style.display = 'none';
    }, 150));

    // Also hide if clicking anywhere else
    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !list.contains(e.target)) {
        list.style.display = 'none';
        }
    });
    })();

  // when ESN changes, cap qty ≤ available
  document.getElementById('borrowedEsn').addEventListener('change', function(){
    const chosen = this.value;
    const avail  = esnMap[chosen] || 0;    // lookup from your PHP‑generated map
    const qtyIn  = document.getElementById('qtyInput');
    qtyIn.max         = avail;
    qtyIn.placeholder = avail
        ? `(max ${avail})`
        : `(unknown ESN)`;
  });

  // ── Equipment “Edit” wiring ─────────────────────
  document.querySelectorAll('.edit-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      document.getElementById('edit-id').value   = btn.dataset.id;
      document.getElementById('edit-name').value = btn.dataset.name;
      document.getElementById('edit-desc').value = btn.dataset.desc;
      document.getElementById('edit-total').value= btn.dataset.total;
    });
  });

  // ── Delete Equipment ───────────────────────────
  document.querySelectorAll('.delete-equipment-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id   = btn.dataset.id;
      const name = btn.dataset.name;
      document.getElementById('confirmDeleteText').textContent = 
        `Delete “${name}”? This cannot be undone.`;
      const form = document.getElementById('confirmDeleteForm');
      form.action = 'functions/equipment_delete.php';
      form.innerHTML = `<input type="hidden" name="id" value="${id}">` +
                       `<button type="submit" class="btn btn-danger">Delete</button>`;
      new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
    });
  });

  // ── Status dropdown change (optional AJAX hook) ─
  document.querySelectorAll('.borrow-status').forEach(sel=>{
    sel.addEventListener('change', ()=>{
      fetch('functions/borrow_toggle_status.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`id=${sel.dataset.id}&status=${sel.value}`
      });
    });
  });
</script>
</body>
</html>