<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/dbconn.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$from = $_POST['date_from'] ?? '';
$to = $_POST['date_to'] ?? '';
$type = $_POST['report_type'] ?? '';
$format = $_POST['format'] ?? 'preview';

if (!$from || !$to || $type === '') {
    die('Missing required filters.');
}

// Normalize type for comparison
$typeLower = strtolower($type);

// === Prepare SQL for report data ===
$sqlBase = "
    SELECT
        o.or_number,
        COALESCE(r.full_name, o.transaction_id) AS full_name,
        r.request_type,
        o.amount_paid,
        o.issued_date
    FROM official_receipt_records o
    JOIN view_request r ON o.transaction_id = r.transaction_id
    WHERE o.issued_date BETWEEN ? AND ?
";

if ($typeLower !== 'all') {
    $sqlBase .= " AND r.request_type = ? ";
}

$sqlBase .= " ORDER BY o.issued_date ASC, o.or_number ASC";

$stmt = $conn->prepare($sqlBase);
if (!$stmt) {
    die("DB prepare failed: " . htmlspecialchars($conn->error));
}

if ($typeLower === 'all') {
    $stmt->bind_param("ss", $from, $to);
} else {
    $stmt->bind_param("sss", $from, $to, $type);
}

if (!$stmt->execute()) {
    die("DB execute failed: " . htmlspecialchars($stmt->error));
}

$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

$totalRecords = count($rows);
$totalAmount = array_sum(array_map(function($r){
    return isset($r['amount_paid']) ? (float)$r['amount_paid'] : 0.0;
}, $rows));

// === Fetch Barangay Treasurer full_name ===
// Strategy:
// 1) find account_id in user_accounts where role LIKE '%treasurer%'
// 2) search for that account_id in the six purok*_rbi tables for a full_name
// 3) fallback to pending_accounts or declined_accounts if still not found
$treasurerName = 'N/A';
$searchRole = '%treasurer%';

if ($stmt_tr = $conn->prepare("SELECT account_id FROM user_accounts WHERE LOWER(role) LIKE ? LIMIT 1")) {
    // pass lowercased pattern because of LOWER(role)
    $lowerSearch = strtolower($searchRole);
    $stmt_tr->bind_param('s', $lowerSearch);
    $stmt_tr->execute();
    $res_tr = $stmt_tr->get_result();
    if ($res_tr && $res_tr->num_rows > 0) {
        $r = $res_tr->fetch_assoc();
        $accountId = $r['account_id'] ?? null;

        if ($accountId) {
            // Search purok tables
            $purokTables = [
                'purok1_rbi',
                'purok2_rbi',
                'purok3_rbi',
                'purok4_rbi',
                'purok5_rbi',
                'purok6_rbi',
            ];

            $found = false;
            foreach ($purokTables as $tbl) {
                // Table name is trusted from schema
                $sql = "SELECT full_name FROM {$tbl} WHERE account_ID = ? LIMIT 1";
                if ($st = $conn->prepare($sql)) {
                    $st->bind_param('s', $accountId);
                    $st->execute();
                    $r2 = $st->get_result();
                    if ($r2 && $r2->num_rows > 0) {
                        $rowName = $r2->fetch_assoc();
                        if (!empty($rowName['full_name'])) {
                            $treasurerName = $rowName['full_name'];
                            $found = true;
                            $st->close();
                            break; // break out of purok loop only
                        }
                    }
                    $st->close();
                }
            }

            // If not found in purok tables, try pending_accounts and declined_accounts
            if (!$found) {
                $fallbacks = ['pending_accounts', 'declined_accounts'];
                foreach ($fallbacks as $tbl) {
                    $sql = "SELECT full_name FROM {$tbl} WHERE account_ID = ? LIMIT 1";
                    if ($st = $conn->prepare($sql)) {
                        $st->bind_param('s', $accountId);
                        $st->execute();
                        $r2 = $st->get_result();
                        if ($r2 && $r2->num_rows > 0) {
                            $rowName = $r2->fetch_assoc();
                            if (!empty($rowName['full_name'])) {
                                $treasurerName = $rowName['full_name'];
                                $found = true;
                                $st->close();
                                break;
                            }
                        }
                        $st->close();
                    }
                }
            }
        }
    }
    $stmt_tr->close();
}

// As a last-ditch attempt: if user_accounts actually has a full_name column on some installations,
// try to fetch it (prepare will fail if column missing — we ignore that failure).
if ($treasurerName === 'N/A') {
    $try = $conn->prepare("SELECT full_name FROM user_accounts WHERE LOWER(role) LIKE ? LIMIT 1");
    if ($try) {
        $lowerSearch = strtolower($searchRole);
        $try->bind_param('s', $lowerSearch);
        $try->execute();
        $res_try = $try->get_result();
        if ($res_try && $res_try->num_rows > 0) {
            $rr = $res_try->fetch_assoc();
            if (!empty($rr['full_name'])) {
                $treasurerName = $rr['full_name'];
            }
        }
        $try->close();
    }
}

// Safe-escape treasurer name for HTML output
$treasurerNameHtml = htmlspecialchars($treasurerName);

// Buffer HTML
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Collection Report</title>
  <style>
    @page { size: A4; margin: 48px; }
    body { font-family: Arial, sans-serif; font-size: 10pt; margin: 0; padding: 0; background: #fff; }
    .page { width: 100%; max-width: 700px; margin: 0 auto; padding: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .second-row { text-align: left; }
    .name-treasurer, .date, .barangay, .rcd, .collection { text-align: left; }
    th, td { border: 1px solid #000; padding: 6px 8px; text-align: center; }
    th { background: #eee; }
    .left { text-align: left; }
    .right { text-align: right; }
    .no-border td { border: none; padding: 4px 0; }
    .report-title { font-weight: bold; text-align: center; text-transform: uppercase; font-size: 14pt; padding-bottom: 10px; }
  </style>
</head>
<body>
  <div class="page">
    <table class="no-border"></table>

    <table>
      <thead>
        <tr><td class="report-title" colspan="5">REPORT OF COLLECTION AND DEPOSITS</td></tr>
        <tr class="second-row">
            <td colspan="3" class="name-treasurer">Name of Barangay Treasurer: <strong><?= $treasurerNameHtml ?></strong></td>
            <td colspan="2" class="date">Date: ________________</td>
        </tr>
        <tr>
            <td colspan="3" class="barangay">Barangay: <strong>MAGANG</strong></td>
            <td colspan="2" class="rcd">RCD No.: 25-07-007</td>
        </tr>
        <tr><td colspan="5" class="collection"><strong>A. COLLECTIONS</strong></td></tr>
            <tr>
            <th colspan="2">Official Receipt/RCR</th>
            <th rowspan="2">Payor/DBC</th>
            <th rowspan="2">Nature of Collection</th>
            <th rowspan="2">Amount</th>
        </tr>
        <tr>
          <th>Date</th>
          <th>Number</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($totalRecords > 0): ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= htmlspecialchars(date('m/d/Y', strtotime($row['issued_date']))) ?></td>
              <td><?= htmlspecialchars($row['or_number']) ?></td>
              <td class="left"><?= htmlspecialchars($row['full_name']) ?></td>
              <td><?= htmlspecialchars($row['request_type']) ?></td>
              <td class="right"><?= number_format((float)($row['amount_paid'] ?? 0), 2) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5">No official receipts found for this range.</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="left">Total Records: <?= $totalRecords ?></td>
          <td class="right">Total Amount:</td>
          <td><?= number_format((float)$totalAmount, 2) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

if ($format === 'pdf') {
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $dompdf->stream("Collection_Report_{$from}_to_{$to}.pdf", ['Attachment' => false]);
    exit;
} else {
    echo $html;
}
