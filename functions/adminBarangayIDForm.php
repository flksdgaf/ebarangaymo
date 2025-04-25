<?php
// partials/partialBarangayIDForm.php
session_start();
require __DIR__ . '/dbconn.php';
$userId = $_SESSION['loggedInUserID'];
$chosenPayment = ''; 

// 1) Check for existing Brgy-ID record
$stmt = $conn->prepare("
  SELECT * 
    FROM barangayid_accounts 
   WHERE account_id = ? 
   LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();

$isRenewal = $res && $res->num_rows === 1;
if ($isRenewal) {
  $row = $res->fetch_assoc();
  $fullName       = $row['full_name'];
  $fullAddress    = $row['address'];
  $height         = $row['height'];
  $weight         = $row['weight'];
  $birthdate      = $row['birthdate'];
  $birthplace     = $row['birthplace'];
  $civilstatus    = $row['civil_status'];
  $religion       = $row['religion'];
  $contactperson  = $row['contact_person'];
} else {
  // New Application → leave every field empty
  $fullName = '';
  $fullAddress = '';
  $birthdate = '';
  $height = $weight = $birthplace = $civilstatus = $religion = $contactperson = '';
  $chosenPayment = ''; 
}

$transactionType = $isRenewal ? 'Renewal' : 'New Application';
?>

<form id="barangayIDForm"
      action="functions/serviceBarangayID_submit.php"
      method="POST" 
      enctype="multipart/form-data">

  <!-- TYPE OF TRANSACTION -->
  <div class="mb-3">
    <label class="form-label">Type of Transaction</label>
    <select class="form-select" disabled>
      <option><?= htmlspecialchars($transactionType, ENT_QUOTES, 'UTF-8') ?></option>
    </select>
    <input type="hidden" name="transactiontype" value="<?= htmlspecialchars($transactionType, ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <!-- FULL NAME -->
  <div class="mb-3">
    <label class="form-label">Full Name</label>
    <input type="text" name="fullname" class="form-control" required
           value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <!-- FULL ADDRESS -->
  <div class="mb-3">
    <label class="form-label">Full Address</label>
    <input type="text" name="address" class="form-control" required
        value="<?= htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <!-- HEIGHT -->
  <div class="mb-3">
    <label class="form-label">Height (cm)</label>
    <input type="number" name="height" class="form-control" required
           value="<?= htmlspecialchars($height, ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <!-- WEIGHT -->
  <div class="mb-3">
    <label class="form-label">Weight (kg)</label>
    <input type="number" name="weight" class="form-control" required
           value="<?= htmlspecialchars($weight, ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <!-- BIRTHDAY -->
  <div class="mb-3">
    <label class="form-label">Birthday</label>
    <input type="date" name="birthday" class="form-control" required
        value="<?= $birthdate ? date('Y-m-d', strtotime($birthdate)) : '' ?>">
  </div>

  <!-- BIRTHPLACE -->
  <div class="mb-3">
    <label class="form-label">Birthplace</label>
    <input type="text" name="birthplace" class="form-control" required
            <?php if ($isRenewal): ?>readonly<?php endif; ?>
           value="<?= htmlspecialchars($birthplace, ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <!-- CIVIL STATUS -->
  <div class="mb-3">
    <label class="form-label">Civil Status</label>
    <select name="civilstatus" class="form-select" required>
        <option value="" disabled <?= $civilstatus === '' ? 'selected' : '' ?>>
            -- Select Civil Status --
        </option>
        <?php foreach (['Single','Married','Separated','Widowed'] as $opt): ?>
        <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"
        <?= $opt === $civilstatus ? 'selected' : '' ?>>
        <?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php endforeach ?>
    </select>
  </div>

  <!-- RELIGION -->
  <div class="mb-3">
    <label class="form-label">Religion</label>
    <input type="text" name="religion" class="form-control" required
           value="<?= htmlspecialchars($religion, ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <!-- CONTACT PERSON -->
  <div class="mb-3">
    <label class="form-label">Contact Person</label>
    <input type="text" name="contactperson" class="form-control" required
           value="<?= htmlspecialchars($contactperson, ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <!-- FORMAL PICTURE -->
  <div class="mb-3">
    <label class="form-label">1x1 Formal Picture</label>
    <input type="file" name="brgyIDpicture" class="form-control"
           accept="image/*" <?= $isRenewal ? '' : 'required' ?>>
  </div>

  <!-- CLAIM DATE -->
  <div class="mb-3">
    <label class="form-label">Preferred Claim Date</label>
    <input type="date" name="claimdate" class="form-control" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Payment Method</label>
    <select name="paymentMethod" class="form-select" required>
      <option value="">-- Select Payment Method --</option>
      <option value="GCash"<?= (!empty($chosenPayment) && $chosenPayment==='GCash') ? ' selected' : '' ?>>GCash</option>
      <option value="Brgy Payment Device"<?= (!empty($chosenPayment) && $chosenPayment==='Brgy Payment Device') ? ' selected' : '' ?>>Brgy Payment Device</option>
      <option value="Over-the-Counter"<?= (!empty($chosenPayment) && $chosenPayment==='Over-the-Counter') ? ' selected' : '' ?>>Over-the-Counter</option>
    </select>
  </div>

  <input type="hidden" name="adminRedirect" value="1">
</form>
