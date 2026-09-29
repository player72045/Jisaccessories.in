<?php
/**
 * includes/currency.php
 * Multi-currency support. Prices are stored in INR in the database and
 * converted on the fly for display and for Stripe.
 * Requires includes/db.php to already be loaded ($conn available).
 */

$GLOBALS['CURRENCIES'] = [
    'INR' => ['symbol' => '₹',  'name' => 'Indian Rupee',      'rate' => 1.00,   'stripe' => 'inr'],
    'USD' => ['symbol' => '$',  'name' => 'US Dollar',         'rate' => 0.0091, 'stripe' => 'usd'],
    'EUR' => ['symbol' => '€',  'name' => 'Euro',              'rate' => 0.0084, 'stripe' => 'eur'],
    'GBP' => ['symbol' => '£',  'name' => 'British Pound',     'rate' => 0.0072, 'stripe' => 'gbp'],
    'CAD' => ['symbol' => 'C$', 'name' => 'Canadian Dollar',   'rate' => 0.0124, 'stripe' => 'cad'],
    'AUD' => ['symbol' => 'A$', 'name' => 'Australian Dollar', 'rate' => 0.0140, 'stripe' => 'aud'],
    'SGD' => ['symbol' => 'S$', 'name' => 'Singapore Dollar',  'rate' => 0.0122, 'stripe' => 'sgd'],
    'SAR' => ['symbol' => '﷼',  'name' => 'Saudi Riyal',       'rate' => 0.034,  'stripe' => 'sar'],
    'AED' => ['symbol' => 'إ.د','name' => 'UAE Dirham',        'rate' => 0.033,  'stripe' => 'aed'],
    'JPY' => ['symbol' => '¥',  'name' => 'Japanese Yen',      'rate' => 1.39,   'stripe' => 'jpy'],
    'MYR' => ['symbol' => 'RM', 'name' => 'Malaysian Ringgit', 'rate' => 0.042,  'stripe' => 'myr'],
];

function getActiveCurrency(): array {
    global $conn;
    if (isset($_SESSION['active_currency_code']) && isset($GLOBALS['CURRENCIES'][$_SESSION['active_currency_code']])) {
        $code = $_SESSION['active_currency_code'];
    } else {
        $code = 'INR';
        $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key='active_currency' LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            if (isset($GLOBALS['CURRENCIES'][$row['setting_value']])) {
                $code = $row['setting_value'];
            }
        }
        $_SESSION['active_currency_code'] = $code;
    }
    return array_merge(['code' => $code], $GLOBALS['CURRENCIES'][$code]);
}

function invalidateCurrencyCache(): void {
    unset($_SESSION['active_currency_code']);
}

function convertAmount(float $inr): float {
    $cur = getActiveCurrency();
    return $inr * $cur['rate'];
}

function formatPrice(float $inr): string {
    $cur = getActiveCurrency();
    $amount = $inr * $cur['rate'];
    // Zero-decimal-style currencies read cleaner without decimals
    $decimals = in_array($cur['code'], ['JPY']) ? 0 : 2;
    $formatted = number_format($amount, $decimals);
    return $cur['symbol'] . $formatted;
}

function getStripeAmount(float $inr): int {
    $cur = getActiveCurrency();
    $amount = $inr * $cur['rate'];
    // Stripe wants the smallest currency unit; JPY has no decimal subunit
    if ($cur['code'] === 'JPY') return (int) round($amount);
    return (int) round($amount * 100);
}

function getStripeCurrencyCode(): string {
    $cur = getActiveCurrency();
    return $cur['stripe'];
}
