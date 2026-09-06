/* =========================================================
   BookNest — main.js
   • Search autocomplete        • async cart (quick-add)
   • AJAX catalogue filtering   • client-side form validation
   ========================================================= */
'use strict';

const $  = (s, c) => (c || document).querySelector(s);
const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));

/* ---------------- toast flash helper ---------------- */
function toast(type, msg) {
  let zone = $('.flash-zone');
  if (!zone) {
    zone = document.createElement('div');
    zone.className = 'container flash-zone';
    const main = $('main');
    (main ? main.parentNode : document.body).insertBefore(zone, main || document.body.firstChild);
  }
  const el = document.createElement('div');
  el.className = 'flash flash-' + type;
  el.textContent = msg;
  zone.appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

/* ---------------- async cart helper ---------------- */
async function postCart(params) {
  const res = await fetch('ajax_add_to_cart.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
    body: new URLSearchParams(params).toString(),
  });
  let data = {};
  try { data = await res.json(); } catch (_err) { /* non-JSON response */ }
  data._status = res.status;
  return data;
}

function setCartBadge(n) {
  const b = $('#cartCount');
  if (b) b.textContent = n;
}

/* ---------------- Add-to-Cart modal (AJAX flow) ----------------
 * 1) User clicks "Add to cart" — the button carries data-book-id / -title /
 *    -stock attributes.
 * 2) We POST { action:'add', book_id, quantity } to ajax_add_to_cart.php —
 *    no page reload.
 * 3) On success a modal opens showing "Added to Cart!", the book title and a
 *    quantity stepper capped at the available stock.
 * 4) "Continue Shopping" closes the modal. "Update Cart & Checkout" syncs the
 *    chosen quantity (action:'set'), then redirects to cart.php.
 * If JavaScript is disabled the original <form> still POSTs to cart.php
 * (progressive enhancement).
 * ---------------------------------------------------------------- */
function initAddToCartModal() {
  const modal = $('#addToCartModal');
  if (!modal) return;

  const els = {
    overlay:   modal.querySelector('.modal-overlay'),
    closeBtn:  modal.querySelector('.modal-close'),
    title:     $('#atcBookTitle'),
    meta:      $('#atcBookMeta'),
    bookId:    $('#atcBookId'),
    qty:       $('#atcQty'),
    stockNote: $('#atcStockNote'),
    msg:       $('#atcMsg'),
    form:      $('#atcForm'),
    checkout:  $('#atcCheckoutBtn'),
  };
  let lastStock = 1;

  const setMsg = (text, kind) => {
    els.msg.textContent = text || '';
    els.msg.className = 'modal-msg' + (kind ? ' ' + kind : '');
  };
  const updateSteps = () => {
    modal.querySelectorAll('[data-atc-step]').forEach((b) => {
      const dir = parseInt(b.dataset.atcStep, 10);
      const cur = parseInt(els.qty.value, 10);
      b.disabled = (dir < 0 && cur <= 1) || (dir > 0 && cur >= lastStock);
    });
  };
  const clampQty = () => {
    let v = parseInt(els.qty.value, 10);
    if (Number.isNaN(v)) v = 1;
    v = Math.max(1, Math.min(v, lastStock));
    els.qty.value = v;
    updateSteps();
  };
  const openModal = (book) => {
    els.bookId.value = book.id;
    els.title.textContent = book.title;
    els.meta.textContent = book.stock === 1 ? '1 copy in stock' : book.stock + ' copies in stock';
    lastStock = Math.max(1, book.stock);
    els.qty.min = '1';
    els.qty.max = String(lastStock);
    els.qty.value = String(Math.min(book.cartQty || 1, lastStock));
    els.stockNote.textContent = 'Available stock: ' + lastStock;
    setMsg('', '');
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    updateSteps();
    els.qty.focus();
    els.qty.select();
  };
  const closeModal = () => {
    modal.hidden = true;
    document.body.style.overflow = '';
    els.checkout.disabled = false;
    els.checkout.textContent = 'Update Cart & Checkout';
  };

  /* ---- handle one "add" request from a quick-add form ---- */
  const addHandler = async (form, button) => {
    const hidden = form.querySelector('input[name="book_id"]');
    const id    = parseInt((button && button.dataset.bookId) || '', 10) || (hidden ? parseInt(hidden.value, 10) : 0);
    const title = (button && button.dataset.bookTitle) || 'Book';
    const stock = parseInt((button && button.dataset.bookStock) || form.dataset.bookStock || '0', 10);
    const qtyField = form.querySelector('input[name="qty"]');
    const qty = qtyField ? Math.max(1, parseInt(qtyField.value, 10) || 1) : 1;

    if (!(id > 0)) return true;                       // nothing to add → native behaviour
    if (!(stock > 0)) return true;                    // no stock data → native POST fallback

    try {
      const res = await postCart({ action: 'add', book_id: id, quantity: qty });
      if (res.success) {
        setCartBadge(res.cartCount);
        openModal({
          id: res.bookId,
          title,
          stock: res.stock,
          cartQty: (res.cartQty > 0 && res.cartQty <= res.stock) ? res.cartQty : qty,
        });
      } else {
        // Server rejected it (out of stock / quantity over limit).
        toast('error', res.message || 'Could not add this item to your cart.');
      }
    } catch (_err) {
      // Fetch failed (offline, server error) — tell the user; the page is untouched.
      toast('error', 'Network error — could not add the item. Please try again.');
    }
    return false; // suppress native form navigation (JS handled the request)
  };

  /* ---- intercept "Add to cart" form submissions (catalogue/book/wishlist) ---- */
  document.addEventListener('submit', (ev) => {
    const form = ev.target.closest('form.quick-add, form.js-add-form');
    if (!form) return;
    ev.preventDefault();                     // stop the native page reload
    const btn = form.querySelector('.js-add-to-cart');
    addHandler(form, btn);
  });

  /* ---- quantity stepper (min 1, max stock) ---- */
  modal.querySelectorAll('[data-atc-step]').forEach((b) => {
    b.addEventListener('click', () => {
      const dir = parseInt(b.dataset.atcStep, 10);
      let v = (parseInt(els.qty.value, 10) || 1) + dir;
      v = Math.max(1, Math.min(v, lastStock));
      els.qty.value = v;
      updateSteps();
    });
  });
  els.qty.addEventListener('input', clampQty);
  els.qty.addEventListener('change', clampQty);

  /* ---- "Update Cart & Checkout": sync exact quantity, then go to cart ---- */
  els.form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const id = parseInt(els.bookId.value, 10);
    const qty = parseInt(els.qty.value, 10) || 1;

    els.checkout.disabled = true;
    els.checkout.textContent = 'Updating…';
    setMsg('', '');

    try {
      // action 'set' makes the session cart match the modal quantity exactly.
      const res = await postCart({ action: 'set', book_id: id, quantity: qty });
      if (res.success) {
        setCartBadge(res.cartCount);
        setMsg('Added to Cart! Taking you to checkout…', 'success');
        setTimeout(() => { window.location.href = 'cart.php'; }, 450);
      } else {
        setMsg(res.message || 'Could not update the cart.', 'error');
        els.checkout.disabled = false;
        els.checkout.textContent = 'Update Cart & Checkout';
      }
    } catch (_err) {
      // AJAX failed — leave the modal open and let the user retry.
      setMsg('Network error — could not reach the server. Please try again.', 'error');
      els.checkout.disabled = false;
      els.checkout.textContent = 'Update Cart & Checkout';
    }
  });

  /* ---- close modal (×, overlay, "Continue Shopping", Escape) ---- */
  modal.querySelectorAll('[data-atc-close]').forEach((el) => {
    el.addEventListener('click', (ev) => { ev.preventDefault(); closeModal(); });
  });
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && !modal.hidden) closeModal();
  });
  els.overlay.addEventListener('click', closeModal);
}



/* ---------------- search autocomplete ---------------- */
function initAutocomplete(input, box) {
  if (!input) return;
  const holder = box || document.createElement('div');
  if (!box) {
    holder.className = 'ac-dropdown';
    holder.hidden = true;
    const wrap = input.closest('form') || input.parentElement;
    wrap.style.position = 'relative';
    wrap.appendChild(holder);
  }
  const searchForm = input.closest('form');
  let timer = null;
  let items = [];
  let sel = -1;

  function close() { holder.hidden = true; sel = -1; }

  function render() {
    holder.innerHTML = '';
    if (!items.length) {
      holder.hidden = true;
      return;
    }
    items.forEach((b, i) => {
      const div = document.createElement('div');
      div.className = 'ac-item' + (i === sel ? ' sel' : '');
      div.dataset.id = b.BookID;
      div.innerHTML = '<strong>' + escapeHtml(b.Title) + '</strong>' +
        '<small>by ' + escapeHtml(b.Author) + '</small>';
      div.addEventListener('mousedown', () => go(b.BookID));
      holder.appendChild(div);
    });
    holder.hidden = false;
  }

  function go(id) {
    close();
    window.location.href = 'book.php?id=' + encodeURIComponent(id);
  }

  async function run(q) {
    if (q.trim().length < 2) { close(); return; }
    try {
      const res = await fetch('api/search.php?q=' + encodeURIComponent(q.trim()));
      items = await res.json();
      sel = items.length ? 0 : -1;
      render();
    } catch (_err) { close(); }
  }

  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => run(input.value), 180);
  });
  input.addEventListener('keydown', (ev) => {
    if (holder.hidden) return;
    if (ev.key === 'ArrowDown') { ev.preventDefault(); sel = (sel + 1) % items.length; render(); }
    else if (ev.key === 'ArrowUp') { ev.preventDefault(); sel = (sel - 1 + items.length) % items.length; render(); }
    else if (ev.key === 'Enter' && sel >= 0 && items[sel]) { ev.preventDefault(); go(items[sel].BookID); }
    else if (ev.key === 'Escape') { close(); }
  });
  input.addEventListener('blur', () => setTimeout(close, 150));
  if (searchForm) {
    searchForm.addEventListener('submit', () => { if (sel >= 0 && items[sel]) { /* fall through to search page */ } });
  }
  document.addEventListener('click', (ev) => {
    if (!holder.contains(ev.target) && ev.target !== input) close();
  });
}

function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

/* ---------------- catalogue AJAX ---------------- */
function initCatalogueAjax() {
  const results = $('#catalogueResults');
  const form = $('#filterForm');
  if (!results || !form) return;
  const sort = $('#sortSelect');
  const countEl = $('#resultCount');
  let timer = null;

  async function refresh(extra) {
    const p = new URLSearchParams(new FormData(form));
    if (sort) p.set('sort', sort.value);
    if (extra) Object.keys(extra).forEach((k) => p.set(k, extra[k]));
    try {
      const res = await fetch('api/books.php?' + p.toString());
      const data = await res.json();
      results.innerHTML = data.html;
      if (countEl) {
        countEl.textContent = data.total + ' book' + (data.total === 1 ? '' : 's') + ' found';
      }
    } catch (_err) { /* leave current view intact */ }
  }

  function schedule(extra) {
    clearTimeout(timer);
    timer = setTimeout(() => refresh(extra), 250);
  }

  form.addEventListener('submit', (ev) => { ev.preventDefault(); refresh(); });
  form.addEventListener('change', () => schedule());       // dynamic filters
  if (sort) sort.addEventListener('change', () => refresh());

  // pagination (delegation — also works after AJAX re-renders)
  results.addEventListener('click', (ev) => {
    const link = ev.target.closest('a.page-link');
    if (!link) return;
    ev.preventDefault();
    const m = link.getAttribute('href').match(/[?&]page=(\d+)/);
    refresh({ page: m ? m[1] : '1' });
    window.scrollTo({ top: Math.max(0, results.getBoundingClientRect().top + window.scrollY - 120), behavior: 'smooth' });
  });
}

/* ---------------- client-side validation ---------------- */
function fieldError(el, msg) {
  const wrap = el.closest('.field') || el.parentElement;
  let err = wrap.querySelector('.field-error');
  if (msg) {
    if (!err) {
      err = document.createElement('span');
      err.className = 'field-error';
      wrap.appendChild(err);
    }
    err.textContent = msg;
    el.setAttribute('aria-invalid', 'true');
    el.classList.add('input-error');
  } else {
    if (err) err.remove();
    el.removeAttribute('aria-invalid');
    el.classList.remove('input-error');
  }
}

function checkRules(el) {
  const rules = (el.dataset.rules || '').split('|').filter(Boolean);
  const val = el.value.trim();

  for (const rule of rules) {
    const [name, arg] = rule.split(':');
    if (name === 'required' && val === '') return 'This field is required.';
    if (name === 'min' && val.length < Number(arg)) return 'Minimum ' + arg + ' characters.';
    if (name === 'email' && val !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return 'Enter a valid e-mail address.';
    if (name === 'match') {
      const other = $(el.form ? '[name="' + arg + '"]' : arg, el.form);
      if (other && val !== other.value) return 'Values do not match.';
    }
  }
  return '';
}

function checkSpecial(el) {
  const v = el.value.replace(/\s+/g, '');
  if (el.hasAttribute('data-card')) {
    if (!/^\d{15,16}$/.test(v)) return 'Card number must be 15–16 digits.';
  }
  if (el.hasAttribute('data-exp')) {
    if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(el.value.trim())) return 'Use MM/YY format (e.g. 12/28).';
  }
  if (el.hasAttribute('data-cvv')) {
    if (!/^\d{3,4}$/.test(el.value.trim())) return 'CVV must be 3–4 digits.';
  }
  return '';
}

function validateField(el) {
  let msg = checkRules(el);
  if (!msg) msg = checkSpecial(el);
  fieldError(el, msg);
  return !msg;
}

function initValidation() {
  $$('form[data-validate]').forEach((form) => {
    form.setAttribute('novalidate', 'novalidate');
    const active = (el) =>
      !el.disabled && el.type !== 'hidden' && !el.closest('[hidden]') && !el.hidden;

    form.addEventListener('submit', (ev) => {
      let ok = true;
      $$('input, select, textarea', form).forEach((el) => {
        if (!active(el)) return;
        if (!validateField(el)) ok = false;
      });
      if (!ok) ev.preventDefault();
    });
    $$('input, select, textarea', form).forEach((el) => {
      el.addEventListener('blur', () => { if (active(el)) validateField(el); });
      el.addEventListener('input', () => {
        if (active(el) && el.getAttribute('aria-invalid') === 'true') validateField(el);
      });
    });
  });
}

/* ---------------- input formatting (card number/expiry) ---------------- */
function initFormatting() {
  const cardNo = $('[data-card]');
  if (cardNo) {
    cardNo.addEventListener('input', () => {
      let d = cardNo.value.replace(/\D/g, '').slice(0, 16);
      d = d.replace(/(\d{4})(?=\d)/g, '$1 ');
      cardNo.value = d;
    });
  }
  const exp = $('[data-exp]');
  if (exp) {
    exp.addEventListener('input', () => {
      let d = exp.value.replace(/\D/g, '').slice(0, 4);
      if (d.length >= 3) d = d.slice(0, 2) + '/' + d.slice(2);
      exp.value = d;
    });
  }
}

/* ---------------- delivery method → live totals ---------------- */
function initShippingMethod() {
  const radios = $$('input[name="ship_method"]');
  if (!radios.length) return;
  const rands = (n) => 'R' + n.toFixed(2);

  const update = () => {
    radios.forEach((r) => r.closest('.ship-option').classList.toggle('checked', r.checked));
    const selected = radios.find((r) => r.checked);
    const cost = selected ? parseFloat(selected.dataset.cost || '0') : 0;
    const form = radios[0].closest('form');
    const subtotal = parseFloat(form.dataset.subtotal || '0');
    const discount = parseFloat(form.dataset.discount || '0');
    const total = subtotal - discount + cost;

    const shipEl = $('#sumShipping');
    const totEl = $('#sumTotal');
    const btnEl = $('#btnTotal');
    if (shipEl) shipEl.textContent = cost > 0 ? rands(cost) : 'Free';
    if (totEl) totEl.textContent = rands(total);
    if (btnEl) btnEl.textContent = rands(total);
  };

  radios.forEach((r) => r.addEventListener('change', update));
}

/* ---------------- profile payment card: replace toggle ---------------- */
function initProfileCard() {
  const replaceChk = $('#replaceCard');
  const removeChk  = $('#removeCard');
  const box        = $('#newCardFields');
  if (!replaceChk || !box) return;

  const fields = ['card_name', 'card_no', 'card_expiry', 'card_cvv']
    .map((n) => $('[name="' + n + '"]'))
    .filter(Boolean);
  const maskedRe = /^\*{4}/;

  const sync = () => {
    const show = replaceChk.checked && !(removeChk && removeChk.checked);
    box.hidden = !show;
    fields.forEach((f) => {
      f.disabled = !show;
      if (!show) { f.classList.remove('input-error'); f.removeAttribute('aria-invalid'); }
    });
    if (show) {
      const no = $('#card_no');
      if (no && maskedRe.test(no.value || '')) no.value = ''; // drop masked stub, expect fresh digits
      setTimeout(() => { const n = $('#card_name'); if (n) n.focus(); }, 0);
    }
  };

  replaceChk.addEventListener('change', sync);
  if (removeChk) {
    removeChk.addEventListener('change', () => {
      if (removeChk.checked) replaceChk.checked = false;
      sync();
    });
  }
  sync();
}

/* ---------------- boot ---------------- */
document.addEventListener('DOMContentLoaded', () => {
  initAddToCartModal();
  initAutocomplete($('#searchInput'), $('#autocomplete'));
  const heroInput = $('#heroQ');
  if (heroInput) initAutocomplete(heroInput, null);
  initCatalogueAjax();
  initValidation();
  initFormatting();
  initShippingMethod();
  initProfileCard();
});

