<?php
// residentList.php
require_once 'functions/dbconn.php';

// Determine purok (default=1)
$purokNum = isset($_GET['purok']) && in_array((int)$_GET['purok'], [1,2,3,4,5,6]) ? (int)$_GET['purok'] : 1;

// --- Search setup ---
$search = trim($_GET['search'] ?? '');

// Build WHERE clauses
$where = [];
$params = [];
$types = '';

// columns you want to search
$searchCols = [
  'r.account_ID',
  'r.full_name',
  'r.house_number',
  'r.relationship_to_head',
  'r.registry_number',
  'r.total_population',
  'ua.role',
  'r.remarks'
];


// Global search 
if ($search !== '') {
    // build a placeholder for each column
    $likes = [];
    foreach ($searchCols as $col) {
        $likes[]    = "$col LIKE ?";
        $types     .= 's';
        $params[]   = "%{$search}%";
    }
    $where[] = '(' . implode(' OR ', $likes) . ')';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';            

$tableName = "purok{$purokNum}_rbi";

// Fetch all columns plus role from user_accounts
$sql = "SELECT r.*, ua.role FROM `{$tableName}` AS r LEFT JOIN user_accounts AS ua ON r.account_ID = ua.account_id {$whereSQL}";
$stmt = $conn->prepare($sql);
if ($whereSQL) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Build PHP array for JS
$allRows = [];
while ($row = $result->fetch_assoc()) {
    $row['purok'] = $purokNum;
    $allRows[] = $row;
}
$stmt->close();
?>

<title>eBarangay Mo | Residents</title>

<div class="container-fluid p-3">
  <div class="card shadow-sm p-3">
    <!-- Filter -->
    <div class="d-flex justify-content-end mb-3">
      <select id="purokFilter" class="form-select form-select-sm w-auto">
        <?php for ($i = 1; $i <= 6; $i++): ?>
          <option value="<?= $i ?>" <?= $i === $purokNum ? 'selected' : '' ?>>
            Purok <?= $i ?>
          </option>
        <?php endfor; ?>
      </select>

      <!-- Search Form -->
      <form id="searchForm" method="get" class="d-flex ms-auto me-2">
        <input type="hidden" name="page" value="superAdminResidents">
        <input type="hidden" name="purok" value="<?= $purokNum ?>">
        <input type="hidden" name="page_num" value="1">
        <div class="input-group input-group-sm w-100">
          <input name="search" id="searchInput" type="text" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
          <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center" id="searchBtn">
            <span class="material-symbols-outlined" id="searchIcon">
              <?= !empty($search) ? 'close' : 'search' ?>
            </span>
          </button>
        </div>
      </form>
    </div>
    
    <div class="table-responsive admin-table" style="height:500px;overflow-y:auto;">
      <table class="table table-hover align-middle resident-table">
        <thead class="table-light">
          <tr>
            <th class="text-nowrap">Account ID</th>
            <th class="text-nowrap">Full Name</th>
            <th class="text-nowrap">Birthdate</th>
            <th class="text-nowrap">House No.</th>
            <th class="text-nowrap">Relationship to Head</th>
            <th class="text-nowrap">Registry No.</th>
            <th class="text-nowrap">Total Population</th>
            <th class="text-nowrap">Account Role</th>
            <th class="text-nowrap">Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allRows)): ?>
            <tr><td colspan="7" class="text-center">No data for Purok <?= $purokNum ?></td></tr>
          <?php else: ?>
            <?php foreach ($allRows as $row):
              // map enum to CSS color
              switch($row['remarks']) {
                case 'On Hold': $bgColor = 'yellow'; break;
                case 'Transferred': $bgColor = 'orange'; break;
                case 'Deceased': $bgColor = 'red'; break;
                default: $bgColor = '';
              }
              $cellStyle = $bgColor ? "background-color:{$bgColor}!important;" : '';
              $escapedName = htmlspecialchars($row['full_name'], ENT_QUOTES);
            ?>
              <tr class="resident-row" data-name="<?= $escapedName ?>">
                <td style="<?= $cellStyle ?>"><?= htmlspecialchars($row['account_ID']) ?></td>
                <td style="<?= $cellStyle ?>"><?= $escapedName ?></td>
                <td style="<?= $cellStyle ?>"><?= htmlspecialchars($row['birthdate']) ?></td>
                <td style="<?= $cellStyle ?>"><?= htmlspecialchars($row['house_number'] ?? '—') ?></td>
                <td style="<?= $cellStyle ?>"><?= htmlspecialchars($row['relationship_to_head'] ?? '—') ?></td>
                <td style="<?= $cellStyle ?>"><?= htmlspecialchars($row['registry_number'] ?? '—') ?></td>
                <td style="<?= $cellStyle ?>"><?= htmlspecialchars($row['total_population'] ?? '—') ?></td>
                <td style="<?= $cellStyle ?>">
                  <?php if ($row['role'] !== null): ?>
                    <select class="form-select form-select-sm role-select" style="width:137px; background-image: none; padding-right: 0.5rem;">
                      <?php 
                      $roles = ['Resident','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Lupon Tagapamayapa'];
                      foreach ($roles as $r): ?>
                        <option value="<?= $r ?>"
                          <?= $row['role'] === $r ? 'selected' : '' ?>>
                          <?= $r ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php else: ?>
                    — 
                  <?php endif; ?>
                </td>
                <td style="<?= $cellStyle ?>">
                  <select class="form-select form-select-sm remarks-select" style="width:101px; background-image: none; padding-right: 0.5rem;">
                    <option value="">None</option>
                    <option value="On Hold" <?= $row['remarks']==='On Hold' ? 'selected' : '' ?>>On Hold</option>
                    <option value="Transferred" <?= $row['remarks']==='Transferred' ? 'selected' : '' ?>>Transferred</option>
                    <option value="Deceased" <?= $row['remarks']==='Deceased' ? 'selected' : '' ?>>Deceased</option>
                  </select>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Details / Edit Modal -->
<div class="modal fade" id="residentDetailsModal" tabindex="-1" aria-labelledby="residentDetailsLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><span id="detailsModalTitle">Resident Details</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="residentDetailsForm">
          <!-- fields will be injected here by JS -->
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="detailsEditSaveBtn">Edit</button>
      </div>
    </div>
  </div>
</div>

<!-- Add this confirmation modal right after your existing modal -->
<div class="modal fade" id="confirmSaveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Changes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to save these changes?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmSaveBtn">Yes, Save</button>
      </div>
    </div>
  </div>
</div>

<script>
  // Pass data to JS
  const residents = <?= json_encode($allRows, JSON_HEX_TAG) ?>;
  const purokNum  = <?= $purokNum ?>;

  // map remarks to colors in JS
  const remarkColor = {
    'On Hold': 'yellow',
    'Transferred': 'orange',
    'Deceased': 'red'
  };

  document.addEventListener('DOMContentLoaded', () => {
    // --- Filter Purok
    document.getElementById('purokFilter').addEventListener('change', function() {
      const url = new URL(window.location.href);
      url.searchParams.set('purok', this.value);
      window.location.href = url;
    });

    // Search handler
    const Sform = document.getElementById('searchForm');
    const input = document.getElementById('searchInput');
    const btn = document.getElementById('searchBtn');
    let hasSearch = <?= json_encode($search !== '') ?>;
    btn.addEventListener('click', () => {
      if (hasSearch) input.value = '';
      Sform.submit();
    });

    // --- Remarks dropdown handler
    document.querySelectorAll('.remarks-select').forEach(sel => {
      sel.addEventListener('change', async function() {
        const row = this.closest('tr');
        const name = row.dataset.name;
        const remark = this.value;
        const color = remarkColor[remark] || '';

        // update backgrounds
        row.querySelectorAll('td').forEach(td => td.style.backgroundColor = color);

        // persist
        await fetch('functions/update_remarks.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({ full_name: name, purok: purokNum, remarks: remark })
        });
      });
    });

    // --- Role dropdown handler
    document.querySelectorAll('.role-select').forEach(sel => {
      sel.addEventListener('change', async function() {
        const tr = this.closest('tr');
        const acct = tr.children[0].textContent.trim();
        const newRole = this.value;
        await fetch('functions/update_role.php', {
          method: 'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({ account_id: acct, role: newRole })
        });
      });
    });

    // --- Details / Edit Modal setup ---
    const modalEl = document.getElementById('residentDetailsModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('residentDetailsForm');
    const editSaveBtn = document.getElementById('detailsEditSaveBtn');
    let currentData = null;
    let isEditing = false;

    // Confirmation modal
    const confirmSaveModalEl = document.getElementById('confirmSaveModal');
    const confirmSaveModal = new bootstrap.Modal(confirmSaveModalEl);
    const confirmSaveBtn = document.getElementById('confirmSaveBtn');

    // Build form fields (readonly by default)
    function buildForm(data) {
      currentData = data;
      isEditing = false;
      editSaveBtn.textContent = 'Edit';
      
      form.innerHTML = '';
      if (data.profile_picture) {
        const picDiv = document.createElement('div');
        picDiv.className = 'text-center mb-4';
        const img = document.createElement('img');
        img.src = `profilePictures/${data.profile_picture}`;
        img.className = 'rounded-circle';
        img.style = 'width:120px;height:120px;object-fit:cover;';
        picDiv.appendChild(img);
        form.appendChild(picDiv);
      }

      const fields = [
        { key:'purok',                          label:'Purok',                          type:'select', readonly:true, editable:true, options:['1','2','3','4','5','6'] },
        { key:'account_ID',                     label:'Account ID',                     type:'text',   readonly:true },
        { key:'full_name',                      label:'Full Name',                      type:'text',   readonly:true, editable:true },
        { key:'birthdate',                      label:'Birthdate',                      type:'date',   readonly:true },
        { key:'sex',                            label:'Sex',                            type:'select', readonly:true, editable:true, options:['Male','Female','Prefer not to say','Unknown'] },
        { key:'civil_status',                   label:'Civil Status',                   type:'select', readonly:true, editable:true, options:['Single','Married','Widowed','Separated','Divorced','Unknown'] },
        { key:'blood_type',                     label:'Blood Type',                     type:'select', readonly:true, editable:true, options:['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown'] },
        { key:'birth_registration_number',      label:'Birth Reg. No.',                 type:'text',   readonly:true, editable:true },
        { key:'highest_educational_attainment', label:'Highest Educational Attainment', type:'select', readonly:true, editable:true, options:['Kindergarten','Elementary','High School','Senior High School','Undergraduate','College Graduate','Post-Graduate','Vocational','None','Unknown'] },
        { key:'occupation',                     label:'Occupation',                     type:'text',   readonly:true, editable:true },
        { key:'house_number',                   label:'House No.',                      type:'number', readonly:true, editable:true },
        { key:'relationship_to_head',           label:'Relationship to Head',           type:'text',   readonly:true, editable:true },
        { key:'registry_number',                label:'Registry No.',                   type:'number', readonly:true, editable:true },
        { key:'total_population',               label:'Total Population',               type:'number', readonly:true, editable:true },
        { key:'role',                           label:'Role',                           type:'text',   readonly:true },
        { key:'remarks',                        label:'Remarks',                        type:'text',   readonly:true }
      ];

      fields.forEach(f => {
        const wr = document.createElement('div');
        wr.className = 'mb-3 row';

        const lbl = document.createElement('label');
        lbl.className = 'col-sm-5 col-form-label fw-bold';
        lbl.textContent = f.label;

        const inner = document.createElement('div');
        inner.className = 'col-sm-7';

        let input;
        if (f.type === 'select') {
          input = document.createElement('select');
          input.className = 'form-select';
          f.options.forEach(opt => {
            const o = document.createElement('option');
            o.value = o.textContent = opt;
            if (String(data[f.key]) === opt) o.selected = true;
            input.appendChild(o);
          });
          input.disabled = true;
        } else {
          input = document.createElement('input');
          input.className = 'form-control';
          input.type = f.type;
          input.value = data[f.key] ?? '';
          input.disabled = true;

          if (f.key === 'remarks') {
            input.value = data.remarks ? data.remarks : 'None';
          } else {
            input.value = data[f.key] ?? '';
          }
        }

        input.id = `field_${f.key}`;
        input.name = f.key;

        wr.appendChild(lbl);
        wr.appendChild(inner);
        inner.appendChild(input);
        form.appendChild(wr);
      });
    }

    // Toggle Edit ↔ Save
    editSaveBtn.addEventListener('click', () => {
      if (!isEditing) {
        // switch to edit mode
        isEditing = true;
        editSaveBtn.textContent = 'Save';

        // unlock only the editable controls
        // ['full_name','birthdate','house_number','relationship_to_head','registry_number','total_population']
        //   .forEach(k => document.getElementById(`field_${k}`).readOnly = false);
        // ['sex','civil_status','blood_type','highest_educational_attainment']
        //   .forEach(k => document.getElementById(`field_${k}`).disabled = false);

          // enable only editable controls
       ['purok','full_name','house_number','relationship_to_head','registry_number','total_population',
        'sex','civil_status','blood_type', 'birth_registration_number','highest_educational_attainment', 'occupation'
       ].forEach(k => {
         const el = document.getElementById(`field_${k}`);
         if (el) el.disabled = false;
       });
      }
      else {
        // ask for confirmation
        confirmSaveModal.show();
      }
    });

    // actual save once confirmed
    confirmSaveBtn.addEventListener('click', async () => {
      confirmSaveModal.hide();

      // gather payload
      const originalPurok = currentData.purok;
      const newPurok = document.getElementById('field_purok').value;
      const payload = new URLSearchParams({ 
        account_id: currentData.account_ID,
        original_purok: originalPurok,
        new_purok: newPurok
      });

      if (currentData.profile_picture) {
        payload.append('profile_picture', currentData.profile_picture);
      }

      ['full_name','birthdate','sex','civil_status','blood_type',
      'birth_registration_number','highest_educational_attainment',
      'occupation','house_number','relationship_to_head',
      'registry_number','total_population'
      ].forEach(k => {
        const el = document.getElementById(`field_${k}`);
        payload.append(k, el.tagName==='SELECT' ? el.value : el.value);
      });

      // send to your update_resident.php
      const resp = await fetch('functions/update_resident.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: payload
      });

      let text = await resp.text();
      console.log('Raw response:', text);
      let json;
      try {
        json = JSON.parse(text);
      } catch(e) {
        return alert('Invalid JSON response, see console for raw output');
      }

      if (!json.success) {
        return alert('Save failed: ' + (json.error||'unknown'));
      }

      // update table row in the UI
      const row = document.querySelector(
        `.resident-row[data-name="${currentData.full_name.replace(/"/g,'\\"')}"]`
      );
      // columns: 1=full_name,2=birthdate,3=house_no,4=rel,5=regno,6=pop
      ['full_name','birthdate','house_number','relationship_to_head','registry_number','total_population']
        .forEach((k,idx) => {
          row.children[idx+1].textContent = document.getElementById(`field_${k}`).value;
        });

      // flip back to readonly mode
      isEditing = false;
      editSaveBtn.textContent = 'Edit';
      form.querySelectorAll('input').forEach(i => i.readOnly = true);
      ['sex','civil_status','blood_type','highest_educational_attainment'].forEach(key=>{
        const sel = document.getElementById(`field_${key}`);
        if (sel && sel.tagName === 'SELECT') sel.disabled = true;
      });

      modal.hide();
      window.location.reload();
    });

    // Row click opens the modal
    document.querySelectorAll('.resident-row').forEach(row => {
      row.addEventListener('click', e => {
        if (e.target.closest('.remarks-select') || e.target.closest('.role-select')) return;
        const name = row.dataset.name;
        const data = residents.find(r => r.full_name === name);
        if (!data) return;
        buildForm(data);
        modal.show();
      });
    });
  });
</script>
