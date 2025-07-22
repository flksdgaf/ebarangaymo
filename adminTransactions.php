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
                <div class="col-md-4">
                  <label for="filterAge" class="form-label">Age</label>
                  <input type="number" id="filterAge" name="exact_age" class="form-control" min="0" placeholder="All">
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
              <form id="blotterReportForm" method="post" action="functions/generate_blotter_report.php" target="_blank">
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
                    <button type="submit" name="format" value="csv" class="btn btn-outline-success me-2">CSV</button>
                    <button type="button" id="generateBlotterPDFBtn" class="btn btn-success">PDF</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Complaint Reports -->
    <div class="accordion-item">
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
    </div>

    <!-- Katarungang Pambarangay Reports -->
    <div class="accordion-item">
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
    </div>

  </div>

  <script>
    // Resident PDF generation
    document.getElementById('previewResidentBtn').addEventListener('click', function () {
      const form = document.getElementById('residentReportForm');
      const formData = new FormData(form);
      formData.append('format', 'preview');

      fetch(form.action, {
        method: 'POST',
        body: formData
      })
      .then(res => res.text())
      .then(html => {
        document.getElementById('residentPreviewOutput').innerHTML = html;
      })
      .catch(err => {
        document.getElementById('residentPreviewOutput').innerHTML = '<p class="text-danger">Preview failed to load.</p>';
      });
    });

    // Collection Report
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

    // Blotter PDF generation
    document.getElementById('generateBlotterPDFBtn').addEventListener('click', function () {
      const from = document.getElementById('blotterFrom').value;
      const to = document.getElementById('blotterTo').value;

      if (!from || !to) {
        alert("Please select both FROM and TO dates.");
        return;
      }

      const url = `functions/generateBlotterReport.php?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`;
      window.open(url, 'BlotterReportWindow', 'width=1000,height=800,resizable=yes,scrollbars=yes');
    });

    // Official Receipt PDF generation (with 'All Types' support)
    document.getElementById('generateOfficialReceiptPDFBtn').addEventListener('click', function () {
      const from = document.getElementById('receiptFrom').value;
      const to = document.getElementById('receiptTo').value;
      const type = document.getElementById('receiptReportType').value;

      if (!from || !to || !type) {
        alert("Please select FROM, TO, and Report Type.");
        return;
      }

      // Include type even if it's 'all'
      const url = `functions/generateOfficialReceiptReport.php?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&type=${encodeURIComponent(type)}`;
      window.open(url, 'OfficialReceiptReportWindow', 'width=1000,height=800,resizable=yes,scrollbars=yes');
    });
  </script>






</div>


