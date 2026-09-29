<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

$pageTitle = 'Shop';

// ── Read filters from GET ───────────────────────────────
$catSlug   = trim($_GET['cat'] ?? '');
$search    = trim($_GET['search'] ?? '');
$badge     = trim($_GET['badge'] ?? '');
$sort      = trim($_GET['sort'] ?? 'newest');
$priceMax  = isset($_GET['price_max']) ? (int)$_GET['price_max'] : 300000;
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 9;

// ── Categories (with counts, for sidebar) ───────────────
$categories = [];
$res = $conn->query("SELECT c.*, COUNT(p.id) AS item_count FROM categories c
                      LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.id");
while ($row = $res->fetch_assoc()) $categories[] = $row;

// ── Build dynamic WHERE clause ──────────────────────────
$where = ['p.price <= ?'];
$types = 'd';
$params = [$priceMax];

$activeCategoryName = null;
if ($catSlug !== '') {
    $where[] = 'c.slug = ?';
    $types .= 's';
    $params[] = $catSlug;
    foreach ($categories as $c) if ($c['slug'] === $catSlug) $activeCategoryName = $c['name'];
}
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $types .= 'ss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($badge !== '' && in_array($badge, ['NEW', 'HOT', 'SALE'])) {
    $where[] = 'p.badge = ?';
    $types .= 's';
    $params[] = $badge;
}
$whereSql = implode(' AND ', $where);

// ── Sort ─────────────────────────────────────────────────
$orderBy = match ($sort) {
    'popular'    => 'p.featured DESC, p.rating DESC',
    'top_rated'  => 'p.rating DESC',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    default      => 'p.created_at DESC, p.id DESC',
};

// ── Total count for pagination ──────────────────────────
$countSql = "SELECT COUNT(*) AS total FROM products p JOIN categories c ON c.id = p.category_id WHERE $whereSql";
$stmt = $conn->prepare($countSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totalRecords = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = max(1, (int) ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// ── Fetch products ───────────────────────────────────────
$sql = "SELECT p.*, c.name AS category_name FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE $whereSql ORDER BY $orderBy LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$typesLimit = $types . 'ii';
$paramsLimit = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($typesLimit, ...$paramsLimit);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Helper to preserve query params while overriding one ──
function shopUrl(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return BASE_PATH . '/pages/shop.php?' . http_build_query($params);
}

function productCard(array $p): string {
    $badge = $p['badge'] ? '<span class="product-badge badge-' . e($p['badge']) . '">' . e($p['badge']) . '</span>' : '';
    $oldPrice = $p['old_price'] ? '<span class="price-old">' . formatPrice($p['old_price']) . '</span>' : '';
    return '
    <div class="product-card">
      <a href="' . BASE_PATH . '/pages/product.php?slug=' . e($p['slug']) . '" class="product-media">
        ' . $badge . '
        <img src="' . e($p['image_url']) . '" alt="' . e($p['name']) . '" loading="lazy">
      </a>
      <div class="product-body">
        <div class="product-cat">' . e($p['category_name']) . '</div>
        <a href="' . BASE_PATH . '/pages/product.php?slug=' . e($p['slug']) . '"><h4 class="product-name">' . e($p['name']) . '</h4></a>
        <div class="product-rating">' . renderStars((float)$p['rating']) . ' <span>' . number_format((float)$p['rating'], 1) . '</span></div>
        <div class="product-price"><span class="price-now">' . formatPrice($p['price']) . '</span>' . $oldPrice . '</div>
        <button class="add-cart-btn" data-product-id="' . (int)$p['id'] . '">Add to Cart</button>
      </div>
    </div>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
  <div class="breadcrumb">
    <a href="<?= BASE_PATH ?>/index.php">Home</a><span class="sep">›</span><span class="current">Shop</span>
  </div>
</div>

<div class="container shop-layout">
  <aside class="filters-sidebar">
    <form class="filters-form" method="GET" action="<?= BASE_PATH ?>/pages/shop.php">
      <?php if ($search): ?><input type="hidden" name="search" value="<?= e($search) ?>"><?php endif; ?>
      <?php if ($badge): ?><input type="hidden" name="badge" value="<?= e($badge) ?>"><?php endif; ?>

      <h4>Category</h4>
      <div class="filter-chip-group">
        <a href="<?= shopUrl(['cat' => null, 'page' => null]) ?>" class="filter-chip <?= $catSlug === '' ? 'active' : '' ?>">All</a>
        <?php foreach ($categories as $c): ?>
          <a href="<?= shopUrl(['cat' => $c['slug'], 'page' => null]) ?>" class="filter-chip <?= $catSlug === $c['slug'] ? 'active' : '' ?>"><?= $c['icon'] ?> <?= e($c['name']) ?></a>
        <?php endforeach; ?>
      </div>

      <h4>Price Range</h4>
      <div class="price-range">
        <input type="range" id="price_max" name="price_max" min="0" max="300000" step="500" value="<?= (int)$priceMax ?>">
        <div class="price-range-labels">
          <span>₹0</span>
          <span id="price_max_label">₹<?= number_format($priceMax) ?></span>
        </div>
      </div>

      <h4>Sort By</h4>
      <select name="sort" class="sort-select" data-autosubmit style="width:100%;">
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
        <option value="top_rated" <?= $sort === 'top_rated' ? 'selected' : '' ?>>Top Rated</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price Low–High</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price High–Low</option>
      </select>

      <div class="filter-actions">
        <button type="submit" class="btn btn-primary btn-sm" style="flex:1;">Apply Filters</button>
        <a href="<?= BASE_PATH ?>/pages/shop.php" class="btn btn-outline btn-sm" style="flex:1;text-align:center;">Clear All</a>
      </div>
    </form>
  </aside>

  <main>
    <div class="shop-header">
      <div class="shop-results-count">
        Showing <strong><?= $totalRecords ? ($offset + 1) : 0 ?>-<?= min($offset + $perPage, $totalRecords) ?></strong> of <strong><?= $totalRecords ?></strong> results
        <?= $activeCategoryName ? ' in ' . e($activeCategoryName) : '' ?>
        <?= $search ? ' for "' . e($search) . '"' : '' ?>
      </div>
    </div>

    <?php if (empty($products)): ?>
      <div class="empty-state">
        <div class="icon">🔍</div>
        <h3>No products found</h3>
        <p>Try adjusting your filters or search terms.</p>
        <a href="<?= BASE_PATH ?>/pages/shop.php" class="btn btn-primary">Clear Filters</a>
      </div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $p) echo productCard($p); ?>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <a href="<?= shopUrl(['page' => max(1, $page - 1)]) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="<?= shopUrl(['page' => $i]) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= shopUrl(['page' => min($totalPages, $page + 1)]) ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">›</a>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
