<?php
// residentList.php
require_once 'functions/dbconn.php';

// Determine purok (default=1)
$purokNum = isset($_GET['purok']) && in_array((int)$_GET['purok'], [1,2,3,4,5,6])
            ? (int)$_GET['purok']
            : 1;
$tableName = "purok{$purokNum}_rbi";

// Fetch all columns plus role from user_accounts
$sql = "
  SELECT r.*, ua.role
  FROM `$tableName` AS r
  LEFT JOIN user_accounts AS ua
    ON r.account_ID = ua.account_id
";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Build PHP array for JS
$allRows = [];
while ($row = $result->fetch_assoc()) {
    $allRows[] = $row;
}
$stmt->close();
?>

<div class="container py-3">
  <!-- Filter -->
  <div class="d-flex justify-content-end mb-3">
    <select id="purokFilter" class="form-select w-auto">
      <?php for ($i = 1; $i <= 6; $i++): ?>
        <option value="<?= $i ?>" <?= $i === $purokNum ? 'selected' : '' ?>>
          Purok <?= $i ?>
        </option>
      <?php endfor; ?>
    </select>
  </div>

  <!-- Table -->
  <div class="card shadow-sm p-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle resident-table">
        <thead class="table-light">
          <tr>
            <th>Account ID</th>
            <th>Full Name</th>
            <th>Birthdate</th>
            <th>House No.</th>
            <th>Relationship to Head</th>
            <th>Registry No.</th>
            <th>Total Population</th>
            <th>Role</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allRows)): ?>
            <tr><td colspan="9" class="text-center">No data for Purok <?= $purokNum ?></td></tr>
          <?php else: ?>
            <?php foreach ($allRows as $row):
              // map enum to CSS color
              switch($row['remarks']) {
                case 'Missing': $bgColor = 'yellow'; break;
                case 'Deceased':    $bgColor = 'red';    break;
                default:        $bgColor = '';
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
                      $roles = ['Resident','Brgy Captain','Brgy Secretary','Brgy Bookkeeper','Brgy Kagawad','Brgy Staff'];
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
                  <select class="form-select form-select-sm remarks-select" style="width:91px; background-image: none; padding-right: 0.5rem;">
                    <option value="">None</option>
                    <option value="Missing"  <?= $row['remarks']==='Missing'  ? 'selected' : '' ?>>Missing</option>
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

<!-- Details Modal -->
<div class="modal fade" id="residentDetailsModal" tabindex="-1" aria-labelledby="residentDetailsLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="residentDetailsLabel">Resident Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"></div>
    </div>
  </div>
</div>

<script>
// Pass data to JS
const residents = <?= json_encode($allRows, JSON_HEX_TAG) ?>;
const purokNum  = <?= $purokNum ?>;

// map remarks to colors in JS
const remarkColor = {
  'Missing':  'yellow',
  'Deceased': 'red'
};

document.addEventListener('DOMContentLoaded', () => {
  // Filter
  document.getElementById('purokFilter').addEventListener('change', function() {
    const url = new URL(window.location.href);
    url.searchParams.set('purok', this.value);
    window.location.href = url;
  });

  // Remarks dropdown handler
  document.querySelectorAll('.remarks-select').forEach(sel => {
    sel.addEventListener('change', async function(e) {
      const row = this.closest('tr');
      const name = row.dataset.name;
      const remark = this.value;
      const color = remarkColor[remark] || '';

      // update cell backgrounds immediately
      row.querySelectorAll('td').forEach(td => {
        td.style.backgroundColor = color || '';
      });

      // persist change by full_name
      await fetch('functions/update_remarks.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          full_name: name,
          purok: purokNum,
          remarks: remark
        })
      });
    });
  });

  // handle role changes
  document.querySelectorAll('.role-select').forEach(sel=>{
    sel.addEventListener('change', async function(){
      const tr = this.closest('tr');
      const acct = tr.querySelector('td').textContent.trim();
      const newRole = this.value;
      // immediate UI (no bg change here)
      // persist
      await fetch('functions/update_role.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({account_id:acct,purok:purokNum,role:newRole})
      });
    });
  });

  // Details modal setup
  const modalEl = document.getElementById('residentDetailsModal');
  const modal   = new bootstrap.Modal(modalEl);
  const body    = modalEl.querySelector('.modal-body');

  document.querySelectorAll('.resident-row').forEach(row => {
    row.addEventListener('click', e => {
      // don’t trigger on the remarks dropdown
      if (e.target.closest('.remarks-select') || e.target.closest('.role-select')) return;

      const name = row.dataset.name;
      const data = residents.find(r => r.full_name === name);
      if (!data) return;

      // build the contents
      let html = '<div class="text-center mb-3">';
      if (data.profile_picture) {
        html += `<img src="profilePictures/${data.profile_picture}"
                     class="rounded-circle mb-3"
                     width="120" height="120"
                     style="object-fit:cover;">`;
      }
      html += '</div><dl class="row">';
      for (let key in data) {
        if (key === 'profile_picture') continue;
        const label = key.replace(/_/g,' ')
                         .replace(/\b\w/g, c=>c.toUpperCase());
        const val   = data[key]===null ? '—' : data[key];
        html += `<dt class="col-sm-5">${label}</dt>
                 <dd class="col-sm-7 mb-2">${val}</dd>`;
      }
      html += '</dl>';

      body.innerHTML = html;
      modal.show();
    });
  });


});
</script>