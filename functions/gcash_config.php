<?php
require_once 'env_loader.php';

// Load .env file
loadEnv(__DIR__ . '/../.env');

// PayMongo Configuration
define('PAYMONGO_SECRET_KEY', $_ENV['PAYMONGO_SECRET_KEY'] ?? '');
define('PAYMONGO_PUBLIC_KEY', $_ENV['PAYMONGO_PUBLIC_KEY'] ?? '');
define('PAYMONGO_API_URL', 'https://api.paymongo.com/v1');

// Domain configuration
define('PRODUCTION_DOMAIN', 'https://ebarangaymo.qpcamnorte.com');
define('LOCAL_DOMAIN', 'http://localhost:3000');

// Determine current domain
function getCurrentDomain() {
    if (isset($_SERVER['HTTP_HOST'])) {
        if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
            return LOCAL_DOMAIN;
        } else {
            return PRODUCTION_DOMAIN;
        }
    }
    return PRODUCTION_DOMAIN; // Default to production
}