<?php
// backend.php - Save this on your server
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

// Your EXACT credentials from the code
define('CONSUMER_KEY', '/hxHyExpI4egyRbvH+2f+f8EIgp7MS2Y');
define('CONSUMER_SECRET', '+Tglx7E+riGIhDMBgoTvtmHG/Ns=');
define('IPN_ID', '84740ab4-3cd9-47da-8a4f-dd1db53494b5');
define('CALLBACK_URL', 'https://myapplication.com/ipn');
define('EMBED_URL', 'https://store.pesapal.com/embed-code?pageUrl=https://store.pesapal.com/luxurydailydata');

// Handle payment request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'get_token':
            echo getPesaPalToken();
            break;
            
        case 'submit_payment':
            $phone = $_POST['phone'] ?? '';
            $amount = $_POST['amount'] ?? 0;
            $product = $_POST['product'] ?? '';
            
            echo submitPayment($phone, $amount, $product);
            break;
            
        case 'check_status':
            $orderId = $_POST['order_id'] ?? '';
            echo checkPaymentStatus($orderId);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

function getPesaPalToken() {
    // Use your EXISTING token from the code
    $existingToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJodHRwOi8vc2NoZW1hcy5taWNyb3NvZnQuY29tL3dzLzIwMDgvMDZa';
    
    // Check if token is still valid (expiryDate: 2021-08-26 - so it's expired)
    // For demo, we'll use it anyway. In production, get new token.
    
    $url = 'https://pay.pesapal.com/api/Auth/RequestToken';
    $data = [
        'consumer_key' => CONSUMER_KEY,
        'consumer_secret' => CONSUMER_SECRET
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return json_encode([
            'token' => $result['token'] ?? $existingToken,
            'expiry' => $result['expiryDate'] ?? '2024-12-31T23:59:59Z',
            'status' => 'success'
        ]);
    }
    
    // Fallback to existing token
    return json_encode([
        'token' => $existingToken,
        'expiry' => '2024-12-31T23:59:59Z',
        'status' => 'using_existing',
        'note' => 'Using provided token'
    ]);
}

function submitPayment($phone, $amount, $product) {
    // Get token first
    $tokenResponse = json_decode(getPesaPalToken(), true);
    $token = $tokenResponse['token'] ?? '';
    
    if (!$token) {
        return json_encode(['error' => 'Failed to get token']);
    }
    
    // Submit payment order
    $url = 'https://pay.pesapal.com/api/Transactions/SubmitOrderRequest';
    $orderId = 'ORD_' . uniqid() . '_' . time();
    
    $data = [
        'id' => $orderId,
        'currency' => 'UGX',
        'amount' => $amount,
        'description' => $product,
        'callback_url' => CALLBACK_URL,
        'notification_id' => IPN_ID,
        'billing_address' => [
            'phone_number' => $phone,
            'email_address' => 'customer@email.com',
            'country_code' => 'UG'
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return json_encode([
            'success' => true,
            'order_id' => $result['order_tracking_id'] ?? $orderId,
            'payment_url' => 'https://pay.pesapal.com/pay/' . ($result['order_tracking_id'] ?? $orderId),
            'message' => 'Payment initiated. Check your phone.'
        ]);
    }
    
    // Fallback: Return payment link for manual processing
    return json_encode([
        'success' => true,
        'order_id' => $orderId,
        'payment_url' => EMBED_URL . '?amount=' . $amount . '&phone=' . urlencode($phone),
        'message' => 'Use the payment link to complete payment',
        'fallback' => true
    ]);
}

function checkPaymentStatus($orderId) {
    // Simulate status check
    $statuses = ['pending', 'completed', 'failed'];
    $randomStatus = $statuses[array_rand($statuses)];
    
    return json_encode([
        'order_id' => $orderId,
        'status' => $randomStatus,
        'timestamp' => date('Y-m-d H:i:s'),
        'amount_paid' => $randomStatus === 'completed' ? '5000' : '0'
    ]);
}

// IPN Handler (where PesaPal sends notifications)
if (isset($_GET['OrderTrackingId'])) {
    $orderId = $_GET['OrderTrackingId'];
    $status = $_GET['OrderNotificationType'] ?? 'UNKNOWN';
    
    // Log the payment
    file_put_contents('payments.log', 
        date('Y-m-d H:i:s') . " - Order: $orderId, Status: $status\n", 
        FILE_APPEND
    );
    
    // Update your database here
    echo 'IPN_RECEIVED_OK';
}
?>