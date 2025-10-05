<?php
require_once 'env_loader.php';

// Load .env file
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    error_log("WARNING: .env file not found at: " . $envPath);
}
loadEnv($envPath);

// PayMongo Configuration - Only secret key needed for GCash
define('PAYMONGO_SECRET_KEY', $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY') ?? '');
define('PAYMONGO_API_URL', 'https://api.paymongo.com/v1');

// Validate secret key
if (empty(PAYMONGO_SECRET_KEY)) {
    error_log("CRITICAL: PAYMONGO_SECRET_KEY is not configured!");
}

// Domain configuration
define('PRODUCTION_DOMAIN', 'https://ebarangaymo.qpcamnorte.com');
define('LOCAL_DOMAIN', 'http://localhost:3000');

// Determine current domain
function getCurrentDomain() {
    if (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            return LOCAL_DOMAIN;
        }
    }
    return PRODUCTION_DOMAIN;
}