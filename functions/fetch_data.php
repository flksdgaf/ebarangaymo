<?php
include 'dbconn.php';

$requestTypes = [
  'Barangay ID' => 'barangay_id_requests',
  'Business Permit' => 'business_permit_requests',
  'Certification' => 'certification_requests'
];

$transactionId = $_GET['transaction_id'] ?? '';
$tableType = $_GET['table_type'] ?? '';

if (!$transactionId || !$tableType) {
  echo "Invalid request.";
  exit;
}

if (!array_key_exists($tableType, $requestTypes)) {
  echo "Unknown request type.";
  exit;
}

$tableName = $requestTypes[$tableType];

// Now fetch full details
$stmt = $conn->prepare("SELECT * FROM $tableName WHERE transaction_id = ?");
$stmt->bind_param("s", $transactionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  $row = $result->fetch_assoc();
  echo "<div class='table-responsive'>";
  echo "<table class='table table-bordered'>";

  foreach ($row as $field => $value) {
    $fieldLabel = ucwords(str_replace('_', ' ', $field));
    $fieldValue = htmlspecialchars($value);

    echo "
      <tr>
        <th style='width: 30%; background-color: #f8f9fa;'>$fieldLabel</th>
        <td>$fieldValue</td>
      </tr>
    ";
  }

  echo "</table>";
  echo "</div>";
} else {
  echo "No data found.";
}


$stmt->close();
$conn->close();
?>
