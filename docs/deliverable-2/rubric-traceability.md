# Rubric Traceability — how the submission meets each "Excellent" criterion

Maps the assessment grid to concrete files, features and screenshots so a marker can verify every row quickly.

## 1. Sample HTML code — *semantic, clean structure, accessibility* (13–15)

**Demonstrated by:** the page templates at the web root.

| Criterion | Evidence |
|---|---|
| Semantic landmarks | `<header>` (header.php), `<nav>` (catnav + admin nav), `<main id="main">`, `<footer>` in `public/includes/header.php` / `footer.php`; `<article>`/`<section>`/`<aside>`/`<fieldset>`/`<legend>` throughout |
| Accessibility | Skip-to-content link, `.sr-only` labels on every icon-only/search input, labelled form controls (`<label for>`), `aria-label`s, star ratings with textual fallback (`stars_html()`), alt-role covers with `title` attributes |
| Well-formedness | Validated with browser tools; `php -l` passes on all 31 files |

**Files:** `public/index.php`, `public/catalogue.php`, `public/book.php`, `public/cart.php`, `public/checkout.php`, `public/includes/header.php` · **Screenshots:** `docs/screenshots/app/*`

## 2. Sample CSS code — *responsive, well-organised, flex/grid* (16–20)

**Demonstrated by:** one organised stylesheet with CSS variables and clearly commented sections.

| Criterion | Evidence |
|---|---|
| Responsive | Three media-query breakpoints: 1024 px (tablet), 860 px (portrait tablet), 600 px (mobile) — `@media` blocks at the end of `style.css` |
| Layout techniques | Flexbox (`.header-row`, `.field-row`, `.cart-item`, `.chart-row`) and CSS Grid (`.book-grid`, `.catalogue-layout`, `.cart-layout`, `.kpi-grid`, `.admin-layout`) |
| Organisation | Section comments, CSS custom properties (`:root` tokens), reusable components (`.btn`, `.panel`, `.data-table`, `.badge`) |

**Files:** `public/css/style.css` (≈ 480 lines) · **Screenshots:** responsive states captured in `docs/screenshots/app/`

## 3. Sample JavaScript code — *dynamic, interactive features* (16–20)

**Demonstrated by:** `public/js/main.js` (one file, four clearly separated features).

| Feature | Implementation |
|---|---|
| Search autocomplete | Debounced fetch to `api/search.php`; keyboard navigation (↑/↓/Enter/Esc); click-to-open |
| Cart updates | `form.quick-add` intercepted → POST `api/cart.php` → badge count updated + toast |
| Dynamic filtering | `#filterForm` change events + sort → fetch `api/books.php` → results swapped without page reload; AJAX pagination delegation |
| Form validation | `data-validate` forms: required/min/email/match rules, card-number/expiry/CVV checks, inline `.field-error` messages |
| Extra polish | Card number & expiry auto-formatting |

**Files:** `public/js/main.js`, `public/api/search.php`, `public/api/cart.php`, `public/api/books.php`

## 4. Sample MySQL table screenshots — *normalised schema, PK/FK* (16–20)

**Demonstrated by:** Workbench schema screenshots + exported DDL.

| Criterion | Evidence |
|---|---|
| 7 normalised tables | `users`, `books`, `orders`, `order_details`, `reviews`, `promotions`, `wishlist_items` (3NF — see D1 doc §06) |
| PK/FK relationships | InnoDB FKs (`fk_orders_user`, `fk_od_order`, `fk_od_book`, `fk_rev_user`, `fk_rev_book`, `fk_wish_*`), UNIQUE keys (Email, ISBN, DiscountCode) |
| Snapshot design | `order_details.PriceAtPurchase`, `orders.DiscountAmount/PromoCode`, status ENUMs |

**Files:** `db/schema.sql`, `db/seed.sql` · **Screenshots:** `docs/screenshots/mysql/*` (Workbench: tables, ERD, seed rows)

## 5. Full-stack functionality — *front-end + back-end integration* (16–20)

**Demonstrated by:** the running application (every UI action hits MySQL and back).

| Flow | Proof trail |
|---|---|
| Catalogue browsing | Homepage/catalogue query `books` via PDO (`includes/catalog.php`) |
| Cart | session basket (`functions.php`) → checkout persists to `orders` + `order_details` in a transaction |
| Checkout | simulated gateway (`includes/payment.php`) → order row + stock decrement (`checkout.php`) → receipt + e-mail log |
| Admin panel with DB | dashboard aggregates (`admin/index.php`), book CRUD, order status workflow, user/promotion management |
| Reviews | `book.php` INSERT + `reviews` reads; average + distribution recomputed |

**Run:** `php -S localhost:8000 -t public` → http://localhost:8000

## 6. User manual (5)

**File:** `docs/deliverable-2/user-manual.md` — step-by-step guide with screenshots for every module.

---

### Screenshot plan (`docs/screenshots/`)

| Folder | Contents |
|---|---|
| `mysql/` | each table's structure (Workbench), ERD, seed data |
| `app/` | homepage, catalogue w/ filters, book details w/ reviews, cart w/ promo, checkout, confirmation, admin dashboard, admin books, admin orders, promotions |

Captured with headless Edge (`msedge --headless=new --screenshot`), full-page where supported.
