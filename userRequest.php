<div class="container py-4">
  <!-- Title and Filter -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold text-success">All Requests</h4>
    <div class="dropdown">
      <button class="btn btn-outline-success dropdown-toggle" type="button" id="requestFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        Filter
      </button>
      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="requestFilterDropdown">
        <li><a class="dropdown-item" href="adminrequest.php?filter=All">All</a></li>
        <li><a class="dropdown-item" href="adminrequest.php?filter=Barangay ID">Barangay ID</a></li>
        <li><a class="dropdown-item" href="adminrequest.php?filter=Business Permit">Business Permit</a></li>
        <li><a class="dropdown-item" href="adminrequest.php?filter=Certificate">Certificate</a></li>
        <li><a class="dropdown-item" href="adminrequest.php?filter=Cert. of Indigency">Cert. of Indigency</a></li>
        <li><a class="dropdown-item" href="adminrequest.php?filter=Released">Released</a></li>
        <li><a class="dropdown-item" href="adminrequest.php?filter=Pending">Pending</a></li>
      </ul>
    </div>
  </div>

  <!-- Request Table -->
  <div class="card shadow-sm p-3">
    <div class="table-responsive">
      <table class="table align-middle text-center">
        <thead class="table-light">
          <tr>
            <th>Transaction No.</th>
            <th>Name</th>
            <th>Request</th>
            <th>Payment Method</th>
            <th>Date Request</th>
            <th>Payment Status</th> 
            <th>Document Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          // Retrieve the filter value from the GET request
          $filter = isset($_GET['filter']) ? $_GET['filter'] : '';
          $sql = "SELECT transaction_id, full_name, transaction_type, payment_method, created_at, payment_status, document_status FROM barangay_id_requests";

          // Modify SQL query based on the selected filter
          if ($filter && $filter !== 'All') {
            // You can add multiple conditions depending on the filter
            $sql .= " WHERE ";
            
            // Check if the filter is a request type or a status (or any other field)
            if ($filter === 'Barangay ID' || $filter === 'Business Permit' || $filter === 'Certificate' || $filter === 'Cert. of Indigency') {
                $sql .= "transaction_type = ?";
            } else if ($filter === 'Released' || $filter === 'Pending') {
                $sql .= "document_status = ?";
            }

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $filter);
          } else {
            // No filter selected, fetch all data
            $stmt = $conn->prepare($sql);
          }

          // Execute the query
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
              $badgeClass = ($row['document_status'] === 'Ready To Release') ? 'bg-success' : 'bg-warning text-dark';
              $viewUrl = "view_request.php?id={$row['transaction_id']}";
              $editUrl = "edit_request.php?id={$row['transaction_id']}";

              echo "<tr>
                <td>{$row['transaction_id']}</td>
                <td>{$row['full_name']}</td>
                <td>{$row['transaction_type']}</td>
                <td>{$row['payment_method']}</td>
                <td>{$row['created_at']}</td>
                <td>{$row['payment_status']}</td>
                <td>{$row['document_status']}</td>
                <td>
                  <div class='d-flex justify-content-center gap-2'>
                    <!-- View Button -->
                    <button class='btn btn-sm btn-outline-success' data-bs-toggle='modal' data-bs-target='#viewModal-{$row['transaction_id']}'>View</button>

                    <!-- Edit Button -->
                    <button class='btn btn-sm btn-success text-white' data-bs-toggle='modal' data-bs-target='#editModal-{$row['transaction_id']}'>Edit</button>
                  </div>
                </td>
              </tr>";

              // View Modal
              echo "<div class='modal fade' id='viewModal-{$row['transaction_id']}' tabindex='-1' aria-labelledby='viewModalLabel-{$row['transaction_id']}' aria-hidden='true'>
              <div class='modal-dialog'>
                <div class='modal-content'>
                  <div class='modal-header'>
                    <h5 class='modal-title' id='viewModalLabel-{$row['transaction_id']}'>View Request Details</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                  </div>
                  <div class='modal-body'>
                    <p><strong>Transaction No.:</strong> {$row['transaction_id']}</p>
                    <p><strong>Name:</strong> {$row['full_name']}</p>
                    <p><strong>Request:</strong> {$row['transaction_type']}</p>
                    <p><strong>Payment Method:</strong> {$row['payment_method']}</p>
                    <p><strong>Date Request:</strong> {$row['created_at']}</p>
                    <p><strong>Payment Status:</strong> {$row['payment_status']}</p>
                    <p><strong>Document Status:</strong> {$row['document_status']}</p>
                  </div>
                  <div class='modal-footer'>
                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                    <button type='button' class='btn btn-primary' onclick='window.print();'>Print</button>
                  </div>
                </div>
              </div>
              </div>";

              // Edit Modal
              echo "<div class='modal fade' id='editModal-{$row['transaction_id']}' tabindex='-1' aria-labelledby='editModalLabel-{$row['transaction_id']}' aria-hidden='true'>
              <div class='modal-dialog'>
                <div class='modal-content'>
                  <div class='modal-header'>
                    <h5 class='modal-title' id='editModalLabel-{$row['transaction_id']}'>Edit Request Details</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                  </div>
                  <div class='modal-body'>
                    <form action='edit_request.php?id={$row['transaction_id']}' method='POST'>
                      <div class='mb-3'>
                        <label for='full_name' class='form-label'>Name</label>
                        <input type='text' class='form-control' id='full_name' name='full_name' value='{$row['full_name']}' readonly>
                      </div>
                      <div class='mb-3'>
                        <label for='transaction_type' class='form-label'>Request</label>
                        <input type='text' class='form-control' id='transaction_type' name='transaction_type' value='{$row['transaction_type']}' readonly>
                      </div>
                      <div class='mb-3'>
                        <label for='payment_method' class='form-label'>Payment Method</label>
                        <input type='text' class='form-control' id='payment_method' name='payment_method' value='{$row['payment_method']}' readonly>
                      </div>
                      <div class='mb-3'>
                        <label for='created_at' class='form-label'>Date Request</label>
                        <input type='text' class='form-control' id='created_at' name='created_at' value='{$row['created_at']}' readonly>
                      </div>
                      <div class='mb-3'>
                        <label for='payment_status' class='form-label'>Payment Status</label>
                        <select class='form-control' id='status' name='payment_status'>
                          <option value='Paid' " . ($row['payment_status'] === 'Paid' ? 'selected' : '') . ">Paid</option>
                          <option value='Unpaid' " . ($row['payment_status'] === 'Unpaid' ? 'selected' : '') . ">Unpaid</option>
                        </select>
                      </div>
                      <div class='mb-3'>
                        <label for='document_status' class='form-label'>Document Status</label>
                        <select class='form-control' id='status' name='document_status'>
                          <option value='Processing' " . ($row['document_status'] === 'Processing' ? 'selected' : '') . ">Processing</option>
                          <option value='Ready To Release' " . ($row['document_status'] === 'Ready To Release' ? 'selected' : '') . ">Ready To Release</option>
                          <option value='Released' " . ($row['document_status'] === 'Released' ? 'selected' : '') . ">Released</option>
                        </select>
                      </div>
                      <button type='submit' class='btn btn-warning'>Save Changes</button>
                    </form>
                  </div>
                </div>
              </div>
              </div>";
            }
          } else {
            echo "<tr><td colspan='9'>No requests found.</td></tr>";
          }

          $stmt->close();
          $conn->close();
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
