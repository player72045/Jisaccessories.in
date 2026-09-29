<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/currency.php';

$pageTitle = 'Fashion Which Suits You';

// Categories with live item counts
$categories = [];
$res = $conn->query("SELECT c.*, COUNT(p.id) AS item_count FROM categories c
                      LEFT JOIN products p ON p.category_id = c.id
                      GROUP BY c.id ORDER BY c.id");
while ($row = $res->fetch_assoc()) $categories[] = $row;

$styleChips = ['American Diamond', 'Gold Plated', 'Oxidized', 'Kundan', 'Stainless Steel', 'Silver', 'Demi Fine (18k Gold-Plated Steel)'];

// Featured products
$featured = [];
$res = $conn->query("SELECT p.*, c.name AS category_name FROM products p
                      JOIN categories c ON c.id = p.category_id
                      WHERE p.featured = 1 ORDER BY p.created_at DESC LIMIT 8");
while ($row = $res->fetch_assoc()) $featured[] = $row;

// Deal of the day — pick the item with the deepest discount
$deal = null;
$res = $conn->query("SELECT p.*, c.name AS category_name FROM products p
                      JOIN categories c ON c.id = p.category_id
                      WHERE p.old_price IS NOT NULL
                      ORDER BY (p.old_price - p.price) / p.old_price DESC LIMIT 1");
if ($res && $res->num_rows) $deal = $res->fetch_assoc();

// New arrivals
$newArrivals = [];
$res = $conn->query("SELECT p.*, c.name AS category_name FROM products p
                      JOIN categories c ON c.id = p.category_id
                      ORDER BY p.created_at DESC, p.id DESC LIMIT 4");
while ($row = $res->fetch_assoc()) $newArrivals[] = $row;

// All products for homepage showcase
$allProducts = [];
$res = $conn->query("SELECT p.*, c.name AS category_name FROM products p
                      JOIN categories c ON c.id = p.category_id
                      ORDER BY p.rating DESC, p.created_at DESC LIMIT 12");
while ($row = $res->fetch_assoc()) $allProducts[] = $row;

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

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container hero-inner">
    <div class="hero-copy">
      <span class="hero-eyebrow">✨ New Season Edit</span>
      <h1>Fashion which<br>suits you</h1>
      <p class="lead">Handpicked necklaces, mangalsutra, rings and more — crafted for everyday radiance and the moments that call for more sparkle.</p>
      <div class="hero-ctas">
        <a href="<?= BASE_PATH ?>/pages/shop.php" class="btn btn-primary btn-lg">Shop Now</a>
        <a href="<?= BASE_PATH ?>/pages/shop.php?badge=SALE" class="btn btn-outline btn-lg">Explore Deals</a>
      </div>
      <div class="hero-stats">
        <div><strong>500+</strong><span>Products</span></div>
        <div><strong>50K+</strong><span>Happy Customers</span></div>
        <div><strong>4.9★</strong><span>Average Rating</span></div>
      </div>
    </div>
    <div class="hero-media">
      <img src="https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=800&q=80" alt="Featured jewellery">
      <div class="hero-badge">
        <span class="fire">🔥</span>
        <div><strong>Hot Deal Today</strong><span>Up to 40% Off</span></div>
      </div>
    </div>
  </div>
</section>

<div class="feature-strip">
  <div class="container">
    <div class="feature-item"><span class="icon">🚚</span><div><strong>Free Delivery</strong><span>On orders over ₹3,000</span></div></div>
    <div class="feature-item"><span class="icon">↩️</span><div><strong>7-Day Returns</strong><span>Easy & hassle-free</span></div></div>
    <div class="feature-item"><span class="icon">🛡️</span><div><strong>1-Year anti tarnish</strong><span>On all fine jewellery</span></div></div>
    <div class="feature-item"><span class="icon">💬</span><div><strong>24/7 Support</strong><span>We're here to help</span></div></div>
    <div class="feature-item"><span class="icon">🔒</span><div><strong>Secure Payment</strong><span>100% protected</span></div></div>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div><h2>Shop by Category</h2><p>Find exactly what completes your look.</p></div>
    </div>
    <div class="category-grid">
      <?php foreach ($categories as $cat): ?>
        <a href="<?= BASE_PATH ?>/pages/shop.php?cat=<?= e($cat['slug']) ?>" class="category-card">
          <div class="emoji"><?= $cat['icon'] ?></div>
          <strong><?= e($cat['name']) ?></strong>
          <span><?= (int)$cat['item_count'] ?> items</span>
        </a>
      <?php endforeach; ?>
    </div>
    <h4 style="margin-top:36px;color:var(--text2);font-size:13px;text-transform:uppercase;letter-spacing:.04em;">Shop by Style</h4>
    <div class="style-chip-row">
      <?php foreach ($styleChips as $chip): ?>
        <a href="<?= BASE_PATH ?>/pages/shop.php?search=<?= urlencode($chip) ?>" class="style-chip"><?= e($chip) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div><h2>Featured Products</h2><p>Our most-loved pieces, picked for you.</p></div>
      <a href="<?= BASE_PATH ?>/pages/shop.php" class="view-all">View All →</a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $p) echo productCard($p); ?>
    </div>
  </div>
</section>

<?php if ($deal): ?>
<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="deal-banner">
      <div class="deal-info">
        <span class="kicker">Deal of the Day</span>
        <h3><?= e($deal['name']) ?></h3>
        <p><?= e(mb_strimwidth($deal['description'] ?? '', 0, 110, '…')) ?></p>
        <div class="deal-timer">
          <div><strong class="t-hours">00</strong><span>Hrs</span></div>
          <div><strong class="t-minutes">00</strong><span>Min</span></div>
          <div><strong class="t-seconds">00</strong><span>Sec</span></div>
        </div>
        <div class="deal-price">
          <?= formatPrice($deal['price']) ?>
          <?php if ($deal['old_price']): ?><span class="price-old" style="font-size:18px;"><?= formatPrice($deal['old_price']) ?></span><?php endif; ?>
        </div>
      </div>
      <div class="deal-media">
        <img src="<?= e($deal['image_url']) ?>" alt="<?= e($deal['name']) ?>">
      </div>
      <div class="deal-side">
        <div class="stars"><?= renderStars((float)$deal['rating']) ?></div>
        <p style="margin-bottom:18px;">🚚 Delivered in 3–5 days</p>
        <button class="btn btn-primary btn-block add-cart-btn" data-product-id="<?= (int)$deal['id'] ?>">Add to Cart</button>
        <a href="<?= BASE_PATH ?>/pages/shop.php" class="btn btn-outline btn-block">View Shop</a>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div><h2>New Arrivals</h2><p>Fresh in, and already turning heads.</p></div>
      <a href="<?= BASE_PATH ?>/pages/shop.php?sort=newest" class="view-all">View All →</a>
    </div>
    <div class="product-grid">
      <?php foreach ($newArrivals as $p) echo productCard($p); ?>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div><h2>Our Products</h2><p>Browse our complete collection — every piece shows the name and price upfront.</p></div>
      <a href="<?= BASE_PATH ?>/pages/shop.php" class="view-all">View All →</a>
    </div>
    <div class="product-grid">
      <?php foreach ($allProducts as $p) echo productCard($p); ?>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head"><div><h2>What Our Customers Say</h2></div></div>
    <div class="testimonial-grid">
      <div class="testimonial-card">
        <div class="stars">★★★★★</div>
        <p>"The Blossom Mangalsutra exceeded every expectation — the craftsmanship feels genuinely premium and it arrived beautifully packaged."</p>
        <div class="testimonial-person"><div class="avatar-circle">RA</div><div><strong>Riya Agarwal</strong><span>Bengaluru</span></div></div>
      </div>
      <div class="testimonial-card">
        <div class="stars">★★★★★</div>
        <p>"Fast delivery, true-to-photo quality, and their support team helped me pick the right size for a gift. Will definitely shop again."</p>
        <div class="testimonial-person"><div class="avatar-circle">PS</div><div><strong>Priya Sharma</strong><span>Mumbai</span></div></div>
      </div>
      <div class="testimonial-card">
        <div class="stars">★★★★★</div>
        <p>"I bought the Equilibrium Link Bracelet for my anniversary — it looks even better in person. Worth every rupee."</p>
        <div class="testimonial-person"><div class="avatar-circle">AK</div><div><strong>Anjali Kumar</strong><span>Delhi</span></div></div>
      </div>
    </div>
  </div>
</section>

<section class="newsletter">
  <div class="container">
    <h2>Get Exclusive Deals First 🎉</h2>
    <p>Join our list for early access to new drops, festive collections, and subscriber-only discounts.</p>
    <form class="newsletter-form" onsubmit="event.preventDefault(); this.querySelector('button').textContent='Subscribed ✓';">
      <input type="email" placeholder="Enter your email address" required>
      <button type="submit">Subscribe</button>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
