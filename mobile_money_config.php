<?php
// mobile_money_config.php
// Orange Money & Africell Money API Configuration for Sierra Leone

// ============================================
// ORANGE MONEY SIERRA LEONE API CONFIGURATION
// ============================================
define('ORANGE_MONEY_API_URL', 'https://api.orange.com/orange-money-webpay/sl/v1');
define('ORANGE_MONEY_MERCHANT_ID', 'YOUR_ORANGE_MERCHANT_ID');
define('ORANGE_MONEY_CLIENT_ID', 'YOUR_ORANGE_CLIENT_ID');
define('ORANGE_MONEY_CLIENT_SECRET', 'YOUR_ORANGE_CLIENT_SECRET');
define('ORANGE_MONEY_CALLBACK_URL', 'http://localhost/market-ops/callback-orange.php');
define('ORANGE_MONEY_CURRENCY', 'SLL');

// ============================================
// AFRICELL MONEY SIERRA LEONE API CONFIGURATION
// ============================================
define('AFRICELL_MONEY_API_URL', 'https://api.africell.sl/africell-money/v1');
define('AFRICELL_MONEY_MERCHANT_ID', 'YOUR_AFRICELL_MERCHANT_ID');
define('AFRICELL_MONEY_API_KEY', 'YOUR_AFRICELL_API_KEY');
define('AFRICELL_MONEY_CALLBACK_URL', 'http://localhost/market-ops/callback-africell.php');
define('AFRICELL_MONEY_CURRENCY', 'SLL');

// ============================================
// SIMULATION MODE (Set to false in production)
// ============================================
define('SIMULATION_MODE', true);

/**
 * Process Orange Money Payment
 */
function processOrangeMoneyPayment($phone_number, $amount, $reference, $trader_name)
{
    if (SIMULATION_MODE) {
        return simulateMobilePayment('Orange Money', $phone_number, $amount, $reference);
    }

    try {
        // Step 1: Get Access Token
        $token = getOrangeMoneyToken();

        // Step 2: Initiate Payment
        $paymentData = [
            'merchantId' => ORANGE_MONEY_MERCHANT_ID,
            'amount' => $amount,
            'currency' => ORANGE_MONEY_CURRENCY,
            'reference' => $reference,
            'customerPhone' => formatPhoneNumber($phone_number),
            'callbackUrl' => ORANGE_MONEY_CALLBACK_URL,
            'description' => "Market Dues - $trader_name"
        ];

        $ch = curl_init(ORANGE_MONEY_API_URL . '/payments');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($paymentData),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 || $httpCode == 201) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'provider' => 'Orange Money',
                'transaction_id' => $result['transactionId'] ?? $reference,
                'message' => 'Payment request sent. Waiting for confirmation.',
                'data' => $result
            ];
        } else {
            error_log("Orange Money API Error: " . $response);
            return [
                'success' => false,
                'provider' => 'Orange Money',
                'message' => 'Payment failed. Please try again.'
            ];
        }

    } catch (Exception $e) {
        error_log("Orange Money Exception: " . $e->getMessage());
        return [
            'success' => false,
            'provider' => 'Orange Money',
            'message' => 'Service temporarily unavailable.'
        ];
    }
}

/**
 * Process Africell Money Payment
 */
function processAfricellMoneyPayment($phone_number, $amount, $reference, $trader_name)
{
    if (SIMULATION_MODE) {
        return simulateMobilePayment('Africell Money', $phone_number, $amount, $reference);
    }

    try {
        $paymentData = [
            'merchant_id' => AFRICELL_MONEY_MERCHANT_ID,
            'amount' => $amount,
            'currency' => AFRICELL_MONEY_CURRENCY,
            'reference' => $reference,
            'msisdn' => formatPhoneNumber($phone_number),
            'callback_url' => AFRICELL_MONEY_CALLBACK_URL,
            'narration' => "Market Dues - $trader_name"
        ];

        $ch = curl_init(AFRICELL_MONEY_API_URL . '/request-payment');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . AFRICELL_MONEY_API_KEY,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($paymentData),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 || $httpCode == 201) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'provider' => 'Africell Money',
                'transaction_id' => $result['transaction_id'] ?? $reference,
                'message' => 'Payment request sent. Waiting for confirmation.',
                'data' => $result
            ];
        } else {
            error_log("Africell Money API Error: " . $response);
            return [
                'success' => false,
                'provider' => 'Africell Money',
                'message' => 'Payment failed. Please try again.'
            ];
        }

    } catch (Exception $e) {
        error_log("Africell Money Exception: " . $e->getMessage());
        return [
            'success' => false,
            'provider' => 'Africell Money',
            'message' => 'Service temporarily unavailable.'
        ];
    }
}

/**
 * Simulate Mobile Payment (for testing)
 */
function simulateMobilePayment($provider, $phone, $amount, $reference)
{
    $transactionId = $provider == 'Orange Money' ?
        'OM-SL-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) :
        'AM-SL-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

    return [
        'success' => true,
        'provider' => $provider,
        'transaction_id' => $transactionId,
        'phone' => $phone,
        'amount' => $amount,
        'reference' => $reference,
        'simulated' => true,
        'message' => "SIMULATION: $provider payment of SLL " . number_format($amount) . " processed successfully.",
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Get Orange Money OAuth Token
 */
function getOrangeMoneyToken()
{
    $ch = curl_init('https://api.orange.com/oauth/v3/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode(ORANGE_MONEY_CLIENT_ID . ':' . ORANGE_MONEY_CLIENT_SECRET),
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * Format Sierra Leone phone number
 */
function formatPhoneNumber($phone)
{
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if (strlen($phone) == 8) {
        return '232' . $phone;
    } elseif (strlen($phone) == 10 && substr($phone, 0, 2) == '07') {
        return '232' . substr($phone, 1);
    }

    return $phone;
}

/**
 * Check Mobile Payment Status
 */
function checkPaymentStatus($provider, $transactionId)
{
    if (SIMULATION_MODE) {
        return [
            'success' => true,
            'status' => 'completed',
            'message' => 'Payment verified successfully.'
        ];
    }

    try {
        if ($provider == 'Orange Money') {
            $url = ORANGE_MONEY_API_URL . '/payments/' . $transactionId;
            $token = getOrangeMoneyToken();
            $headers = ['Authorization: Bearer ' . $token];
        } else {
            $url = AFRICELL_MONEY_API_URL . '/transaction-status/' . $transactionId;
            $headers = ['X-API-Key: ' . AFRICELL_MONEY_API_KEY];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);

    } catch (Exception $e) {
        return ['success' => false, 'status' => 'unknown'];
    }
}
?>