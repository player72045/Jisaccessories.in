<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

// Uses Stripe's plain REST API over cURL — no Composer/SDK required.
// This keeps the integration to a single file with zero external dependencies.

if (empty($_SESSION['pending_order_id'])) {
    header('Location: ' . BASE_PATH . '/pages/cart.php');
    exit;
}

$orderId = (int) $_SESSION['pending_order_id'];
$secretKey = getSetting('stripe_secret_key', '');

$stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order || str_contains($secretKey, 'REPLACE_WITH') || $secretKey === '') {
    header('Location: ' . BASE_PATH . '/pages/checkout.php');
    exit;
}

$itemsRes = $conn->prepare('SELECT oi.*, p.name, p.image_url FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$itemsRes->bind_param('i', $orderId);
$itemsRes->execute();
$orderItems = $itemsRes->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsRes->close();

$currencyCode = getStripeCurrencyCode();
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

$fields = [
    'mode' => 'payment',
    'success_url' => "$scheme://$host" . BASE_PATH . '/pages/stripe_return.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'  => "$scheme://$host" . BASE_PATH . '/pages/checkout.php?stripe_cancelled=1',
    'client_reference_id' => (string) $orderId,
    'customer_email' => $order['contact_email'],
];

$i = 0;
foreach ($orderItems as $item) {
    $fields["line_items[$i][price_data][currency]"] = $currencyCode;
    $fields["line_items[$i][price_data][product_data][name]"] = $item['name'];
    $fields["line_items[$i][price_data][unit_amount]"] = getStripeAmount((float) $item['price']);
    $fields["line_items[$i][quantity]"] = (int) $item['quantity'];
    $i++;
}
// Shipping as its own line item so the Stripe total matches the order total
$shipping = getShippingCost((float) array_sum(array_map(fn($it) => $it['price'] * $it['quantity'], $orderItems)));
if ($shipping > 0) {
    $fields["line_items[$i][price_data][currency]"] = $currencyCode;
    $fields["line_items[$i][price_data][product_data][name]"] = 'Shipping';
    $fields["line_items[$i][price_data][unit_amount]"] = getStripeAmount($shipping);
    $fields["line_items[$i][quantity]"] = 1;
}

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($fields),
    CURLOPT_USERPWD => $secretKey . ':',
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

$session = json_decode($response, true);

if ($curlError || empty($session['url'])) {
    $_SESSION['checkout_error'] = 'We could not start the Stripe payment session. Please try Cash on Delivery or UPI, or try again shortly.';
    header('Location: ' . BASE_PATH . '/pages/checkout.php');
    exit;
}

$stmt = $conn->prepare('UPDATE orders SET stripe_session_id = ? WHERE id = ?');
$stmt->bind_param('si', $session['id'], $orderId);
$stmt->execute();
$stmt->close();

header('Location: ' . $session['url']);
exit;
