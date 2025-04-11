<?php 
session_start();
$page = 'login'; 
include 'includes/header.php';

// Retrieve and clear error message if any.
$loginError = "";
if(isset($_SESSION['login_error'])){
    $loginError = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="signin.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- <script src="js/signin.js"></script> -->

<body>
  <div class="login-container">
    <h1 class="mb-2">SIGN IN</h1>
    <h5 class="mb-3">Enter your username and password.</h5>

    <form action="functions/login_process.php" method="POST">
      
        <div class="row mb-3">
            <label class="col-md-4 text-start fw-bold">Username</label>
            <div class="col-md-8">
                <input type="text" id="username" name="username" class="form-control custom-input" required>
            </div>
        </div>
        <div class="row mb-3">
            <label class="col-md-4 text-start fw-bold">Password</label>
            <div class="col-md-8 position-relative">
                <input type="password" class="form-control custom-input password-field" id="password" name="password" required>
                <span class="toggle-password" onclick="togglePassword('password')">
                    <i class="fa fa-eye-slash"></i>
                </span>
            </div>
        </div>
        
        <!-- Changed button type from "button" to "submit" so form is submitted -->
        <button type="submit" class="btn btn-gradient w-50 py-2">Login</button>
    </form>
  </div>

  <!-- Modal for login errors -->
  <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="errorModalLabel">Login Error</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                  <?php echo htmlspecialchars($loginError); ?>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              </div>
          </div>
      </div>
  </div>

  <!-- If there is an error, trigger the modal using JavaScript -->
  <?php if(!empty($loginError)) { ?>
  <script>
      document.addEventListener("DOMContentLoaded", function() {
          let errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
          errorModal.show();
      });
  </script>
  <?php } ?>

  <script>
    window.togglePassword = function (id) {
        let input = document.getElementById(id);
        let icon = input.nextElementSibling.querySelector("i");

        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        } else {
            input.type = "password";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        }
    };
  </script>

</body>
