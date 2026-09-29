<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$productId = (int) ($_POST['product_id'] ?? 0);
$qty = (int) ($_POST['qty'] ?? 1);

if ($productId <= 0 || !in_array($action, ['add', 'update', 'remove'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Verify the product actually exists before mutating the cart
$stmt = $conn->prepare('SELECT id, stock FROM products WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product && $action !== 'remove') {
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}

switch ($action) {
    case 'add':
        addToCart($productId, max(1, $qty));
        break;
    case 'update':
        updateCartQty($productId, max(1, $qty));
        break;
    case 'remove':
        removeFromCart($productId);
        break;
}

$subtotal = getCartTotal();
$shipping = getShippingCost($subtotal);
$total = $subtotal + $shipping;
$freeShipRemaining = max(0, 3000 - $subtotal);

$itemSubtotalFormatted = null;
if (in_array($action, ['add', 'update'], true)) {
    $cart = getCart();
    if (isset($cart[$productId])) {
        $stmt = $conn->prepare('SELECT price FROM products WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($p) $itemSubtotalFormatted = formatPrice($p['price'] * $cart[$productId]);
    }
}

echo json_encode([
    'success' => true,
    'cart_count' => getCartCount(),
    'formatted_total' => formatPrice($total),
    'formatted_subtotal' => formatPrice($subtotal),
    'formatted_shipping' => $shipping == 0 ? 'Free' : formatPrice($shipping),
    'ship_message' => $freeShipRemaining > 0 ? ('Add ' . formatPrice($freeShipRemaining) . ' more for free shipping!') : '',
    'item_subtotal_formatted' => $itemSubtotalFormatted,
]);
