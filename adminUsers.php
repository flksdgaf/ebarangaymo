<div class="container py-3">
  <!-- Title and Filter -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold">Users</h4>
  </div>

  <!-- Account Requests Table -->
  <div class="card shadow-sm p-3">
    <div class="table-responsive">
      <table class="table align-middle text-center table-hover">
        <thead class="table-light">
          <tr>
            <th>Profile Picture</th>
            <th>Account ID</th>
            <th>Name</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $stmt = $conn->prepare("SELECT * FROM user_profiles");
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
              $profilePicPath = 'profilePictures/' . $row['profilePic'];

              echo "<tr>";
              echo "<td><img src='{$profilePicPath}' alt='Profile Picture' style='width: 50px; height: 50px; object-fit: cover; border-radius: 50%;'></td>";
              echo "<td>{$row['account_id']}</td>";
              echo "<td>{$row['full_name']}</td>";
              echo "<td>"; // 👈 Action buttons inside
              echo "<button class='btn btn-sm btn-outline-success viewBtn'
                      data-account_id='{$row['account_id']}'
                      data-full_name='{$row['full_name']}'
                      data-birthdate='{$row['birthdate']}'
                      data-sex='{$row['sex']}'
                      data-contact='{$row['contact']}'
                      data-full_address='{$row['full_address']}'
                      data-profile_pic='{$profilePicPath}'
                      data-bs-toggle='modal' data-bs-target='#userDetailsModal'>
                      View
                    </button>";
              echo "</td>";
              echo "</tr>";
            }
          }
          $stmt->close();
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="userDetailsModalLabel">User Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-3">
          <img id="modalProfilePic" src="" alt="Profile Picture" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%;">
        </div>
        <p><strong>Account ID:</strong> <span id="modalAccountID"></span></p>
        <p><strong>Full Name:</strong> <span id="modalFullName"></span></p>
        <p><strong>Birthdate:</strong> <span id="modalBirthdate"></span></p>
        <p><strong>Sex:</strong> <span id="modalSex"></span></p>
        <p><strong>Contact Number:</strong> <span id="modalContact"></span></p>
        <p><strong>Address:</strong> <span id="modalFullAddress"></span></p>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const viewButtons = document.querySelectorAll('.viewBtn');

  viewButtons.forEach(button => {
    button.addEventListener('click', function() {
      const accountId = this.dataset.account_id;
      const fullName = this.dataset.full_name;
      const birthdate = this.dataset.birthdate;
      const sex = this.dataset.sex;
      const contact = this.dataset.contact;
      const fullAddress = this.dataset.full_address;
      const profilePic = this.dataset.profile_pic;

      document.getElementById('modalProfilePic').src = profilePic;
      document.getElementById('modalAccountID').textContent = accountId;
      document.getElementById('modalFullName').textContent = fullName;
      document.getElementById('modalBirthdate').textContent = birthdate;
      document.getElementById('modalSex').textContent = sex;
      document.getElementById('modalContact').textContent = contact;
      document.getElementById('modalFullAddress').textContent = fullAddress;
    });
  });
});
</script>
