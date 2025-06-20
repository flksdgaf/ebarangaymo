<?php
require 'functions/dbconn.php';
// … (any session / auth checks here)
?>

<div class="container-fluid mt-4">
  <!-- Page Title -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">System Logs</h1>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header py-2">
      <div class="row align-items-center">
        <!-- Filter dropdown -->
        <div class="col-auto">
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-success dropdown-toggle" 
                    type="button" id="logsFilterDropdown" data-bs-toggle="dropdown" 
                    aria-expanded="false">
              <i class="material-symbols-outlined align-middle">filter_alt</i>
              Filter
            </button>
            <div class="dropdown-menu p-3" aria-labelledby="logsFilterDropdown" 
                 style="min-width:280px; font-size:.875rem;">
              <form method="get" id="logsFilterForm">
                <input type="hidden" name="page_num" value="1">

                <!-- User filter -->
                <div class="mb-3">
                  <label class="form-label small mb-1">User</label>
                  <select name="user" class="form-select form-select-sm">
                    <option value="">All Users</option>
                    <?php foreach($allUsers as $u): ?>
                    <option value="<?= htmlspecialchars($u['id']); ?>">
                      <?= htmlspecialchars($u['name']); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Action filter -->
                <div class="mb-3">
                  <label class="form-label small mb-1">Action Type</label>
                  <select name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    <option value="CREATE">Create</option>
                    <option value="UPDATE">Update</option>
                    <option value="DELETE">Delete</option>
                    <option value="LOGIN">Login</option>
                    <option value="LOGOUT">Logout</option>
                  </select>
                </div>

                <!-- Date range filter -->
                <div class="mb-3">
                  <label class="form-label small mb-1">Date Range</label>
                  <div class="d-flex gap-2">
                    <input type="date" name="date_from" class="form-control form-control-sm">
                    <input type="date" name="date_to" class="form-control form-control-sm">
                  </div>
                </div>

                <div class="d-flex justify-content-end">
                  <a href="?page_num=1" class="btn btn-sm btn-outline-secondary me-2">Reset</a>
                  <button type="submit" class="btn btn-sm btn-success">Apply</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Search box -->
        <div class="col-auto ms-auto">
          <form method="get" id="logsSearchForm" class="d-flex">
            <input type="hidden" name="page_num" value="1">
            <div class="input-group input-group-sm">
              <input name="search" type="text" class="form-control" placeholder="Search logs…">
              <button class="btn btn-outline-success" type="submit">
                <i class="material-symbols-outlined">search</i>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Logs Table -->
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">#</th>
              <th scope="col">User</th>
              <th scope="col">Action</th>
              <th scope="col">Module</th>
              <th scope="col">Details</th>
              <th scope="col">Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($logs->num_rows): ?>
              <?php while ($row = $logs->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['id']); ?></td>
                  <td><?= htmlspecialchars($row['user_name']); ?></td>
                  <td><?= htmlspecialchars($row['action_type']); ?></td>
                  <td><?= htmlspecialchars($row['module']); ?></td>
                  <td><?= htmlspecialchars($row['description']); ?></td>
                  <td><?= date('M d, Y H:i:s', strtotime($row['created_at'])); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center py-4">No log entries found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="card-footer bg-white">
        <nav aria-label="Logs pagination">
          <ul class="pagination justify-content-center pagination-sm mb-0">
            <!-- build your prev/next and page number links here -->
          </ul>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  // Retain any JavaScript for search-clear or modal popups
</script>
