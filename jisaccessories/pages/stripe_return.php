<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

$sessionId = $_GET['session_id'] ?? '';
$secretKey = getSetting('stripe_secret_key', '');

if ($sessionId === '' || $secretKey === '') {
    header('Location: ' . BASE_PATH . '/pages/checkout.php');
    exit;
}

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $secretKey . ':',
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
curl_close($ch);
$session = json_decode($response, true);

if (empty($session['payment_status']) || $session['payment_status'] !== 'paid') {
    $_SESSION['checkout_error'] = 'Payment was not completed. Please try again.';
    header('Location: ' . BASE_PATH . '/pages/checkout.php');
    exit;
}

$orderId = (int) ($session['client_reference_id'] ?? 0);
$stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('SELECT order_number FROM orders WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

unset($_SESSION['cart'], $_SESSION['pending_order_id']);

header('Location: ' . BASE_PATH . '/pages/order_success.php?order=' . urlencode($order['order_number'] ?? ''));
exit;
