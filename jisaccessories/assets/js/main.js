/* ════════════════════════════════════════════════════════
   JIS Accessories — main.js
   Auto-detects base path so the same file works on localhost
   (XAMPP under /jis) and on a VPS deployed to the domain root.
   ════════════════════════════════════════════════════════ */
const _BASE = window.location.pathname.startsWith('/jisaccessories') ? '/jisaccessories' : '';

document.addEventListener('DOMContentLoaded', () => {
  initCartBadgeSync();
  initAddToCartButtons();
  initCartPage();
  initQtyStepper();
  initFadeInOnScroll();
  initDealTimer();
  initFilterAutoSubmit();
  initPasswordToggles();
});

/* ── Cart badge ─────────────────────────────────────── */
function updateCartBadge(count) {
  document.querySelectorAll('.cart-badge').forEach(el => {
    el.textContent = count;
    el.style.display = count > 0 ? 'flex' : 'none';
  });
}
function initCartBadgeSync() {
  // no-op placeholder — badge is server-rendered on load, updated live via AJAX responses
}

/* ── Add to cart (shop / home / product detail) ──────── */
function initAddToCartButtons() {
  document.querySelectorAll('.add-cart-btn[data-product-id]').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const id = btn.getAttribute('data-product-id');
      const qtyInput = document.querySelector(`#qty-${id}`);
      const qty = qtyInput ? parseInt(qtyInput.value, 10) || 1 : 1;
      btn.disabled = true;
      const originalText = btn.textContent;
      try {
        const res = await fetch(_BASE + '/pages/cart_action.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=add&product_id=${encodeURIComponent(id)}&qty=${encodeURIComponent(qty)}`
        });
        const data = await res.json();
        if (data.success) {
          updateCartBadge(data.cart_count);
          btn.textContent = 'Added ✓';
          btn.classList.add('added');
          setTimeout(() => { btn.textContent = originalText; btn.classList.remove('added'); btn.disabled = false; }, 1400);
        } else {
          btn.textContent = originalText;
          btn.disabled = false;
          alert(data.message || 'Could not add to cart.');
        }
      } catch (err) {
        btn.disabled = false;
        btn.textContent = originalText;
      }
    });
  });
}

/* ── Cart page: qty update + remove w/ AJAX, form fallback ── */
function initCartPage() {
  const cartTable = document.querySelector('.cart-table');
  if (!cartTable) return;

  // Quantity +/- and direct input change
  cartTable.querySelectorAll('.qty-stepper').forEach(stepper => {
    const id = stepper.getAttribute('data-product-id');
    const input = stepper.querySelector('input');
    const minus = stepper.querySelector('.qty-minus');
    const plus = stepper.querySelector('.qty-plus');

    const commit = (val) => {
      val = Math.max(1, Math.min(99, parseInt(val, 10) || 1));
      input.value = val;
      updateCartItem(id, val);
    };
    minus.addEventListener('click', () => commit(parseInt(input.value, 10) - 1));
    plus.addEventListener('click', () => commit(parseInt(input.value, 10) + 1));
    input.addEventListener('change', () => commit(input.value));
  });

  // Remove — intercept the form's submit event (not the button click),
  // so if AJAX/JS fails for any reason the form submits normally and
  // cart.php removes the item server-side before redirecting.
  cartTable.querySelectorAll('.remove-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = form.getAttribute('data-product-id');
      const row = form.closest('.cart-row');
      try {
        const res = await fetch(_BASE + '/pages/cart_action.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=remove&product_id=${encodeURIComponent(id)}`
        });
        const data = await res.json();
        if (data.success) {
          updateCartBadge(data.cart_count);
          row.classList.add('removing');
          setTimeout(() => {
            row.remove();
            refreshOrderSummary(data);
            if (data.cart_count === 0) window.location.reload();
          }, 280);
        } else {
          form.submit(); // fallback to server-side removal
        }
      } catch (err) {
        form.submit(); // network/JS failure — natural form submit handles it
      }
    });
  });
}

async function updateCartItem(id, qty) {
  try {
    const res = await fetch(_BASE + '/pages/cart_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=update&product_id=${encodeURIComponent(id)}&qty=${encodeURIComponent(qty)}`
    });
    const data = await res.json();
    if (data.success) {
      updateCartBadge(data.cart_count);
      const row = document.querySelector(`.cart-row[data-product-id="${id}"]`);
      if (row) {
        const sub = row.querySelector('.cart-subtotal');
        if (sub && data.item_subtotal_formatted) sub.textContent = data.item_subtotal_formatted;
      }
      refreshOrderSummary(data);
    }
  } catch (err) { /* silent — value stays as typed */ }
}

function refreshOrderSummary(data) {
  if (data.formatted_subtotal) {
    const el = document.querySelector('[data-summary="subtotal"]');
    if (el) el.textContent = data.formatted_subtotal;
  }
  if (data.formatted_shipping) {
    const el = document.querySelector('[data-summary="shipping"]');
    if (el) el.textContent = data.formatted_shipping;
  }
  if (data.formatted_total) {
    const el = document.querySelector('[data-summary="total"]');
    if (el) el.textContent = data.formatted_total;
  }
  if (data.ship_message !== undefined) {
    const el = document.querySelector('[data-summary="ship-note"]');
    if (el) el.style.display = data.ship_message ? 'block' : 'none';
    if (el && data.ship_message) el.textContent = data.ship_message;
  }
}

/* ── Generic qty stepper (product detail page) ───────── */
function initQtyStepper() {
  document.querySelectorAll('.qty-stepper[data-standalone]').forEach(stepper => {
    const input = stepper.querySelector('input');
    stepper.querySelector('.qty-minus').addEventListener('click', () => {
      input.value = Math.max(1, (parseInt(input.value, 10) || 1) - 1);
    });
    stepper.querySelector('.qty-plus').addEventListener('click', () => {
      input.value = Math.min(99, (parseInt(input.value, 10) || 1) + 1);
    });
  });
}

/* ── Fade-in on scroll ────────────────────────────────── */
function initFadeInOnScroll() {
  const items = document.querySelectorAll('.product-card');
  if (!('IntersectionObserver' in window) || items.length === 0) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        entry.target.style.animationDelay = (i % 8) * 0.05 + 's';
        entry.target.style.opacity = '';
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });
  items.forEach(el => io.observe(el));
}

/* ── Deal-of-the-day countdown ───────────────────────── */
function initDealTimer() {
  const timer = document.querySelector('.deal-timer');
  if (!timer) return;
  const h = timer.querySelector('.t-hours');
  const m = timer.querySelector('.t-minutes');
  const s = timer.querySelector('.t-seconds');
  // Counts down to midnight local time
  function tick() {
    const now = new Date();
    const midnight = new Date(now);
    midnight.setHours(24, 0, 0, 0);
    let diff = Math.max(0, midnight - now) / 1000;
    const hh = Math.floor(diff / 3600);
    const mm = Math.floor((diff % 3600) / 60);
    const ss = Math.floor(diff % 60);
    if (h) h.textContent = String(hh).padStart(2, '0');
    if (m) m.textContent = String(mm).padStart(2, '0');
    if (s) s.textContent = String(ss).padStart(2, '0');
  }
  tick();
  setInterval(tick, 1000);
}

/* ── Shop filters: auto-submit on change ─────────────── */
function initFilterAutoSubmit() {
  const form = document.querySelector('.filters-form');
  if (!form) return;
  form.querySelectorAll('select[data-autosubmit]').forEach(el => {
    el.addEventListener('change', () => form.submit());
  });
  const priceRange = form.querySelector('#price_max');
  const priceLabel = form.querySelector('#price_max_label');
  if (priceRange && priceLabel) {
    priceRange.addEventListener('input', () => {
      priceLabel.textContent = '₹' + Number(priceRange.value).toLocaleString('en-IN');
    });
  }
}

/* ── Password visibility toggles ─────────────────────── */
function initPasswordToggles() {
  document.querySelectorAll('.pw-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.querySelector(btn.getAttribute('data-target'));
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
    });
  });
}
