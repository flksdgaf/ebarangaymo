<?php
require_once 'functions/dbconn.php';
// Determine purok (default=1) and table name
$purokNum = isset($_GET['purok']) && in_array((int)$_GET['purok'], [1,2,3,4,5,6])
            ? (int)$_GET['purok']
            : 1;
$tableName = "purok{$purokNum}_rbi";

// Fetch rows in schema order
$stmt = $conn->prepare("
  SELECT
    account_ID,
    full_name,
    birthdate,
    sex,
    civil_status,
    blood_type,
    birth_registration_number,
    highest_educational_attainment,
    occupation,
    house_number,
    relationship_to_head,
    registry_number,
    total_population
  FROM `$tableName`
");
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container py-3">
  <!-- Filter Dropdown -->
  <div class="d-flex justify-content-end mb-3">
    <select id="purokFilter" class="form-select w-auto">
      <?php for ($i = 1; $i <= 6; $i++): ?>
        <option value="<?php echo $i; ?>" <?php if($i === $purokNum) echo 'selected'; ?>>
          Purok <?php echo $i; ?>
        </option>
      <?php endfor; ?>
    </select>
  </div>

  <!-- Residents Table -->
  <div class="card shadow-sm p-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle text-start resident-table">
        <thead class="table-light">
          <tr>
            <th>Account ID</th>
            <th>Full Name</th>
            <th>Birthdate</th>
            <th>Sex</th>
            <th>Civil Status</th>
            <th>Blood Type</th>
            <th>Birth Reg. No.</th>
            <th>Education</th>
            <th>Occupation</th>
            <th>House No.</th>
            <th>Relationship to Head</th>
            <th>Registry No.</th>
            <th>Total Population</th>
          </tr>
        </thead>
        <tbody>
          <?php if($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['account_ID']); ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['birthdate']); ?></td>
                <td><?php echo htmlspecialchars($row['sex']); ?></td>
                <td><?php echo htmlspecialchars($row['civil_status']); ?></td>
                <td><?php echo htmlspecialchars($row['blood_type']); ?></td>
                <td><?php echo htmlspecialchars($row['birth_registration_number']); ?></td>
                <td><?php echo htmlspecialchars($row['highest_educational_attainment']); ?></td>
                <td><?php echo htmlspecialchars($row['occupation']); ?></td>
                <td><?php echo htmlspecialchars($row['house_number']); ?></td>
                <td><?php echo htmlspecialchars($row['relationship_to_head']); ?></td>
                <td><?php echo htmlspecialchars($row['registry_number']); ?></td>
                <td><?php echo htmlspecialchars($row['total_population']); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="13" class="text-center">
                No data found for Purok <?php echo $purokNum; ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// Reload with chosen purok
document.getElementById('purokFilter').addEventListener('change', function() {
  const p = this.value;
  const url = new URL(window.location.href);
  url.searchParams.set('purok', p);
  window.location.href = url.toString();
});
</script>
