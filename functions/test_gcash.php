<?php
require 'dbconn.php';
require 'gcash_handler.php';

echo "<h2>GCash PayMongo Integration Test</h2>";

// Test configuration
echo "<h3>1. Configuration Test</h3>";
echo "Domain: " . getCurrentDomain() . "<br>";
echo "PayMongo Secret Key: " . (PAYMONGO_SECRET_KEY ? "✓ Configured" : "✗ Missing") . "<br>";

// Test database
echo "<h3>2. Database Test</h3>";
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM barangay_id_requests");
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    echo "Database connection: ✓ Connected<br>";
    echo "Total requests: {$count}<br>";
} catch (Exception $e) {
    echo "Database error: ✗ " . $e->getMessage() . "<br>";
}

// Test PayMongo API
echo "<h3>3. PayMongo API Test</h3>";
$testTransactionId = 'TEST-' . time();

echo "Testing with transaction ID: <strong>{$testTransactionId}</strong><br>";

// Insert test record
try {
    $stmt = $conn->prepare("
        INSERT INTO barangay_id_requests 
        (account_id, transaction_id, transaction_type, full_name, payment_method, payment_status) 
        VALUES (1, ?, 'Test', 'Test User', 'GCash', 'pending')
    ");
    $stmt->bind_param("s", $testTransactionId);
    $stmt->execute();
    echo "Test record created: ✓<br>";
} catch (Exception $e) {
    echo "Test record error: ✗ " . $e->getMessage() . "<br>";
}

// Test GCash source creation
$result = createGCashSource($testTransactionId, 100);

echo "<h4>API Response:</h4>";
echo "<pre>";
print_r($result);
echo "</pre>";

if ($result['success']) {
    echo "<p><strong>✓ Success!</strong></p>";
    echo "<p><a href='{$result['checkout_url']}' target='_blank' class='btn'>Test Checkout URL</a></p>";
} else {
    echo "<p><strong>✗ Failed:</strong> " . $result['error'] . "</p>";
}

// Cleanup test record
$stmt = $conn->prepare("DELETE FROM barangay_id_requests WHERE transaction_id = ?");
$stmt->bind_param("s", $testTransactionId);
$stmt->execute();
echo "<p><em>Test record cleaned up.</em></p>";

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3, h4 { color: #2c3e50; }
pre { background: #f8f9fa; padding: 10px; border-radius: 4px; }
.btn { background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
</style>";
?>