<?php 
$page = 'login'; 
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="signin.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<body>
  <div class="login-container">
    <h1 class="mb-2">SIGN IN</h1>
    <h5 class="mb-100">Enter your username and password.</h5>

    <form action="login_process.php" method="POST">
      
        <div class="row mb-3">
            <label class="col-md-4 text-start fw-bold">Username</label>
            <div class="col-md-8"><input type="text" id="username" name="username" class="form-control custom-input" required></div>
        </div>
        <div class="row mb-3">
            <label class="col-md-4 text-start fw-bold">Password</label>
            <div class="col-md-8"><input type="text" id="password" name="password" class="form-control custom-input" required></div>
        </div>
      <button type="submit" class="btn btn-gradient w-50 py-2">Sign In</button>
    </form>

  </div>

</body>

