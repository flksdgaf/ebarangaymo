<?php
require 'dbconn.php';
header('Content-Type: application/json');

$id = (int)$_GET['account_id'];
// search all purok tables
$sql = "
  SELECT *, 'purok1_rbi' AS tbl FROM purok1_rbi WHERE account_ID=?
  UNION ALL
  SELECT *, 'purok2_rbi'       FROM purok2_rbi WHERE account_ID=?
  UNION ALL
  SELECT *, 'purok3_rbi'       FROM purok3_rbi WHERE account_ID=?
  UNION ALL
  SELECT *, 'purok4_rbi'       FROM purok4_rbi WHERE account_ID=?
  UNION ALL
  SELECT *, 'purok5_rbi'       FROM purok5_rbi WHERE account_ID=?
  UNION ALL
  SELECT *, 'purok6_rbi'       FROM purok6_rbi WHERE account_ID=?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiiii",$id,$id,$id,$id,$id,$id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
  echo json_encode($row);
} else {
  echo json_encode([]);
}
