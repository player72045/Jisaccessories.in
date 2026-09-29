<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

$pageTitle = 'Checkout';
$items = getCartItemsDetailed();

if (empty($items)) {
    header('Location: ' . BASE_PATH . '/pages/cart.php');
    exit;
}

$currentUser = isLoggedIn() ? getCurrentUser() : null;
$errors = [];

$subtotal = getCartTotal();
$shipping = getShippingCost($subtotal);
$total = $subtotal + $shipping;

$stripeEnabled = getSetting('stripe_publishable_key', '') !== '' && !str_contains(getSetting('stripe_publishable_key'), 'REPLACE_WITH');

$rzpKeyId     = getSetting('razorpay_key_id', '');
$rzpKeySecret = getSetting('razorpay_key_secret', '');
$razorpayEnabled = $rzpKeyId !== '' && $rzpKeySecret !== '';

// Handle Stripe cancellation or error return
if (!empty($_SESSION['checkout_error'])) {
    $errors[] = $_SESSION['checkout_error'];
    unset($_SESSION['checkout_error']);
}

// Pre-fill values
$vals = [
    'first_name'     => $currentUser['first_name'] ?? '',
    'last_name'      => $currentUser['last_name'] ?? '',
    'email'          => $currentUser['email'] ?? '',
    'phone'          => $currentUser['phone'] ?? '',
    'house'          => '',
    'address'        => $currentUser['address'] ?? '',
    'city'           => $currentUser['city'] ?? '',
    'pincode'        => '',
    'country'        => 'India',
    'notes'          => '',
    'payment_method' => 'cod',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($vals as $key => $default) {
        $vals[$key] = trim($_POST[$key] ?? $default);
    }

    if ($vals['first_name'] === '') $errors[] = 'First name is required.';
    if ($vals['last_name'] === '')  $errors[] = 'Last name is required.';
    if ($vals['email'] === '' || !filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if ($vals['phone'] === '' || !preg_match('/^[0-9+\-\s()]{7,20}$/', $vals['phone'])) $errors[] = 'A valid phone number is required.';
    if ($vals['house'] === '') $errors[] = 'House / Flat / Building is required.';
    if ($vals['address'] === '') $errors[] = 'Street address is required.';
    if ($vals['city'] === '') $errors[] = 'City is required.';
    if ($vals['pincode'] === '' || !preg_match('/^[0-9]{4,10}$/', $vals['pincode'])) $errors[] = 'A valid pincode is required.';
    if (!in_array($vals['payment_method'], ['cod', 'upi', 'card', 'razorpay'], true)) $errors[] = 'Please select a payment method.';

    if (empty($errors)) {
        $orderNumber = generateOrderNumber();
        $shippingAddress = $vals['address'];
        $userId = $currentUser['id'] ?? null;
        $payMap = ['cod' => 'Cash on Delivery', 'upi' => 'UPI', 'card' => 'Credit/Debit Card', 'razorpay' => 'Razorpay'];
        $paymentMethodLabel = $payMap[$vals['payment_method']];

        $stmt = $conn->prepare("INSERT INTO orders
            (user_id, order_number, total_amount, status, payment_method, payment_status,
             house, shipping_address, city, pincode, country,
             contact_name, contact_email, contact_phone, notes)
            VALUES (?, ?, ?, 'pending', ?, 'unpaid', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $contactName = $vals['first_name'] . ' ' . $vals['last_name'];
        $stmt->bind_param('isdssssssssss',
            $userId, $orderNumber, $total, $paymentMethodLabel,
            $vals['house'], $shippingAddress, $vals['city'], $vals['pincode'], $vals['country'],
            $contactName, $vals['email'], $vals['phone'], $vals['notes']
        );
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();

        $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
        foreach ($items as $item) {
            $itemStmt->bind_param('iiid', $orderId, $item['id'], $item['qty'], $item['price']);
            $itemStmt->execute();
        }
        $itemStmt->close();

        // Stripe card payment
        if ($vals['payment_method'] === 'card' && $stripeEnabled) {
            $_SESSION['pending_order_id'] = $orderId;
            header('Location: ' . BASE_PATH . '/pages/stripe_checkout.php');
            exit;
        }

        // Razorpay — create server-side order, then hand off to JS
        if ($vals['payment_method'] === 'razorpay' && $razorpayEnabled) {
            $amountPaise = (int) round($total * 100);
            $rzpPayload = json_encode([
                'amount'   => $amountPaise,
                'currency' => 'INR',
                'receipt'  => $orderNumber,
            ]);
            $ch = curl_init('https://api.razorpay.com/v1/orders');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $rzpPayload,
                CURLOPT_USERPWD        => $rzpKeyId . ':' . $rzpKeySecret,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            ]);
            $rzpResponse = curl_exec($ch);
            $curlErr     = curl_error($ch);
            curl_close($ch);

            $rzpOrder = $rzpResponse ? json_decode($rzpResponse, true) : null;

            if ($rzpOrder && isset($rzpOrder['id'])) {
                // Save Razorpay order ID in DB
                $upd = $conn->prepare('UPDATE orders SET razorpay_order_id = ? WHERE id = ?');
                $upd->bind_param('si', $rzpOrder['id'], $orderId);
                $upd->execute();
                $upd->close();

                // Store in session for verify page
                $_SESSION['rzp_order_id']  = $orderId;
                $_SESSION['rzp_order_num'] = $orderNumber;

                // Render Razorpay checkout JS inline (before cart clear)
                $rzpData = json_encode([
                    'key'         => $rzpKeyId,
                    'amount'      => $amountPaise,
                    'currency'    => 'INR',
                    'name'        => 'JIS Accessories',
                    'description' => 'Order ' . $orderNumber,
                    'order_id'    => $rzpOrder['id'],
                    'prefill'     => [
                        'name'    => $contactName,
                        'email'   => $vals['email'],
                        'contact' => $vals['phone'],
                    ],
                    'theme'       => ['color' => '#b5832a'],
                ]);

                require_once __DIR__ . '/../includes/header.php';
                echo '<div class="container" style="padding:60px 20px;text-align:center;">';
                echo '<h2 style="margin-bottom:8px;">Completing your payment…</h2>';
                echo '<p style="color:var(--text2);">Please do not close this window.</p>';
                echo '</div>';
                echo '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>';
                echo '<script>';
                echo 'var rzpOptions = ' . $rzpData . ';';
                echo 'rzpOptions.handler = function(response){';
                echo '  var form = document.createElement("form");';
                echo '  form.method = "POST";';
                echo '  form.action = "' . BASE_PATH . '/pages/razorpay_verify.php";';
                echo '  ["razorpay_payment_id","razorpay_order_id","razorpay_signature"].forEach(function(k){';
                echo '    var inp = document.createElement("input");';
                echo '    inp.type = "hidden"; inp.name = k; inp.value = response[k];';
                echo '    form.appendChild(inp);';
                echo '  });';
                echo '  document.body.appendChild(form); form.submit();';
                echo '};';
                echo 'rzpOptions.modal = { ondismiss: function(){';
                echo '  window.location = "' . BASE_PATH . '/pages/checkout.php?rzp_cancelled=1";';
                echo '}};';
                echo 'var rzp = new Razorpay(rzpOptions); rzp.open();';
                echo '</script>';
                require_once __DIR__ . '/../includes/footer.php';
                exit;
            } else {
                $errors[] = 'Razorpay order creation failed. Please try another payment method. ' . ($curlErr ?: '');
                // Delete the order we just created so user can retry
                $conn->prepare('DELETE FROM orders WHERE id = ?')->execute(); // safe to leave, or:
                // just show error and let user resubmit
            }
        }

        // COD / UPI — mark paid immediately for COD-like flow
        if (in_array($vals['payment_method'], ['cod', 'upi'])) {
            $upd = $conn->prepare("UPDATE orders SET payment_status = 'unpaid' WHERE id = ?");
            $upd->bind_param('i', $orderId);
            $upd->execute();
            $upd->close();
        }

        unset($_SESSION['cart']);
        $_SESSION['last_order_number'] = $orderNumber;
        header('Location: ' . BASE_PATH . '/pages/order_success.php?order=' . urlencode($orderNumber));
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
  <div class="breadcrumb">
    <a href="<?= BASE_PATH ?>/index.php">Home</a><span class="sep">›</span>
    <a href="<?= BASE_PATH ?>/pages/cart.php">Cart</a><span class="sep">›</span>
    <span class="current">Checkout</span>
  </div>
</div>

<div class="container checkout-layout">
  <form method="POST" action="<?= BASE_PATH ?>/pages/checkout.php" id="checkoutForm">
    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err) echo '<div>⚠ ' . e($err) . '</div>'; ?>
      </div>
    <?php endif; ?>

    <!-- Step 1: Contact -->
    <div class="checkout-section">
      <div class="step-head"><span class="step-badge">1</span><h3 style="margin:0;">Contact Information</h3></div>
      <div class="form-grid">
        <div class="form-group"><label>First Name <span class="req">*</span></label><input type="text" name="first_name" value="<?= e($vals['first_name']) ?>" required></div>
        <div class="form-group"><label>Last Name <span class="req">*</span></label><input type="text" name="last_name" value="<?= e($vals['last_name']) ?>" required></div>
        <div class="form-group"><label>Email Address <span class="req">*</span></label><input type="email" name="email" value="<?= e($vals['email']) ?>" required></div>
        <div class="form-group"><label>Phone Number <span class="req">*</span></label><input type="tel" name="phone" value="<?= e($vals['phone']) ?>" required></div>
      </div>
    </div>

    <!-- Step 2: Shipping Address -->
    <div class="checkout-section">
      <div class="step-head"><span class="step-badge">2</span><h3 style="margin:0;">Shipping Address</h3></div>
      <div class="form-grid">
        <div class="form-group full"><label>House / Flat / Building <span class="req">*</span></label><input type="text" name="house" value="<?= e($vals['house']) ?>" placeholder="e.g. Flat 4B, Sunrise Apartments" required></div>
        <div class="form-group full"><label>Street Address <span class="req">*</span></label><input type="text" name="address" value="<?= e($vals['address']) ?>" placeholder="e.g. 12 MG Road" required></div>
        <div class="form-group"><label>City <span class="req">*</span></label><input type="text" name="city" value="<?= e($vals['city']) ?>" required></div>
        <div class="form-group"><label>Pincode <span class="req">*</span></label><input type="text" name="pincode" value="<?= e($vals['pincode']) ?>" placeholder="e.g. 560001" pattern="[0-9]{4,10}" required></div>
        <div class="form-group"><label>Country</label><input type="text" name="country" value="India" readonly></div>
        <div class="form-group full"><label>Order Notes (optional)</label><textarea name="notes" rows="3"><?= e($vals['notes']) ?></textarea></div>
      </div>
    </div>

    <!-- Step 3: Payment -->
    <div class="checkout-section">
      <div class="step-head"><span class="step-badge">3</span><h3 style="margin:0;">Payment Method</h3></div>
      <div class="payment-options">
        <label class="payment-card"><input type="radio" name="payment_method" value="cod" <?= $vals['payment_method'] === 'cod' ? 'checked' : '' ?>><span class="emoji">💵</span><strong>Cash on Delivery</strong></label>
        <label class="payment-card"><input type="radio" name="payment_method" value="upi" <?= $vals['payment_method'] === 'upi' ? 'checked' : '' ?>><span class="emoji">📱</span><strong>UPI Payment</strong></label>
        <?php if ($razorpayEnabled): ?>
        <label class="payment-card razorpay-option">
          <input type="radio" name="payment_method" value="razorpay" <?= $vals['payment_method'] === 'razorpay' ? 'checked' : '' ?>>
          <span class="emoji">🟢</span>
          <strong>Pay with Razorpay</strong>
          <span style="font-size:11px;color:var(--text2);margin-left:4px;">Cards · UPI · Net Banking · Wallets</span>
        </label>
        <?php endif; ?>
        <label class="payment-card">
          <input type="radio" name="payment_method" value="card" <?= $vals['payment_method'] === 'card' ? 'checked' : '' ?> <?= $stripeEnabled ? '' : 'disabled' ?>>
          <span class="emoji">💳</span><strong>Credit/Debit Card (Stripe)</strong>
          <?= $stripeEnabled ? '' : '<span style="color:var(--text2);font-size:12px;margin-left:6px;">(configure Stripe keys in Admin → Settings)</span>' ?>
        </label>
      </div>
    </div>

  </form>

  <aside class="order-summary">
    <div class="step-head"><span class="step-badge">✓</span><h3 style="margin:0;">Order Review</h3></div>
    <div class="order-review-list">
      <?php foreach ($items as $item): ?>
        <div class="review-item">
          <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>">
          <div><strong><?= e($item['name']) ?></strong><span>Qty <?= (int)$item['qty'] ?></span></div>
          <span class="review-sub"><?= formatPrice($item['subtotal']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="summary-row"><span>Subtotal</span><span><?= formatPrice($subtotal) ?></span></div>
    <div class="summary-row"><span>Shipping</span><span><?= $shipping == 0 ? 'Free' : formatPrice($shipping) ?></span></div>
    <div class="summary-row total"><span>Total</span><span><?= formatPrice($total) ?></span></div>
    <button type="submit" form="checkoutForm" class="btn btn-primary btn-block btn-lg" style="margin-top:10px;">Place Order – <?= formatPrice($total) ?></button>
    <div class="security-badge">🔒 Your information is secure &amp; encrypted</div>
  </aside>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
