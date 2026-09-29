<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

requireAdmin();
$pageTitle = 'Settings';
$activeNav = 'settings';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currency = $_POST['active_currency'] ?? 'INR';
    $pubKey = trim($_POST['stripe_publishable_key'] ?? '');
    $secKey = trim($_POST['stripe_secret_key'] ?? '');
    $webhookSecret = trim($_POST['stripe_webhook_secret'] ?? '');
    $rzpKeyId = trim($_POST['razorpay_key_id'] ?? '');
    $rzpKeySecret = trim($_POST['razorpay_key_secret'] ?? '');

    if (isset($GLOBALS['CURRENCIES'][$currency])) {
        setSetting('active_currency', $currency);
    }
    setSetting('stripe_publishable_key', $pubKey);
    setSetting('stripe_secret_key', $secKey);
    setSetting('stripe_webhook_secret', $webhookSecret);
    setSetting('razorpay_key_id', $rzpKeyId);
    setSetting('razorpay_key_secret', $rzpKeySecret);
    invalidateCurrencyCache();

    $successMsg = 'Settings saved successfully.';
}

$activeCurrencyCode = getSetting('active_currency', 'INR');
$stripePub = getSetting('stripe_publishable_key', '');
$stripeSec = getSetting('stripe_secret_key', '');
$stripeWebhook = getSetting('stripe_webhook_secret', '');
$rzpKeyId = getSetting('razorpay_key_id', '');
$rzpKeySecret = getSetting('razorpay_key_secret', '');

require_once __DIR__ . '/layout.php';
?>

<?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>

<form method="POST">
  <div class="panel">
    <div class="panel-head"><h3>💱 Currency</h3></div>
    <p style="color:var(--text2);font-size:13px;margin-bottom:16px;">Prices are always stored in INR and converted on the fly for display.</p>
    <div class="currency-grid">
      <?php foreach ($GLOBALS['CURRENCIES'] as $code => $c): ?>
        <label class="currency-option">
          <input type="radio" name="active_currency" value="<?= $code ?>" <?= $activeCurrencyCode === $code ? 'checked' : '' ?>>
          <div class="sym"><?= $c['symbol'] ?></div>
          <div class="code"><?= $code ?></div>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h3>💳 Stripe Payment Keys</h3></div>
    <p style="color:var(--text2);font-size:13px;margin-bottom:16px;">Get test keys from <strong>dashboard.stripe.com → Developers → API keys</strong>. Card payment at checkout only appears once both keys are set.</p>
    <div class="form-group"><label>Publishable Key</label><input type="text" name="stripe_publishable_key" value="<?= e($stripePub) ?>" placeholder="pk_test_…"></div>
    <div class="form-group"><label>Secret Key</label><input type="password" name="stripe_secret_key" value="<?= e($stripeSec) ?>" placeholder="sk_test_…"></div>
    <div class="form-group"><label>Webhook Secret (optional)</label><input type="text" name="stripe_webhook_secret" value="<?= e($stripeWebhook) ?>" placeholder="whsec_…"></div>
  </div>

  <div class="panel">
    <div class="panel-head"><h3>🟢 Razorpay Payment Keys</h3></div>
    <p style="color:var(--text2);font-size:13px;margin-bottom:16px;">
      Get your API keys from <strong>dashboard.razorpay.com → Settings → API Keys</strong>.<br>
      Use <code>rzp_test_…</code> keys for testing and <code>rzp_live_…</code> for production.<br>
      Razorpay payment option will appear at checkout once Key ID and Key Secret are set.
    </p>
    <div class="form-group">
      <label>Key ID</label>
      <input type="text" name="razorpay_key_id" value="<?= e($rzpKeyId) ?>" placeholder="rzp_test_…">
    </div>
    <div class="form-group">
      <label>Key Secret</label>
      <input type="password" name="razorpay_key_secret" value="<?= e($rzpKeySecret) ?>" placeholder="Your Razorpay Key Secret">
      <small style="color:var(--text2);font-size:12px;margin-top:4px;display:block;">⚠ This secret is used server-side to verify payment signatures. Never share it publicly.</small>
    </div>
    <?php
    $rzpStatus = (!empty($rzpKeyId) && !empty($rzpKeySecret))
        ? '<span style="color:#22c55e;font-weight:600;">✓ Razorpay is configured</span>'
        : '<span style="color:var(--text2);">✗ Not configured — enter both keys above</span>';
    ?>
    <div style="padding:10px 0;font-size:13px;"><?= $rzpStatus ?></div>
  </div>

  <button type="submit" class="btn btn-primary">Save All Settings</button>
</form>

<?php require_once __DIR__ . '/footer.php'; ?>
