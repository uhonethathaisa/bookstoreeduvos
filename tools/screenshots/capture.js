/**
 * Screenshot capture for BookNest documentation.
 * Uses puppeteer-core + the locally installed Microsoft Edge (no Chromium download).
 * Run:  node tools/screenshots/capture.js
 * Output: docs/screenshots/app/*.png
 */
'use strict';

const path = require('path');
const fs = require('fs');
const puppeteer = require('puppeteer-core');

const BASE = 'http://localhost:8090';
const ROOT = path.resolve(__dirname, '..', '..');
const OUT = path.join(ROOT, 'docs', 'screenshots', 'app');
const EDGE = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

fs.mkdirSync(OUT, { recursive: true });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function shot(page, name, { full = false } = {}) {
  await sleep(450); // allow fonts/JS to settle
  const file = path.join(OUT, name);
  await page.screenshot({ path: file, fullPage: full });
  console.log('saved', name);
}

async function main() {
  const browser = await puppeteer.launch({
    executablePath: EDGE,
    headless: 'new',
    defaultViewport: { width: 1440, height: 950 },
    args: ['--no-sandbox', '--disable-gpu'],
  });

  /* ---------- guest view ---------- */
  const guest = await browser.newPage();
  await guest.setViewport({ width: 1440, height: 950 });

  await guest.goto(BASE + '/', { waitUntil: 'networkidle0' });
  await shot(guest, '01-home.png', { full: true });

  await guest.goto(BASE + '/catalogue.php?genre=Fiction', { waitUntil: 'networkidle0' });
  await shot(guest, '02-catalogue.png', { full: true });

  await guest.goto(BASE + '/catalogue.php?genre=Fiction&rating=4&sort=price_asc', { waitUntil: 'networkidle0' });
  await shot(guest, '03-catalogue-filtered.png', { full: true });

  // Add-to-Cart modal (AJAX) — click the first "Add to cart" button
  await guest.goto(BASE + '/catalogue.php?genre=Fiction', { waitUntil: 'networkidle0' });
  await guest.click('button.js-add-to-cart');
  await guest.waitForSelector('#addToCartModal:not([hidden])', { timeout: 5000 });
  await sleep(600);
  await shot(guest, '18-add-to-cart-modal.png');
  await guest.keyboard.press('Escape');

  await guest.goto(BASE + '/book.php?id=1', { waitUntil: 'networkidle0' });
  await shot(guest, '04-book-details.png', { full: true });

  await guest.goto(BASE + '/login.php', { waitUntil: 'networkidle0' });
  await shot(guest, '05-login.png');

  /* ---------- customer view ---------- */
  const cust = await browser.newPage();
  await cust.setViewport({ width: 1440, height: 950 });
  await cust.goto(BASE + '/login.php', { waitUntil: 'networkidle0' });
  await cust.type('#email', 'demo@booknest.com');
  await cust.type('#password', 'Password1!');
  await Promise.all([cust.waitForNavigation({ waitUntil: 'networkidle0' }), cust.click('.auth-card button[type="submit"]')]);

  // add two books to the cart
  await cust.evaluate(async () => {
    const post = (params) => fetch('cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(params).toString(),
    });
    await post({ action: 'add', book_id: 4, qty: 1, next: 'cart.php' });
    await post({ action: 'add', book_id: 2, qty: 1, next: 'cart.php' });
    await post({ action: 'promo', code: 'READ30' });
  });

  await cust.goto(BASE + '/cart.php', { waitUntil: 'networkidle0' });
  await shot(cust, '06-cart.png', { full: true });

  await cust.goto(BASE + '/checkout.php', { waitUntil: 'networkidle0' });
  await shot(cust, '07-checkout.png', { full: true });

  await cust.goto(BASE + '/profile.php', { waitUntil: 'networkidle0' });
  await shot(cust, '08-profile.png', { full: true });

  await cust.goto(BASE + '/track.php?order=1', { waitUntil: 'networkidle0' });
  await shot(cust, '17-track-delivery.png', { full: true });

  await cust.goto(BASE + '/wishlist.php', { waitUntil: 'networkidle0' });
  await shot(cust, '09-wishlist.png', { full: true });

  // mobile home (responsive demo)
  await cust.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
  await cust.goto(BASE + '/', { waitUntil: 'networkidle0' });
  await shot(cust, '10-home-mobile.png', { full: true });
  await cust.close();

  /* ---------- admin view (separate incognito context) ---------- */
  const actx = await browser.createBrowserContext();
  const admin = await actx.newPage();
  await admin.setViewport({ width: 1440, height: 950 });
  await admin.goto(BASE + '/login.php', { waitUntil: 'networkidle0' });
  await admin.type('#email', 'admin@booknest.com');
  await admin.type('#password', 'Admin123!');
  await Promise.all([admin.waitForNavigation({ waitUntil: 'networkidle0' }), admin.click('.auth-card button[type="submit"]')]);

  await admin.goto(BASE + '/admin/index.php', { waitUntil: 'networkidle0' });
  await shot(admin, '11-admin-dashboard.png', { full: true });

  await admin.goto(BASE + '/admin/books.php', { waitUntil: 'networkidle0' });
  await shot(admin, '12-admin-books.png', { full: true });

  await admin.goto(BASE + '/admin/orders.php', { waitUntil: 'networkidle0' });
  await shot(admin, '13-admin-orders.png', { full: true });

  await admin.goto(BASE + '/admin/order.php?id=1', { waitUntil: 'networkidle0' });
  await shot(admin, '14-admin-order.png', { full: true });

  await admin.goto(BASE + '/admin/promotions.php', { waitUntil: 'networkidle0' });
  await shot(admin, '15-admin-promotions.png', { full: true });

  await admin.goto(BASE + '/admin/users.php', { waitUntil: 'networkidle0' });
  await shot(admin, '16-admin-users.png', { full: true });

  await actx.close();
  await browser.close();
  console.log('All screenshots captured.');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
