document.addEventListener('DOMContentLoaded', () => {
  initModals();
  initImagePreview();
  initStatusAutoSubmit();
  initConfirmDelete();
});

/* ── Modal open/close ─────────────────────────────────── */
function initModals() {
  document.querySelectorAll('[data-open-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = document.querySelector(btn.getAttribute('data-open-modal'));
      if (modal) modal.classList.add('open');
    });
  });
  document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.closest('.modal-overlay')?.classList.remove('open');
    });
  });
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });
}

/* ── Live image preview on file input ────────────────── */
function initImagePreview() {
  document.querySelectorAll('input[type=file][data-preview]').forEach(input => {
    input.addEventListener('change', () => {
      const target = document.querySelector(input.getAttribute('data-preview'));
      if (target && input.files[0]) {
        target.src = URL.createObjectURL(input.files[0]);
      }
    });
  });
}

/* ── Order status dropdown: auto-submit ──────────────── */
function initStatusAutoSubmit() {
  document.querySelectorAll('.status-select[data-autosubmit]').forEach(sel => {
    sel.addEventListener('change', () => sel.closest('form').submit());
  });
}

/* ── Confirm before delete actions ───────────────────── */
function initConfirmDelete() {
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('submit', (e) => {
      if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
}
