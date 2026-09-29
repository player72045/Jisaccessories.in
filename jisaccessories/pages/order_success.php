<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

$pageTitle = 'Order Confirmed';
$orderNumber = trim($_GET['order'] ?? '');

$order = null;
if ($orderNumber !== '') {
    $stmt = $conn->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
    $stmt->bind_param('s', $orderNumber);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$order) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
  <div class="success-wrap">
    <div class="success-icon">✓</div>
    <h1 style="font-size:28px;">Thank you, <?= e($order['contact_name'] ?: 'valued customer') ?>! 🎉</h1>
    <p>Your order has been placed successfully. We've sent a confirmation to <?= e($order['contact_email']) ?>.</p>

    <div class="order-number-box">
      <span>Order Number</span>
      <strong><?= e($order['order_number']) ?></strong>
    </div>

    <p style="font-size:13.5px;">Payment method: <strong style="color:var(--text);"><?= e($order['payment_method']) ?></strong> · Total: <strong style="color:var(--accent-light);"><?= formatPrice((float)$order['total_amount']) ?></strong></p>

    <div style="display:flex;gap:12px;justify-content:center;margin-top:28px;">
      <a href="<?= BASE_PATH ?>/pages/myaccount.php?tab=orders" class="btn btn-outline">View My Orders</a>
      <a href="<?= BASE_PATH ?>/pages/shop.php" class="btn btn-primary">Continue Shopping</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
