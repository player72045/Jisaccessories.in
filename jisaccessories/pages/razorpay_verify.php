<?php
/**
 * pages/razorpay_verify.php
 * Called by the Razorpay checkout.js success handler (POST form).
 * Verifies HMAC-SHA256 signature, marks order paid, clears cart.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$rzpPaymentId  = trim($_POST['razorpay_payment_id'] ?? '');
$rzpOrderId    = trim($_POST['razorpay_order_id'] ?? '');
$rzpSignature  = trim($_POST['razorpay_signature'] ?? '');

$orderId    = (int) ($_SESSION['rzp_order_id'] ?? 0);
$orderNum   = $_SESSION['rzp_order_num'] ?? '';

if (!$orderId || !$rzpPaymentId || !$rzpOrderId || !$rzpSignature) {
    $_SESSION['checkout_error'] = 'Payment verification failed — missing data. Please contact support.';
    header('Location: ' . BASE_PATH . '/pages/checkout.php');
    exit;
}

$keySecret = getSetting('razorpay_key_secret', '');

// Razorpay signature verification
$expectedSig = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, $keySecret);

if (!hash_equals($expectedSig, $rzpSignature)) {
    // Signature mismatch — possible tampering
    $_SESSION['checkout_error'] = 'Payment signature verification failed. If money was deducted, please contact us with your order number: ' . $orderNum;
    header('Location: ' . BASE_PATH . '/pages/checkout.php');
    exit;
}

// Signature valid — update order
$stmt = $conn->prepare("UPDATE orders SET payment_status='paid', razorpay_payment_id=?, razorpay_order_id=? WHERE id=?");
$stmt->bind_param('ssi', $rzpPaymentId, $rzpOrderId, $orderId);
$stmt->execute();
$stmt->close();

// Clear cart and session vars
unset($_SESSION['cart'], $_SESSION['rzp_order_id'], $_SESSION['rzp_order_num']);
$_SESSION['last_order_number'] = $orderNum;

header('Location: ' . BASE_PATH . '/pages/order_success.php?order=' . urlencode($orderNum));
exit;
