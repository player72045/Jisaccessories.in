<?php
/**
 * includes/functions.php
 * Auth, cart, and general helper functions.
 * Requires includes/db.php to already be loaded ($conn available).
 */

// ── Auth ─────────────────────────────────────────────────

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_PATH . '/pages/login.php');
        exit;
    }
}

function isAdmin(): bool {
    return isLoggedIn() && in_array($_SESSION['user_role'] ?? '', ['admin', 'super_admin']);
}

function isSuperAdmin(): bool {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'super_admin';
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: ' . BASE_PATH . '/pages/login.php');
        exit;
    }
}

function getCurrentUser(): ?array {
    global $conn;
    if (!isLoggedIn()) return null;
    $stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

// ── Sanitization / formatting ───────────────────────────

function sanitize($data) {
    global $conn;
    $data = trim($data ?? '');
    $data = stripslashes($data);
    return $conn->real_escape_string($data);
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function generateOrderNumber(): string {
    return 'JA-' . strtoupper(substr(uniqid(), -8));
}

function initials(string $first, string $last): string {
    $a = mb_substr(trim($first), 0, 1);
    $b = mb_substr(trim($last), 0, 1);
    return mb_strtoupper($a . $b);
}

function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    return $text ?: 'item';
}

// ── Cart (session-based: $_SESSION['cart'] = [product_id => qty]) ─

function getCart(): array {
    return $_SESSION['cart'] ?? [];
}

function addToCart(int $productId, int $qty = 1): void {
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $qty = max(1, min(99, $qty));
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] = min(99, $_SESSION['cart'][$productId] + $qty);
    } else {
        $_SESSION['cart'][$productId] = $qty;
    }
}

function updateCartQty(int $productId, int $qty): void {
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $qty = max(1, min(99, $qty));
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] = $qty;
    }
}

function removeFromCart(int $productId): void {
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }
}

function getCartCount(): int {
    $cart = getCart();
    return array_sum($cart);
}

function getCartItemsDetailed(): array {
    global $conn;
    $cart = getCart();
    if (empty($cart)) return [];
    $ids = array_map('intval', array_keys($cart));
    $in = implode(',', $ids);
    $res = $conn->query("SELECT p.*, c.name AS category_name FROM products p
                          LEFT JOIN categories c ON c.id = p.category_id
                          WHERE p.id IN ($in)");
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $row['qty'] = $cart[$row['id']];
        $row['subtotal'] = $row['price'] * $row['qty'];
        $items[] = $row;
    }
    return $items;
}

function getCartTotal(): float {
    $items = getCartItemsDetailed();
    $total = 0;
    foreach ($items as $item) $total += $item['subtotal'];
    return $total;
}

function getShippingCost(float $subtotalInr): float {
    return $subtotalInr >= 3000 ? 0 : 150;
}

// ── Settings ─────────────────────────────────────────────

function getSetting(string $key, string $default = ''): string {
    global $conn;
    $stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row['setting_value'] ?? $default;
}

function setSetting(string $key, string $value): void {
    global $conn;
    $stmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}

// ── Star rating render helper ───────────────────────────

function renderStars(float $rating): string {
    $full = floor($rating);
    $half = ($rating - $full) >= 0.5;
    $html = '';
    for ($i = 0; $i < $full; $i++) $html .= '★';
    if ($half) $html .= '⯪';
    for ($i = $full + ($half ? 1 : 0); $i < 5; $i++) $html .= '☆';
    return $html;
}
