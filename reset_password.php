<?php 
session_start();
$page = 'login'; 
include 'includes/header.php';
include 'functions/dbconn.php';

// Validate token from URL
$token = $_GET['token'] ?? '';
$validToken = false;
$expired = false;

if ($token) {
    $stmt = $conn->prepare("
        SELECT prt.account_id, prt.expires_at, ua.username 
        FROM password_reset_tokens prt
        JOIN user_accounts ua ON prt.account_id = ua.account_id
        WHERE prt.token = ? 
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $tokenData = $result->fetch_assoc();
        $expiresAt = strtotime($tokenData['expires_at']);
        
        if ($expiresAt > time()) {
            $validToken = true;
            $username = $tokenData['username'];
        } else {
            $expired = true;
        }
    }
    $stmt->close();
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
      <?php if (!$token || (!$validToken && !$expired)): ?>
        <h1 class="mb-2 text-center">INVALID LINK</h1>
        <h6 class="text-center">This password reset link is invalid.</h6>
        <div class="text-center mt-4">
          <a href="forgot_password.php" class="btn btn-gradient w-50 py-2">Request New Link</a>
        </div>
        
      <?php elseif ($expired): ?>
        <h1 class="mb-2 text-center">LINK EXPIRED</h1>
        <h6 class="text-center">This password reset link has expired. Please request a new one.</h6>
        <div class="text-center mt-4">
          <a href="forgot_password.php" class="btn btn-gradient w-50 py-2">Request New Link</a>
        </div>
        
      <?php else: ?>
        <h1 class="mb-2 text-center">RESET PASSWORD</h1>
        <h6 class="text-center">Enter your new password for <strong><?= htmlspecialchars($username) ?></strong></h6>

        <form action="functions/process_reset_password.php" method="POST">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          
          <div class="row mb-3 justify-content-center align-items-center">
            <label class="col-md-4 text-start fw-bold">New Password</label>
            <div class="col-md-8 position-relative">
              <input type="password" class="form-control custom-input password-field" id="password" name="password" required>
              <span class="toggle-password" onclick="togglePassword('password')">
                <i class="fa fa-eye-slash"></i>
              </span>
            </div>
          </div>

          <div class="row mb-3 justify-content-center align-items-center">
            <label class="col-md-4 text-start fw-bold">Confirm Password</label>
            <div class="col-md-8 position-relative">
              <input type="password" class="form-control custom-input password-field" id="confirm_password" name="confirm_password" required>
              <span class="toggle-password" onclick="togglePassword('confirm_password')">
                <i class="fa fa-eye-slash"></i>
              </span>
            </div>
          </div>

          <div class="text-center">
            <button type="submit" class="btn btn-gradient w-50 py-2 mb-3" id="resetBtn">Reset Password</button>
            <p><a href="signin.php" style="color: #0D2C15; font-size: 13px; font-weight: bold; text-decoration: none;">Back to Sign In</a></p>
          </div>
        </form>
      <?php endif; ?>
    </div>
    </div>

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

    // Password match validation
    document.querySelector('form')?.addEventListener('submit', function(e) {
      const pwd = document.getElementById('password').value;
      const confirmPwd = document.getElementById('confirm_password').value;
      
      if (pwd !== confirmPwd) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
      }
      
      if (pwd.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long!');
        return false;
      }
    });
  </script>

</body>