<div class="container-fluid p-3">
  
  <!-- Devices Stats Cards -->
  <div class="row g-3 mb-4">
    <!-- Total Devices -->
    <div class="col-md-3 col-sm-6">
      <div class="card shadow-sm text-center p-3">
        <span class="material-symbols-outlined fs-1 text-success">devices</span>
        <h2 class="fw-bold text-success">120</h2>
        <p class="text-muted mb-0">Total Devices</p>
        <small class="text-muted">As of <?php echo date('m-d-Y'); ?></small>
      </div>
    </div>

    <!-- Online Devices -->
    <div class="col-md-3 col-sm-6">
      <div class="card shadow-sm text-center p-3">
        <span class="material-symbols-outlined fs-1 text-success">wifi</span>
        <h2 class="fw-bold text-success">90</h2>
        <p class="text-muted mb-0">Online Devices</p>
        <small class="text-muted">As of <?php echo date('m-d-Y'); ?></small>
      </div>
    </div>

    <!-- Offline Devices -->
    <div class="col-md-3 col-sm-6">
      <div class="card shadow-sm text-center p-3">
        <span class="material-symbols-outlined fs-1 text-danger">wifi_off</span>
        <h2 class="fw-bold text-danger">30</h2>
        <p class="text-muted mb-0">Offline Devices</p>
        <small class="text-muted">As of <?php echo date('m-d-Y'); ?></small>
      </div>
    </div>

    <!-- Devices Needing Maintenance -->
    <div class="col-md-3 col-sm-6">
      <div class="card shadow-sm text-center p-3">
        <span class="material-symbols-outlined fs-1 text-warning">build</span>
        <h2 class="fw-bold text-warning">5</h2>
        <p class="text-muted mb-0">Maintenance Needed</p>
        <small class="text-muted">As of <?php echo date('m-d-Y'); ?></small>
      </div>
    </div>
  </div>

  <!-- Devices Table -->
  <div class="row g-3">
    <div class="col-12">
      <div class="card p-3 shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Device Status</h5>
          <button class="btn btn-success btn-sm">Filter</button>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle text-start">
            <thead class="table-light">
              <tr>
                <th>Device ID</th>
                <th>Device Name</th>
                <th>Status</th>
                <th>Last Online</th>
                <th class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <!-- Example Row -->
              <tr>
                <td>DVC-001</td>
                <td>Device Alpha</td>
                <td>
                  <span class="badge bg-success">Online</span>
                </td>
                <td>01-01-2025 10:00 AM</td>
                <td class="text-center">
                  <button class="btn btn-primary btn-sm me-2">View</button>
                  <button class="btn btn-success btn-sm">Edit</button>
                </td>
              </tr>
              <!-- More rows if needed -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>
