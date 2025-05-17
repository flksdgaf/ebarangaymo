<?php
require 'dbconn.php';

$acct   = (int)$_POST['account_id'];
$orig   = $_POST['original_purok'];         // e.g. "Purok 3"
$purok  = $_POST['purok'];                  // new value
$fields = [
  'full_name'                       => $_POST['full_name'],
  'birthdate'                       => $_POST['birthdate'],
  'sex'                             => $_POST['sex'],
  'civil_status'                    => $_POST['civil_status'],
  'blood_registration_number'       => $_POST['birth_registration_number'] ?: null,
  'highest_educational_attainment'  => $_POST['highest_educational_attainment'],
  'occupation'                      => $_POST['occupation']
];

$origTable = strtolower(str_replace(' ', '', $orig)) . '_rbi';  // purok3_rbi
$newTable  = strtolower(str_replace(' ', '', $purok)) . '_rbi';

$conn->begin_transaction();

try {
  // If purok changed, move the record
  if ($origTable !== $newTable) {
    // insert into new
    $insert = $conn->prepare("
      INSERT INTO `$newTable`
        (account_ID, full_name, birthdate, sex, civil_status,
         blood_type, birth_registration_number,
         highest_educational_attainment, occupation, purok, profile_picture)
      SELECT account_ID, full_name, birthdate, sex, civil_status,
             blood_type, birth_registration_number,
             highest_educational_attainment, occupation, ?, profile_picture
      FROM `$origTable`
      WHERE account_ID=?
    ");
    $insert->bind_param("si", $purok, $acct);
    $insert->execute();
    $insert->close();

    // delete from old
    $del = $conn->prepare("DELETE FROM `$origTable` WHERE account_ID=?");
    $del->bind_param("i",$acct);
    $del->execute();
    $del->close();
  }

  // update the target table (whether moved or not)
  $target = ($origTable !== $newTable) ? $newTable : $origTable;
  $sets = [];
  $types = '';
  $vals  = [];
  foreach ($fields as $col => $val) {
    $sets[] = "`$col` = ?";
    $types .= 's';
    $vals[]  = $val;
  }
  $types .= 'i';
  $vals[]  = $acct;
  $sql = "UPDATE `$target` SET " . implode(',', $sets) . " WHERE account_ID=?";
  $upd = $conn->prepare($sql);
  $upd->bind_param($types, ...$vals);
  $upd->execute();
  $upd->close();

  $conn->commit();
  header("Location: ../adminPanel.php?page=adminResidents");
  exit();
} catch (Exception $e) {
  $conn->rollback();
  die("Update failed: " . $e->getMessage());
}
