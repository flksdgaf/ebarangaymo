<?php
session_start();
require 'dbconn.php';
require '../vendor/autoload.php'; // for Dompdf

use Dompdf\Dompdf;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $transaction_id = $_POST['transaction_id'] ?? '';
  $scheduled_at   = $_POST['scheduled_at'] ?? '';
  $account_id     = $_SESSION['loggedInUserID'] ?? null;

  if (!$transaction_id || !$scheduled_at || !$account_id) {
    die("Missing required fields.");
  }

  // 1. Check if complaint exists in complaint_records
  $stmt = $conn->prepare("SELECT * FROM complaint_records WHERE transaction_id = ?");
  $stmt->bind_param('s', $transaction_id);
  $stmt->execute();
  $complaint = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$complaint) {
    die("Complaint record not found.");
  }

  // 2. Check if already scheduled in katarungang_pambarangay_records
  $stmt = $conn->prepare("SELECT id FROM katarungang_pambarangay_records WHERE transaction_id = ?");
  $stmt->bind_param('s', $transaction_id);
  $stmt->execute();
  $existing = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($existing) {
    header("Location: ../adminPanel.php?page=adminComplaints&error=summon_exists&transaction_id={$transaction_id}");
    exit();
  }

  // 3. Insert new summon schedule
  $created_at = date('Y-m-d H:i:s');
  $insert = $conn->prepare("INSERT INTO katarungang_pambarangay_records 
    (account_id, transaction_id, complainant_affidavit, respondent_affidavit, complaint_status, scheduled_at, created_at) 
    VALUES (?, ?, NULL, NULL, 'Scheduled', ?, ?)");
  $insert->bind_param("isss", $account_id, $transaction_id, $scheduled_at, $created_at);

  if (!$insert->execute()) {
    die('Insert failed: ' . $insert->error);
  }

  // 4. Generate PDF using Dompdf
  $dompdf = new Dompdf();

  ob_start();
  ?>
  <html>
    <head><title>Summon Letter</title></head>
    <body>
      <h2>Barangay Summon Notice</h2>
      <p><strong>Case No.:</strong> <?= htmlspecialchars($transaction_id) ?></p>
      <p><strong>Scheduled Date:</strong> <?= date('F j, Y g:i A', strtotime($scheduled_at)) ?></p>
      <hr>
      <h4>Complainant</h4>
      <p><?= htmlspecialchars($complaint['complainant_name']) ?><br><?= htmlspecialchars($complaint['complainant_address']) ?></p>
      <h4>Respondent</h4>
      <p><?= htmlspecialchars($complaint['respondent_name']) ?><br><?= htmlspecialchars($complaint['respondent_address']) ?></p>
      <h4>Complaint Type</h4>
      <p><?= htmlspecialchars($complaint['complaint_type']) ?></p>
      <h4>Pleading Statement</h4>
      <p><?= nl2br(htmlspecialchars($complaint['pleading_statement'])) ?></p>
    </body>
  </html>
  <?php
  $html = ob_get_clean();

  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();

  $dompdf->stream("summon_{$transaction_id}.pdf", ["Attachment" => false]);
  exit;
}

$conn->close();
?>
