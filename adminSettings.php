<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'functions/dbconn.php';

// Local messages (pulled from session if set)
$picMessage = $_SESSION['picMessage'] ?? '';
$infoMessage = $_SESSION['infoMessage'] ?? '';
$loginMessage = $_SESSION['loginMessage'] ?? '';
// Clear session-stored messages (they should only show once)
unset($_SESSION['picMessage'], $_SESSION['infoMessage'], $_SESSION['loginMessage']);

/* ---------- Logged in user ---------- */
$userId  = (int) ($_SESSION['loggedInUserID'] ?? 0);

/* ---------- Find purok table & label ---------- */
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

/* ---------- Fetch login credentials early (username and role) ---------- */
$stmt = $conn->prepare("SELECT username, role FROM user_accounts WHERE account_ID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$userAcct = $stmt->get_result()->fetch_assoc() ?: ['username' => '', 'role' => 'User'];
$stmt->close();

/* ---------- Fields we store in purok table (only civil_status is editable) ---------- */
$updateFields = [
    'civil_status'
];

/* ---------- Fetch current profile (used for change detection) ---------- */
$allFields = ['account_ID', 'profile_picture', 'full_name', 'birthdate', 'civil_status'];
$sql   = sprintf("SELECT %s FROM %s WHERE account_ID = ? LIMIT 1", implode(',', $allFields), $purokTable);
$stmt  = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$currentProfile = $stmt->get_result()->fetch_assoc() ?: array_fill_keys($allFields, '');
$stmt->close();

/* ensure profile picture variable */
$currentPic = $currentProfile['profile_picture'] ?: 'default_profile_pic.png';

/* ---------- Helper: perform client-side redirect and exit (PRG) ---------- */
function client_redirect_and_exit() {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Redirecting...</title></head><body>';
    echo '<script>window.location.replace(window.location.pathname + window.location.search);</script>';
    echo '</body></html>';
    exit;
}

/* ---------- Handle personal info submission ---------- */
if (isset($_POST['personal_submit'])) {
    // Only civil_status can be updated
    $civil_status = trim($_POST['civil_status'] ?? '');
    
    // Check if there's a change
    $changes = false;
    if (trim($currentProfile['civil_status'] ?? '') !== $civil_status) {
        $changes = true;
    }
    if (!$changes && !empty($_FILES['new_picture']['name']) && $_FILES['new_picture']['error'] === UPLOAD_ERR_OK) {
        $changes = true;
    }

    if (!$changes) {
        $_SESSION['infoMessage'] = '<div class="alert alert-info alert-dismissible fade show" role="alert">No changes detected.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        client_redirect_and_exit();
    }

    // Update civil_status
    $stmt = $conn->prepare("UPDATE {$purokTable} SET civil_status = ? WHERE account_ID = ?");
    if ($stmt) {
        $stmt->bind_param('si', $civil_status, $userId);
        if ($stmt->execute()) {
            // Handle profile picture if included
            if (!empty($_FILES['new_picture']['name']) && $_FILES['new_picture']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/profilePictures/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext = pathinfo($_FILES['new_picture']['name'], PATHINFO_EXTENSION);
                $newName = $userId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['new_picture']['tmp_name'], $uploadDir . $newName)) {
                    $u = $conn->prepare("UPDATE {$purokTable} SET profile_picture = ? WHERE account_ID = ?");
                    $u->bind_param('si', $newName, $userId);
                    $u->execute();
                    $u->close();
                    $_SESSION['picMessage'] = '<div class="alert alert-success alert-dismissible fade show">Profile picture updated.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } else {
                    $_SESSION['picMessage'] = '<div class="alert alert-danger alert-dismissible fade show">Profile picture upload failed.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                }
            }
        } else {
            $_SESSION['infoMessage'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Failed to update information.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
        $stmt->close();
    } else {
        $_SESSION['infoMessage'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Update statement could not be prepared.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }

    client_redirect_and_exit();
}

/* ---------- Handle username/password update ---------- */
if (isset($_POST['login_submit'])) {
    // fetch current info
    $stmt = $conn->prepare("SELECT username, password FROM user_accounts WHERE account_ID = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $acct = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $newUsername    = trim($_POST['new_username'] ?? '');
    $confirmUsername = trim($_POST['new_username_confirm'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword    = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['new_password_confirm'] ?? '';

    $errors = [];
    $successes = [];

    // NEW USERNAME flow (if provided)
    if ($newUsername !== '') {
        if ($newUsername !== $confirmUsername) {
            $errors[] = 'Confirm Username does not match.';
        } else {
            // check uniqueness
            $chk = $conn->prepare("SELECT account_ID FROM user_accounts WHERE username = ? AND account_ID != ?");
            $chk->bind_param('si', $newUsername, $userId);
            $chk->execute();
            if ($chk->get_result()->num_rows) {
                $errors[] = 'Username is already taken.';
            } else {
                // update username
                $upd = $conn->prepare("UPDATE user_accounts SET username = ? WHERE account_ID = ?");
                $upd->bind_param('si', $newUsername, $userId);
                $upd->execute();
                $upd->close();
                $successes[] = 'Username updated successfully.';
                $userAcct['username'] = $newUsername;
            }
            $chk->close();
        }
    }

    // NEW PASSWORD flow (if provided)
    if ($newPassword !== '') {
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Confirm New Password does not match.';
        } else {
            // check current password correct
            if (!($currentPassword && password_verify($currentPassword, $acct['password']))) {
                $errors[] = 'Current password incorrect. Password not changed.';
            } else {
                // complexity: 8-15 chars, upper & lower, number, special
                $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,15}$/';
                if (!preg_match($pattern, $newPassword)) {
                    $errors[] = 'Password must be 8-15 characters, include upper & lower case letters, at least one number and one special character.';
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE user_accounts SET password = ? WHERE account_ID = ?");
                    $upd->bind_param('si', $hash, $userId);
                    $upd->execute();
                    $upd->close();
                    $successes[] = 'Password updated successfully.';
                }
            }
        }
    }

    if ($errors) {
        $_SESSION['loginMessage'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . implode('<br>', $errors) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } elseif ($successes) {
        $_SESSION['loginMessage'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">' . implode('<br>', $successes) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    client_redirect_and_exit();
}

/* ---------- After any possible redirect above, refresh profile for display ---------- */
$stmt  = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?: array_fill_keys($allFields, '');
$stmt->close();
if (!empty($profile['profile_picture'])) $currentPic = $profile['profile_picture'];
$profile_birthdate = $profile['birthdate'] ?? '';

?>

<title>eBarangay Mo | Admin Settings</title>

<style>
:root{
  --ebg-dark:#234d2f;
  --ebg-green:#2e8b57;
}
body { background:#f3f4f6; }
.full-page { min-height:100vh; background-color: #efefef;}
.settings-tabs { display:flex; gap:0.6rem; justify-content:flex-start; align-items:center; margin-bottom:1rem; }
.tab-btn { 
  border-radius: 40px; 
  padding: 8px 20px; 
  border: 2px solid var(--ebg-green); 
  background: transparent; 
  color: var(--ebg-green); 
  cursor: pointer; 
  font-weight: 600; 
  transition: all 0.2s ease;
  font-size: 0.95rem;
}
.tab-btn.active { background:var(--ebg-green); color:#fff; border-color:var(--ebg-green); }
.profile-hero { display:flex; gap:2rem; align-items:flex-start; padding:1.5rem; background:#fff; border-radius:6px; box-shadow:0 6px 18px rgba(0,0,0,0.06); }
.left-card { width:320px; height: 410px; background:var(--ebg-dark); color:#fff; padding:28px 22px; display:flex; flex-direction:column; align-items:center; border-radius:6px; }
.left-card img { width:180px; height:180px; object-fit:cover; border-radius:100px; border:4px solid #fff; background:#fff;}
.left-username { margin-top:18px; font-size:20px; font-weight:700; letter-spacing:0.5px; text-align:center; }
.left-role { color:#90EE90; font-weight:600; margin-top:8px; font-size:16px; }
.left-id { opacity:0.9; margin-top:6px; color:#dfeee0; }
.right-form { flex:1; padding:4px 8px; }
.right-form h2 { color:var(--ebg-green); font-weight:700; margin-bottom:4px; }
.small-muted { font-size:0.86rem; color:#6c757d; }
.form-control[readonly], .form-select[disabled] { background:#f8f9fa; color:#212529; }
.edit-btn { border-radius:20px; padding:8px 20px; background:transparent; border:2px solid var(--ebg-green); color:var(--ebg-green); font-weight:600; transition: all 0.2s ease; cursor:pointer; }
.edit-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(46, 139, 87, 0.2); }
/* username/password section styling */
.cred-wrap { display:flex; gap:1rem; align-items:flex-start; }
.cred-card { flex:1; background:#fff; border-radius:6px; padding:18px; box-shadow:0 4px 12px rgba(0,0,0,0.04); border:1px solid #e9ecef; }
.cred-card h5 { margin-bottom:12px; color:var(--ebg-green); font-weight:700; }
.btn-save { border-radius:20px; padding:8px 18px; }
.alert-area { margin-bottom:12px; }

/* ============================================
  RESPONSIVE LAYOUT ADJUSTMENTS
  ============================================ */

/* Tablet view (768px - 991px) */
@media (max-width: 991px) {
  .container-fluid.full-page { padding-left: 1rem; padding-right: 1rem;}
  
  .profile-hero { 
    gap: 1.5rem; 
    padding: 1.25rem; 
  }
  
  .left-card { 
    width: 280px; 
    height: auto;
    padding: 20px 18px;
  }
  
  .left-card img { 
    width: 150px; 
    height: 150px; 
    margin-top: 20px;
  }
  
  .left-username { font-size: 18px; }
  
  .right-form { padding: 8px; }
  
  .right-form h2 { font-size: 1.5rem; }
  
  .settings-tabs { 
    gap: 0.5rem; 
    flex-wrap: wrap;
  }
  
  .tab-btn { 
    padding: 6px 16px; 
    font-size: 0.95rem; 
  }
  
  .cred-wrap { gap: 0.75rem; }
  
  .cred-card { padding: 16px; }
}

/* Mobile view (below 768px) */
@media (max-width: 767px) {
  .container-fluid.full-page { 
    padding-left: 0.75rem; 
    padding-right: 0.75rem; 
    padding-top: 0.5rem;
  }
  
  .profile-hero { 
    flex-direction: column; 
    align-items: center; 
    padding: 1rem;
  }
  
  .left-card { 
    width: 100%; 
    height: auto;
    display: flex; 
    flex-direction: row; 
    gap: 16px; 
    padding: 16px; 
    align-items: center;
  }
  
  .left-card img { 
    width: 100px; 
    height: 100px; 
    margin-top: 0;
  }
  
  .left-username { 
    font-size: 1.1rem; 
    margin-top: 0;
    text-align: left;
  }
  
  .left-role { font-size: 0.95rem; }
  
  .left-id { font-size: 0.9rem; }
  
  .right-form { 
    width: 100%; 
    padding: 12px 8px; 
  }
  
  .right-form h2 { 
    font-size: 1.3rem; 
    text-align: center;
  }
  
  .small-muted { 
    font-size: 0.8rem; 
    text-align: center;
  }
  
  .edit-btn { 
    width: 100%; 
    padding: 10px 20px; 
  }
  
  .settings-tabs { 
    justify-content: center; 
    gap: 0.4rem; 
  }
  
  .tab-btn { 
    font-size: 0.8rem; 
    padding: 7px 12px; 
    flex: 1;
    text-align: center;
    white-space: nowrap;
  }
  
  .cred-wrap { 
    flex-direction: column; 
  }
  
  .cred-card { 
    padding: 14px; 
    width: 100%;
  }
  
  .cred-card h5 { 
    font-size: 1.05rem; 
    text-align: center;
  }
  
  .btn-save { 
    width: 100%; 
    padding: 10px 18px; 
  }
  
  /* Stack form fields better on mobile */
  .col-md-6.mb-3.d-flex.gap-2 {
    flex-direction: column !important;
  }
  
  .col-md-6.mb-3.d-flex.gap-2 > div {
    flex: 1 1 100% !important;
  }
  
  /* Keep input groups horizontal on mobile for password fields */
  .input-group { 
    flex-direction: row !important; 
  }

  .input-group .form-control {
    flex: 1;
  }

  .input-group .btn { 
    width: auto !important;
    border-radius: 0 0.375rem 0.375rem 0 !important;
    padding: 0.5rem 0.75rem;
  }
}

/* Extra small mobile (below 480px) */
@media (max-width: 479px) {
  .container-fluid.full-page { 
    padding-left: 0.5rem; 
    padding-right: 0.5rem; 
  }
  
  .profile-hero { 
    padding: 0.75rem; 
  }
  
  .left-card { 
    flex-direction: column; 
    text-align: center; 
    padding: 14px;
  }
  
  .left-card img { 
    width: 110px; 
    height: 110px; 
    margin: 10px auto 0;
  }
  
  .left-username { 
    font-size: 1rem; 
    text-align: center;
    margin-top: 0;
  }

  .left-role { 
    font-size: 0.9rem;
    text-align: center;
  }

  .left-id {
    margin-top: -10px;
  }
  
  .right-form { 
    padding: 8px 4px; 
  }
  
  .right-form h2 { 
    font-size: 1.15rem; 
  }
  
  .small-muted { 
    font-size: 0.75rem; 
  }
  
  .tab-btn { 
    font-size: 0.7rem; 
    padding: 6px 8px; 
    white-space: nowrap;
  }
  
  .form-label { 
    font-size: 0.8rem; 
  }
  
  .form-control, .form-select { 
    font-size: 0.85rem; 
    padding: 0.5rem 0.75rem; 
  }
  
  .edit-btn { 
    font-size: 0.9rem; 
    padding: 8px 16px; 
  }
  
  .cred-card { 
    padding: 12px; 
  }
  
  .cred-card h5 { 
    font-size: 0.95rem; 
  }
  
  .btn-save { 
    font-size: 0.9rem; 
    padding: 8px 16px; 
  }

  #changePicBtn {
    margin-top: -20px;
  }
}
</style>

<div class="container-fluid full-page py-4">
  <div class="row">
    <div class="col-12 px-4">

      <!-- Tabs (Data Policy tab removed) -->
      <div class="settings-tabs mt-2 mb-3">
        <button id="tabProfile" class="tab-btn active" data-target="profileSection">Profile</button>
        <button id="tabPrivacy" class="tab-btn" data-target="privacySection">Username &amp; Password</button>
      </div>

      <!-- PROFILE SECTION -->
      <div id="profileSection" class="settings-section mt-4">
        <?= ($picMessage ?? '') . ($infoMessage ?? '') ?>

        <div class="profile-hero mb-4">
          <div class="left-card text-center">
            <img src="profilePictures/<?= htmlspecialchars($currentPic) ?>" alt="Avatar">
            <div class="left-username"><?= htmlspecialchars($profile['full_name'] ?: ($userAcct['username'] ?? '')) ?></div>
            <div class="left-role"><?= htmlspecialchars($userAcct['role'] ?? 'User') ?></div>
            <div class="left-id">#<?= htmlspecialchars($profile['account_ID'] ?? '') ?></div>

            <!-- Change Profile Picture button -->
            <div style="width:100%; display:flex; justify-content:center; margin-top: 20px;">
              <button type="button" id="changePicBtn" class="edit-btn">Change Profile Picture</button>
            </div>
          </div>

          <div class="right-form">
            <h2>Personal Details</h2>

            <form id="personalForm" method="POST" enctype="multipart/form-data" class="row" onkeydown="return handleFormKeydown(event)">
              <input type="hidden" name="personal_submit" value="1">
              <input type="hidden" name="birthdate" id="birthdateHidden" value="<?= htmlspecialchars($profile_birthdate) ?>">

              <div class="col-12 mb-3">
                <div class="small-muted">Administrator information</div>
              </div>

              <div class="col-md-6 mb-3">
                <label class="form-label small-muted">Full Name</label>
                <input name="full_name" id="full_name" class="form-control" value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>" readonly required>
              </div>

              <div class="col-md-6 mb-3">
                <label class="form-label small-muted">Purok</label>
                <input name="address_display" id="address_display" class="form-control" value="<?= htmlspecialchars($purokLabel) ?>" readonly>
              </div>

              <div class="col-md-6 mb-3 d-flex gap-2">
                <div style="flex:0 0 35%;">
                  <label class="form-label small-muted">Birth Month</label>
                  <input type="text" id="birth_m" class="form-control" readonly value="<?= $profile['birthdate'] ? date('M', strtotime($profile['birthdate'])) : '' ?>">
                </div>
                <div style="flex:0 0 25%;">
                  <label class="form-label small-muted">Day</label>
                  <input type="text" id="birth_d" class="form-control" readonly value="<?= $profile['birthdate'] ? date('d', strtotime($profile['birthdate'])) : '' ?>">
                </div>
                <div style="flex:0 0 35%;">
                  <label class="form-label small-muted">Year</label>
                  <input type="text" id="birth_y" class="form-control" readonly value="<?= $profile['birthdate'] ? date('Y', strtotime($profile['birthdate'])) : '' ?>">
                </div>
              </div>

              <div class="col-md-6 mb-3">
                <label class="form-label small-muted">Civil Status</label>
                <select name="civil_status" id="civil_status" class="form-select" disabled>
                  <?php foreach (['Single','Married','Widowed','Separated','Divorced','Unknown'] as $status): ?>
                    <option value="<?= $status ?>" <?= (isset($profile['civil_status']) && $profile['civil_status'] == $status) ? 'selected' : '' ?>><?= $status ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Hidden file input -->
              <input type="file" name="new_picture" id="new_picture" accept="image/*" style="display:none">

              <div class="col-12 mt-2">
                <div class="small-muted">To update any personal information, please contact the system administrator.</div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- USERNAME & PASSWORD SECTION -->
      <div id="privacySection" class="settings-section d-none">
        <?= $loginMessage ?>
        <div class="cred-wrap mb-4">
          <!-- Username card -->
          <div class="cred-card">
            <div id="usernameAlertArea" class="alert-area"></div>
            <h5>Username</h5>
            <form id="usernameForm" method="POST" class="mb-0" onsubmit="return validateUsernameForm(event)">
              <input type="hidden" name="login_submit" value="1">
              <div class="mb-3">
                <label class="form-label small-muted">Current Username</label>
                <input class="form-control bg-light" readonly value="<?= htmlspecialchars($userAcct['username'] ?? '') ?>">
              </div>
              <div class="mb-3">
                <label class="form-label small-muted">New Username</label>
                <input name="new_username" id="new_username" class="form-control" value="" placeholder="Enter a new username">
              </div>
              <div class="mb-3">
                <label class="form-label small-muted">Confirm Username</label>
                <input name="new_username_confirm" id="new_username_confirm" class="form-control" value="" placeholder="Confirm new username">
              </div>
              <div class="text-end">
                <button type="submit" class="edit-btn btn-save"><i class="fas fa-user-edit me-1"></i>Save Username</button>
              </div>
            </form>
          </div>

          <!-- Password card -->
          <div class="cred-card">
            <div id="passwordAlertArea" class="alert-area"></div>
            <h5>Password</h5>
            <form id="passwordForm" method="POST" class="mb-0" onsubmit="return validatePasswordForm(event)">
              <input type="hidden" name="login_submit" value="1">
              <div class="mb-3">
                <label class="form-label small-muted">Current Password</label>
                <div class="input-group">
                  <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Enter current password">
                  <button class="btn btn-outline-secondary" type="button" onclick="toggle('current_password')"><i class="fas fa-eye"></i></button>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label small-muted">New Password</label>
                <div class="input-group">
                  <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Enter new password">
                  <button class="btn btn-outline-secondary" type="button" onclick="toggle('new_password')"><i class="fas fa-eye"></i></button>
                </div>
                <div class="small-muted mt-1">At least 8–15 characters, upper &amp; lowercase letters, a number and a special character.</div>
              </div>
              <div class="mb-3">
                <label class="form-label small-muted">Confirm New Password</label>
                <div class="input-group">
                  <input type="password" name="new_password_confirm" id="new_password_confirm" class="form-control" placeholder="Confirm new password">
                  <button class="btn btn-outline-secondary" type="button" onclick="toggle('new_password_confirm')"><i class="fas fa-eye"></i></button>
                </div>
              </div>
              <div class="text-end">
                <button type="submit" class="edit-btn btn-save"><i class="fas fa-key me-1"></i>Save Password</button>
              </div>
            </form>
          </div>
        </div>
      </div>

    </div> <!-- /.col-12 -->
  </div> <!-- /.row -->
</div> <!-- /.container-fluid -->

<script>
// Tab switching with active state
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const target = this.dataset.target;
    document.querySelectorAll('.settings-section').forEach(s => s.classList.add('d-none'));
    document.getElementById(target).classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
});

// Toggle password visibility helper
function toggle(id) {
  const inp = document.getElementById(id);
  if (!inp) return;
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

/* ----------------------------
   Profile picture flow
   ---------------------------- */
const personalForm = document.getElementById('personalForm');
const changePicBtn = document.getElementById('changePicBtn');
const newPictureInput = document.getElementById('new_picture');

if (changePicBtn && newPictureInput && personalForm) {
  // Open native file picker when button clicked
  changePicBtn.addEventListener('click', function (e) {
    e.preventDefault();
    newPictureInput.click();
  });

  // When a file is chosen, submit the form
  newPictureInput.addEventListener('change', function () {
    if (newPictureInput.files && newPictureInput.files.length) {
      personalForm.submit();
    }
  });
}

// Prevent Enter from submitting the personal form accidentally
function handleFormKeydown(e) {
  if (e.key === 'Enter') {
    const tag = (e.target && e.target.tagName || '').toLowerCase();
    const type = (e.target && e.target.type || '').toLowerCase();
    if (tag === 'textarea' || type === 'file') return true;
    e.preventDefault();
    return false;
  }
  return true;
}

/* ----------------------------
   Username / Password helpers
   ---------------------------- */
function showAlert(areaId, type, message) {
  const area = document.getElementById(areaId);
  if (!area) return;
  area.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
function clearAlert(areaId) {
  const area = document.getElementById(areaId);
  if (area) area.innerHTML = '';
}

function validateUsernameForm(e) {
  clearAlert('usernameAlertArea');
  const newU = document.getElementById('new_username').value.trim();
  const confirmU = document.getElementById('new_username_confirm').value.trim();
  if (newU === '' && confirmU === '') {
    showAlert('usernameAlertArea', 'info', 'Enter a new username to change it.');
    return false;
  }
  if (newU !== confirmU) {
    showAlert('usernameAlertArea', 'danger', 'Confirm Username does not match.');
    return false;
  }
  return true;
}

function validatePasswordForm(e) {
  clearAlert('passwordAlertArea');
  const cur = document.getElementById('current_password').value;
  const nw = document.getElementById('new_password').value;
  const cnf = document.getElementById('new_password_confirm').value;

  if (nw === '' && cnf === '') {
    showAlert('passwordAlertArea', 'info', 'Enter a new password to change it.');
    return false;
  }
  if (nw !== cnf) {
    showAlert('passwordAlertArea', 'danger', 'Confirm New Password does not match.');
    return false;
  }
  const pattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,15}$/;
  if (!pattern.test(nw)) {
    showAlert('passwordAlertArea', 'danger', 'Password must be 8-15 characters, include upper & lower case letters, at least one number and one special character.');
    return false;
  }
  if (!cur) {
    showAlert('passwordAlertArea', 'danger', 'Current password is required to change your password.');
    return false;
  }
  return true;
}

// auto-hide alerts after 8s
document.addEventListener('DOMContentLoaded', function () {
  setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
      try { bootstrap.Alert.getOrCreateInstance(a).close(); } catch(e) {}
    });
  }, 8000);
});
</script>