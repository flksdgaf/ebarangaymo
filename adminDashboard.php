<div class="container-fluid p-3">
  
  <!-- Stats Cards -->
  <!-- <div class="row g-3 mb-4"> -->
  <?php
    // Initialize variables
    // $userCount = $requestCount = $accountRequests = 0;

    // Users count
    // if ($stmt = $conn->prepare("SELECT COUNT(*) FROM users")) {
    //   $stmt->execute();
    //   $stmt->bind_result($userCount);
    //   $stmt->fetch();
    //   $stmt->close();
    // }

    // Service requests count
    // if ($stmt = $conn->prepare("SELECT COUNT(*) FROM requests")) {
    //   $stmt->execute();
    //   $stmt->bind_result($requestCount);
    //   $stmt->fetch();
    //   $stmt->close();
    // }

    // Account requests (pending)
    // $pendingStatus = 'pending';
    // if ($stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE status = ?")) {
    //   $stmt->bind_param("s", $pendingStatus);
    //   $stmt->execute();
    //   $stmt->bind_result($accountRequests);
    //   $stmt->fetch();
    //   $stmt->close();
    // }

    // Optional: Page views (static or pull from DB later)
    // $pageViews = 0;

    // Stats array
    // $stats = [
    //   ['icon' => 'group', 'label' => 'Users', 'count' => $userCount],
    //   ['icon' => 'description', 'label' => 'Service Requests', 'count' => $requestCount],
    //   ['icon' => 'visibility', 'label' => 'Page Views', 'count' => $pageViews],
    //   ['icon' => 'person_add', 'label' => 'Account Requests', 'count' => $accountRequests],
    // ];

    // Output the cards
  //   foreach ($stats as $stat) {
  //     echo '
  //     <div class="col-md-3 col-sm-6">
  //       <div class="card shadow-sm text-center p-3">
  //         <span class="material-symbols-outlined fs-1 text-success">' . htmlspecialchars($stat['icon']) . '</span>
  //         <h2 class="fw-bold">' . ($stat['count'] > 0 ? $stat['count'] : '<span class="text-danger">No data yet</span>') . '</h2>
  //         <p class="text-muted">' . htmlspecialchars($stat['label']) . '</p>
  //       </div>
  //     </div>';
  //   }
  // ?> 
  <!-- </div> -->

  <div class="row g-3 mb-4">
    <?php
      $stats = [
        ['icon' => 'group', 'label' => 'Users', 'count' => 3],
        ['icon' => 'description', 'label' => 'Service Requests', 'count' => 1],
        ['icon' => 'visibility', 'label' => 'Page Views', 'count' => 0],
        ['icon' => 'person_add', 'label' => 'Account Requests', 'count' => 0],
      ];

      foreach ($stats as $stat) {
        echo '
        <div class="col-md-3 col-sm-6">
          <div class="card shadow-sm text-center p-3">
            <span class="material-symbols-outlined fs-1 text-success">' . $stat['icon'] . '</span>
            <h2 class="fw-bold">' . $stat['count'] . '</h2>
            <p class="text-muted">' . $stat['label'] . '</p>
          </div>
        </div>';
      }
    ?>
  </div>

  <!-- Calendar -->
  <!-- <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card p-3 shadow-sm">
        <h5 class="fw-bold mb-3">Meeting Schedule</h5>
        <div id="calendar"></div>
      </div>
    </div> -->

  <!-- Pie Chart -->
  <!-- <div class="col-lg-6">
    <div class="card p-3 shadow-sm" style="height: 425px;">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Recent Service Requests</h5>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            Filter
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterDropdown">
            <li><a class="dropdown-item" href="#" onclick="updateChart('Barangay ID')">Barangay ID</a></li>
            <li><a class="dropdown-item" href="#" onclick="updateChart('Barangay Clearance')">Barangay Clearance</a></li>
            <li><a class="dropdown-item" href="#" onclick="updateChart('Certificate')">Certificate</a></li>
            <li><a class="dropdown-item" href="#" onclick="updateChart('Business Permit')">Business Permit</a></li>
            <li><a class="dropdown-item" href="#" onclick="updateChart('Katarungang Pambarangay')">Katarungang Pambarangay</a></li>
            <li><a class="dropdown-item" href="#" onclick="updateChart('Environmental Services')">Environmental Services</a></li>
          </ul>
        </div>
      </div>
      <div style="height: 300px;">
        <canvas id="servicePieChart" style="max-height: 100%;"></canvas>
      </div>
      <div class="mt-3">
        <span class="badge bg-success me-2">Brgy Clearance</span>
        <span class="badge bg-success-subtle me-2">Certification</span>
        <span class="badge bg-warning-subtle me-2">Business Permit</span>
        <span class="badge bg-success-emphasis">Brgy ID</span>
      </div>
    </div>
  </div> -->


    <!-- Recent Requests Table -->
    <div class="col-12">
      <div class="card p-3 shadow-sm">
        <h5 class="fw-bold mb-3">Recent Requests</h5>
        <div class="table-responsive">
          <table class="table table-hover align-middle text-start">
            <thead class="table-light">
              <tr>
                <th>Transaction No.</th>
                <th>Name</th>
                <th>Request</th>
                <th>Date Created</th>
                <th>Claim Date</th>
                <th>Payment Method</th>
                <th>Payment Status</th>
                <th>Document Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
                $stmt = $conn->prepare("SELECT transaction_id, full_name, request_type, created_at, claim_date, payment_method, payment_status, document_status FROM view_general_requests"); //ORDER BY created_at DESC LIMIT 10
                $stmt->execute();
                $stmt->bind_result($txn, $name, $request, $date_created, $claim_date, $payment_method, $payment_status, $document_status);

                // Table Rows
                while ($stmt->fetch()) {
                  $formattedDateCreated = date("F d, Y h:i A", strtotime($date_created));
                  $formattedClaimDate = !empty($claim_date) ? date("F d, Y", strtotime($claim_date)) : '—';
              
                    // Determine the payment status class
                    if ($payment_status == 'Paid') {
                      $paymentClass = 'paid-status';
                    } else if ($payment_status == 'Unpaid') {
                        $paymentClass = 'unpaid-status';
                    } else {
                        $paymentClass = '';
                    }

                    // Determine the document status class
                    if ($document_status == 'For Verification') {
                        $documentClass = 'for-verification-status';
                    } else if ($document_status == 'Rejected') {
                        $documentClass = 'rejected-status';
                    } else if ($document_status == 'Processing') {
                        $documentClass = 'processing-status';
                    } else if ($document_status == 'Ready To Release') {
                        $documentClass = 'ready-to-release-status';
                    } else if ($document_status == 'Released') {
                        $documentClass = 'released-status';
                    } else {
                        $documentClass = '';
                    }
                    
                    echo "<tr>
                        <td>{$txn}</td>
                        <td>{$name}</td>
                        <td>{$request}</td>
                        <td>{$formattedDateCreated}</td>
                        <td>{$formattedClaimDate}</td>
                        <td>{$payment_method}</td>
                        <td><span class='badge {$paymentClass}'>{$payment_status}</span></td>
                        <td><span class='badge {$documentClass}'>{$document_status}</span></td>
                    </tr>";
                }

                $stmt->close();
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Full Calendar -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<!-- Chart.js (Make sure it's included in admin_header.php or load below) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Full Calendar
  document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');

    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      height: 350,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: ''
      },
      events: [
        {
          title: 'Barangay Meeting',
          start: '2025-04-16',
          description: 'Monthly barangay council meeting',
          color: '#2e7d32'
        },
        {
          title: 'Vaccination Drive',
          start: '2025-04-20',
          end: '2025-04-22',
          description: 'Barangay health initiative',
          color: '#2e7d32'
        }
      ],
      eventClick: function(info) {
        alert(info.event.title + "\n" + info.event.extendedProps.description);
      }
    });

    calendar.render();
  });

  // Chart
  const ctx = document.getElementById('servicePieChart');
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: ['Brgy Clearance', 'Certification', 'Business Permit', 'Brgy ID'],
      datasets: [{
        label: 'Request Types',
        data: [45, 25, 10, 20],
        backgroundColor: ['#1e7e34', '#28a745', '#ffc107', '#20c997'],
        borderColor: '#fff',
        borderWidth: 1
      }]
    },
    options: {
      plugins: {
        legend: {
          display: false
        }
      }
    }
  });
</script>