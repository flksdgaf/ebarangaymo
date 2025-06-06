<?php 
session_start();
$page = 'login'; 
include 'includes/header.php';
include 'functions/dbconn.php';

// Retrieve and clear error message if any.
$loginError = "";
if(isset($_SESSION['login_error'])){
    $loginError = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

$info = $conn->query("SELECT logo, name, address FROM barangay_info WHERE id=1")->fetch_assoc();
$logoUrl = 'images/' . $info['logo'];
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="signin.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- <script src="js/signin.js"></script> -->

<body>
  <!-- <div class="login-wrapper p-1"> -->
    <div class="login-container row p-0">
    <!-- Left Column -->
    <div class="col-md-6 d-flex flex-column align-items-center justify-content-center text-center left-column">
      <div class="logo-wrapper d-flex justify-content-center">
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Brgy. Magang Logo" class="login-logo">
        <img src="images/good_governance_logo.png" alt="Good Governance Logo" class="login-logo">
      </div>
      <h3 class="fw-bold"><?= htmlspecialchars($info['name']) ?></h3>
      <p class="text-uppercase pt-0 magang-address"><?= htmlspecialchars($info['address']) ?></p>
    </div>

    <!-- Right Column -->
    <div class="col-md-6 pt-5 pb-5 login-form-wrapper">
      <h1 class="mb-2 text-center">SIGN IN</h1>
      <h6 class="text-center">Enter your username and password.</h6>

      <form action="functions/login_process.php" method="POST">
        <div class="row mb-3 justify-content-center align-items-center">
          <label class="col-md-3 text-start fw-bold">Username</label>
          <div class="col-md-8">
            <input type="text" id="username" name="username" class="form-control custom-input" required>
          </div>
        </div>

        <div class="row mb-1 justify-content-center align-items-center">
          <label class="col-md-3 text-start fw-bold">Password</label>
          <div class="col-md-8 position-relative mb-10">
            <input type="password" class="form-control custom-input password-field" id="password" name="password" required>
            <span class="toggle-password" onclick="togglePassword('password')">
              <i class="fa fa-eye-slash"></i>
            </span>
          </div>
          <a href="#" class="forgot-pass"><strong><br>Forgot Password?</strong></a>
        </div>

        <div class="text-center">
          <button type="submit" class="btn btn-gradient w-50 py-2 mb-3">Login</button>
          <p>Don't have an account yet?   
            <a href="signup.php" class="signup-now">Sign Up Now</a>
          </p>
        </div>
      </form>
    </div>
    </div>
    
  <!-- Error Modal -->
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