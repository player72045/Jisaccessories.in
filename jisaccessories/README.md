# jisaccessories — E-Commerce Website

A full-featured jewellery & accessories store built with PHP + MySQL: product catalog with filters, AJAX shopping cart, checkout (COD / UPI / Stripe card), user accounts, and an admin panel for products, orders, users, and settings.

## 1. Requirements

- PHP 8.0+ with `mysqli` and `curl` extensions enabled
- MySQL 5.7+ / MariaDB
- Apache (XAMPP/WAMP/LAMP) or any PHP-capable web server

## 2. Local setup (XAMPP)

1. Copy the whole `jisaccessories` folder into `htdocs/jis` (so the URL is `http://localhost/jis/`).
2. Start Apache + MySQL from the XAMPP control panel.
3. Open **phpMyAdmin** → **SQL** tab → paste the contents of `database_setup.sql` → **Go**.
   This creates the `jisaccessories` database, all tables, and seed data (7 categories, 14 products, one admin account).
4. Check `includes/db.php` — the default connection (`root` / no password / `localhost`) matches a stock XAMPP install. Edit `DB_USER` / `DB_PASS` if yours differs.
5. Visit `http://localhost/jis/index.php`.

**Admin login:** `http://localhost/jis/admin/index.php`
Email: `admin@jisaccessories.in` · Password: `Admin@720`

## 3. Deploying to a VPS (domain root)

The whole app auto-detects its base path at runtime — **no find/sed path rewriting needed**. `includes/db.php` sets `BASE_PATH` from `SCRIPT_NAME`, and `assets/js/main.js` does the same on the client. Just upload the files to your web root and import the SQL file into your production database (update the credentials in `includes/db.php`).

If you'd still rather hardcode paths for some reason, the codebase never assumes `/jis/` anywhere except as a fallback default, so no bulk rename is required either way.

## 4. Enabling Stripe card payments

1. Sign in at [dashboard.stripe.com](https://dashboard.stripe.com) and switch on **Test Mode**.
2. Go to **Developers → API keys** and copy your `pk_test_…` and `sk_test_…` keys.
3. In the store, log in as admin → **Settings** → paste both keys → **Save All Settings**.
4. The "Credit/Debit Card" option at checkout unlocks automatically once both keys are saved.

Test card: `4242 4242 4242 4242`, any future expiry, any CVC.

The Stripe integration talks to Stripe's REST API directly over `curl` (`pages/stripe_checkout.php`, `pages/stripe_return.php`) — no Composer SDK required.

## 5. Multi-currency

All prices are stored in INR. The active currency (set in Admin → Settings) determines what's displayed and what Stripe charges — see `includes/currency.php` for the conversion table and supported currencies.

## 6. What's included

- **Storefront:** home, shop (filters/sort/pagination), product detail, cart (AJAX + non-JS fallback), checkout, order confirmation
- **Accounts:** register, login, my account (dashboard, orders, profile with avatar upload, change password)
- **Admin:** dashboard stats, product CRUD (with image upload or URL), order management with status updates, user/role management, currency & Stripe settings
- **Security:** prepared statements throughout, `password_hash()`/`password_verify()`, `htmlspecialchars()` on all output, session-based auth with role checks

## 7. Notes & next steps

- Product images default to Unsplash URLs seeded in `database_setup.sql`; swap them for your own via Admin → Products.
- The `admin/uploads/` and `assets/uploads/avatars/` folders need to stay writable by the web server for image uploads to work.
- Consider adding CSRF tokens to admin forms and a proper email service (order confirmations are currently UI-only) before taking this to production.
