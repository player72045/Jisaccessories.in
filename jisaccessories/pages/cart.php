<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

// ── Server-side POST handling BEFORE any HTML output ────
// This is the non-JS fallback: the remove <form> posts here directly
// if the AJAX interceptor in main.js fails for any reason.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    removeFromCart((int) $_POST['remove_id']);
    header('Location: ' . BASE_PATH . '/pages/cart.php');
    exit;
}

$pageTitle = 'Shopping Cart';
$items = getCartItemsDetailed();
$subtotal = getCartTotal();
$shipping = getShippingCost($subtotal);
$total = $subtotal + $shipping;
$freeShipRemaining = max(0, 3000 - $subtotal);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
  <div class="breadcrumb">
    <a href="<?= BASE_PATH ?>/index.php">Home</a><span class="sep">›</span><span class="current">Shopping Cart</span>
  </div>
  <h1 style="margin-top:16px;font-size:26px;">Shopping Cart <span style="color:var(--text2);font-weight:400;font-size:16px;">(<?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?>)</span></h1>
</div>

<?php if (empty($items)): ?>
  <div class="container">
    <div class="empty-state">
      <div class="icon">🛍️</div>
      <h3>Your cart is empty</h3>
      <p>Looks like you haven't added anything yet — let's fix that.</p>
      <a href="<?= BASE_PATH ?>/pages/shop.php" class="btn btn-primary">Start Shopping</a>
    </div>
  </div>
<?php else: ?>
<div class="container cart-layout">
  <div>
    <table class="cart-table">
      <thead>
        <tr><th>Product</th><th>Price</th><th>Quantity</th><th>Subtotal</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <tr class="cart-row" data-product-id="<?= (int)$item['id'] ?>">
          <td>
            <a href="<?= BASE_PATH ?>/pages/product.php?slug=<?= e($item['slug']) ?>" class="cart-product">
              <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>">
              <div><strong><?= e($item['name']) ?></strong><span><?= e($item['category_name']) ?></span></div>
            </a>
          </td>
          <td class="cart-price"><?= formatPrice($item['price']) ?></td>
          <td>
            <div class="qty-stepper" data-product-id="<?= (int)$item['id'] ?>">
              <button type="button" class="qty-minus">−</button>
              <input type="text" value="<?= (int)$item['qty'] ?>" inputmode="numeric">
              <button type="button" class="qty-plus">+</button>
            </div>
          </td>
          <td class="cart-price cart-subtotal"><?= formatPrice($item['subtotal']) ?></td>
          <td>
            <form method="POST" action="<?= BASE_PATH ?>/pages/cart.php" class="remove-form" data-product-id="<?= (int)$item['id'] ?>">
              <input type="hidden" name="remove_id" value="<?= (int)$item['id'] ?>">
              <button type="submit" class="cart-remove" aria-label="Remove item">×</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <a href="<?= BASE_PATH ?>/pages/shop.php" class="btn btn-outline" style="margin-top:24px;">← Continue Shopping</a>
  </div>

  <aside class="order-summary">
    <h3>Order Summary</h3>
    <?php if ($freeShipRemaining > 0): ?>
      <div class="ship-note" data-summary="ship-note">Add <?= formatPrice($freeShipRemaining) ?> more for free shipping!</div>
    <?php else: ?>
      <div class="ship-note" data-summary="ship-note" style="display:none;"></div>
    <?php endif; ?>
    <div class="summary-row"><span>Subtotal</span><span data-summary="subtotal"><?= formatPrice($subtotal) ?></span></div>
    <div class="summary-row"><span>Shipping</span><span data-summary="shipping"><?= $shipping == 0 ? 'Free' : formatPrice($shipping) ?></span></div>
    <div class="summary-row total"><span>Total</span><span data-summary="total"><?= formatPrice($total) ?></span></div>

    <div class="coupon-row">
      <input type="text" placeholder="Coupon code">
      <button class="btn btn-outline btn-sm">Apply</button>
    </div>

    <a href="<?= BASE_PATH ?>/pages/checkout.php" class="btn btn-primary btn-block btn-lg">Proceed to Checkout</a>

    <div class="pay-icons">💳 🏦 📱 🌐</div>
    <div class="security-badge">🔒 Secure Checkout Guaranteed</div>
  </aside>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
