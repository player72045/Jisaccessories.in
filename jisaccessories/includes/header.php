<?php
// Expects $conn, functions.php and currency.php already loaded, and optional $pageTitle
$cartCount = getCartCount();
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$searchQ = $_GET['search'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' · ' : '' ?>JIS Accessories</title>
<meta name="description" content="Handpicked jewellery & accessories — necklaces, mangalsutra, rings, wristwear and more.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a href="<?= BASE_PATH ?>/index.php" class="logo">JIS<span>accessories</span></a>

    <form class="header-search" action="<?= BASE_PATH ?>/pages/shop.php" method="GET">
      <input type="text" name="search" placeholder="Search necklaces, rings, bangles…" value="<?= e($searchQ) ?>">
      <button type="submit" aria-label="Search">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </button>
    </form>

    <nav class="header-nav">
      <a href="<?= BASE_PATH ?>/pages/shop.php">Shop</a>
      <a href="<?= BASE_PATH ?>/pages/shop.php?badge=SALE">Deals</a>
      <?php if ($currentUser): ?>
        <a href="<?= BASE_PATH ?>/pages/myaccount.php" class="nav-icon" aria-label="My Account">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
        <?php if (isAdmin()): ?>
        <a href="<?= BASE_PATH ?>/admin/index.php" class="nav-icon" aria-label="Admin Panel" title="Admin Panel">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
        </a>
        <?php endif; ?>
      <?php else: ?>
        <a href="<?= BASE_PATH ?>/pages/login.php" class="nav-icon" aria-label="Login">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
      <?php endif; ?>
      <a href="<?= BASE_PATH ?>/pages/cart.php" class="nav-icon cart-link" aria-label="Cart">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        <span class="cart-badge" style="<?= $cartCount === 0 ? 'display:none' : '' ?>"><?= $cartCount ?></span>
      </a>
    </nav>

    <button class="mobile-toggle" aria-label="Menu" onclick="document.querySelector('.header-nav').classList.toggle('open')">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
  </div>
</header>
