<?php if (empty($orders)): ?>
  <div class="empty-state" style="padding:50px 20px;">
    <div class="icon">📦</div>
    <h3>No orders yet</h3>
    <p>When you place an order, it will show up here.</p>
    <a href="<?= BASE_PATH ?>/pages/shop.php" class="btn btn-primary">Start Shopping</a>
  </div>
<?php else: ?>
<div class="table-scroll">
  <table class="data-table">
    <thead>
      <tr>
        <th>Order #</th><th>Date</th>
        <?php if ($tab === 'orders'): ?><th>Items</th><?php endif; ?>
        <th>Total</th><th>Status</th><th>Payment</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><strong><?= e($o['order_number']) ?></strong></td>
        <td><?= $tab === 'orders' ? date('d M Y, H:i', strtotime($o['created_at'])) : date('d M Y', strtotime($o['created_at'])) ?></td>
        <?php if ($tab === 'orders'): ?><td><?= (int)$o['item_count'] ?></td><?php endif; ?>
        <td><?= formatPrice((float)$o['total_amount']) ?></td>
        <td><span class="status-badge status-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
        <td><?= e($o['payment_method']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
