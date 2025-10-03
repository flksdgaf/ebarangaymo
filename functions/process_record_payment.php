<?php
session_start();
require_once __DIR__ . '/dbconn.php';

// Validate inputs
$tid = $_POST['transaction_id'] ?? '';
$or = trim($_POST['or_number'] ?? '');
$amt = $_POST['amount_paid'] ?? 0;
$issued = $_POST['issued_date'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';
$refNumber = trim($_POST['reference_number'] ?? ''); // for GCash

if (!$tid || !$or || !$amt || !$issued || !$paymentMethod) {
    $_SESSION['payment_error'] = 'All fields are required';
    header("Location: ../adminPanel.php?page=adminRequest&error=missing_fields");
    exit;
}

// Validate GCash requires reference number
if ($paymentMethod === 'GCash' && !$refNumber) {
    $_SESSION['payment_error'] = 'Reference number is required for GCash payments';
    header("Location: ../adminPanel.php?page=adminRequest&error=missing_reference");
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // 1) Look up request_type, full_name, and payment_status in view_request
    $stmt = $conn->prepare("SELECT request_type, full_name, payment_status FROM view_request WHERE transaction_id = ?");
    $stmt->bind_param('s', $tid);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows !== 1) {
        throw new Exception('Invalid transaction ID');
    }
    
    $row = $res->fetch_assoc();
    $requestType = $row['request_type'];
    $fullName = $row['full_name'];
    $alreadyPaid = ($row['payment_status'] === 'Paid');
    $stmt->close();

    // 2) Insert/Update official_receipt_records
    if ($paymentMethod === 'GCash') {
        $ins = $conn->prepare("
            INSERT INTO official_receipt_records 
            (transaction_id, payment_method, or_number, amount_paid, issued_date, reference_number) 
            VALUES (?,?,?,?,?,?) 
            ON DUPLICATE KEY UPDATE 
                payment_method = VALUES(payment_method), 
                or_number = VALUES(or_number), 
                amount_paid = VALUES(amount_paid), 
                issued_date = VALUES(issued_date),
                reference_number = VALUES(reference_number),
                updated_at = NOW()
        ");
        $ins->bind_param('sssdss', $tid, $paymentMethod, $or, $amt, $issued, $refNumber);
    } else {
        $ins = $conn->prepare("
            INSERT INTO official_receipt_records 
            (transaction_id, payment_method, or_number, amount_paid, issued_date) 
            VALUES (?,?,?,?,?) 
            ON DUPLICATE KEY UPDATE 
                payment_method = VALUES(payment_method), 
                or_number = VALUES(or_number), 
                amount_paid = VALUES(amount_paid), 
                issued_date = VALUES(issued_date),
                updated_at = NOW()
        ");
        $ins->bind_param('sssds', $tid, $paymentMethod, $or, $amt, $issued);
    }
    
    $ins->execute();
    $ins->close();

    // 3) Mark the original request as paid (if not already)
    if (!$alreadyPaid) {
        $typeMap = [
            'Barangay ID' => 'barangay_id_requests',
            'Business Permit' => 'business_permit_requests',
            'Good Moral' => 'good_moral_requests',
            'Guardianship' => 'guardianship_requests',
            'Indigency' => 'indigency_requests',
            'Residency' => 'residency_requests',
            'Solo Parent' => 'solo_parent_requests',
            'Barangay Clearance' => 'barangay_clearance_requests',
            'Business Clearance' => 'business_clearance_requests'
        ];
        
        $table = $typeMap[$requestType] ?? null;
        if (!$table) {
            throw new Exception('Unknown request type');
        }
        
        $upd = $conn->prepare("UPDATE `$table` SET payment_status = 'Paid' WHERE transaction_id = ?");
        $upd->bind_param('s', $tid);
        $upd->execute();
        $upd->close();
    }

    // 4) Log activity
    if (isset($_SESSION['loggedInUserID'])) {
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs 
            (admin_id, role, action, table_name, record_id, description) 
            VALUES (?,?,?,?,?,?)
        ");
        $adminId = $_SESSION['loggedInUserID'];
        $role = $_SESSION['loggedInUserRole'] ?? 'Unknown';
        $action = 'RECORD_PAYMENT';
        $tableName = 'official_receipt_records';
        $recordId = $tid;
        $description = "Recorded payment for {$requestType}: OR# {$or}, Amount: {$amt}";
        
        $logStmt->bind_param('isssss', $adminId, $role, $action, $tableName, $recordId, $description);
        $logStmt->execute();
        $logStmt->close();
    }

    // Commit transaction
    $conn->commit();
    
    header("Location: ../adminPanel.php?page=adminRequest&payment_transaction_id={$tid}");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Payment recording error: " . $e->getMessage());
    $_SESSION['payment_error'] = $e->getMessage();
    header("Location: ../adminPanel.php?page=adminRequest&error=payment_failed");
    exit;
}
?>
