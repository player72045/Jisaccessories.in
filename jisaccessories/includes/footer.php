  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-col footer-brand">
        <a href="<?= BASE_PATH ?>/index.php" class="logo">JIS<span>accessories</span></a>
        <p>Handpicked jewellery and accessories, crafted for everyday radiance and special occasions alike.</p>
      </div>
      <div class="footer-col">
        <h4>Shop</h4>
        <a href="<?= BASE_PATH ?>/pages/shop.php?cat=necklaces-chains">Necklaces & Chains</a>
        <a href="<?= BASE_PATH ?>/pages/shop.php?cat=rings">Rings</a>
        <a href="<?= BASE_PATH ?>/pages/shop.php?cat=wristwear">Wristwear</a>
        <a href="<?= BASE_PATH ?>/pages/shop.php?badge=SALE">Sale</a>
      </div>
      <div class="footer-col">
        <h4>Account</h4>
        <a href="<?= BASE_PATH ?>/pages/myaccount.php">My Account</a>
        <a href="<?= BASE_PATH ?>/pages/cart.php">Cart</a>
        <a href="<?= BASE_PATH ?>/pages/login.php">Login</a>
      </div>
      <div class="footer-col">
        <h4>Support</h4>
        <p>supporr@jisaccessories.in</p>
        <p>Shop &amp; Establishment: KEB/12345/Bangalore</p>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> jisaccessories. All rights reserved by jisaccessories.com</span>
    </div>
  </footer>

<script src="<?= BASE_PATH ?>/assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>
