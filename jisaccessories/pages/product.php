<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

$slug = trim($_GET['slug'] ?? '');
$stmt = $conn->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p
                         JOIN categories c ON c.id = p.category_id WHERE p.slug = ? LIMIT 1");
$stmt->bind_param('s', $slug);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product Not Found';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="container"><div class="empty-state"><div class="icon">😕</div><h3>Product not found</h3><p>This item may have been removed or is no longer available.</p><a href="' . BASE_PATH . '/pages/shop.php" class="btn btn-primary">Back to Shop</a></div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = $product['name'];

// Fetch gallery images
$stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
$stmt->bind_param('i', $product['id']);
$stmt->execute();
$galleryImages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// If no gallery images, fall back to the primary image_url
if (empty($galleryImages) && $product['image_url'] !== '') {
    $galleryImages = [['id' => 0, 'image_url' => $product['image_url'], 'sort_order' => 0]];
}

// Primary image is the product's image_url or the first gallery image
$primaryImage = $product['image_url'] ?: ($galleryImages[0]['image_url'] ?? '');

// Related products from same category
$stmt = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p
                         JOIN categories c ON c.id = p.category_id
                         WHERE p.category_id = ? AND p.id != ? LIMIT 4");
$stmt->bind_param('ii', $product['category_id'], $product['id']);
$stmt->execute();
$related = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
  <div class="breadcrumb">
    <a href="<?= BASE_PATH ?>/index.php">Home</a><span class="sep">›</span>
    <a href="<?= BASE_PATH ?>/pages/shop.php?cat=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a><span class="sep">›</span>
    <span class="current"><?= e($product['name']) ?></span>
  </div>
</div>

<div class="container product-detail">
  <!-- Gallery -->
  <div class="pd-gallery">
    <!-- Main image -->
    <div class="pd-main-img-wrap">
      <?php if ($product['badge']): ?>
        <span class="product-badge badge-<?= e($product['badge']) ?>" style="position:absolute;top:12px;left:12px;z-index:2;"><?= e($product['badge']) ?></span>
      <?php endif; ?>
      <img id="pdMainImg" src="<?= e($primaryImage) ?>" alt="<?= e($product['name']) ?>">
    </div>

    <!-- Thumbnail strip (only if >1 image) -->
    <?php if (count($galleryImages) > 1): ?>
    <div class="pd-thumbnails">
      <?php foreach ($galleryImages as $idx => $img): ?>
        <button type="button"
          class="pd-thumb-btn <?= $img['image_url'] === $primaryImage && $idx === 0 ? 'active' : '' ?>"
          onclick="switchImage(this, '<?= e($img['image_url']) ?>')"
          aria-label="View image <?= $idx + 1 ?>">
          <img src="<?= e($img['image_url']) ?>" alt="">
        </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="pd-info">
    <div class="product-cat"><?= e($product['category_name']) ?></div>
    <h1><?= e($product['name']) ?></h1>
    <div class="product-rating"><?= renderStars((float)$product['rating']) ?> <span><?= number_format((float)$product['rating'], 1) ?> · <?= (int)$product['stock'] ?> in stock</span></div>

    <div class="pd-price">
      <span class="price-now"><?= formatPrice($product['price']) ?></span>
      <?php if ($product['old_price']): ?><span class="price-old"><?= formatPrice($product['old_price']) ?></span>
      <span class="product-badge badge-SALE" style="position:static;">
        <?= round((($product['old_price'] - $product['price']) / $product['old_price']) * 100) ?>% OFF
      </span>
      <?php endif; ?>
    </div>

    <div class="pd-desc"><p><?= e($product['description']) ?></p></div>

    <div class="pd-actions">
      <div class="qty-stepper" data-standalone>
        <button type="button" class="qty-minus">−</button>
        <input type="text" id="qty-<?= (int)$product['id'] ?>" value="1" readonly>
        <button type="button" class="qty-plus">+</button>
      </div>
      <button class="btn btn-primary btn-lg add-cart-btn" data-product-id="<?= (int)$product['id'] ?>" style="flex:1;">Add to Cart</button>
    </div>

    <div class="pd-meta-row">
      <div>🚚 <strong>Free delivery</strong> over ₹3,000</div>
      <div>↩️ <strong>7-day</strong> returns</div>
      <div>🔒 <strong>Secure</strong> checkout</div>
    </div>
  </div>
</div>

<style>
.pd-gallery {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.pd-main-img-wrap {
  position: relative;
  border-radius: 16px;
  overflow: hidden;
  background: var(--surface);
  aspect-ratio: 1;
}
.pd-main-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: opacity .25s ease;
}
.pd-main-img-wrap img.fade-out { opacity: 0; }
.pd-thumbnails {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.pd-thumb-btn {
  width: 68px;
  height: 68px;
  border-radius: 10px;
  overflow: hidden;
  border: 2px solid var(--border);
  background: var(--surface);
  cursor: pointer;
  padding: 0;
  transition: border-color .2s, transform .15s;
  flex-shrink: 0;
}
.pd-thumb-btn img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.pd-thumb-btn:hover { border-color: var(--accent); transform: translateY(-2px); }
.pd-thumb-btn.active { border-color: var(--accent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 30%, transparent); }
</style>

<script>
function switchImage(btn, url) {
  const mainImg = document.getElementById('pdMainImg');
  mainImg.classList.add('fade-out');
  setTimeout(() => {
    mainImg.src = url;
    mainImg.classList.remove('fade-out');
  }, 220);
  document.querySelectorAll('.pd-thumb-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
</script>

<?php if (!empty($related)): ?>
<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head"><div><h2>You May Also Like</h2></div></div>
    <div class="product-grid">
      <?php foreach ($related as $p):
        $badge = $p['badge'] ? '<span class="product-badge badge-' . e($p['badge']) . '">' . e($p['badge']) . '</span>' : '';
        $oldPrice = $p['old_price'] ? '<span class="price-old">' . formatPrice($p['old_price']) . '</span>' : '';
      ?>
      <div class="product-card">
        <a href="<?= BASE_PATH ?>/pages/product.php?slug=<?= e($p['slug']) ?>" class="product-media">
          <?= $badge ?>
          <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        </a>
        <div class="product-body">
          <div class="product-cat"><?= e($p['category_name']) ?></div>
          <a href="<?= BASE_PATH ?>/pages/product.php?slug=<?= e($p['slug']) ?>"><h4 class="product-name"><?= e($p['name']) ?></h4></a>
          <div class="product-rating"><?= renderStars((float)$p['rating']) ?> <span><?= number_format((float)$p['rating'], 1) ?></span></div>
          <div class="product-price"><span class="price-now"><?= formatPrice($p['price']) ?></span><?= $oldPrice ?></div>
          <button class="add-cart-btn" data-product-id="<?= (int)$p['id'] ?>">Add to Cart</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
