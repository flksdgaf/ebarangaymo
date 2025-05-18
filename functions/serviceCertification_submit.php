<?php
// functions/serviceCertification_submit.php
require_once 'dbconn.php';
session_start();

// 1) Auth check
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    header("HTTP/1.1 403 Forbidden");
    exit("Not authorized");
}

// 2) Required POST fields
$required = ['certification_type','name','age','civil_status','purok','claim_date','purpose','paymentMethod'];
foreach ($required as $f) {
    if (empty($_POST[$f])) {
        header("HTTP/1.1 400 Bad Request");
        exit("Missing field $f");
    }
}

// 3) Map type → table & prefix
$type = $_POST['certification_type'];
$map  = [
  'Residency' => ['table'=>'residency_requests','prefix'=>'RES-', 'amount'=>130],
  'Indigency' => ['table'=>'indigency_requests','prefix'=>'IND-', 'amount'=>130],
  // add more if you have Good Moral, etc.
];


if (!isset($map[$type])) {
    header("HTTP/1.1 400 Bad Request");
    exit("Unknown certification type");
}

$table  = $map[$type]['table'];
$prefix = $map[$type]['prefix'];
$amount = $map[$type]['amount'];
$acct   = (int) $_SESSION['loggedInUserID'];

// 4) Generate next transaction_id
$stmt = $conn->prepare("
  SELECT transaction_id 
    FROM `{$table}`
   ORDER BY id DESC
   LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $lastTid = $res->fetch_assoc()['transaction_id'];
    $num     = intval(substr($lastTid, strlen($prefix))) + 1;
} else {
    $num = 1;
}
$stmt->close();

$transactionId = sprintf('%s%07d', $prefix, $num);

// 5) Gather common fields
$fullName      = $_POST['name'];
$age           = (int) $_POST['age'];
$civilStatus   = $_POST['civil_status'];
$purok         = $_POST['purok'];
$claimDate     = $_POST['claim_date'];
$purpose       = $_POST['purpose'];
$paymentMethod = $_POST['paymentMethod'];

// 6) Build & execute INSERT
if ($type === 'Residency') {
    // must have years_residing
    $years = isset($_POST['years_residing']) ? (int)$_POST['years_residing'] : 0;

    $sql = "
      INSERT INTO `{$table}`
        (account_id, transaction_id, full_name, age, civil_status,
         purok, residing_years, claim_date, purpose,
         payment_method, amount)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      "issississsi",
      $acct,
      $transactionId,
      $fullName,
      $age,
      $civilStatus,
      $purok,
      $years,
      $claimDate,
      $purpose,
      $paymentMethod,
      $amount
    );
}
elseif ($type === 'Indigency') {
    // no years_residing column
    $sql = "
      INSERT INTO `{$table}`
        (account_id, transaction_id, full_name, age, civil_status,
         purok, claim_date, purpose,
         payment_method, amount)
      VALUES (?,?,?,?,?,?,?,?,?,?)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      "ississsssi",
      $acct,
      $transactionId,
      $fullName,
      $age,
      $civilStatus,
      $purok,
      $claimDate,
      $purpose,
      $paymentMethod,
      $amount
    );
}
else {
    // you can add more elseif blocks for other types…
    header("HTTP/1.1 400 Bad Request");
    exit("Unsupported certification type");
}

if (!$stmt->execute()) {
    error_log("Insert failed: " . $stmt->error);
    header("HTTP/1.1 500 Internal Server Error");
    exit("Database error");
}

$stmt->close();
$conn->close();

// 7) Redirect to a “thank you” or “receipt” page, passing the new transaction ID
header("Location: ../userPanel.php?page=serviceCertification&tid={$transactionId}");
exit;
