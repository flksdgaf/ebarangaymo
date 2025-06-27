<?php
require 'functions/dbconn.php';

// Logged in user
$userId  = (int) $_SESSION['loggedInUserID'];
$picMessage = '';
$infoMessage = '';
$loginMessage = '';

// Find purok table & label
$purokTable = null;
$purokLabel = '';
for ($i = 1; $i <= 6; $i++) {
    $tbl = "purok{$i}_rbi";
    $chk = $conn->prepare("SELECT 1 FROM {$tbl} WHERE account_ID = ?");
    $chk->bind_param('i', $userId);
    $chk->execute();
    if ($chk->get_result()->num_rows) {
        $purokTable = $tbl;
        $purokLabel = "Purok $i";
        $chk->close();
        break;
    }
    $chk->close();
}
if (!$purokTable) {
    echo "<div class='alert alert-danger'>User record not found.</div>";
    exit;
}

// Fetch profile picture
$stmt = $conn->prepare("SELECT profile_picture FROM {$purokTable} WHERE account_ID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$picRow = $stmt->get_result()->fetch_assoc();
$currentPic = $picRow['profile_picture'] ?: 'default_profile_pic.png';
$stmt->close();

// Handle personal info submission
$updateFields = [
    'full_name',
    'birthdate',
    'sex',
    'civil_status',
    'blood_type',
    'birth_registration_number',
    'highest_educational_attainment',
    'occupation'
];

if (isset($_POST['personal_submit'])) {
    $values = [];
    foreach ($updateFields as $f) {
        $values[$f] = trim($_POST[$f] ?? '');
    }
    $setList = implode(', ', array_map(fn($f) => "$f = ?", $updateFields));
    $types   = str_repeat('s', count($updateFields)) . 'i';
    $params  = array_merge(array_values($values), [$userId]);

    $stmt = $conn->prepare("UPDATE {$purokTable} SET {$setList} WHERE account_ID = ?");
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $infoMessage = '<div class="alert alert-success alert-dismissible fade show" role="alert">Personal information updated successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } else {
        $infoMessage = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Failed to update personal information.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    $stmt->close();
}

// Handle login credentials submission
if (isset($_POST['login_submit'])) {
    // fetch current info
    $stmt = $conn->prepare("SELECT username, password FROM user_accounts WHERE account_ID = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $acct = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $newUsername    = trim($_POST['new_username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword    = $_POST['new_password'] ?? '';

    $errors = [];
    $successes = [];

    // Username update (no password required)
    if ($newUsername && $newUsername !== $acct['username']) {
        // check uniqueness
        $chk = $conn->prepare("SELECT account_ID FROM user_accounts WHERE username = ? AND account_ID != ?");
        $chk->bind_param('si', $newUsername, $userId);
        $chk->execute();
        if ($chk->get_result()->num_rows) {
            $errors[] = 'Username is already taken.';
        } else {
            $upd = $conn->prepare("UPDATE user_accounts SET username = ? WHERE account_ID = ?");
            $upd->bind_param('si', $newUsername, $userId);
            $upd->execute();
            $upd->close();
            $successes[] = 'Username updated successfully.';
        }
        $chk->close();
    }

    // Password update (requires correct current password)
    if ($newPassword) {
        if ($currentPassword && password_verify($currentPassword, $acct['password'])) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE user_accounts SET password = ? WHERE account_ID = ?");
            $upd->bind_param('si', $hash, $userId);
            $upd->execute();
            $upd->close();
            $successes[] = 'Password updated successfully.';
        } else {
            $errors[] = 'Current password incorrect. Password not changed.';
        }
    }

    // Compile loginMessage
    if ($errors) {
        $loginMessage = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . implode('<br>', $errors) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } elseif ($successes) {
        $loginMessage = '<div class="alert alert-success alert-dismissible fade show" role="alert">' . implode('<br>', $successes) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// Handle picture upload and submission
if (isset($_POST['picture_submit']) && !empty($_FILES['new_picture']['name'])) {
  $uploadDir = __DIR__ . '/profilePictures/';
  $tmpPath   = $_FILES['new_picture']['tmp_name'];
  $ext       = pathinfo($_FILES['new_picture']['name'], PATHINFO_EXTENSION);
  $newName   = $userId . '_' . time() . '.' . $ext;

  // delete old file if not default
  // if ($currentPic && file_exists($uploadDir . $currentPic) && $currentPic !== 'default_profile_pic.png') {
  //   unlink($uploadDir . $currentPic);
  // }

  // move new file
  if (move_uploaded_file($tmpPath, $uploadDir . $newName)) {
    $u = $conn->prepare("UPDATE {$purokTable} SET profile_picture = ? WHERE account_ID = ?");
    $u->bind_param('si', $newName, $userId);
    $u->execute();
    $u->close();
    $picMessage = '<div class="alert alert-success alert-dismissible fade show">Profile picture updated.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    $currentPic = $newName;
  } else {
    $picMessage = '<div class="alert alert-danger alert-dismissible fade show">Upload failed.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  }

  
}

// Fetch profile info values
$allFields = array_merge(['account_ID'], $updateFields);
$sql   = sprintf("SELECT %s FROM %s WHERE account_ID = ? LIMIT 1", implode(',', $allFields), $purokTable);
$stmt  = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?: array_fill_keys($allFields, '');
$stmt->close();

// Fetch login credentials
$stmt = $conn->prepare("SELECT username FROM user_accounts WHERE account_ID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$userAcct = $stmt->get_result()->fetch_assoc() ?: ['username' => ''];
$stmt->close();
?>

<title>eBarangay Mo | User Settings</title>

<div class="container py-3">

  <?= $picMessage ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white">
      <h5 class="mb-0">
        <i class="fas fa-image me-2"></i>Profile Picture
      </h5>
    </div>

    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="picture_submit" value="1">

        <div class="d-flex justify-content-center align-items-center">
          <!-- Current -->
          <div class="me-4 text-center">
            <img src="profilePictures/<?= htmlspecialchars($currentPic) ?>" class="rounded-circle border" width="120" height="120" alt="Current Profile">
            <div class="mt-2 small text-muted">Current</div>
          </div>

          <!-- New picker -->
          <div class="text-center">
            <label for="newPicInput" class="d-inline-block rounded-circle border bg-light" style="width:120px; height:120px; line-height:120px; font-size:2rem; cursor:pointer; user-select:none;" id="newPicLabel" tabindex="-1">＋</label>
            <input type="file" accept="image/*" name="new_picture" id="newPicInput" class="d-none">
            <div class="mt-2 small text-muted">New</div>
          </div>
        </div>

        <div class="text-end mt-3">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-1"></i>Save Changes
          </button>
        </div>
        
      </form>
    </div>
  </div>

  <?= $infoMessage ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white">
      <h5 class="mb-0"><i class="fas fa-user-cog me-2"></i>Personal Information</h5>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="personal_submit" value="1">

        <!-- Row 1 -->
        <div class="row mb-3">
          <div class="col-md-3">
            <div class="row">
              <label class="col-sm-6 col-form-label">Current Purok</label>
              <div class="col-sm-6">
                <input class="form-control bg-light" readonly value="<?= htmlspecialchars($purokLabel) ?>">
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="row">
              <label class="col-sm-5 col-form-label">Account ID</label>
              <div class="col-sm-7">
                <input class="form-control bg-light" readonly value="<?= htmlspecialchars($profile['account_ID']) ?>">
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="row">
              <label class="col-sm-3 col-form-label">Full Name</label>
              <div class="col-sm-9">
                <input name="full_name" class="form-control bg-light" readonly value="<?= htmlspecialchars($profile['full_name']) ?>" required>
              </div>
            </div>
          </div>
        </div>

        <!-- Row 2 -->
        <div class="row mb-3">
          <div class="col-md-3">
            <div class="row">
              <label class="col-sm-4 col-form-label">Birthdate</label>
              <div class="col-sm-8">
                <input type="date" name="birthdate" class="form-control bg-light" readonly value="<?= htmlspecialchars($profile['birthdate']) ?>">
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="row">
              <label class="col-sm-3 col-form-label">Sex</label>
              <div class="col-sm-9">
                <select name="sex" class="form-select">
                  <?php foreach (['Male', 'Female', 'Prefer not to say', 'Unknown'] as $sexOption): ?>
                    <option value="<?= $sexOption ?>" <?= $profile['sex'] === $sexOption ? 'selected' : '' ?>><?= $sexOption ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="row">
              <label class="col-sm-5 col-form-label">Civil Status</label>
              <div class="col-sm-7">
                <select name="civil_status" class="form-select">
                  <?php foreach (['Single','Married','Widowed','Separated','Divorced','Unknown'] as $status): ?>
                    <option value="<?= $status ?>" <?= $profile['civil_status']==$status?'selected':'' ?>><?= $status ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="row">
              <label class="col-sm-5 col-form-label">Blood Type</label>
              <div class="col-sm-7">
                <select name="blood_type" class="form-select">
                  <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown'] as $bt): ?>
                    <option value="<?= $bt ?>" <?= $profile['blood_type']==$bt?'selected':'' ?>><?= $bt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Row 3 -->
        <div class="row mb-3">
          <div class="col-md-4">
            <div class="row">
              <label class="col-sm-4 col-form-label">Birth Reg. No.</label>
              <div class="col-sm-8">
                <input name="birth_registration_number" class="form-control" value="<?= htmlspecialchars($profile['birth_registration_number']) ?>">
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="row">
              <label class="col-sm-4 col-form-label">Education</label>
              <div class="col-sm-8">
                <select name="highest_educational_attainment" class="form-select">
                  <?php foreach (['Kindergarten','Elementary','High School','Senior High School','Undergraduate','College Graduate','Post-Graduate','Vocational','None','Unknown'] as $edu): ?>
                    <option value="<?= $edu ?>" <?= $profile['highest_educational_attainment']==$edu?'selected':'' ?>><?= $edu ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="row">
              <label class="col-sm-4 col-form-label">Occupation</label>
              <div class="col-sm-8">
                <input name="occupation" class="form-control" value="<?= htmlspecialchars($profile['occupation']) ?>">
              </div>
            </div>
          </div>
        </div>

        <div class="text-end">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-1"></i>Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <?= $loginMessage ?>

  <div class="card shadow-sm">
    <div class="card-header bg-dark text-white">
      <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Login Credentials</h5>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="login_submit" value="1">

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="col-form-label">Current Username</label>
            <input class="form-control bg-light mb-0" readonly value="<?= htmlspecialchars($userAcct['username']) ?>">
          </div>
          <div class="col-md-6">
            <label class="col-form-label">New Username</label>
            <input name="new_username" class="form-control mb-0" value="">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="col-form-label">Current Password</label>
            <div class="input-group mb-0">
              <input type="password" name="current_password" class="form-control" id="curPass">
              <button class="btn btn-outline-secondary" type="button" onclick="toggle('curPass')">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="col-form-label">New Password</label>
            <div class="input-group mb-0">
              <input type="password" name="new_password" class="form-control" id="newPass">
              <button class="btn btn-outline-secondary" type="button" onclick="toggle('newPass')">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="text-end">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-1"></i>Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const input = document.getElementById('newPicInput');
  const label = document.getElementById('newPicLabel');

  input.addEventListener('change', () => {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      label.style.backgroundImage = `url(${e.target.result})`;
      label.style.backgroundSize = 'cover';
      label.textContent = '';
    };
    reader.readAsDataURL(file);
  });

  function toggle(id) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
  }
</script>
