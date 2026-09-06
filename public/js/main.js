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

/* ---------------- async cart ---------------- */
async function postCart(params) {
  const res = await fetch('api/cart.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
    body: new URLSearchParams(params).toString(),
  });
  return res.json();
}

function setCartBadge(n) {
  const b = $('#cartCount');
  if (b) b.textContent = n;
}

function initQuickAdd() {
  $$('form.quick-add').forEach((form) => {
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      try {
        const data = await postCart(new FormData(form));
        setCartBadge(data.count);
        if (data.ok) toast('success', data.message || 'Added to your cart.');
        else toast('error', data.message || 'Could not add that item.');
      } catch (_err) {
        form.submit(); // graceful fallback to normal POST
      }
    });
  });
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
  initQuickAdd();
  initAutocomplete($('#searchInput'), $('#autocomplete'));
  const heroInput = $('#heroQ');
  if (heroInput) initAutocomplete(heroInput, null);
  initCatalogueAjax();
  initValidation();
  initFormatting();
  initShippingMethod();
  initProfileCard();
});

