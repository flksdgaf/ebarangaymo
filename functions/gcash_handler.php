<?php
require_once 'dbconn.php';
require_once 'gcash_config.php';

class GCashPaymentHandler {
    private $conn;
    private $secretKey;
    private $baseDomain;
    
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
        $this->secretKey = PAYMONGO_SECRET_KEY;
        $this->baseDomain = getCurrentDomain();
        
        // Validate secret key
        if (empty($this->secretKey)) {
            throw new Exception('PayMongo secret key is not configured');
        }
    }
    
    /**
     * Create GCash payment source
     */
    public function createSource($transactionId, $amount = 100) {
        try {
            // Validation
            if ($amount < 20) {
                return [
                    'success' => false,
                    'error' => 'Minimum payment amount is ₱20.00 due to GCash restrictions.'
                ];
            }
            
            $amountInCentavos = $amount * 100;
            
            // Build redirect URLs based on transaction type
            $isCertification = !str_starts_with($transactionId, 'BRGYID-');

            if ($isCertification) {
                $successUrl = $this->baseDomain . '/functions/gcash_cert_success.php?transaction_id=' . $transactionId;
                $failedUrl = $this->baseDomain . '/functions/gcash_cert_failed.php?transaction_id=' . $transactionId;
            } else {
                $successUrl = $this->baseDomain . '/functions/gcash_success.php?transaction_id=' . $transactionId;
                $failedUrl = $this->baseDomain . '/functions/gcash_failed.php?transaction_id=' . $transactionId;
            }
            
            // Log URLs for debugging
            $this->logTransaction('Creating GCash source', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'success_url' => $successUrl,
                'failed_url' => $failedUrl
            ]);
            
            // PayMongo API request data
            $data = [
                'data' => [
                    'attributes' => [
                        'amount' => $amountInCentavos,
                        'redirect' => [
                            'success' => $successUrl,
                            'failed' => $failedUrl,
                        ],
                        'type' => 'gcash',
                        'currency' => 'PHP',
                    ]
                ]
            ];
            
            // Make API request
            $response = $this->makeApiRequest('POST', '/sources', $data);
            
            if ($response['success']) {
                $responseData = $response['data'];
                
                if (isset($responseData['data']['id'])) {
                    $sourceId = $responseData['data']['id'];
                    
                    // Store source ID in database
                    $this->updateRequestWithSourceId($transactionId, $sourceId);
                    
                    // Log successful source creation
                    $this->logTransaction('GCash source created successfully', [
                        'source_id' => $sourceId,
                        'transaction_id' => $transactionId,
                    ]);
                    
                    // Return checkout URL
                    if (isset($responseData['data']['attributes']['redirect']['checkout_url'])) {
                        return [
                            'success' => true,
                            'checkout_url' => $responseData['data']['attributes']['redirect']['checkout_url'],
                            'source_id' => $sourceId
                        ];
                    }
                }
            }
            
            $this->logTransaction('Failed to create GCash source', [
                'error' => $response['error'] ?? 'Unknown error',
                'transaction_id' => $transactionId
            ]);
            
            return [
                'success' => false,
                'error' => 'Unable to create GCash source. Please try again.'
            ];
            
        } catch (Exception $e) {
            $this->logTransaction('Exception in createSource', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            
            return [
                'success' => false,
                'error' => 'System error. Please contact support.'
            ];
        }
    }
    
    /**
     * Handle successful payment
     */
    public function handleSuccess($transactionId) {
        try {
            if (!$transactionId) {
                return [
                    'success' => false,
                    'error' => 'Missing transaction ID.'
                ];
            }
            
            // Get transaction data
            $transactionData = $this->getTransactionData($transactionId);
            if (!$transactionData) {
                return [
                    'success' => false,
                    'error' => 'Transaction not found.'
                ];
            }
            
            $sourceId = $transactionData['paymongo_source_id'];
            if (!$sourceId) {
                return [
                    'success' => false,
                    'error' => 'Payment source not found.'
                ];
            }
            
            $this->logTransaction('Processing GCash success callback', [
                'transaction_id' => $transactionId,
                'source_id' => $sourceId
            ]);
            
            // Verify source status with PayMongo
            $sourceResponse = $this->makeApiRequest('GET', "/sources/{$sourceId}");
            
            if (!$sourceResponse['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to verify payment status.'
                ];
            }
            
            $sourceData = $sourceResponse['data']['data']['attributes'];
            
            $this->logTransaction('Source verification result', [
                'status' => $sourceData['status'],
                'source_id' => $sourceId
            ]);
            
            // Check if source is chargeable
            if ($sourceData['status'] === 'chargeable') {
                return $this->createPayment($transactionId, $sourceId, $sourceData, $transactionData);
            }
            
            return [
                'success' => false,
                'error' => 'Payment not yet completed. Please wait and try again.'
            ];
            
        } catch (Exception $e) {
            $this->logTransaction('Exception in handleSuccess', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            
            return [
                'success' => false,
                'error' => 'System error processing payment.'
            ];
        }
    }
    
    /**
     * Create payment from chargeable source
     */
    private function createPayment($transactionId, $sourceId, $sourceData, $transactionData) {
        $paymentData = [
            'data' => [
                'attributes' => [
                    'amount' => $sourceData['amount'],
                    'source' => [
                        'id' => $sourceId,
                        'type' => 'source'
                    ],
                    'currency' => 'PHP',
                    'description' => "Barangay ID - {$transactionId}"
                ]
            ]
        ];
        
        $paymentResponse = $this->makeApiRequest('POST', '/payments', $paymentData);
        
        if ($paymentResponse['success']) {
            $paymentResult = $paymentResponse['data']['data'];
            $paymentId = $paymentResult['id'];
            $status = $paymentResult['attributes']['status'] ?? 'pending';
            $gcashRef = $paymentResult['attributes']['source']['id'] ?? 'GCASH-' . strtoupper(uniqid());
            
            // Only mark as paid if PayMongo confirms it
            $finalStatus = ($status === 'paid') ? 'paid' : 'processing';
            
            // Update database with payment info
            $stmt = $this->conn->prepare("
                UPDATE barangay_id_requests 
                SET payment_status = ?,
                    paymongo_payment_id = ?,
                    gcash_reference = ?,
                    updated_at = NOW()
                WHERE transaction_id = ?
            ");
            $stmt->bind_param("ssss", $finalStatus, $paymentId, $gcashRef, $transactionId);
            $stmt->execute();
            $stmt->close();
            
            // Create audit log
            $this->createAuditLog(
                'Payment Processed',
                "GCash payment {$finalStatus} for transaction {$transactionId} (₱" . number_format($sourceData['amount'] / 100, 2) . ")",
                $transactionData['full_name'],
                'Payment'
            );
            
            $this->logTransaction('Payment created successfully', [
                'payment_id' => $paymentId,
                'reference' => $gcashRef,
                'status' => $finalStatus,
                'transaction_id' => $transactionId
            ]);
            
            return [
                'success' => true,
                'message' => 'Payment processed successfully.',
                'payment_id' => $paymentId,
                'reference' => $gcashRef
            ];
        }
        
        // Log the actual error from PayMongo
        $this->logTransaction('Payment creation failed', [
            'error' => $paymentResponse['error'] ?? 'Unknown error',
            'http_code' => $paymentResponse['http_code'] ?? 0,
            'transaction_id' => $transactionId
        ]);
        
        return [
            'success' => false,
            'error' => 'Failed to process payment: ' . ($paymentResponse['error'] ?? 'Unknown error')
        ];
    }
    
    /**
     * Process webhook notification
     */
    public function handleWebhook($payload) {
        try {
            $this->logTransaction('Webhook received', $payload);
            
            if (!isset($payload['data'])) {
                return ['success' => false, 'error' => 'Invalid webhook payload'];
            }
            
            $data = $payload['data'];
            $eventType = $data['attributes']['type'] ?? '';
            
            switch ($eventType) {
                case 'source.chargeable':
                    return $this->handleChargeableWebhook($data);
                case 'payment.paid':
                    return $this->handlePaidWebhook($data);
                case 'payment.failed':
                    return $this->handleFailedWebhook($data);
                default:
                    $this->logTransaction('Unhandled webhook event', ['type' => $eventType]);
                    return ['success' => true, 'message' => 'Event ignored'];
            }
            
        } catch (Exception $e) {
            $this->logTransaction('Webhook exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Webhook processing failed'];
        }
    }
    
    private function handleChargeableWebhook($data) {
        // Handle when source becomes chargeable
        $sourceId = $data['id'];
        
        // Find transaction by source ID
        $stmt = $this->conn->prepare("SELECT transaction_id FROM barangay_id_requests WHERE paymongo_source_id = ?");
        $stmt->bind_param("s", $sourceId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $transactionId = $result->fetch_assoc()['transaction_id'];
            $this->logTransaction('Source became chargeable via webhook', [
                'source_id' => $sourceId,
                'transaction_id' => $transactionId
            ]);
        }
        
        return ['success' => true];
    }
    
    private function handlePaidWebhook($data) {
        // Handle payment success webhook
        $paymentId = $data['id'];
        $sourceId = $data['attributes']['source']['id'] ?? null;
        
        if ($sourceId) {
            $stmt = $this->conn->prepare("
                UPDATE barangay_id_requests 
                SET payment_status = 'paid' 
                WHERE paymongo_source_id = ? AND payment_status != 'paid'
            ");
            $stmt->bind_param("s", $sourceId);
            $stmt->execute();
            
            $this->logTransaction('Payment confirmed via webhook', [
                'payment_id' => $paymentId,
                'source_id' => $sourceId
            ]);
        }
        
        return ['success' => true];
    }
    
    private function handleFailedWebhook($data) {
        // Handle payment failure webhook
        $paymentId = $data['id'];
        $sourceId = $data['attributes']['source']['id'] ?? null;
        
        if ($sourceId) {
            $stmt = $this->conn->prepare("
                UPDATE barangay_id_requests 
                SET payment_status = 'failed' 
                WHERE paymongo_source_id = ?
            ");
            $stmt->bind_param("s", $sourceId);
            $stmt->execute();
            
            $this->logTransaction('Payment failed via webhook', [
                'payment_id' => $paymentId,
                'source_id' => $sourceId
            ]);
        }
        
        return ['success' => true];
    }
    
    // Helper methods
    private function makeApiRequest($method, $endpoint, $data = null) {
        $url = PAYMONGO_API_URL . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->secretKey . ':')
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return [
                'success' => false,
                'error' => 'Connection error: ' . $curlError,
                'http_code' => $httpCode
            ];
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $decodedResponse
            ];
        }
        
        return [
            'success' => false,
            'error' => $decodedResponse['errors'][0]['detail'] ?? $response,
            'http_code' => $httpCode
        ];
    }
    
    private function updateRequestWithSourceId($transactionId, $sourceId) {
        // Determine table based on transaction prefix
        $table = 'barangay_id_requests'; // default
        
        $certMap = [
            'RES-'  => 'residency_requests',
            'IND-'  => 'indigency_requests',
            'GM-'   => 'good_moral_requests',
            'SP-'   => 'solo_parent_requests',
            'GUA-'  => 'guardianship_requests',
            'FTJS-' => 'job_seeker_requests',
        ];
        
        foreach ($certMap as $prefix => $tbl) {
            if (strpos($transactionId, $prefix) === 0) {
                $table = $tbl;
                break;
            }
        }
        
        $stmt = $this->conn->prepare("
            UPDATE `$table` 
            SET paymongo_source_id = ? 
            WHERE transaction_id = ?
        ");
        $stmt->bind_param("ss", $sourceId, $transactionId);
        $stmt->execute();
        $stmt->close();
    }
    
    private function getTransactionData($transactionId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM barangay_id_requests 
            WHERE transaction_id = ? 
            LIMIT 1
        ");
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    
    private function updatePaymentSuccess($transactionId, $paymentId, $gcashRef) {
        $stmt = $this->conn->prepare("
            UPDATE barangay_id_requests 
            SET payment_status = 'paid',
                paymongo_payment_id = ?,
                gcash_reference = ?
            WHERE transaction_id = ?
        ");
        $stmt->bind_param("sss", $paymentId, $gcashRef, $transactionId);
        $stmt->execute();
        $stmt->close();
    }
    
    private function createAuditLog($action, $description, $user, $type) {
        // Check if logs table exists first
        $stmt = $this->conn->prepare("SHOW TABLES LIKE 'logs'");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt = $this->conn->prepare("
                INSERT INTO logs (action, description, user, date_time, type) 
                VALUES (?, ?, ?, NOW(), ?)
            ");
            $stmt->bind_param("ssss", $action, $description, $user, $type);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    private function logTransaction($message, $data) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = date('Y-m-d H:i:s') . " - " . $message . " - " . json_encode($data) . "\n";
        file_put_contents($logDir . '/gcash_payments.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Convenience functions
function createGCashSource($transactionId, $amount = 20) {
    global $conn;
    $handler = new GCashPaymentHandler($conn);
    return $handler->createSource($transactionId, $amount);
}

function handleGCashSuccess($transactionId) {
    global $conn;
    $handler = new GCashPaymentHandler($conn);
    return $handler->handleSuccess($transactionId);
}

function handleGCashWebhook($payload) {
    global $conn;
    $handler = new GCashPaymentHandler($conn);
    return $handler->handleWebhook($payload);
}

/**
 * Handle GCash success for certification requests
 */
function handleGCashSuccessForTable($transactionId, $table) {
    global $conn;
    
    try {
        // Get transaction data
        $stmt = $conn->prepare("SELECT * FROM `$table` WHERE transaction_id = ? LIMIT 1");
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            return ['success' => false, 'error' => 'Transaction not found.'];
        }
        
        $transactionData = $result->fetch_assoc();
        $stmt->close();
        
        $sourceId = $transactionData['paymongo_source_id'] ?? null;
        if (!$sourceId) {
            return ['success' => false, 'error' => 'Payment source not found.'];
        }
        
        // Log the attempt
        logGCashTransaction('Processing certification payment', [
            'transaction_id' => $transactionId,
            'table' => $table,
            'source_id' => $sourceId
        ]);
        
        // Create handler instance
        $handler = new GCashPaymentHandler($conn);
        
        // Verify source using reflection to access private method
        $reflection = new ReflectionClass($handler);
        $method = $reflection->getMethod('makeApiRequest');
        $method->setAccessible(true);
        
        $sourceResponse = $method->invoke($handler, 'GET', "/sources/{$sourceId}");
        
        if (!$sourceResponse['success']) {
            return ['success' => false, 'error' => 'Failed to verify payment status.'];
        }
        
        $sourceData = $sourceResponse['data']['data']['attributes'];
        
        logGCashTransaction('Source verification result', [
            'status' => $sourceData['status'],
            'transaction_id' => $transactionId
        ]);
        
        if ($sourceData['status'] === 'chargeable') {
            return createPaymentForTable($transactionId, $sourceId, $sourceData, $transactionData, $table);
        }
        
        return ['success' => false, 'error' => 'Payment not yet completed.'];
        
    } catch (Exception $e) {
        logGCashTransaction('Exception in handleGCashSuccessForTable', [
            'error' => $e->getMessage(),
            'transaction_id' => $transactionId,
            'table' => $table
        ]);
        return ['success' => false, 'error' => 'System error processing payment.'];
    }
}

/**
 * Create payment for certification request
 */
function createPaymentForTable($transactionId, $sourceId, $sourceData, $transactionData, $table) {
    global $conn;
    
    $handler = new GCashPaymentHandler($conn);
    
    $paymentData = [
        'data' => [
            'attributes' => [
                'amount' => $sourceData['amount'],
                'source' => ['id' => $sourceId, 'type' => 'source'],
                'currency' => 'PHP',
                'description' => "Certificate - {$transactionId}"
            ]
        ]
    ];
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($handler);
    $method = $reflection->getMethod('makeApiRequest');
    $method->setAccessible(true);
    
    $paymentResponse = $method->invoke($handler, 'POST', '/payments', $paymentData);
    
    if ($paymentResponse['success']) {
        $paymentResult = $paymentResponse['data']['data'];
        $paymentId = $paymentResult['id'];
        $status = $paymentResult['attributes']['status'] ?? 'pending';
        $gcashRef = $paymentResult['attributes']['source']['id'] ?? 'GCASH-' . strtoupper(uniqid());
        
        $finalStatus = ($status === 'paid') ? 'paid' : 'processing';
        
        // Update database
        $stmt = $conn->prepare("
            UPDATE `$table` 
            SET payment_status = ?,
                paymongo_payment_id = ?,
                gcash_reference = ?
            WHERE transaction_id = ?
        ");
        $stmt->bind_param("ssss", $finalStatus, $paymentId, $gcashRef, $transactionId);
        $stmt->execute();
        $stmt->close();
        
        logGCashTransaction('Payment created for certification', [
            'payment_id' => $paymentId,
            'reference' => $gcashRef,
            'status' => $finalStatus,
            'transaction_id' => $transactionId,
            'table' => $table
        ]);
        
        return [
            'success' => true,
            'message' => 'Payment processed successfully.',
            'payment_id' => $paymentId,
            'reference' => $gcashRef
        ];
    }
    
    logGCashTransaction('Payment creation failed for certification', [
        'error' => $paymentResponse['error'] ?? 'Unknown error',
        'transaction_id' => $transactionId,
        'table' => $table
    ]);
    
    return ['success' => false, 'error' => 'Failed to process payment.'];
}

/**
 * Helper function to log GCash transactions
 */
function logGCashTransaction($message, $data) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " - " . $message . " - " . json_encode($data) . "\n";
    file_put_contents($logDir . '/gcash_payments.log', $logEntry, FILE_APPEND | LOCK_EX);
}