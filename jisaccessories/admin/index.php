<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

requireAdmin();
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

$totalRevenue = (float) ($conn->query("SELECT SUM(total_amount) t FROM orders WHERE status != 'cancelled'")->fetch_assoc()['t'] ?? 0);
$totalOrders = (int) $conn->query('SELECT COUNT(*) c FROM orders')->fetch_assoc()['c'];
$totalProducts = (int) $conn->query('SELECT COUNT(*) c FROM products')->fetch_assoc()['c'];
$totalUsers = (int) $conn->query('SELECT COUNT(*) c FROM users')->fetch_assoc()['c'];
$pendingOrders = (int) $conn->query("SELECT COUNT(*) c FROM orders WHERE status = 'pending'")->fetch_assoc()['c'];
$lowStock = (int) $conn->query('SELECT COUNT(*) c FROM products WHERE stock < 10')->fetch_assoc()['c'];

$recentOrders = $conn->query("SELECT o.*, u.first_name, u.last_name FROM orders o
                               LEFT JOIN users u ON u.id = o.user_id
                               ORDER BY o.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/layout.php';
?>

<div class="stat-grid">
  <div class="stat-card"><div class="label"><span>Total Revenue</span> 💰</div><strong><?= formatPrice($totalRevenue) ?></strong></div>
  <div class="stat-card"><div class="label"><span>Total Orders</span> 📦</div><strong><?= $totalOrders ?></strong></div>
  <div class="stat-card"><div class="label"><span>Products</span> 💍</div><strong><?= $totalProducts ?></strong></div>
  <div class="stat-card"><div class="label"><span>Registered Users</span> 👥</div><strong><?= $totalUsers ?></strong></div>
</div>

<div class="stat-grid" style="grid-template-columns:repeat(2,1fr);">
  <div class="stat-card"><div class="label"><span>Pending Orders</span> ⏳</div><strong><?= $pendingOrders ?></strong></div>
  <div class="stat-card"><div class="label"><span>Low Stock (&lt;10)</span> ⚠️</div><strong><?= $lowStock ?></strong></div>
</div>

<div class="panel">
  <div class="panel-head">
    <h3>Recent Orders</h3>
    <a href="<?= BASE_PATH ?>/admin/orders.php" class="btn btn-outline btn-sm">View All →</a>
  </div>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>Order #</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th><th>Payment</th></tr></thead>
      <tbody>
        <?php if (empty($recentOrders)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--text2);padding:30px;">No orders yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><strong><?= e($o['order_number']) ?></strong></td>
          <td><?= e(trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: 'Guest') ?></td>
          <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
          <td><?= formatPrice((float)$o['total_amount']) ?></td>
          <td><span class="status-badge status-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
          <td><?= e($o['payment_method']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
