<?php
require 'functions/dbconn.php';
// Fetch report types from database or define statically
$reportTypes = ['All','Barangay ID', 'Business Permit', 'Good Moral', 'Guardianship', 'Indigency', 'Residency', 'Solo Parent'];
?>

<title>eBarangay Mo | Generate Reports</title>

<div class="container-fluid p-3">
  <div class="accordion" id="adminAccordion">

    <!-- Resident Reports -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingResident">
        <button class="accordion-button collapsed text-success fw-bold"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapseResident"
                aria-expanded="false"
                aria-controls="collapseResident">
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
                    <button type="submit" name="format" value="pdf" class="btn btn-success">PDF</button>
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
                  <button type="submit" name="format" value="pdf" class="btn btn-success">PDF</button>
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
        <button class="accordion-button collapsed text-success fw-bold"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapseBlotter"
                aria-expanded="false"
                aria-controls="collapseBlotter">
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

                    <!-- PDF submit posts the form with name="format" value="pdf" and opens in a new tab -->
                    <button type="submit" name="format" value="pdf" class="btn btn-success">PDF</button>
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

    <!-- Complaint Reports -->
    <!-- <div class="accordion-item">
      <h2 class="accordion-header" id="headingComplaint">
        <button class="accordion-button collapsed text-success fw-bold"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapseComplaint"
                aria-expanded="false"
                aria-controls="collapseComplaint">
          Complaint Reports
        </button>
      </h2>
      <div id="collapseComplaint" class="accordion-collapse collapse"
           aria-labelledby="headingComplaint"
           data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body">
              <form method="post" action="functions/generate_complaint_report.php" target="_blank">
                <div class="row align-items-end g-3 mb-4">
                  <div class="col-md-3">
                    <label for="complaintFrom" class="form-label">From</label>
                    <input type="date" id="complaintFrom" name="date_from" class="form-control" required>
                  </div>
                  <div class="col-md-3">
                    <label for="complaintTo" class="form-label">To</label>
                    <input type="date" id="complaintTo" name="date_to" class="form-control" required>
                  </div>
                  <div class="col-md-6 text-end">
                    <button type="submit" name="format" value="csv" class="btn btn-outline-success me-2">CSV</button>
                    <button type="submit" name="format" value="pdf" class="btn btn-success">PDF</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div> -->

    <!-- Katarungang Pambarangay Reports -->
    <!-- <div class="accordion-item">
      <h2 class="accordion-header" id="headingKatarungang">
        <button class="accordion-button collapsed text-success fw-bold"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapseKatarungang"
                aria-expanded="false"
                aria-controls="collapseKatarungang">
          Katarungang Pambarangay Reports
        </button>
      </h2>
      <div id="collapseKatarungang" class="accordion-collapse collapse"
           aria-labelledby="headingKatarungang"
           data-bs-parent="#adminAccordion">
        <div class="accordion-body p-0">
          <div class="card border-0">
            <div class="card-body">
              <form method="post" action="functions/generate_katarungang_report.php" target="_blank">
                <div class="row align-items-end g-3 mb-4">
                  <div class="col-md-3">
                    <label for="kataFrom" class="form-label">From</label>
                    <input type="date" id="kataFrom" name="date_from" class="form-control" required>
                  </div>
                  <div class="col-md-3">
                    <label for="kataTo" class="form-label">To</label>
                    <input type="date" id="kataTo" name="date_to" class="form-control" required>
                  </div>
                  <div class="col-md-6 text-end">
                    <button type="submit" name="format" value="csv" class="btn btn-outline-success me-2">CSV</button>
                    <button type="submit" name="format" value="pdf" class="btn btn-success">PDF</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div> -->

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

    // Official Receipt PDF generation (unchanged)
    document.getElementById('generateOfficialReceiptPDFBtn').addEventListener('click', function () {
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
  </script>