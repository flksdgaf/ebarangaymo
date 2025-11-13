<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../dbconn.php';

/**
 * Universal GCash Payment Handler
 * Handles all request types automatically based on transaction ID prefix
 */
class UniversalGCashHandler {
    private $conn;
    private $secretKey;
    private $baseDomain;
    
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
        $this->secretKey = PAYMONGO_SECRET_KEY;
        $this->baseDomain = getCurrentDomain();
    }
    
    /**
     * Create GCash payment source
     * Automatically detects request type from transaction ID
     */
    public function createPaymentSource($transactionId, $customAmount = null) {
        try {
            // Get request type info
            $requestInfo = getRequestTypeInfo($transactionId);
            if (!$requestInfo) {
                return [
                    'success' => false,
                    'error' => 'Invalid transaction ID format'
                ];
            }
            
            // Determine amount
            $amount = $customAmount !== null ? floatval($customAmount) : $requestInfo['amount'];
            
            // Validate minimum amount for GCash
            if ($amount < 20) {
                return [
                    'success' => false,
                    'error' => 'Minimum payment amount is ₱20.00'
                ];
            }
            
            $amountInCentavos = intval($amount * 100);
            
            // Build universal redirect URLs
            $successUrl = $this->baseDomain . '/functions/gcash/success.php?tid=' . urlencode($transactionId);
            $failedUrl = $this->baseDomain . '/functions/gcash/failed.php?tid=' . urlencode($transactionId);
            
            $this->log('Creating payment source', [
                'transaction_id' => $transactionId,
                'type' => $requestInfo['name'],
                'amount' => $amount
            ]);
            
            // PayMongo API request
            $data = [
                'data' => [
                    'attributes' => [
                        'amount' => $amountInCentavos,
                        'redirect' => [
                            'success' => $successUrl,
                            'failed' => $failedUrl
                        ],
                        'type' => 'gcash',
                        'currency' => 'PHP'
                    ]
                ]
            ];
            
            $response = $this->makeApiRequest('POST', '/sources', $data);
            
            if ($response['success'] && isset($response['data']['data']['id'])) {
                $sourceId = $response['data']['data']['id'];
                $checkoutUrl = $response['data']['data']['attributes']['redirect']['checkout_url'] ?? null;
                
                if ($checkoutUrl) {
                    // Store source ID in appropriate table
                    $this->updateSourceId($transactionId, $sourceId, $requestInfo['table']);
                    
                    $this->log('Payment source created', [
                        'source_id' => $sourceId,
                        'transaction_id' => $transactionId
                    ]);
                    
                    return [
                        'success' => true,
                        'checkout_url' => $checkoutUrl,
                        'source_id' => $sourceId
                    ];
                }
            }
            
            $error = $response['error'] ?? 'Failed to create payment source';
            $this->log('Source creation failed', ['error' => $error]);
            
            return [
                'success' => false,
                'error' => $error
            ];
            
        } catch (Exception $e) {
            $this->log('Exception in createPaymentSource', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            
            return [
                'success' => false,
                'error' => 'System error. Please try again.'
            ];
        }
    }
    
    /**
     * Handle successful payment callback
     */
    public function handleSuccess($transactionId) {
        try {
            // Get request type info
            $requestInfo = getRequestTypeInfo($transactionId);
            if (!$requestInfo) {
                return ['success' => false, 'error' => 'Invalid transaction type'];
            }
            
            // Get transaction data from appropriate table
            $stmt = $this->conn->prepare("
                SELECT * FROM `{$requestInfo['table']}` 
                WHERE transaction_id = ? 
                LIMIT 1
            ");
            $stmt->bind_param("s", $transactionId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result || $result->num_rows === 0) {
                return ['success' => false, 'error' => 'Transaction not found'];
            }
            
            $transaction = $result->fetch_assoc();
            $stmt->close();
            
            $sourceId = $transaction['paymongo_source_id'] ?? null;
            if (!$sourceId) {
                return ['success' => false, 'error' => 'Payment source not found'];
            }
            
            $this->log('Processing success callback', [
                'transaction_id' => $transactionId,
                'type' => $requestInfo['name'],
                'source_id' => $sourceId
            ]);
            
            // Verify source with PayMongo
            $sourceResponse = $this->makeApiRequest('GET', "/sources/{$sourceId}");
            
            if (!$sourceResponse['success']) {
                return ['success' => false, 'error' => 'Failed to verify payment'];
            }
            
            $sourceStatus = $sourceResponse['data']['data']['attributes']['status'] ?? null;
            
            $this->log('Source status', ['status' => $sourceStatus]);
            
            if ($sourceStatus === 'chargeable') {
                return $this->createPayment($transactionId, $sourceId, $sourceResponse['data']['data']['attributes'], $requestInfo);
            }
            
            return ['success' => false, 'error' => 'Payment not ready yet'];
            
        } catch (Exception $e) {
            $this->log('Exception in handleSuccess', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            
            return ['success' => false, 'error' => 'System error'];
        }
    }
    
    /**
     * Create payment from chargeable source
     */
    private function createPayment($transactionId, $sourceId, $sourceAttributes, $requestInfo) {
        try {
            $amount = $sourceAttributes['amount'] ?? 0;
            
            $paymentData = [
                'data' => [
                    'attributes' => [
                        'amount' => $amount,
                        'source' => [
                            'id' => $sourceId,
                            'type' => 'source'
                        ],
                        'currency' => 'PHP',
                        'description' => "{$requestInfo['name']} - {$transactionId}"
                    ]
                ]
            ];
            
            $paymentResponse = $this->makeApiRequest('POST', '/payments', $paymentData);
            
            if ($paymentResponse['success']) {
                $payment = $paymentResponse['data']['data'];
                $paymentId = $payment['id'];
                $status = $payment['attributes']['status'] ?? 'pending';
                $reference = 'GCASH-' . strtoupper(substr($paymentId, -8));
                
                $finalStatus = ($status === 'paid') ? 'paid' : 'processing';
                
                // Update database
                $stmt = $this->conn->prepare("
                    UPDATE `{$requestInfo['table']}` 
                    SET payment_status = ?,
                        paymongo_payment_id = ?,
                        gcash_reference = ?,
                        updated_at = NOW()
                    WHERE transaction_id = ?
                ");
                $stmt->bind_param("ssss", $finalStatus, $paymentId, $reference, $transactionId);
                $stmt->execute();
                $stmt->close();
                
                $this->log('Payment created', [
                    'payment_id' => $paymentId,
                    'reference' => $reference,
                    'status' => $finalStatus,
                    'type' => $requestInfo['name']
                ]);
                
                return [
                    'success' => true,
                    'payment_id' => $paymentId,
                    'reference' => $reference,
                    'status' => $finalStatus
                ];
            }
            
            $error = $paymentResponse['error'] ?? 'Payment creation failed';
            $this->log('Payment creation failed', ['error' => $error]);
            
            return ['success' => false, 'error' => $error];
            
        } catch (Exception $e) {
            $this->log('Exception in createPayment', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Payment processing error'];
        }
    }
    
    /**
     * Make API request to PayMongo
     */
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
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $decoded
            ];
        }
        
        $errorMsg = 'API Error';
        if (isset($decoded['errors'][0]['detail'])) {
            $errorMsg = $decoded['errors'][0]['detail'];
        }
        
        return [
            'success' => false,
            'error' => $errorMsg,
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Update source ID in appropriate table
     */
    private function updateSourceId($transactionId, $sourceId, $table) {
        $stmt = $this->conn->prepare("
            UPDATE `$table` 
            SET paymongo_source_id = ? 
            WHERE transaction_id = ?
        ");
        $stmt->bind_param("ss", $sourceId, $transactionId);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Log transaction
     */
    private function log($message, $data = []) {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $entry = date('Y-m-d H:i:s') . " - {$message} - " . json_encode($data) . "\n";
        file_put_contents($logDir . '/gcash_universal.log', $entry, FILE_APPEND | LOCK_EX);
    }
}