<?php
/**
 * Universal GCash Payment Configuration
 * Works for all request types: Barangay ID, Clearances, Certifications
 */

// Load environment variables from .env file
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove quotes if present
        $value = trim($value, '"\'');
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }
    }
}

// PayMongo API Configuration
define('PAYMONGO_SECRET_KEY', $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY') ?: '');
define('PAYMONGO_API_URL', 'https://api.paymongo.com/v1');

// Domain Configuration
define('PRODUCTION_DOMAIN', 'https://ebarangaymo.qpcamnorte.com');
define('LOCAL_DOMAIN', 'http://localhost:3000');

// Validate secret key is configured
if (empty(PAYMONGO_SECRET_KEY)) {
    error_log("CRITICAL: PAYMONGO_SECRET_KEY not configured in .env file!");
}

/**
 * Request Type Configuration Map
 * Maps transaction prefixes to their database tables
 */
// define('REQUEST_TYPE_MAP', [
//     'BID-' => [
//         'table' => 'barangay_id_requests',
//         'name' => 'Barangay ID',
//         'amount' => 130
//     ],
//     'CLR-' => [
//         'table' => 'barangay_clearance_requests',
//         'name' => 'Barangay Clearance',
//         'amount' => 130
//     ],
//     'BUS-' => [
//         'table' => 'business_clearance_requests',
//         'name' => 'Business Clearance',
//         'amount' => 130
//     ],
//     'RES-' => [
//         'table' => 'residency_requests',
//         'name' => 'Certificate of Residency',
//         'amount' => 130
//     ],
//     'IND-' => [
//         'table' => 'indigency_requests',
//         'name' => 'Certificate of Indigency',
//         'amount' => 0  // Free
//     ],
//     'CGM-' => [
//         'table' => 'good_moral_requests',
//         'name' => 'Certificate of Good Moral',
//         'amount' => 130
//     ],
//     'CSP-' => [
//         'table' => 'solo_parent_requests',
//         'name' => 'Solo Parent Certificate',
//         'amount' => 130
//     ],
//     'GUA-' => [
//         'table' => 'guardianship_requests',
//         'name' => 'Certificate of Guardianship',
//         'amount' => 130
//     ],
//     'FJS-' => [
//         'table' => 'job_seeker_requests',
//         'name' => 'First Time Job Seeker',
//         'amount' => 0  // Free
//     ]
// ]);

define('REQUEST_TYPE_MAP', [
    'BID-' => [
        'table' => 'barangay_id_requests',
        'name' => 'Barangay ID',
        'amount' => 20  // TESTING: was 130
    ],
    'CLR-' => [
        'table' => 'barangay_clearance_requests',
        'name' => 'Barangay Clearance',
        'amount' => 20  // TESTING: was 130
    ],
    'BUS-' => [
        'table' => 'business_clearance_requests',
        'name' => 'Business Clearance',
        'amount' => 20  // TESTING: was 130
    ],
    'RES-' => [
        'table' => 'residency_requests',
        'name' => 'Certificate of Residency',
        'amount' => 20  // TESTING: was 130
    ],
    'IND-' => [
        'table' => 'indigency_requests',
        'name' => 'Certificate of Indigency',
        'amount' => 0  // Free
    ],
    'CGM-' => [
        'table' => 'good_moral_requests',
        'name' => 'Certificate of Good Moral',
        'amount' => 20  // TESTING: was 130
    ],
    'CSP-' => [
        'table' => 'solo_parent_requests',
        'name' => 'Solo Parent Certificate',
        'amount' => 20  // TESTING: was 130
    ],
    'GUA-' => [
        'table' => 'guardianship_requests',
        'name' => 'Certificate of Guardianship',
        'amount' => 20  // TESTING: was 130
    ],
    'FJS-' => [
        'table' => 'job_seeker_requests',
        'name' => 'First Time Job Seeker',
        'amount' => 0  // Free
    ]
]);

/**
 * Get current domain based on environment
 */
function getCurrentDomain() {
    if (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            return LOCAL_DOMAIN;
        }
    }
    return PRODUCTION_DOMAIN;
}

/**
 * Get request type info from transaction ID
 */
function getRequestTypeInfo($transactionId) {
    foreach (REQUEST_TYPE_MAP as $prefix => $info) {
        if (strpos($transactionId, $prefix) === 0) {
            return $info;
        }
    }
    return null;
}

/**
 * Validate configuration
 */
if (empty(PAYMONGO_SECRET_KEY) || PAYMONGO_SECRET_KEY === 'sk_test_YOUR_SECRET_KEY_HERE') {
    error_log("CRITICAL: PAYMONGO_SECRET_KEY not configured!");
}