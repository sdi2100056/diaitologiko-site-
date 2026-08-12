<?php
require_once __DIR__ . '/../includes/functions.php';

$service_id = (int)($_POST['service_id'] ?? 0);
$service = get_service($service_id);

if (!$service) {
    die('Η υπηρεσία δεν βρέθηκε.');
}

$base_auth = VIVA_DEMO ? 'https://demo-accounts.vivapayments.com' : 'https://accounts.vivapayments.com';
$base_api = VIVA_DEMO ? 'https://demo-api.vivapayments.com' : 'https://api.vivapayments.com';
$base_checkout = VIVA_DEMO ? 'https://demo.vivapayments.com' : 'https://www.vivapayments.com';

// Βήμα 1: Λήψη access token (OAuth2 client credentials)
$ch = curl_init("$base_auth/connect/token");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']),
    CURLOPT_USERPWD => VIVA_CLIENT_ID . ':' . VIVA_CLIENT_SECRET,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$token_response = curl_exec($ch);
curl_close($ch);
$token_data = json_decode($token_response, true);
$access_token = $token_data['access_token'] ?? null;

if (!$access_token) {
    die('Σφάλμα σύνδεσης με τη Viva Wallet. Δοκίμασε ξανά αργότερα.');
}

// Βήμα 2: Δημιουργία Payment Order
$amount_cents = (int)round($service['price'] * 100);

$ch = curl_init("$base_api/checkout/v2/orders");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'amount' => $amount_cents,
        'customerTrns' => $service['name'],
        'customer' => [
            'email' => '',
            'fullName' => '',
            'requestLang' => 'el-GR'
        ],
        'paymentTimeout' => 1800,
        'preauth' => false,
        'allowRecurring' => false,
        'sourceCode' => 'Default',
        'merchantTrns' => 'service_id:' . $service['id']
    ])
]);
$order_response = curl_exec($ch);
curl_close($ch);
$order_data = json_decode($order_response, true);
$order_code = $order_data['orderCode'] ?? null;

if (!$order_code) {
    die('Σφάλμα δημιουργίας παραγγελίας. Δοκίμασε ξανά.');
}

// Καταχώρηση pending order στη βάση
$pdo = get_db();
$stmt = $pdo->prepare("INSERT INTO orders (service_id, client_name, client_email, amount, viva_order_code, status) VALUES (?, '', '', ?, ?, 'pending')");
$stmt->execute([$service['id'], $service['price'], $order_code]);

// Redirect στη σελίδα πληρωμής της Viva
header('Location: ' . $base_checkout . '/web/checkout?ref=' . $order_code);
exit;
