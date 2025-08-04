<?php
// equipment.php
require 'functions/dbconn.php';

// check for our single "added" param
$added = $_GET['added'] ?? null;

// 1) Load all equipment
$eqRes = $conn->query("SELECT * FROM equipment_list ORDER BY id");
$equipments = $eqRes->fetch_all(MYSQLI_ASSOC);

// 2) Load all borrow requests
$brRes = $conn->query("SELECT * FROM borrow_requests ORDER BY date DESC, id DESC");
$borrows = $brRes->fetch_all(MYSQLI_ASSOC);
?>

<div class="container p-3">
  <?php if ($added): ?>
    <div class="container mt-3">
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        Equipment <strong><?= htmlspecialchars($added) ?></strong> added successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    </div>
  <?php endif; ?>

  <?php if (($_GET['updated'] ?? '') === 'none'): ?>
    <div class="alert alert-secondary alert-dismissible fade show" role="alert">
      No changes were made. Equipment was not updated.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>


  <?php if (($_GET['updated'] ?? '') === 'partial'): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      Equipment was updated, but quantity was not changed because some items are currently borrowed.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif (($_GET['updated'] ?? '') === 'full'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Equipment updated successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (($_GET['deleted'] ?? '') === '1'): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      Equipment deleted permanently.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- List of Equipment -->
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
              <tr><td colspan="7" class="text-center">No equipment found.</td></tr>
            <?php else: foreach($equipments as $eq): ?>
              <tr>
                <td><?= htmlspecialchars($eq['equipment_sn']) ?></td>
                <td><?= htmlspecialchars($eq['name']) ?></td>
                <td><?= nl2br(htmlspecialchars($eq['description']))?: '—' ?></td>
                <td><?= (int)$eq['available_qty'] ?></td>
                <td><?= (int)$eq['total_qty'] ?></td>
                <td>
                  <button class="btn btn-sm btn-primary me-1 edit-equipment-btn" data-id="<?= $eq['id'] ?>" data-name="<?= htmlspecialchars($eq['name'], ENT_QUOTES) ?>" data-desc="<?= htmlspecialchars($eq['description'], ENT_QUOTES) ?>" data-total="<?= (int)$eq['total_qty'] ?>">
                    Edit
                  </button>
                  <button class="btn btn-sm btn-danger delete-equipment-btn" data-id="<?= $eq['id'] ?>" data-name="<?= htmlspecialchars($eq['name'], ENT_QUOTES) ?>">
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

  <!-- Borrow Requests -->
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
              <tr><td colspan="7" class="text-center">No borrow requests.</td></tr>
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
                  <select class="form-select form-select-sm borrow-status" data-id="<?= $br['id'] ?>">
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

  <!-- Add Equipment Modal -->
  <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="POST" action="functions/equipment_add.php">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="addEquipmentLabel"><i class="fas fa-plus-circle me-2"></i>Add New Equipment</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="new-equipment-name" class="form-label">Equipment Name</label>
            <input type="text" id="new-equipment-name" name="name" class="form-control" placeholder="e.g., Chairs, Tables, etc." autocomplete="off" required>
          </div>
          <div class="mb-3">
            <label for="new-equipment-desc" class="form-label">Description</label>
            <textarea id="new-equipment-desc" name="description" class="form-control" rows="2" placeholder="Briefly describe condition, brand, etc."></textarea>
          </div>
          <div class="mb-3">
            <label for="new-equipment-qty" class="form-label">Total Quantity</label>
            <input type="number" id="new-equipment-qty" name="total_qty" class="form-control" min="1" value="1" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save Equipment</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Equipment Modal -->
  <div class="modal fade" id="editEquipmentModal" tabindex="-1" aria-labelledby="editEquipmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="POST" action="functions/equipment_edit.php">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="editEquipmentLabel">
            <i class="fas fa-edit me-2"></i>Edit Equipment
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="edit-id">

          <div class="mb-3">
            <label for="edit-name" class="form-label">Equipment Name</label>
            <input name="name" id="edit-name" type="text" class="form-control" placeholder="e.g., Chairs, Tables, etc." required>
          </div>

          <div class="mb-3">
            <label for="edit-desc" class="form-label">Description</label>
            <textarea name="description" id="edit-desc" class="form-control" rows="2" placeholder="Briefly describe condition, brand, etc."></textarea>
          </div>

          <div class="mb-3">
            <label for="edit-total" class="form-label">Total Quantity</label>
            <input name="total_qty" id="edit-total" type="number" min="1" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update Equipment
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content shadow">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="deleteConfirmLabel">
            <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="confirmDeleteForm" method="POST">
          <div class="modal-body">
            <p id="confirmDeleteText" class="mb-0 fs-6 text-center fw-medium"></p>
            <input type="hidden" name="id" id="delete-id">
          </div>
          <div class="modal-footer d-flex justify-content-between px-4 pb-3">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i>Delete</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Add Borrow Modal -->
  <div class="modal fade" id="addBorrowModal" tabindex="-1" aria-labelledby="addBorrowLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <form class="modal-content" method="POST" action="functions/borrow_add.php">
        <div class="modal-header text-white" style="background-color: #13411F;">
          <h5 class="modal-title" id="addBorrowLabel"><i class="fas fa-book-reader me-2"></i>New Borrow Request</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row gy-3">
            <div class="col-md-6">
              <label for="borrow-resident-name" class="form-label">Resident’s Name</label>
              <input type="text" id="borrow-resident-name" name="resident_name" class="form-control" placeholder="Lastname, Firstname M." required>
            </div>

            <div class="col-md-6 position-relative">
              <label class="form-label">Equipment SN</label> <!-- for="borrow-equipment-esn" -->
              <input type="text" id="borrowedEsn" name="equipment_sn" class="form-control" placeholder="Type or select ESN" autocomplete="off" required>
              <ul id="borrowedEsnList" class="list-group position-absolute w-100 shadow-sm bg-white" style="top:100%; left:0; max-height:150px; overflow-y:auto; display:none;">
              </ul>
            </div>

            <div class="col-md-4">
              <label for="borrow-qty" class="form-label">Quantity</label>
              <input type="number" id="borrow-qty" name="qty" class="form-control" min="1" placeholder="1" required>
            </div>
            <div class="col-md-8">
              <label for="borrow-location" class="form-label">Location</label>
              <input type="text" id="borrow-location" name="location" class="form-control" placeholder="Office / Home / Event Venue" required>
            </div>

            <div class="col-md-6">
              <label for="borrow-used-for" class="form-label">Used For</label>
              <input type="text" id="borrow-used-for" name="used_for" class="form-control" placeholder="e.g., Presentation, Workshop" required>
            </div>
            <div class="col-md-6">
              <label for="borrow-pudo" class="form-label">Pick-Up / Drop-Off</label>
              <select id="borrow-pudo" name="pudo" class="form-select" required>
                <option value="">Choose…</option>
                <option>Pick Up</option>
                <option>Drop Off</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-1"></i>Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const esnMap = {};
  // const esnMap = <= json_encode($jsMap) ?>;

  // (function(){
  //   const esnOptions = <= json_encode(array_column($equipments,'equipment_sn')) ?>;
  //   const input = document.getElementById('borrowedEsn');
  //   const list = document.getElementById('borrowedEsnList');

  //   function rebuildList(items) {
  //     list.innerHTML = '';
  //     items.forEach(esn => {
  //     const li = document.createElement('li');
  //     li.textContent = esn;
  //     li.className = 'list-group-item list-group-item-action py-1';
  //     li.style.cursor = 'pointer';
  //     li.addEventListener('mousedown', () => {
  //       input.value = esn;
  //       list.style.display = 'none';
  //       input.dispatchEvent(new Event('change')); 
  //     });
  //     list.appendChild(li);
  //     });
  //     list.style.display = items.length ? 'block' : 'none';
  //   }

  //   // Show the full list on focus OR click
  //   input.addEventListener('focus', () => rebuildList(esnOptions));
  //   input.addEventListener('click', () => rebuildList(esnOptions));

  //   // Filter as the user types
  //   input.addEventListener('input', () => {
  //     const v = input.value.trim().toLowerCase();
  //     const filtered = v ? esnOptions.filter(e => e.toLowerCase().includes(v)) : esnOptions;
  //     rebuildList(filtered);
  //   });

  //   // Hide after blur (small timeout to allow click on an <li>)
  //   input.addEventListener('blur', () => setTimeout(() => {
  //       list.style.display = 'none';
  //   }, 150));

  //   // Also hide if clicking anywhere else
  //   document.addEventListener('click', e => {
  //     if (!input.contains(e.target) && !list.contains(e.target)) {
  //     list.style.display = 'none';
  //     }
  //   });
  //   })();

  // when ESN changes, cap qty ≤ available
  // document.getElementById('borrowedEsn').addEventListener('change', function(){
  //   const chosen = this.value;
  //   const avail = esnMap[chosen] || 0;    // lookup from your PHP‑generated map
  //   const qtyIn = document.getElementById('qtyInput');
  //   qtyIn.max = avail;
  //   qtyIn.placeholder = avail ? `(max ${avail})` : `(unknown ESN)`;
  // });

  // ── Delete Equipment ───────────────────────────
  document.querySelectorAll('.delete-equipment-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const name = btn.dataset.name;

      // Update confirmation text
      document.getElementById('confirmDeleteText').textContent = `Are you sure you want to delete “${name}”? This action cannot be undone.`;

      // Set hidden input value & form action
      document.getElementById('delete-id').value = id;
      document.getElementById('confirmDeleteForm').action = 'functions/equipment_delete.php';

      // Show modal
      const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
      modal.show();
    });
  });

  document.querySelectorAll('.edit-equipment-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const name = btn.dataset.name;
      const desc = btn.dataset.desc;
      const total = btn.dataset.total;

      document.getElementById('edit-id').value = id;
      document.getElementById('edit-name').value = name;
      document.getElementById('edit-desc').value = desc;
      document.getElementById('edit-total').value = total;

      // Open modal manually
      const modal = new bootstrap.Modal(document.getElementById('editEquipmentModal'));
      modal.show();
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
