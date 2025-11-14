<?php
require 'functions/dbconn.php';
$userId = (int)$_SESSION['loggedInUserID'];
$currentRole = $_SESSION['loggedInUserRole'] ?? '';

// Fetch report types from database or define statically
$reportTypes = ['All','Barangay ID', 'Business Permit', 'Good Moral', 'Guardianship', 'Indigency', 'Residency', 'Solo Parent'];
?>

<title>eBarangay Mo | Generate Reports</title>

<div class="container-fluid p-3">
  <div class="accordion" id="adminAccordion">

    <!-- Resident Reports -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingResident">
        <button class="accordion-button collapsed text-success fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseResident" aria-expanded="false" aria-controls="collapseResident">
          Resident Reports
        </button>
      </h2>
      <div id="collapseResident" class="accordion-collapse collapse" aria-labelledby="headingResident" data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body">

              <form method="post" action="functions/generateResidentsReport.php" target="_blank" id="residentReportForm">
                <div class="row align-items-end g-3 mb-4">
                  <div class="col-md-4">
                    <label for="filterPurok" class="form-label">Purok</label>
                    <select id="filterPurok" name="purok" class="form-select">
                      <option value="all" selected>All</option>
                      <?php for($i=1; $i<=6; $i++): ?>
                      <option value="<?= $i ?>">Purok <?= $i ?></option>
                      <?php endfor; ?>
                    </select>
                  </div>

                  <!-- CHANGED: text input so user can type "18" or "18-20" -->
                  <div class="col-md-4">
                    <label for="filterAge" class="form-label">Age</label>
                    <input
                      type="text"
                      id="filterAge"
                      name="exact_age"
                      class="form-control"
                      placeholder="e.g. 18 or 18-20"
                      pattern="^\d{1,3}(\s*-\s*\d{1,3})?$"
                      title="Enter a single age (e.g. 18) or a range (e.g. 18-20)">
                  </div>

                  <div class="col-md-4 text-end">
                    <button type="button" id="previewResidentBtn" class="btn btn-outline-success me-2">Preview</button>
                    <?php if ($currentRole !== 'Brgy Kagawad'): ?>
                      <button type="submit" name="format" value="pdf" class="btn btn-success">PDF</button>
                    <?php endif; ?>
                  </div>
                </div>
              </form>

              <div id="residentPreviewOutput" class="mt-4"></div>

            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Collection Reports -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingCollection">
        <button class="accordion-button collapsed text-success fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCollection" aria-expanded="false" aria-controls="collapseCollection">
          Collection Reports
        </button>
      </h2>
      <div id="collapseCollection" class="accordion-collapse collapse" aria-labelledby="headingCollection" data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body">

            <form method="post" action="functions/generateCollectionReport.php" target="_blank" id="collectionForm">
              <div class="row align-items-end g-3 mb-4">
                <div class="col-md-3">
                  <label for="collectionReportType" class="form-label">Report Type</label>
                  <select id="collectionReportType" name="report_type" class="form-select" required>
                    <?php foreach($reportTypes as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="collectionFrom" class="form-label">From</label>
                  <input type="date" id="collectionFrom" name="date_from" class="form-control" required>
                </div>
                <div class="col-md-3">
                  <label for="collectionTo" class="form-label">To</label>
                  <input type="date" id="collectionTo" name="date_to" class="form-control" required>
                </div>
                <div class="col-md-3 text-end">
                  <button type="button" id="previewCollectionBtn" class="btn btn-outline-success me-2">Preview</button>
                  <?php if ($currentRole !== 'Brgy Kagawad'): ?>
                    <button type="submit" name="format" value="pdf" class="btn btn-success">PDF</button>
                  <?php endif; ?>
                </div>
              </div>
            </form>

            <div id="collectionPreviewOutput" class="mt-4"></div>

            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Blotter Reports -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingBlotter">
        <button class="accordion-button collapsed text-success fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBlotter" aria-expanded="false" aria-controls="collapseBlotter">
          Blotter Reports
        </button>
      </h2>
      <div id="collapseBlotter" class="accordion-collapse collapse"
          aria-labelledby="headingBlotter"
          data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body">
              <!-- NOTE: action points to the report generator script -->
              <form id="blotterReportForm" method="post" action="functions/generateBlotterReport.php" target="_blank">
                <div class="row align-items-end g-3 mb-4">
                  <div class="col-md-3">
                    <label for="blotterFrom" class="form-label">From</label>
                    <input type="date" id="blotterFrom" name="date_from" class="form-control" required>
                  </div>
                  <div class="col-md-3">
                    <label for="blotterTo" class="form-label">To</label>
                    <input type="date" id="blotterTo" name="date_to" class="form-control" required>
                  </div>
                  <div class="col-md-6 text-end">
                    <!-- Preview is handled by JS and will load HTML into #blotterPreviewOutput -->
                    <button type="button" id="previewBlotterBtn" class="btn btn-outline-success me-2">Preview</button>
                    <?php if ($currentRole !== 'Brgy Kagawad'): ?>
                      <!-- PDF submit posts the form with name="format" value="pdf" and opens in a new tab -->
                      <button type="submit" name="format" value="pdf" class="btn btn-success">PDF</button>
                    <?php endif; ?>
                  </div>
                </div>
              </form>

              <!-- Preview output area -->
              <div id="blotterPreviewOutput" class="mt-4"></div>

            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Kasambahay Reports -->
    <!-- <div class="accordion-item">
      <h2 class="accordion-header" id="headingKasambahay">
        <button class="accordion-button collapsed text-success fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseKasambahay" aria-expanded="false" aria-controls="collapseKasambahay">
          Kasambahay Reports
        </button>
      </h2>
      <div id="collapseKasambahay" class="accordion-collapse collapse" aria-labelledby="headingKasambahay" data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body"> -->
              <!-- TODO: Add Kasambahay report filters and form here -->
              <!-- <p class="text-muted mb-0">No filters or report options available yet.</p>
            </div>
          </div>
        </div>
      </div>
    </div> -->

    <!-- VAWC Reports -->
    <!-- <div class="accordion-item">
      <h2 class="accordion-header" id="headingVAWC">
        <button class="accordion-button collapsed text-success fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVAWC" aria-expanded="false" aria-controls="collapseVAWC">
          VAWC Reports
        </button>
      </h2>
      <div id="collapseVAWC" class="accordion-collapse collapse" aria-labelledby="headingVAWC" data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body"> -->
              <!-- TODO: Add VAWC report filters and form here -->
              <!-- <p class="text-muted mb-0">No filters or report options available yet.</p>
            </div>
          </div>
        </div>
      </div>
    </div> -->

    <!-- Katarungang Pambarangay Reports -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingKP">
        <button class="accordion-button collapsed text-success fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseKP" aria-expanded="false" aria-controls="collapseKP">
          Katarungang Pambarangay Reports
        </button>
      </h2>
      <div id="collapseKP" class="accordion-collapse collapse" aria-labelledby="headingKP" data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body">
              <form method="post" action="functions/generateKatarunganPambarangayReport.php" target="_blank" id="kpReportForm">
                <div class="row align-items-end g-3 mb-4">
                  <div class="col-md-4">
                    <label for="kpMonth" class="form-label">Month</label>
                    <select id="kpMonth" name="month" class="form-select" required>
                      <option value="01">January</option>
                      <option value="02">February</option>
                      <option value="03">March</option>
                      <option value="04">April</option>
                      <option value="05">May</option>
                      <option value="06">June</option>
                      <option value="07">July</option>
                      <option value="08">August</option>
                      <option value="09">September</option>
                      <option value="10">October</option>
                      <option value="11">November</option>
                      <option value="12">December</option>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label for="kpYear" class="form-label">Year</label>
                    <select id="kpYear" name="year" class="form-select" required>
                      <?php
                      $currentYear = date('Y');
                      for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                        echo "<option value=\"$y\">$y</option>";
                      }
                      ?>
                    </select>
                  </div>
                  <div class="col-md-4 text-end">
                    <button type="button" id="previewKPBtn" class="btn btn-outline-success me-2">Preview</button>
                    <?php if ($currentRole !== 'Brgy Kagawad'): ?>
                      <button type="submit" name="format" value="pdf" class="btn btn-success">Print</button>
                    <?php endif; ?>
                  </div>
                </div>
              </form>

              <div id="kpPreviewOutput" class="mt-4"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

  <script>
    // Resident PDF generation (preview)
    document.getElementById('previewResidentBtn').addEventListener('click', function (e) {
      e.preventDefault();

      const form = document.getElementById('residentReportForm');
      const output = document.getElementById('residentPreviewOutput');

      const ageInput = form.querySelector('input[name="exact_age"]');
      const ageVal = (ageInput && ageInput.value) ? ageInput.value.trim() : '';

      // Valid formats: "18"  OR  "18-20" (spaces allowed around -)
      const singleRe = /^\d{1,3}$/;
      const rangeRe  = /^\s*\d{1,3}\s*-\s*\d{1,3}\s*$/;

      if (ageVal !== '' && !(singleRe.test(ageVal) || rangeRe.test(ageVal))) {
        alert('Invalid age format. Use a single number (e.g. 18) or a range (e.g. 18-20).');
        ageInput.focus();
        return;
      }

      const formData = new FormData(form);
      formData.set('format', 'preview'); // ensure preview mode

      output.innerHTML = '<div class="text-center py-4">Loading preview&hellip;</div>';

      fetch(form.action, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
      .then(res => {
        if (!res.ok) throw new Error('Network response not ok');
        return res.text();
      })
      .then(html => {
        // If server returns a full HTML document, extract the body for cleaner insertion
        const match = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
        const inner = (match && match[1]) ? match[1] : html;
        document.getElementById('residentPreviewOutput').innerHTML = inner;
      })
      .catch(err => {
        console.error(err);
        document.getElementById('residentPreviewOutput').innerHTML = '<p class="text-danger">Preview failed to load.</p>';
      });
    });

    // Collection Report preview (unchanged)
    document.getElementById('previewCollectionBtn').addEventListener('click', function () {
      const form = document.getElementById('collectionForm');
      const formData = new FormData(form);
      formData.append('format', 'preview');

      fetch(form.action, {
        method: 'POST',
        body: formData
      })
      .then(res => res.text())
      .then(html => {
        document.getElementById('collectionPreviewOutput').innerHTML = html;
      })
      .catch(err => {
        document.getElementById('collectionPreviewOutput').innerHTML = '<p class="text-danger">Failed to load preview.</p>';
      });
    });

    // Blotter: Preview + PDF handling (unchanged)
    (function () {
      const previewBtn = document.getElementById('previewBlotterBtn');
      const form = document.getElementById('blotterReportForm');
      const output = document.getElementById('blotterPreviewOutput');
      const pdfBtn = document.getElementById('generateBlotterPDFBtn');

      // Helper: extract <body> innerHTML if full document returned
      function extractBody(html) {
        const match = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
        return (match && match[1]) ? match[1] : html;
      }

      if (previewBtn) {
        previewBtn.addEventListener('click', function (e) {
          e.preventDefault();

          const from = form.querySelector('input[name="date_from"]').value;
          const to = form.querySelector('input[name="date_to"]').value;
          if (!from || !to) {
            alert('Please select both From and To dates.');
            return;
          }

          const fd = new FormData(form);
          fd.set('format', 'preview');

          output.innerHTML = '<div class="text-center py-4">Loading preview&hellip;</div>';

          fetch(form.action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
          })
          .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
          })
          .then(html => {
            output.innerHTML = extractBody(html);
            output.scrollIntoView({ behavior: 'smooth' });
          })
          .catch(err => {
            console.error(err);
            output.innerHTML = '<div class="text-danger py-2">Failed to load preview. Check console for details.</div>';
          });
        });
      }

      if (pdfBtn) {
        pdfBtn.addEventListener('click', function (e) {
          e.preventDefault();

          const from = form.querySelector('input[name="date_from"]').value;
          const to = form.querySelector('input[name="date_to"]').value;
          if (!from || !to) {
            alert("Please select both FROM and TO dates.");
            return;
          }

          let formatInput = form.querySelector('input[name="format"][type="hidden"]');
          if (!formatInput) {
            formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'format';
            form.appendChild(formatInput);
          }
          formatInput.value = 'pdf';

          const origTarget = form.target;
          form.target = '_blank';
          form.submit();
          form.target = origTarget || '';
        });
      }
    })();

    // Official Receipt PDF generation
    const officialReceiptBtn = document.getElementById('generateOfficialReceiptPDFBtn');
    if (officialReceiptBtn) {
      officialReceiptBtn.addEventListener('click', function () {
        const from = document.getElementById('receiptFrom').value;
        const to = document.getElementById('receiptTo').value;
        const type = document.getElementById('receiptReportType').value;

        if (!from || !to || !type) {
          alert("Please select FROM, TO, and Report Type.");
          return;
        }

        const url = `functions/generateOfficialReceiptReport.php?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&type=${encodeURIComponent(type)}`;
        window.open(url, 'OfficialReceiptReportWindow', 'width=1000,height=800,resizable=yes,scrollbars=yes');
      });
    }

    // Katarungang Pambarangay Report
    (function () {
      const previewBtn = document.getElementById('previewKPBtn');
      const form = document.getElementById('kpReportForm');
      const output = document.getElementById('kpPreviewOutput');
      const collapseEl = document.getElementById('collapseKP');

      // Helper: extract <body> innerHTML if full document returned
      function extractBody(html) {
        const match = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
        return (match && match[1]) ? match[1] : html;
      }

      // Helper: show bootstrap collapse (so preview is visible)
      function showKPAccordion() {
        try {
          if (!collapseEl) return;
          // Bootstrap 5: get or create instance, then show
          const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl);
          bsCollapse.show();
        } catch (err) {
          // If bootstrap not available, ignore (no side-effects)
          console.warn('Bootstrap collapse show failed:', err);
        }
      }

      if (previewBtn) {
        previewBtn.addEventListener('click', function (e) {
          e.preventDefault();

          const month = form.querySelector('select[name="month"]').value;
          const year = form.querySelector('select[name="year"]').value;

          if (!month || !year) {
            alert('Please select both Month and Year.');
            return;
          }

          const fd = new FormData(form);
          fd.set('format', 'preview');

          // Ensure accordion is open before inserting preview
          showKPAccordion();

          output.innerHTML = `
            <div class="text-center py-4">
              <div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading...</span></div>
              <p class="mt-2 mb-0">Loading preview...</p>
            </div>
          `;

          fetch(form.action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
          })
          .then(response => {
            if (!response.ok) throw new Error('HTTP error! status: ' + response.status);
            return response.text();
          })
          .then(html => {
            // Extract body if the server returned a full HTML document
            const inner = extractBody(html).trim();
            if (!inner) {
              throw new Error('Empty response from server');
            }
            // Insert preview HTML
            output.innerHTML = inner;

            // Small safety: if preview doesn't include the .preview-container wrapper,
            // add light wrapping so it looks consistent in accordion
            if (!output.querySelector('.kp-preview-wrapper') && !output.querySelector('.preview-container')) {
              const wrapper = document.createElement('div');
              wrapper.className = 'kp-preview-wrapper';
              wrapper.style.cssText = 'background:#fff;padding:18px;border:1px solid #dee2e6;border-radius:6px;margin:10px 0;overflow-x:auto;';
              wrapper.innerHTML = output.innerHTML;
              output.innerHTML = '';
              output.appendChild(wrapper);
            }

            // Smooth-scroll the preview into view
            output.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          })
          .catch(err => {
            console.error('Preview error:', err);
            output.innerHTML = '<div class="alert alert-danger" role="alert"><strong>Error:</strong> Failed to load preview. ' + err.message + '</div>';
          });
        });
      } else {
        console.error('Preview button not found!');
      }
    })();
  </script>