<div class="dashboard-container">
  <div class="dashboard-card">
    <h3>Users</h3>
    <h1 class="dashboard-value">3</h1> 
  </div>
  <div class="dashboard-card">
    <h3>Service Requests</h3>
    <h1 class="dashboard-value">3</h1>
  </div>
  <div class="dashboard-card">
    <h3>Page Views</h3>
    <h1 class="dashboard-value">3</h1>
  </div>
  <div class="dashboard-card">
    <h3>Account Requests</h3>
    <h1 class="dashboard-value">3</h1>
  </div>

  <div class="dashboard-card large-card">
    <h3 style="color: #61AD41; margin-bottom: 10px;">Meeting Schedule</h3>
    <div id="calendar"></div>
  </div>
  
  <div class="dashboard-card large-card">
    <h3 style="color: #61AD41; margin-bottom: 10px;">Recent Service Requests</h3>
    <div style="display: flex; flex-direction: column;">
        <canvas id="servicePieChart" width="300" height="300"></canvas>
        
        <div class="chart-legend" style="align-self: flex-end;">
        <div class="legend-items">
            <div class="legend-color" style="background-color: #388e3c;"></div> Brgy Clearance
        </div>
        <div class="legend-items">
            <div class="legend-color" style="background-color: #1b5e20;"></div> Certification
        </div>
        <div class="legend-items">
            <div class="legend-color" style="background-color: #43a047;"></div> Business Permit
        </div>
        <div class="legend-items">
            <div class="legend-color" style="background-color: #66bb6a;"></div> Brgy ID
        </div>
        </div>
    </div>
  </div>

  <div class="dashboard-card full-width-card">
  <h3 style="color: #61AD41; margin-bottom: 10px;">Recent Requests</h3>
        <table>
        <thead>
            <tr>
                <th>Transaction No.</th>
                <th>Name</th>
                <th>Request</th>
                <th>Date Request</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>0123456789</td>
                <td>Britos, Kent Gabriel V.</td>
                <td>Business Permit</td>
                <td>04-01-2025</td>
                <td><span class="status">Pending</span></td>
            </tr>
            <tr>
                <td>0123456788</td>
                <td>Britos, Kent Gabriel V.</td>
                <td>Cert of Indigency</td>
                <td>04-01-2025</td>
                <td><span class="status">&nbsp;</span></td>
            </tr>
            <tr>
                <td>0123456787</td>
                <td>Britos, Kent Gabriel V.</td>
                <td>Barangay ID</td>
                <td>04-01-2025</td>
                <td><span class="status">Pending</span></td>
            </tr>
        </tbody>
        </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('calendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    initialDate: '2025-04-01',
    headerToolbar: {
        left: '',
        center: 'title',
        right: 'prev,next'
    },
    events: [
        {
        title: 'Barangay Meeting',
        start: '2025-04-22',
        display: 'block'
        },
        {
        title: '✔',
        start: '2025-04-04',
        display: 'block'
        }
    ]
    });

    calendar.render();
});

const ctx = document.getElementById('servicePieChart').getContext('2d');
const servicePieChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
    labels: ['Brgy Clearance', 'Certification', 'Business Permit', 'Brgy ID'],
    datasets: [{
        label: 'Service Requests',
        data: [25, 45, 10, 20],
        backgroundColor: [
        '#388e3c', // Brgy Clearance
        '#1b5e20', // Certification
        '#43a047', // Business Permit
        '#66bb6a'  // Brgy ID
        ],
        borderWidth: 0,
        cutout: '50%'
    }]
    },
    options: {
    plugins: {
        legend: {
        display: false
        },
        tooltip: {
        callbacks: {
            label: function(context) {
            return context.parsed + '%';
            }
        }
        },
        datalabels: {
        display: true,
        color: '#fff',
        font: {
            weight: 'bold'
        },
        formatter: (value) => value + '%'
        }
    }
    },
    plugins: [ChartDataLabels]
});
</script>