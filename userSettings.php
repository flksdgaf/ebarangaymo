<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'functions/dbconn.php';

// Local messages (pulled from session if set)
$picMessage = $_SESSION['picMessage'] ?? '';
$infoMessage = $_SESSION['infoMessage'] ?? '';
$loginMessage = $_SESSION['loginMessage'] ?? '';
$idUploadMessage = $_SESSION['idUploadMessage'] ?? '';
// Clear session-stored messages (they should only show once)
unset($_SESSION['picMessage'], $_SESSION['infoMessage'], $_SESSION['loginMessage'], $_SESSION['idUploadMessage']);

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

/* ---------- Fetch login credentials early (username) ---------- */
$stmt = $conn->prepare("SELECT username FROM user_accounts WHERE account_ID = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$userAcct = $stmt->get_result()->fetch_assoc() ?: ['username' => ''];
$stmt->close();

/* ---------- Fields we store in purok table (ONLY columns that exist) ---------- */
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

/* ---------- Fetch current profile (used for change detection) ---------- */
$allFields = array_merge(['account_ID','profile_picture'], $updateFields);
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

/* ---------- Handle Valid ID upload from modal ---------- */
if (isset($_POST['valid_id_submit'])) {
    if (!empty($_FILES['valid_id_file']['name'])) {
        $uploadDir = __DIR__ . '/validIDs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = pathinfo($_FILES['valid_id_file']['name'], PATHINFO_EXTENSION);
        $newName = $userId . '_validid_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['valid_id_file']['tmp_name'], $uploadDir . $newName)) {
            $_SESSION['idUploadMessage'] = '<div class="alert alert-success alert-dismissible fade show">Valid ID uploaded successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } else {
            $_SESSION['idUploadMessage'] = '<div class="alert alert-danger alert-dismissible fade show">Failed to upload Valid ID.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    } else {
        $_SESSION['idUploadMessage'] = '<div class="alert alert-warning alert-dismissible fade show">Please choose a file to upload.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    client_redirect_and_exit();
}

/* ---------- Handle personal info submission (Edit Profile -> Save Changes) ---------- */
if (isset($_POST['personal_submit'])) {
    // gather posted values
    $posted = [];
    foreach ($updateFields as $f) {
        $posted[$f] = trim($_POST[$f] ?? '');
    }
    $postedUsername = trim($_POST['username'] ?? $userAcct['username']);

    // detect if any changes exist compared to $currentProfile
    $changes = false;
    foreach ($updateFields as $f) {
        $cur = trim($currentProfile[$f] ?? '');
        $new = $posted[$f];
        if ($cur !== $new) { $changes = true; break; }
    }
    if (!$changes && $postedUsername !== ($userAcct['username'] ?? '')) $changes = true;
    if (!$changes && !empty($_FILES['new_picture']['name']) && $_FILES['new_picture']['error'] === UPLOAD_ERR_OK) $changes = true;

    if (!$changes) {
        $_SESSION['infoMessage'] = '<div class="alert alert-info alert-dismissible fade show" role="alert">No changes detected.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        client_redirect_and_exit();
    }

    // username uniqueness check & update (if changed)
    if ($postedUsername && $postedUsername !== ($userAcct['username'] ?? '')) {
        $chk = $conn->prepare("SELECT account_ID FROM user_accounts WHERE username = ? AND account_ID != ?");
        $chk->bind_param('si', $postedUsername, $userId);
        $chk->execute();
        if ($chk->get_result()->num_rows) {
            $_SESSION['infoMessage'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Username is already taken.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            $chk->close();
            client_redirect_and_exit();
        } else {
            $chk->close();
            $upd = $conn->prepare("UPDATE user_accounts SET username = ? WHERE account_ID = ?");
            $upd->bind_param('si', $postedUsername, $userId);
            $upd->execute();
            $upd->close();
            $userAcct['username'] = $postedUsername;
        }
    }

    // Update purok table personal fields
    $setList = implode(', ', array_map(fn($f) => "$f = ?", $updateFields));
    $types   = str_repeat('s', count($updateFields)) . 'i';
    $params  = array_merge(array_values($posted), [$userId]);

    $stmt = $conn->prepare("UPDATE {$purokTable} SET {$setList} WHERE account_ID = ?");
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            // profile picture if included
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
            $_SESSION['infoMessage'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">Personal information updated successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } else {
            $_SESSION['infoMessage'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Failed to update personal information.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
        $stmt->close();
    } else {
        $_SESSION['infoMessage'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Update statement could not be prepared.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }

    client_redirect_and_exit();
}

/* ---------- Handle username/password update (privacy section) ---------- */
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
        // confirm matches
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
        // must confirm and meet complexity
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
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>eBarangay Mo | User Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --ebg-dark:#234d2f;
      --ebg-green:#2e8b57;
    }
    body { background:#f3f4f6; }
    body.no-scroll { overflow: hidden; }
    .full-page { min-height:100vh; }
    .settings-tabs { display:flex; gap:0.6rem; justify-content:flex-start; align-items:center; margin-bottom:1rem; }
    .tab-btn { border-radius:40px; padding:6px 18px; border:2px solid var(--ebg-green); background:transparent; color:var(--ebg-green); cursor:pointer; font-weight:600; }
    .tab-btn.active { background:var(--ebg-green); color:#fff; border-color:var(--ebg-green); }
    .profile-hero { display:flex; gap:2rem; align-items:flex-start; padding:1.5rem; background:#fff; border-radius:6px; box-shadow:0 6px 18px rgba(0,0,0,0.06); }
    .left-card { width:320px; height: 410px; background:var(--ebg-dark); color:#fff; padding:28px 22px; display:flex; flex-direction:column; align-items:center; }
    .left-card img { width:180px; height:180px; object-fit:cover; border-radius:100px; border:4px solid #fff; background:#fff; margin-top: 30px;}
    .left-username { margin-top:18px; font-size:20px; font-weight:700; letter-spacing:0.5px; }
    .left-id { opacity:0.9; margin-top:6px; color:#dfeee0; }
    .right-form { flex:1; padding:4px 8px; }
    .right-form h2 { color:var(--ebg-green); font-weight:700; margin-bottom:4px; }
    .small-muted { font-size:0.86rem; color:#6c757d; }
    .form-control[readonly], .form-select[disabled] { background:#f8f9fa; color:#212529; }
    .upload-file-btn { display:inline-block; margin-top:8px; }
    .upload-valid-link { font-size:0.9rem; color:#111827; text-decoration:none; }
    .upload-valid-link a { text-decoration:none; color:inherit; cursor:pointer; font-weight:700; }
    .profile-actions { display:flex; gap:12px; align-items:center; justify-content:flex-end; margin-top:12px; }
    .edit-btn { border-radius:20px; padding:8px 20px; background:transparent; border:2px solid var(--ebg-green); color:var(--ebg-green); font-weight:600; }
    .edit-btn.editing { background:var(--ebg-green); color:#fff; border-color:var(--ebg-green); }
    .valid-id-note { font-size:0.85rem; color:#6c757d; }
    /* username/password section styling to align with profile */
    .cred-wrap { display:flex; gap:1rem; align-items:flex-start; }
    .cred-card { flex:1; background:#fff; border-radius:6px; padding:18px; box-shadow:0 4px 12px rgba(0,0,0,0.04); border:1px solid #e9ecef; }
    .cred-card h5 { margin-bottom:12px; color:var(--ebg-green); font-weight:700; }
    .btn-save { border-radius:20px; padding:8px 18px; }
    .alert-area { margin-bottom:12px; }
    @media (max-width: 900px) {
      .profile-hero { flex-direction:column; align-items:center; }
      .left-card { width:100%; display:flex; flex-direction:row; gap:16px; padding:16px; }
      .left-username { font-size:1rem; }
      .cred-wrap { flex-direction:column; }
    }
  </style>
</head>
<body class="no-scroll">
  <div class="container-fluid full-page py-4">
    <div class="row">
      <div class="col-12 px-4">

        <!-- Tabs -->
        <div class="settings-tabs mt-2 mb-3">
          <button id="tabProfile" class="tab-btn active" data-target="profileSection">Profile</button>
          <button id="tabPrivacy" class="tab-btn" data-target="privacySection">Username &amp; Password</button>
          <button id="tabPolicy" class="tab-btn" data-target="policySection">Data Policy</button>
        </div>

        <!-- PROFILE SECTION -->
        <div id="profileSection" class="settings-section mt-4">
          <?= ($picMessage ?? '') . ($infoMessage ?? '') . ($idUploadMessage ?? '') ?>

          <div class="profile-hero mb-4">
            <div class="left-card text-center">
              <img src="profilePictures/<?= htmlspecialchars($currentPic) ?>" alt="Avatar">
              <div class="left-username"><?= htmlspecialchars($profile['full_name'] ?: ($userAcct['username'] ?? '')) ?></div>
              <div class="left-id">#<?= htmlspecialchars($profile['account_ID'] ?? '') ?></div>
            </div>

            <div class="right-form">
              <h2>Personal Details</h2>

              <form id="personalForm" method="POST" enctype="multipart/form-data" class="row" onkeydown="return handleFormKeydown(event)">
                <input type="hidden" name="personal_submit" value="1">
                <input type="hidden" name="birthdate" id="birthdateHidden" value="<?= htmlspecialchars($profile_birthdate) ?>">

                <div class="col-12 d-flex justify-content-between align-items-start mb-3">
                  <div class="small-muted">Fill your personal information</div>
                  <div class="upload-valid-link">
                    Want to fill these without typing? <a id="openValidId" data-bs-toggle="modal" data-bs-target="#uploadIDModal">Upload a Valid ID</a>
                  </div>
                </div>

                <div class="col-md-6 mb-3">
                  <label class="form-label small-muted">Full Name</label>
                  <input name="full_name" id="full_name" class="form-control" value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>" readonly required>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label small-muted">Username</label>
                  <input name="username" id="username" class="form-control" value="<?= htmlspecialchars($userAcct['username'] ?? '') ?>" readonly required>
                </div>

                <div class="col-md-6 mb-3">
                  <label class="form-label small-muted">Address</label>
                  <input name="address_display" id="address_display" class="form-control" value="<?= htmlspecialchars($profile['address'] ?? $purokLabel) ?>" readonly>
                </div>

                <div class="col-md-6 mb-3 d-flex gap-2">
                  <div style="flex:0 0 30%;">
                    <label class="form-label small-muted">Birth Month</label>
                    <input type="text" id="birth_m" class="form-control" readonly value="<?= $profile['birthdate'] ? date('M', strtotime($profile['birthdate'])) : '' ?>">
                  </div>
                  <div style="flex:0 0 20%;">
                    <label class="form-label small-muted">Day</label>
                    <input type="text" id="birth_d" class="form-control" readonly value="<?= $profile['birthdate'] ? date('d', strtotime($profile['birthdate'])) : '' ?>">
                  </div>
                  <div style="flex:0 0 30%;">
                    <label class="form-label small-muted">Year</label>
                    <input type="text" id="birth_y" class="form-control" readonly value="<?= $profile['birthdate'] ? date('Y', strtotime($profile['birthdate'])) : '' ?>">
                  </div>
                </div>

                <div class="col-md-3 mb-3">
                  <label class="form-label small-muted">Sex</label>
                  <select name="sex" id="sex" class="form-select" disabled>
                    <?php foreach (['Male', 'Female', 'Prefer not to say', 'Unknown'] as $sexOption): ?>
                      <option value="<?= $sexOption ?>" <?= (isset($profile['sex']) && $profile['sex'] === $sexOption) ? 'selected' : '' ?>><?= $sexOption ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3 mb-3">
                  <label class="form-label small-muted">Civil Status</label>
                  <select name="civil_status" id="civil_status" class="form-select" disabled>
                    <?php foreach (['Single','Married','Widowed','Separated','Divorced','Unknown'] as $status): ?>
                      <option value="<?= $status ?>" <?= (isset($profile['civil_status']) && $profile['civil_status'] == $status) ? 'selected' : '' ?>><?= $status ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label small-muted">Contact Number</label>
                  <input name="contact_number_display" id="contact_number_display" class="form-control" value="<?= htmlspecialchars($profile['contact_number'] ?? '') ?>" readonly>
                </div>

                <div class="col-md-4 mb-3">
                  <label class="form-label small-muted">Birth Registration No.</label>
                  <input name="birth_registration_number" id="birth_registration_number" class="form-control" value="<?= htmlspecialchars($profile['birth_registration_number'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label small-muted">Education</label>
                  <select name="highest_educational_attainment" id="hea" class="form-select" disabled>
                    <?php foreach (['Kindergarten','Elementary','High School','Senior High School','Undergraduate','College Graduate','Post-Graduate','Vocational','None','Unknown'] as $edu): ?>
                      <option value="<?= $edu ?>" <?= (isset($profile['highest_educational_attainment']) && $profile['highest_educational_attainment'] == $edu) ? 'selected' : '' ?>><?= $edu ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label small-muted">Occupation</label>
                  <input name="occupation" id="occupation" class="form-control" value="<?= htmlspecialchars($profile['occupation'] ?? '') ?>" readonly>
                </div>

                <div class="col-12 mb-2 d-none" id="profilePicUploadWrap">
                  <label class="form-label small-muted">Upload New Profile Picture</label>
                  <div class="d-flex gap-2 align-items-center">
                    <input type="file" name="new_picture" id="new_picture" accept="image/*" class="form-control form-control-sm">
                    <div class="small-muted">Max 2MB</div>
                  </div>
                </div>

                <div class="col-12 profile-actions">
                  <button type="button" id="editProfileBtn" class="edit-btn">Edit Profile</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- USERNAME & PASSWORD SECTION (NEW LAYOUT) -->
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

        <!-- DATA POLICY SECTION -->
        <div id="policySection" class="settings-section d-none">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="mb-3" style="color:var(--ebg-green)">Data Privacy Policy</h5>
              <p class="small-muted">eBarangay Mo is operated by the Barangay Local Government Unit to provide online barangay services such as applications, certificates, reports, payment processing, and recordkeeping. We collect and process only the minimum personal data necessary to deliver services...</p>
              <div class="form-check mt-3">
                <input type="checkbox" class="form-check-input" id="agreePolicy">
                <label for="agreePolicy" class="form-check-label">I have read and agree to the eBarangay Mo Privacy & Data Protection Policy.</label>
              </div>
            </div>
          </div>
        </div>

        <!-- Upload Valid ID Modal -->
        <div class="modal fade" id="uploadIDModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                  <h5 class="modal-title">Upload a Valid ID</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <p class="valid-id-note">Upload a clear scanned copy or photo of a valid ID (driver's license, passport, SSS/GSIS, UMID, etc.).</p>
                  <div class="mb-3">
                    <input type="file" name="valid_id_file" accept="image/*,.pdf" class="form-control" required>
                  </div>
                </div>
                <div class="modal-footer">
                  <input type="hidden" name="valid_id_submit" value="1">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-success">Upload</button>
                </div>
              </form>
            </div>
          </div>
        </div>

      </div> <!-- /.col-12 -->
    </div> <!-- /.row -->
  </div> <!-- /.container-fluid -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

    // Edit Profile toggle behavior (keeps previous logic)
    const editBtn = document.getElementById('editProfileBtn');
    const personalForm = document.getElementById('personalForm');
    const body = document.body;
    let editing = false;

    function setEditable(enable) {
      personalForm.querySelectorAll('input, select, textarea').forEach(el => {
        if (el.type === 'hidden' || el.type === 'file') return;
        if (['address_display','contact_number_display','birth_m','birth_d','birth_y'].includes(el.name) || el.id === 'address_display' || el.id === 'contact_number_display') return;
        if (el.tagName.toLowerCase() === 'select') {
          el.disabled = !enable;
        } else {
          el.readOnly = !enable;
        }
      });

      const picWrap = document.getElementById('profilePicUploadWrap');
      if (enable) picWrap.classList.remove('d-none'); else picWrap.classList.add('d-none');

      if (enable) {
        editBtn.classList.add('editing');
        editBtn.textContent = 'Save Changes';
        body.classList.remove('no-scroll');
      } else {
        editBtn.classList.remove('editing');
        editBtn.textContent = 'Edit Profile';
        body.classList.add('no-scroll');
      }
      editing = enable;
    }

    setEditable(false);

    function handleFormKeydown(e) {
      if (!editing && e.key === 'Enter') {
        e.preventDefault();
        return false;
      }
      return true;
    }

    editBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (!editing) {
        setEditable(true);
        setTimeout(() => {
          const first = personalForm.querySelector('input:not([readonly]), select:not([disabled])');
          if (first) first.focus();
        }, 60);
      } else {
        personalForm.submit();
      }
    });

    // username form client-side validation
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
      // run before submit
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
      // ok - allow submit
      return true;
    }

    // password form client-side validation & complexity
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
      // complexity check: 8-15 chars, upper & lower, number, special
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

    // prevent modal click from submitting form; open modal safely
    const openValid = document.getElementById('openValidId');
    if (openValid) {
      openValid.addEventListener('click', function (e) {
        e.preventDefault();
        const modalEl = document.getElementById('uploadIDModal');
        if (modalEl) {
          const modal = new bootstrap.Modal(modalEl);
          modal.show();
        }
      });
    }

    document.getElementById('uploadIDModal')?.addEventListener('show.bs.modal', function () {
      if (!editing) document.body.classList.remove('no-scroll');
    });
    document.getElementById('uploadIDModal')?.addEventListener('hidden.bs.modal', function () {
      if (!editing) document.body.classList.add('no-scroll');
    });

    // auto-hide alerts after 8s
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
          try { bootstrap.Alert.getOrCreateInstance(a).close(); } catch(e) {}
        });
      }, 8000);
    });
  </script>
</body>
</html>
