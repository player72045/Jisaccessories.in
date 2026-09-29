-- ════════════════════════════════════════════════════════
--  JIS Accessories — Database Setup
-- ════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS jisaccessories CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jisaccessories;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80)  NOT NULL,
    last_name  VARCHAR(80)  NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    phone      VARCHAR(20),
    address    TEXT,
    city       VARCHAR(100),
    avatar     VARCHAR(255) DEFAULT NULL,
    role       ENUM('member','admin','super_admin') DEFAULT 'member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories
CREATE TABLE IF NOT EXISTS categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(10)  NOT NULL DEFAULT '📦'
);

-- Products
CREATE TABLE IF NOT EXISTS products (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    category_id   INT            NOT NULL,
    name          VARCHAR(200)   NOT NULL,
    slug          VARCHAR(200)   NOT NULL UNIQUE,
    description   TEXT,
    price         DECIMAL(10,2)  NOT NULL,
    old_price     DECIMAL(10,2),
    image_url     VARCHAR(500)   NOT NULL DEFAULT '',
    badge         ENUM('NEW','HOT','SALE','') DEFAULT '',
    stock         INT            NOT NULL DEFAULT 100,
    featured      TINYINT(1)     DEFAULT 0,
    rating        DECIMAL(2,1)   DEFAULT 4.5,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Orders
CREATE TABLE IF NOT EXISTS orders (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT,
    order_number       VARCHAR(60)  NOT NULL UNIQUE,
    total_amount       DECIMAL(10,2) NOT NULL,
    status             ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    payment_method     VARCHAR(40)  NOT NULL,
    payment_status     ENUM('unpaid','paid','refunded') DEFAULT 'unpaid',
    stripe_session_id  VARCHAR(200) DEFAULT NULL,
    razorpay_order_id  VARCHAR(200) DEFAULT NULL,
    razorpay_payment_id VARCHAR(200) DEFAULT NULL,
    house              VARCHAR(255) DEFAULT NULL,
    shipping_address   TEXT         NOT NULL,
    city               VARCHAR(100) DEFAULT NULL,
    pincode            VARCHAR(20)  DEFAULT NULL,
    country            VARCHAR(100) DEFAULT 'India',
    contact_name       VARCHAR(160) DEFAULT NULL,
    contact_email      VARCHAR(150) DEFAULT NULL,
    contact_phone      VARCHAR(20)  DEFAULT NULL,
    notes              TEXT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Product Images (gallery — up to 7 per product)
CREATE TABLE IF NOT EXISTS product_images (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_url  VARCHAR(500) NOT NULL,
    sort_order TINYINT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Order Items
CREATE TABLE IF NOT EXISTS order_items (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    order_id   INT            NOT NULL,
    product_id INT            NOT NULL,
    quantity   INT            NOT NULL,
    price      DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Settings (currency, Stripe keys, etc.)
CREATE TABLE IF NOT EXISTS settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT         NOT NULL,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('active_currency', 'INR'),
('stripe_publishable_key', 'pk_test_REPLACE_WITH_YOUR_KEY'),
('stripe_secret_key', 'sk_test_REPLACE_WITH_YOUR_KEY'),
('stripe_webhook_secret', ''),
('razorpay_key_id', ''),
('razorpay_key_secret', '');

-- ── Seed data ──────────────────────────────────────────────

INSERT IGNORE INTO users (first_name, last_name, email, password, role) VALUES
('Super', 'Admin', 'admin@jisaccessories.in',
 '$2y$10$IHDQwagf1JxAncjpyZj5oefo9utzJ7AQG0bez9kMXj2aEexbnIFBa', 'super_admin');
-- Password above is: Admin@720

INSERT IGNORE INTO categories (name, slug, icon) VALUES
('Necklaces & Chains', 'necklaces-chains', '📿'),
('Mangalsutra',        'mangalsutra',      '📿'),
('Rings',              'rings',            '💍'),
('Wristwear',          'wristwear',        '💫'),
('Anklets',            'anklets',          '🦶'),
('Head Jewellery',     'head-jewellery',   '👸'),
('Bags & Clutches',    'bags-clutches',    '👜');

INSERT IGNORE INTO products (category_id, name, slug, description, price, old_price, image_url, badge, stock, featured, rating) VALUES
(1, 'Dazzling Emerald Necklace', 'dazzling-emerald-necklace', 'Elegant necklace with dazzling emerald stones, finished for everyday radiance.', 14999.00, 16499.00, 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=600&q=80', 'HOT', 45, 1, 4.8),
(1, 'Black Onyx Two Layer Necklace', 'black-onyx-two-layer-necklace', 'Elegant two-layer necklace with black onyx stones.', 5999.00, 7999.00, 'https://images.unsplash.com/photo-1610945264803-c22b62d2a7b3?w=600&q=80', 'NEW', 30, 1, 4.6),
(2, 'Blossom Mangalsutra', 'blossom-mangalsutra', 'Elegant mangalsutra with a beautiful floral-inspired design.', 13999.00, 15499.00, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&q=80', 'SALE', 20, 1, 4.7),
(2, 'Serenity Mangalsutra', 'serenity-mangalsutra', 'Minimal, refined mangalsutra crafted for daily wear.', 18999.00, NULL, 'https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?w=600&q=80', 'NEW', 15, 1, 4.9),
(3, 'Mini Solitaire Ring', 'mini-solitaire-ring', 'Elegant solitaire ring with a sparkling centre stone.', 2999.00, NULL, 'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?w=600&q=80', '', 100, 0, 4.5),
(3, 'Droplet Blush Ring', 'droplet-blush-ring', 'Featuring a single pink synthetic stone, waterproof and anti-tarnish for everyday wear. A minimalist, droplet-inspired design.', 999.00, 1299.00, 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=600&q=80', 'SALE', 50, 0, 4.4),
(4, 'Equilibrium Link Bracelet', 'equilibrium-link-bracelet', 'A dazzling bracelet adorned with radiant stones across its band, exuding timeless glamour.', 24999.00, NULL, 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=600&q=80', 'NEW', 8, 1, 4.9),
(4, 'Heart Charm Bracelet', 'heart-charm-bracelet', 'Delicate charm bracelet with a soft, romantic heart motif.', 2199.00, 2349.00, 'https://images.unsplash.com/photo-1502920917128-1aa500764bed?w=600&q=80', '', 12, 0, 4.3),
(5, 'Link Chain Anklet', 'link-chain-anklet', 'Base metal stainless steel, 18k gold-tone plating, 26.5cm length.', 899.00, NULL, 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=600&q=80', 'HOT', 35, 1, 4.6),
(5, 'Floral Finesse Anklet', 'floral-finesse-anklet', 'Made for those who adore little things — quiet floral charm in demi-fine jewellery.', 1299.00, 1499.00, 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&q=80', 'SALE', 55, 0, 4.5),
(6, 'Kundan Polki Maangtika', 'kundan-polki-maangtika', 'Regal kundan polki maangtika for festive and bridal styling.', 4999.00, 6499.00, 'https://images.unsplash.com/photo-1625772452859-1c03d5bf1137?w=600&q=80', 'NEW', 200, 0, 4.7),
(6, 'Pearl Drop Maang Tikka', 'pearl-drop-maang-tikka', 'Timeless pearl drop maang tikka for occasion wear.', 3499.00, NULL, 'https://images.unsplash.com/photo-1611652022419-a9419f74343d?w=600&q=80', '', 90, 0, 4.4),
(7, 'Lino Perros Texas Glow Clutch', 'lino-perros-texas-glow-clutch', 'Elegant, compact, and always fashion-forward — an evening clutch that elevates any look.', 2499.00, 3199.00, 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?w=600&q=80', 'NEW', 200, 1, 4.6),
(7, 'Chloe Sling Bag Light Gold', 'chloe-sling-bag-light-gold', 'Crafted in a luxurious metallic finish with a refined textured surface, this chic sling effortlessly elevates festive and evening looks.', 999.00, NULL, 'https://images.unsplash.com/photo-1585771724684-38269d6639fd?w=600&q=80', '', 500, 0, 4.2);
