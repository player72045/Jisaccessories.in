<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

requireAdmin();
$pageTitle = 'Orders';
$activeNav = 'orders';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'update_status') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'], true)) {
        $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt->close();
        $successMsg = 'Order status updated.';
    }
}

$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where = ['1=1'];
$types = '';
$params = [];
if ($statusFilter !== '') { $where[] = 'o.status = ?'; $types .= 's'; $params[] = $statusFilter; }
if ($search !== '') { $where[] = '(o.order_number LIKE ? OR o.contact_name LIKE ? OR o.contact_email LIKE ?)'; $types .= 'sss'; $like = '%' . $search . '%'; $params[] = $like; $params[] = $like; $params[] = $like; }
$whereSql = implode(' AND ', $where);

$stmt = $conn->prepare("SELECT COUNT(*) t FROM orders o WHERE $whereSql");
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalCount = (int) $stmt->get_result()->fetch_assoc()['t'];
$stmt->close();
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) item_count
        FROM orders o WHERE $whereSql ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$typesLimit = $types . 'ii';
$paramsLimit = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($typesLimit, ...$paramsLimit);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch order items for all listed orders (for the details modal)
$orderItems = [];
if (!empty($orders)) {
    $oids = implode(',', array_map(fn($o) => (int)$o['id'], $orders));
    $iRes = $conn->query("SELECT oi.*, p.name AS product_name, p.image_url FROM order_items oi
                          JOIN products p ON p.id = oi.product_id
                          WHERE oi.order_id IN ($oids)");
    while ($row = $iRes->fetch_assoc()) {
        $orderItems[$row['order_id']][] = $row;
    }
}

require_once __DIR__ . '/layout.php';
?>

<?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <h3>All Orders (<?= $totalCount ?>)</h3>
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
      <input type="text" name="search" placeholder="Search order #, name, email…" value="<?= e($search) ?>" style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text);font-size:13px;min-width:220px;">
      <select name="status" onchange="this.form.submit()" style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text);font-size:13px;">
        <option value="">All Statuses</option>
        <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline btn-sm">Filter</button>
    </form>
  </div>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>Order #</th><th>Customer</th><th>Phone</th><th>Items</th><th>Total</th><th>Payment</th><th>Date</th><th>Status</th><th>Details</th></tr></thead>
      <tbody>
        <?php if (empty($orders)): ?><tr><td colspan="9" style="text-align:center;color:var(--text2);padding:30px;">No orders found.</td></tr><?php endif; ?>
        <?php foreach ($orders as $o):
            $items = $orderItems[$o['id']] ?? [];
            $orderJson = json_encode([
                'id'             => $o['id'],
                'order_number'   => $o['order_number'],
                'created_at'     => date('d M Y, h:i A', strtotime($o['created_at'])),
                'contact_name'   => $o['contact_name'],
                'contact_email'  => $o['contact_email'],
                'contact_phone'  => $o['contact_phone'],
                'house'          => $o['house'] ?? '',
                'shipping_address'=> $o['shipping_address'],
                'city'           => $o['city'] ?? '',
                'pincode'        => $o['pincode'] ?? '',
                'country'        => $o['country'] ?? 'India',
                'notes'          => $o['notes'],
                'payment_method' => $o['payment_method'],
                'payment_status' => $o['payment_status'],
                'total_amount'   => $o['total_amount'],
                'status'         => $o['status'],
                'items'          => $items,
            ], JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
        <tr>
          <td><strong><?= e($o['order_number']) ?></strong></td>
          <td><?= e($o['contact_name']) ?><br><span style="color:var(--text2);font-size:12px;"><?= e($o['contact_email']) ?></span></td>
          <td><?= e($o['contact_phone'] ?? '—') ?></td>
          <td><?= (int)$o['item_count'] ?></td>
          <td><?= formatPrice((float)$o['total_amount']) ?></td>
          <td>
            <?= e($o['payment_method']) ?>
            <br><span class="status-badge <?= $o['payment_status'] === 'paid' ? 'status-delivered' : 'status-pending' ?>" style="font-size:10px;"><?= e($o['payment_status']) ?></span>
          </td>
          <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
          <td>
            <form method="POST">
              <input type="hidden" name="form" value="update_status">
              <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <select name="status" class="status-select" data-autosubmit>
                <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td>
            <button class="icon-btn" title="View Details" onclick='showOrderDetails(<?= $orderJson ?>)'>👁️</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Order Details Modal -->
<div class="modal-overlay" id="orderDetailModal">
  <div class="modal-box" style="max-width:720px;max-height:90vh;overflow-y:auto;">
    <div class="modal-head">
      <h3 id="odTitle">Order Details</h3>
      <button class="modal-close" onclick="document.getElementById('orderDetailModal').classList.remove('open')">&times;</button>
    </div>
    <div id="odBody"></div>
  </div>
</div>

<style>
.od-section { margin-bottom: 20px; }
.od-section h4 { font-size: 13px; font-weight: 700; color: var(--text2); text-transform: uppercase; letter-spacing: .05em; margin: 0 0 10px; padding-bottom: 6px; border-bottom: 1px solid var(--border); }
.od-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 20px; font-size: 13px; }
.od-grid .od-label { color: var(--text2); font-weight: 600; }
.od-grid .od-val { color: var(--text); }
.od-full { grid-column: 1/-1; }
.od-items-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.od-items-table th { text-align: left; padding: 6px 8px; color: var(--text2); font-weight: 600; border-bottom: 1px solid var(--border); }
.od-items-table td { padding: 8px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.od-items-table img { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); }
.od-badge { display:inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.od-badge.paid   { background: #22c55e22; color: #16a34a; }
.od-badge.unpaid { background: #f59e0b22; color: #d97706; }
.od-badge.refunded { background: #6366f122; color: #4f46e5; }
.od-total-row { font-weight: 700; background: color-mix(in srgb, var(--accent) 8%, transparent); }
</style>

<script>
function showOrderDetails(o) {
  document.getElementById('odTitle').textContent = 'Order #' + o.order_number;

  const payBadge = `<span class="od-badge ${o.payment_status}">${o.payment_status.toUpperCase()}</span>`;

  // Build items table
  let itemsHtml = '<table class="od-items-table"><thead><tr><th>Image</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr></thead><tbody>';
  let grandTotal = 0;
  (o.items || []).forEach(item => {
    const sub = (parseFloat(item.price) * parseInt(item.quantity)).toFixed(2);
    grandTotal += parseFloat(sub);
    itemsHtml += `<tr>
      <td><img src="${escHtml(item.image_url)}" alt=""></td>
      <td>${escHtml(item.product_name)}</td>
      <td>${item.quantity}</td>
      <td>₹${parseFloat(item.price).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
      <td>₹${parseFloat(sub).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
    </tr>`;
  });
  itemsHtml += `<tr class="od-total-row"><td colspan="4" style="text-align:right;padding:8px;">Order Total</td><td>₹${parseFloat(o.total_amount).toLocaleString('en-IN', {minimumFractionDigits:2})}</td></tr>`;
  itemsHtml += '</tbody></table>';

  const fullAddress = [
    o.house ? `<strong>House/Flat:</strong> ${escHtml(o.house)}` : '',
    o.shipping_address ? `<strong>Street:</strong> ${escHtml(o.shipping_address)}` : '',
    o.city ? `<strong>City:</strong> ${escHtml(o.city)}` : '',
    o.pincode ? `<strong>Pincode:</strong> ${escHtml(o.pincode)}` : '',
    `<strong>Country:</strong> ${escHtml(o.country || 'India')}`,
  ].filter(Boolean).join('<br>');

  document.getElementById('odBody').innerHTML = `
    <!-- Order Info -->
    <div class="od-section">
      <h4>📋 Order Information</h4>
      <div class="od-grid">
        <span class="od-label">Order ID</span><span class="od-val">#${o.id}</span>
        <span class="od-label">Order Number</span><span class="od-val">${escHtml(o.order_number)}</span>
        <span class="od-label">Date</span><span class="od-val">${escHtml(o.created_at)}</span>
        <span class="od-label">Status</span><span class="od-val">${escHtml(o.status.charAt(0).toUpperCase() + o.status.slice(1))}</span>
        <span class="od-label">Payment Method</span><span class="od-val">${escHtml(o.payment_method)}</span>
        <span class="od-label">Payment Status</span><span class="od-val">${payBadge}</span>
      </div>
    </div>

    <!-- Customer Info -->
    <div class="od-section">
      <h4>👤 Customer Details</h4>
      <div class="od-grid">
        <span class="od-label">Full Name</span><span class="od-val">${escHtml(o.contact_name || '—')}</span>
        <span class="od-label">Email</span><span class="od-val"><a href="mailto:${escHtml(o.contact_email)}" style="color:var(--accent);">${escHtml(o.contact_email || '—')}</a></span>
        <span class="od-label">Phone</span><span class="od-val"><a href="tel:${escHtml(o.contact_phone)}" style="color:var(--accent);">${escHtml(o.contact_phone || '—')}</a></span>
      </div>
    </div>

    <!-- Shipping Address -->
    <div class="od-section">
      <h4>🚚 Shipping Address</h4>
      <div style="font-size:13px;line-height:1.8;color:var(--text);">${fullAddress || '—'}</div>
    </div>

    <!-- Order Notes -->
    ${o.notes ? `<div class="od-section"><h4>📝 Order Note</h4><p style="font-size:13px;color:var(--text);margin:0;padding:10px;background:var(--bg);border-radius:8px;border:1px solid var(--border);">${escHtml(o.notes)}</p></div>` : ''}

    <!-- Items -->
    <div class="od-section">
      <h4>📦 Order Items</h4>
      ${itemsHtml}
    </div>
  `;

  document.getElementById('orderDetailModal').classList.add('open');
}

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
