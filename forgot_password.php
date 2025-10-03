<?php 
session_start();
$page = 'login'; 
include 'includes/header.php';
include 'functions/dbconn.php';

$message = "";
$messageType = "";
if(isset($_SESSION['reset_message'])){
    $message = $_SESSION['reset_message'];
    $messageType = $_SESSION['reset_message_type'];
    unset($_SESSION['reset_message']);
    unset($_SESSION['reset_message_type']);
}

$info = $conn->query("SELECT logo, name, address FROM barangay_info WHERE id=1")->fetch_assoc();
$logoUrl = 'images/' . $info['logo'];
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="signin.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<body>
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
      <h1 class="mb-2 text-center">FORGOT PASSWORD</h1>
      <h6 class="text-center">Enter your email address to receive a password reset link.</h6>

      <form action="functions/send_reset_link.php" method="POST">
        <div class="row mb-3 justify-content-center align-items-center">
          <label class="col-md-3 text-start fw-bold">Email</label>
          <div class="col-md-8">
            <input type="email" id="email" name="email" class="form-control custom-input" placeholder="your.email@example.com" required>
          </div>
        </div>

        <div class="text-center">
          <button type="submit" class="btn btn-gradient w-50 py-2 mb-3">Send Reset Link</button>
          <p><a href="signin.php" style="color: #0D2C15; font-size: 13px; font-weight: bold; text-decoration: none;">Back to Sign In</a></p>
        </div>
      </form>
    </div>
    </div>
    
  <!-- Message Modal -->
  <?php if(!empty($message)) { ?>
  <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="messageModalLabel"><?= $messageType === 'success' ? 'Success' : 'Error' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <?= htmlspecialchars($message) ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
      document.addEventListener("DOMContentLoaded", function() {
          let messageModal = new bootstrap.Modal(document.getElementById("messageModal"));
          messageModal.show();
      });
  </script>
  <?php } ?>

</body>