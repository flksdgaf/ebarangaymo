<?php
// Test if GCash system is set up correctly
require_once 'config.php';
require_once 'handler.php';
require_once '../dbconn.php';

echo "<h2>GCash Configuration Test</h2>";

// 1. Check secret key
echo "<h3>1. Secret Key:</h3>";
if (defined('PAYMONGO_SECRET_KEY') && !empty(PAYMONGO_SECRET_KEY) && PAYMONGO_SECRET_KEY !== 'sk_test_YOUR_ACTUAL_PAYMONGO_SECRET_KEY') {
    echo "✅ Secret key is configured: " . substr(PAYMONGO_SECRET_KEY, 0, 10) . "...<br>";
} else {
    echo "❌ Secret key NOT configured or still has placeholder value<br>";
}

// 2. Check request type map
echo "<h3>2. Request Type Map:</h3>";
if (defined('REQUEST_TYPE_MAP')) {
    echo "✅ REQUEST_TYPE_MAP defined<br>";
    echo "<pre>";
    print_r(REQUEST_TYPE_MAP);
    echo "</pre>";
} else {
    echo "❌ REQUEST_TYPE_MAP not defined<br>";
}

// 3. Test getRequestTypeInfo function
echo "<h3>3. Test Transaction ID Recognition:</h3>";
$testIds = ['BUS-0000001', 'CLR-0000001', 'RES-0000001'];
foreach ($testIds as $testId) {
    $info = getRequestTypeInfo($testId);
    if ($info) {
        echo "✅ {$testId} → {$info['name']} (₱{$info['amount']})<br>";
    } else {
        echo "❌ {$testId} → Not recognized<br>";
    }
}

// 4. Test handler instantiation
echo "<h3>4. Handler Class:</h3>";
try {
    $handler = new UniversalGCashHandler($conn);
    echo "✅ UniversalGCashHandler class loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ Error loading handler: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p>If all checks pass, GCash integration is ready to use!</p>";