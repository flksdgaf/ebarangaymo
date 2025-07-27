<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// 1. Load .env from project root
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// 2. Read your PayMongo secret key
$paymongoSecretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? '';
if (! $paymongoSecretKey) {
    die('PAYMONGO_SECRET_KEY is not set in .env');
}

// 3. Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get amount from the form
    $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;

    // Validate amount (₱20 minimum for GCash)
    if ($amount < 20) {
        die('Minimum payment amount is ₱20.00 due to GCash restrictions.');
    }

    // Convert to centavos
    $amountInCentavos = (int) ($amount * 100);

    // Redirect URLs (make sure these files exist)
    $successUrl = 'https://yourdomain.com/gcash_success.php';
    $failedUrl  = 'https://yourdomain.com/gcash_failed.php';

    // Build payload
    $data = [
        'data' => [
            'attributes' => [
                'amount'   => $amountInCentavos,
                'currency' => 'PHP',
                'type'     => 'gcash',
                'redirect' => [
                    'success' => $successUrl,
                    'failed'  => $failedUrl,
                ],
            ],
        ],
    ];

    // Send to PayMongo
    $ch = curl_init('https://api.paymongo.com/v1/sources');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_USERPWD        => $paymongoSecretKey . ':',
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        die('cURL error: ' . curl_error($ch));
    }
    curl_close($ch);

    $result = json_decode($response, true);

    // Redirect to PayMongo checkout or show error
    if (in_array($httpCode, [200, 201])) {
        $checkoutUrl = $result['data']['attributes']['redirect']['checkout_url'];
        header('Location: ' . $checkoutUrl);
        exit;
    } else {
        echo "<h2>Error creating GCash source</h2>";
        echo '<pre>' . print_r($result, true) . '</pre>';
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pay with GCash</title>
</head>
<body>
    <h2>GCash Payment</h2>
    <form method="POST" action="">
        <label>Enter Amount (₱):</label>
        <input type="number" name="amount" min="20" step="0.01" required>
        <button type="submit">Pay with GCash</button>
    </form>
</body>
</html>
