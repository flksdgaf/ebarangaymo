<?php
require 'functions/dbconn.php';
$userId  = (int) $_SESSION['loggedInUserID'];
$message = '';

// 1) Find purok table & label
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

// Fields to edit
$fields = [
    'full_name', 'birthdate', 'sex', 'civil_status',
    'blood_type', 'birth_registration_number',
    'highest_educational_attainment', 'occupation'
];

// 2) Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [];
    foreach ($fields as $f) {
        $values[$f] = trim($_POST[$f] ?? '');
    }
    $setList = implode(', ', array_map(fn($f) => "$f = ?", $fields));
    $types   = str_repeat('s', count($fields)) . 'i';
    $params  = array_merge(array_values($values), [$userId]);

    $stmt = $conn->prepare("UPDATE {$purokTable} SET {$setList} WHERE account_ID = ?");
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $message = <<<HTML
<div class="alert alert-success alert-dismissible fade show" role="alert">
  Profile updated successfully.
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
HTML;
    } else {
        $message = <<<HTML
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  Failed to update profile.
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
HTML;
    }
    $stmt->close();
}

// 3) Fetch current values
$sql   = sprintf("SELECT %s FROM %s WHERE account_ID = ? LIMIT 1", implode(',', $fields), $purokTable);
$stmt  = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?: array_fill_keys($fields, '');
$stmt->close();
?>

<div class="container py-3">
  <?= $message ?>

  <div class="card shadow-sm">
    <div class="card-header bg-dark text-white">
      <h5 class="mb-0"><i class="fas fa-user-cog me-2"></i>Account Settings</h5>
    </div>
    <div class="card-body">
      <form method="POST">

        <!-- Purok (read-only) -->
        <div class="mb-3 row">
          <label class="col-sm-3 col-form-label">Purok</label>
          <div class="col-sm-9">
            <input class="form-control" readonly value="<?= htmlspecialchars($purokLabel) ?>">
          </div>
        </div>

        <!-- Full Name -->
        <div class="mb-3 row">
          <label class="col-sm-3 col-form-label">Full Name</label>
          <div class="col-sm-9">
            <input name="full_name" class="form-control"
                   value="<?= htmlspecialchars($profile['full_name']) ?>" required>
          </div>
        </div>

        <!-- Birthdate (read-only) -->
        <div class="mb-3 row">
          <label class="col-sm-3 col-form-label">Birthdate</label>
          <div class="col-sm-9 d-flex align-items-center">
            <input type="date" class="form-control w-auto me-3" readonly
                   value="<?= htmlspecialchars($profile['birthdate']) ?>">
            <input type="hidden" name="birthdate"
                   value="<?= htmlspecialchars($profile['birthdate']) ?>">
          </div>
        </div>

        <!-- Sex (read-only as text) -->
        <div class="mb-3 row">
          <label class="col-sm-3 col-form-label">Sex</label>
          <div class="col-sm-9 d-flex align-items-center">
            <input class="form-control w-auto" readonly value="<?= htmlspecialchars($profile['sex']) ?>">
            <input type="hidden" name="sex" value="<?= htmlspecialchars($profile['sex']) ?>">
          </div>
        </div>

        <!-- Civil Status -->
        <div class="mb-3 row">
          <label class="col-sm-3 col-form-label">Civil Status</label>
          <div class="col-sm-9">
            <input name="civil_status" class="form-control"
                   value="<?= htmlspecialchars($profile['civil_status']) ?>">
          </div>
        </div>

        <!-- Blood Type & BRN -->
        <div class="row mb-3">
          <div class="col-md-6">
            <div class="row">
              <label class="col-sm-4 col-form-label">Blood Type</label>
              <div class="col-sm-8">
                <input name="blood_type" class="form-control"
                       value="<?= htmlspecialchars($profile['blood_type']) ?>">
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="row">
              <label class="col-sm-4 col-form-label">Birth Reg. No.</label>
              <div class="col-sm-8">
                <input name="birth_registration_number" class="form-control"
                       value="<?= htmlspecialchars($profile['birth_registration_number']) ?>">
              </div>
            </div>
          </div>
        </div>

        <!-- Education -->
        <div class="mb-3 row">
          <label class="col-sm-3 col-form-label">Education</label>
          <div class="col-sm-9">
            <input name="highest_educational_attainment" class="form-control"
                   value="<?= htmlspecialchars($profile['highest_educational_attainment']) ?>">
          </div>
        </div>

        <!-- Occupation -->
        <div class="mb-3 row">
          <label class="col-sm-3 col-form-label">Occupation</label>
          <div class="col-sm-9">
            <input name="occupation" class="form-control"
                   value="<?= htmlspecialchars($profile['occupation']) ?>">
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