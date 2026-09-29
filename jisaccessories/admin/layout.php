<?php
// Admin layout — handles auth guard, renders sidebar and topbar.
// Each admin page sets $pageTitle, $activeNav, and optionally $extraHead before requiring this.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

requireAdmin();

$admin = getCurrentUser();
$pendingOrders = (int) $conn->query("SELECT COUNT(*) c FROM orders WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Admin') ?> · JIS Admin</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_PATH ?>/admin/admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?>">
<?= $extraHead ?? '' ?>
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-sidebar" id="adminSidebar">
    <a href="<?= BASE_PATH ?>/index.php" class="admin-logo">JIS<span>accessories</span></a>
    <nav class="admin-nav">
      <a href="<?= BASE_PATH ?>/admin/index.php" class="<?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
      <a href="<?= BASE_PATH ?>/admin/products.php" class="<?= ($activeNav ?? '') === 'products' ? 'active' : '' ?>">💍 Products</a>
      <a href="<?= BASE_PATH ?>/admin/orders.php" class="<?= ($activeNav ?? '') === 'orders' ? 'active' : '' ?>">
        📦 Orders
        <?php if ($pendingOrders > 0): ?><span class="nav-badge"><?= $pendingOrders ?></span><?php endif; ?>
      </a>
      <?php if (isSuperAdmin()): ?>
      <a href="<?= BASE_PATH ?>/admin/users.php" class="<?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>">👥 Users</a>
      <?php endif; ?>
      <a href="<?= BASE_PATH ?>/admin/settings.php" class="<?= ($activeNav ?? '') === 'settings' ? 'active' : '' ?>">⚙️ Settings</a>
      <a href="<?= BASE_PATH ?>/index.php" target="_blank">🛍 View Store</a>
    </nav>
    <div class="admin-sidebar-foot">
      <div class="admin-user">
        <div class="avatar-circle"><?= e(initials($admin['first_name'], $admin['last_name'])) ?></div>
        <div><strong><?= e($admin['first_name'] . ' ' . $admin['last_name']) ?></strong><span><?= e($admin['role']) ?></span></div>
      </div>
      <a href="<?= BASE_PATH ?>/pages/logout.php" class="admin-logout">🚪 Logout</a>
    </div>
  </aside>

  <main class="admin-main">
    <header class="admin-topbar">
      <button class="admin-mobile-toggle" onclick="document.getElementById('adminSidebar').classList.toggle('open')" aria-label="Toggle menu">☰</button>
      <h1><?= e($pageTitle ?? '') ?></h1>
      <?php if ($pendingOrders > 0): ?>
        <a href="<?= BASE_PATH ?>/admin/orders.php?status=pending" class="topbar-bell" title="<?= $pendingOrders ?> pending orders">🔔 <span class="bell-badge"><?= $pendingOrders ?></span></a>
      <?php endif; ?>
      <a href="<?= BASE_PATH ?>/index.php" target="_blank" class="btn-view-store">View Store ↗</a>
    </header>
    <div class="admin-content">
