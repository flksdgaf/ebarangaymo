<?php
require 'dbconn.php';
require 'gcash_handler.php';

// Set content type
header('Content-Type: application/json');

// Log all webhook calls
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

try {
    // Get webhook payload
    $input = file_get_contents('php://input');
    $payload = json_decode($input, true);
    
    // Log raw webhook
    $webhookLog = date('Y-m-d H:i:s') . " - Webhook received:\n" . $input . "\n\n";
    file_put_contents($logDir . '/webhook_raw.log', $webhookLog, FILE_APPEND | LOCK_EX);
    
    if (!$payload) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        exit();
    }
    
    // Process webhook
    $result = handleGCashWebhook($payload);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['message' => 'Webhook processed successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['error']]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    
    $errorLog = date('Y-m-d H:i:s') . " - Webhook Error: " . $e->getMessage() . "\n";
    file_put_contents($logDir . '/webhook_errors.log', $errorLog, FILE_APPEND | LOCK_EX);
}

exit();
?>
