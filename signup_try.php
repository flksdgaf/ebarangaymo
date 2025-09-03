<?php 
$page = 'signinup'; 
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
  <div class="form-container shadow rounded bg-white p-4" style="max-width: 450px; width: 100%;">
    <h3 class="text-center fw-bold mb-3">Create Account</h3>

    <form id="signupForm" action="functions/signup_step1.php" method="POST">

      <!-- Name -->
      <div class="row g-2 mb-2">
        <div class="col">
          <input type="text" name="lastname" class="form-control form-control-sm" placeholder="Last Name" required>
        </div>
        <div class="col">
          <input type="text" name="suffix" class="form-control form-control-sm" placeholder="Suffix (Optional)">
        </div>
      </div>
      <div class="row g-2 mb-3">
        <div class="col">
          <input type="text" name="firstname" class="form-control form-control-sm" placeholder="First Name" required>
        </div>
        <div class="col">
          <input type="text" name="middlename" class="form-control form-control-sm" placeholder="Middle Name (Optional)">
        </div>
      </div>

      <!-- Purok -->
      <div class="mb-3">
        <select name="purok" class="form-select form-select-sm" required>
          <option value="">Select Purok</option>
          <option value="Purok 1">Purok 1</option>
          <option value="Purok 2">Purok 2</option>
          <option value="Purok 3">Purok 3</option>
          <option value="Purok 4">Purok 4</option>
          <option value="Purok 5">Purok 5</option>
          <option value="Purok 6">Purok 6</option>
        </select>
      </div>

      <!-- Username -->
      <div class="mb-3">
        <input type="text" name="username" class="form-control form-control-sm" placeholder="Username" required>
      </div>

      <!-- Email -->
      <div class="mb-3">
        <input type="email" name="email" class="form-control form-control-sm" placeholder="Email Address" required>
      </div>

      <!-- Password -->
      <div class="row g-2 mb-3">
        <div class="col">
          <input type="password" id="password" name="password" 
                 class="form-control form-control-sm" placeholder="Password" required>
        </div>
        <div class="col">
          <input type="password" id="confirmPassword" name="confirmPassword" 
                 class="form-control form-control-sm" placeholder="Confirm Password" required>
        </div>
      </div>

      <!-- Error Message -->
      <small id="pwdError" class="text-danger d-block mb-2" style="display:none; font-size: 12px;"></small>

      <!-- Password rules -->
      <small class="text-muted d-block mb-3" style="font-size: 12px;">
        • 8+ characters<br>
        • Uppercase & lowercase<br>
        • At least one number<br>
        • At least one special symbol
      </small>

      <!-- Submit -->
      <button type="submit" class="btn btn-primary w-100 btn-sm">Create Account</button>
    </form>

    <p class="text-center mt-3 mb-0" style="font-size: 13px;">
      Already have an account?
      <a href="signin.php" class="fw-bold text-decoration-none">Sign In</a>
    </p>
  </div>
</div>

<script>
document.getElementById("signupForm").addEventListener("submit", function(e) {
  const pwd = document.getElementById("password").value;
  const confirmPwd = document.getElementById("confirmPassword").value;
  const pwdError = document.getElementById("pwdError");

  // Require: 8+ chars, at least one uppercase, one lowercase, one number, one special char
  const validPwd = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*]).{8,}$/;

  pwdError.style.display = "none";

  if (!validPwd.test(pwd)) {
    e.preventDefault();
    pwdError.innerText = "Password must have 8+ chars, upper & lowercase, number, and special char.";
    pwdError.style.display = "block";
    return false;
  }

  if (pwd !== confirmPwd) {
    e.preventDefault();
    pwdError.innerText = "Passwords do not match!";
    pwdError.style.display = "block";
    return false;
  }
});
</script>
