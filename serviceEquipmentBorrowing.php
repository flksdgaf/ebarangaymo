<?php
// serviceEquipmentBorrowing.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'functions/dbconn.php';

// --- ensure logged in ---
if (!isset($_SESSION['loggedInUserID'])) {
    header('Location: index.php');
    exit();
}
$userId = (int) $_SESSION['loggedInUserID'];

// --- Get resident basic info (full_name, purok) from purok tables ---
$fullName = '';
$userPurok = '';
$unionSql = [];
for ($i = 1; $i <= 6; $i++) {
    $unionSql[] = "SELECT full_name, 'Purok {$i}' AS purok FROM purok{$i}_rbi WHERE account_ID = ?";
}
$sql = implode(' UNION ALL ', $unionSql) . " LIMIT 1";
$stmt = $conn->prepare($sql);
$types = str_repeat('i', 6);
$params = array_fill(0, 6, $userId);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$r = $stmt->get_result();
if ($r && $r->num_rows === 1) {
    $row = $r->fetch_assoc();
    $fullName = $row['full_name'];
    $userPurok = $row['purok'];
}
$stmt->close();

// --- Get selected equipment_sn from REQUEST (GET/POST) and fetch equipment details if provided ---
$equipment_sn = $_REQUEST['equipment_sn'] ?? null;
$equipment = null;
if ($equipment_sn) {
    $stmt = $conn->prepare("SELECT equipment_sn, name, available_qty FROM equipment_list WHERE equipment_sn = ? LIMIT 1");
    $stmt->bind_param('s', $equipment_sn);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $equipment = $res->fetch_assoc();
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Equipment Borrowing</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="serviceEquipmentBorrowing.css">
</head>
<body>
<div class="container py-4 px-3">
    <div class="progress-container">
        <div class="stepss">
            <!-- STEP 1 -->
            <div class="steps">
                <div class="circle" data-step="1">1</div>
                <div class="step-label">APPLICATION FORM</div>
            </div>

            <!-- STEP 2 -->
            <div class="steps">
                <div class="circle" data-step="2">2</div>
                <div class="step-label">REVIEW &amp; CONFIRMATION</div>
            </div>

            <!-- STEP 3 -->
            <div class="steps">
                <div class="circle" data-step="3">3</div>
                <div class="step-label">SUBMISSION</div>
            </div>

            <div class="progress-line"></div>
            <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
        </div>
    </div>
    
  <div class="card shadow-sm px-5 py-5 mb-5 mt-4 form-card">
    <h2 class="mb-1 text-success fw-bold" id="mainHeader">APPLICATION FORM</h2>
    <p id="subHeader" class="mb-2">Provide the necessary details to borrow equipment.</p>
    <hr id="mainHr" class="mb-4">

    <form id="borrowForm" method="post" novalidate>
      <!-- Step 1: Application Form -->
      <div id="step1" class="step active-step">
        <div class="row mb-3">
          <label class="col-md-4 text-start fw-bold">Full Name</label>
          <div class="col-md-8">
            <input type="text" readonly class="form-control" id="resident_name" name="resident_name" value="<?= htmlspecialchars($fullName) ?>">
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-md-4 text-start fw-bold">Purok</label>
          <div class="col-md-8">
            <input type="text" readonly class="form-control" id="purok" name="purok" value="<?= htmlspecialchars($userPurok) ?>">
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-md-4 text-start fw-bold">Type of Equipment/s</label>
          <div class="col-md-8">
            <input type="text" readonly class="form-control" id="equipment_name" value="<?= $equipment ? htmlspecialchars($equipment['name']) : '' ?>">
            <input type="hidden" id="equipment_sn" name="equipment_sn" value="<?= htmlspecialchars($equipment_sn) ?>">
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-md-4 text-start fw-bold">Quantity to be Borrowed</label>
          <div class="col-md-2">
            <input type="number" min="1" class="form-control" id="qty" name="qty" value="1" required>
          </div>
          <div class="col-md-6 d-flex align-items-center">
            <small>Available: <strong id="availableQty"><?= $equipment ? (int)$equipment['available_qty'] : '0' ?></strong></small>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-md-4 text-start fw-bold">Event Location</label>
          <div class="col-md-8">
            <input type="text" class="form-control" id="location" name="location" placeholder="Purok / Barangay / Venue" required>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-md-4 text-start fw-bold">Purpose of Use</label>
          <div class="col-md-8">
            <input type="text" class="form-control" id="used_for" name="used_for" placeholder="e.g., Birthday" required>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-md-4 text-start fw-bold">Date of Borrowing (From)</label>
          <div class="col-md-3">
            <input type="date" class="form-control" id="borrow_date_from" name="borrow_date_from" required>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-md-4 text-start fw-bold">Date of Borrowing (To)</label>
          <div class="col-md-3">
            <input type="date" class="form-control" id="borrow_date_to" name="borrow_date_to" required>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-md-4 text-start fw-bold">Pick-up or Drop-off</label>
          <div class="col-md-8">
            <select name="pudo" id="pudo_option" class="form-control" required>
                <option value="Pick Up">Pick Up</option>
                <option value="Drop Off">Drop Off</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Step 2: Review & Confirmation -->
      <div id="step2" class="step">
        <div id="summaryContainer" class="summary-container p-3"></div>
      </div>

      <!-- Step 3: Submission Confirmation -->
      <div id="step3" class="step">
        <div class="submission-screen text-center">
          <h2 class="submission-title">REQUEST SUBMITTED</h2>
          <p class="submission-text" id="submissionMessage">
            Your request has been successfully submitted and is now pending assessment by the barangay office.<br>
            Please keep your transaction number for reference:
          </p>
          <div id="txnBox" class="txn-display"></div>

          <p class="submission-footer mt-5">
            To check if your permit is ready for release,<br>
            please visit the <strong>My Requests</strong> page and enter your transaction reference number.
          </p>
        </div>
      </div>

      <!-- UPDATED: Buttons arranged so NEXT is lower-right -->
      <div class="row mt-4">
        <div class="col text-start">
          <button type="button" class="btn back-btn" id="prevBtn">&lt; PREVIOUS</button>
        </div>
        <div class="col text-end">
          <button type="button" class="btn next-btn" id="nextBtn">NEXT &gt;</button>
        </div>
      </div>
      <!-- END UPDATED -->
    </form>
  </div>
</div>

<!-- validation modal -->
<div class="modal fade" id="validationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title">Validation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Please fill in required fields before proceeding.</div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">OK</button></div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const total = 3;
  const stepEls = {
    1: document.getElementById('step1'),
    2: document.getElementById('step2'),
    3: document.getElementById('step3')
  };
  const prevBtn = document.getElementById('prevBtn');
  let nextBtn = document.getElementById('nextBtn');
  const mainHeader = document.getElementById('mainHeader');
  const subHeader = document.getElementById('subHeader');
  const mainHr = document.getElementById('mainHr');
  let current = 1;

  function updateNavigation() {
    prevBtn.style.visibility = current === 1 ? 'hidden' : 'visible';

    if (current === 1) {
      if (mainHeader) mainHeader.textContent = "APPLICATION FORM";
      if (subHeader) subHeader.textContent = "Provide the necessary details to borrow equipment.";
      nextBtn.textContent = "NEXT >";
      nextBtn.onclick = null;
    } else if (current === 2) {
      if (mainHeader) mainHeader.textContent = "REVIEW & CONFIRMATION";
      if (subHeader) subHeader.textContent = "Please review all your information before submitting.";
      nextBtn.textContent = "SUBMIT";
      nextBtn.onclick = null;
    } else if (current === 3) {
      if (mainHeader) mainHeader.remove();
      if (subHeader) subHeader.remove();
      if (mainHr) mainHr.remove();
      prevBtn.style.visibility = 'hidden';

      // make bottom button act as "Back to Home"
      nextBtn.textContent = "Back to Home";
      nextBtn.onclick = () => { window.location.href = 'userPanel.php?page=userDashboard'; };

      // remove the inline anchor buttons inside the submission screen for a cleaner UI
      const submissionLinks = document.querySelectorAll('#step3 .submission-screen .mt-3 a');
      submissionLinks.forEach(el => el.remove());
    }

    // update progress UI (circles/labels and progress fill)
    const progressSteps = document.querySelectorAll('.progress-container .stepss .steps');
    const progressFill = document.getElementById('progressFill');

    if (progressSteps && progressSteps.length) {
      progressSteps.forEach((stepEl, idx) => {
        const circle = stepEl.querySelector('.circle');
        const label = stepEl.querySelector('.step-label');
        const stepIndex = idx + 1;

        if (stepIndex < current) {
          circle && circle.classList.add('completed') && circle.classList.remove('active');
          label && label.classList.add('completed') && label.classList.remove('active');
        } else if (stepIndex === current) {
          circle && circle.classList.add('active') && circle.classList.remove('completed');
          label && label.classList.add('active') && label.classList.remove('completed');
        } else {
          circle && circle.classList.remove('active') && circle.classList.remove('completed');
          label && label.classList.remove('active') && label.classList.remove('completed');
        }
      });

      const maxSteps = Math.max(1, progressSteps.length - 1);
      const percent = ((current - 1) / maxSteps) * 100;
      if (progressFill) progressFill.style.width = percent + '%';
    }
  }

  function showStep(n) {
    for (let i = 1; i <= total; i++) {
      stepEls[i].classList.toggle('active-step', i === n);
    }
    prevBtn.style.display = (n === 1) ? 'none' : 'inline-block';
    nextBtn.style.display = 'inline-block';
    updateNavigation();
  }

  function populateSummary() {
    const container = document.getElementById('summaryContainer');
    const pickupOptEl = document.getElementById('pudo_option');
    const pudoText = pickupOptEl ? pickupOptEl.options[pickupOptEl.selectedIndex].text : '-';

    const rows = [
      ['Borrower:', document.getElementById('resident_name').value || '—'],
      ['Purok:', document.getElementById('purok').value || '—'],
      ['Equipment:', document.getElementById('equipment_name').value || '—'],
      ['Quantity:', document.getElementById('qty').value || '—'],
      ['Purpose:', document.getElementById('used_for').value || '—'],
      ['Location:', document.getElementById('location').value || '—'],
      ['Pick-up / Drop-off:', pudoText || '—'],
      ['Date of Borrowing (From):', document.getElementById('borrow_date_from').value || '—'],
      ['Date of Borrowing (To):', document.getElementById('borrow_date_to').value || '—']
    ];

    let html = `
      <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8 col-xl-6">
          <div class="summary-container p-4 rounded shadow-sm border">
            <ul class="list-group list-group-flush">
    `;

    rows.forEach(([label, value]) => {
      html += `
        <li class="list-group-item d-flex justify-content-between">
          <span class="fw-bold">${label}</span>
          <span class="text-success">${value}</span>
        </li>
      `;
    });

    html += `
            </ul>
          </div>
        </div>
      </div>
    `;

    container.innerHTML = html;
  }

  prevBtn.addEventListener('click', () => {
    if (current > 1) {
      current--;
      showStep(current);
    }
  });

  nextBtn.addEventListener('click', () => {
    if (current === 1) {
      const qty = Number(document.getElementById('qty').value || 0);
      const available = Number(document.getElementById('availableQty').textContent || 0);
      const used_for = document.getElementById('used_for').value.trim();
      const location = document.getElementById('location').value.trim();
      const borrow_from = document.getElementById('borrow_date_from').value;
      const borrow_to = document.getElementById('borrow_date_to').value;
      const pudo_option = document.getElementById('pudo_option').value;

      // Validation: required fields + date order
      if (!used_for || !location || !borrow_from || !borrow_to || qty < 1 || !pudo_option) {
        new bootstrap.Modal(document.getElementById('validationModal')).show();
        return;
      }
      // ensure from <= to (ISO date strings lexicographically comparable)
      if (borrow_from > borrow_to) {
        alert('Date of Borrowing (From) must be the same as or earlier than Date of Borrowing (To).');
        return;
      }

      if (qty > available) {
        alert('Requested quantity exceeds available quantity.');
        return;
      }

      populateSummary();
      current = 2;
      showStep(current);
      return;
    }

    if (current === 2) {
      const submitBtn = nextBtn;
      submitBtn.disabled = true;
      const originalText = submitBtn.textContent;
      submitBtn.textContent = 'Submitting...';

      const formData = new FormData();
      formData.append('resident_name', document.getElementById('resident_name').value);
      formData.append('purok', document.getElementById('purok').value);
      formData.append('equipment_sn', document.getElementById('equipment_sn').value);
      formData.append('qty', document.getElementById('qty').value);
      formData.append('location', document.getElementById('location').value);
      formData.append('used_for', document.getElementById('used_for').value);

      // UPDATED: send borrow_date_from and borrow_date_to
      formData.append('borrow_date_from', document.getElementById('borrow_date_from').value);
      formData.append('borrow_date_to', document.getElementById('borrow_date_to').value);
      // END UPDATED

      formData.append('pudo_option', document.getElementById('pudo_option').value);

      fetch('functions/serviceEquipmentBorrowing_submit.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(async r => {
        const text = await r.text();
        try { return JSON.parse(text); }
        catch (e) { throw new Error(text || 'Invalid server response'); }
      })
      .then(data => {
        if (data.status === 'success') {
          current = 3;
          showStep(current);

          document.getElementById('submissionMessage').innerHTML =
            'Your request has been successfully submitted and is now pending assessment by the barangay office.<br>Please keep your transaction number for reference:';

          const txnBox = document.getElementById('txnBox');
          txnBox.innerHTML = '';
          const idStr = 'BRW-' + String(data.id || '').padStart(6, '0');
          for (const ch of idStr) {
            const sp = document.createElement('span');
            sp.className = 'txn-char';
            sp.textContent = ch;
            txnBox.appendChild(sp);
          }
        } else {
          alert(data.message || 'Submission failed.');
        }
      })
      .catch(err => {
        console.error('Submit error:', err);
        alert('An error occurred while submitting. Server said:\n' + (err.message || err));
      })
      .finally(() => {
        submitBtn.disabled = false;
        if (current === 2) {
          submitBtn.textContent = originalText;
        } else if (current === 3) {
          submitBtn.textContent = 'Back to Home';
          submitBtn.onclick = () => { window.location.href = 'userPanel.php?page=userDashboard'; };
        }
      });
    }
  });

  // initialize
  showStep(current);
})();
</script>
</body>
</html>
