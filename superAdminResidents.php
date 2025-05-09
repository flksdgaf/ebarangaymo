<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold">Residents</h4>
  </div>

  <div class="card shadow-sm p-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle text-start resident-table">
        <thead class="table-light">
          <tr>
            <th>No.</th>
            <th>Relationship to Head</th>
            <th>Full Name</th>
            <th>Date of Birth</th>
            <th>Gender</th>
            <th>Civil Status</th>
            <th>Blood Type</th>
            <th>Birth Registration</th>
            <th>Highest Educational Attainment</th>
            <th>Occupation</th>
            <th>Registry</th>
            <th>Total Population</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $stmt = $conn->prepare("SELECT no, relationship_to_head, fullname, date_of_birth, gender, civil_status, blood_type, birth_registration, highest_education_attainment, occupation, registry, total_population FROM purok6_rbi");

          if ($stmt) {
              $stmt->execute();
              $result = $stmt->get_result();

              if ($result->num_rows > 0) {
                  while($row = $result->fetch_assoc()) {
                      echo "<tr>
                              <td>" . htmlspecialchars($row["no"]) . "</td>
                              <td>" . htmlspecialchars($row["relationship_to_head"]) . "</td>
                              <td>" . htmlspecialchars($row["fullname"]) . "</td>
                              <td>" . htmlspecialchars($row["date_of_birth"]) . "</td>
                              <td>" . htmlspecialchars($row["gender"]) . "</td>
                              <td>" . htmlspecialchars($row["civil_status"]) . "</td>
                              <td>" . htmlspecialchars($row["blood_type"]) . "</td>
                              <td>" . htmlspecialchars($row["birth_registration"]) . "</td>
                              <td>" . htmlspecialchars($row["highest_education_attainment"]) . "</td>
                              <td>" . htmlspecialchars($row["occupation"]) . "</td>
                              <td>" . htmlspecialchars($row["registry"]) . "</td>
                              <td>" . htmlspecialchars($row["total_population"]) . "</td>
                            </tr>";
                  }
              } else {
                  echo "<tr><td colspan='12'>No data found</td></tr>";
              }

              $stmt->close();
          } else {
              echo "<tr><td colspan='12'>Query preparation failed</td></tr>";
          }

          $conn->close();
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
