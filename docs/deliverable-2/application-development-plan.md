# Deliverable 2 — Application Development Plan

> Project: **BookNest — Online Bookstore** · Stack: HTML5, CSS3, JavaScript (ES6+), PHP 8.3, MySQL 8

## 1. Technologies

| Technology | Purpose | Where |
|---|---|---|
| **HTML5** | Semantic page structure (`<header>`, `<nav>`, `<main>`, `<footer>`; accessible labels, skip link) | `public/*.php` templates |
| **CSS3** | Styling + responsive design — CSS variables, Flexbox, Grid, media queries (600/860/1024 px) | `public/css/style.css` |
| **JavaScript** | Autocomplete search, async cart, dynamic catalogue filtering, client-side validation, card formatting | `public/js/main.js` |
| **PHP 8.3** | Server-side logic — auth, catalogue queries, cart/order processing, admin CRUD (PDO prepared statements) | `public/*.php`, `public/admin/*.php`, `public/api/*.php`, `public/includes/` |
| **MySQL 8** | Persistence — 7 normalised tables with PK/FK constraints | `db/schema.sql`, `db/seed.sql` |

## 2. Architecture (from Deliverable 1)

- **MVC**: Model = database tables + PDO data access (`includes/db.php`, `catalog.php`); View = PHP/HTML templates (`header.php`, pages) + CSS; Controller = `api/*.php` endpoints + page POST handlers + `js/main.js`.
- **Singleton**: `DatabaseConnection` (`public/includes/db.php`) — one PDO connection per request.
- **Observer**: order/promotion events trigger the e-mail dispatcher (`includes/notify.php` writes to `storage/email.log`); customers are the notified observers.

### Database (7 tables)

`users` 1—N `orders` / `reviews` / `wishlist_items`; `books` 1—N `order_details` / `reviews` / `wishlist_items`;
`orders` 1—N `order_details`; FKs enforced with `InnoDB`. See [D1 database design](../deliverable-1/06-database-design.md).

## 3. Development workflow (as implemented)

1. **Database** — `schema.sql` creates database + 7 tables; `seed.sql` inserts 20 books, 3 users (incl. admin), 6 reviews, 3 promotions, 2 sample orders.
2. **Front-end** — homepage, catalogue, book details, cart, checkout, auth, profile, wishlist, admin panel.
3. **Interactivity (JS)** — search autocomplete (`api/search.php`), async cart (`api/cart.php`), AJAX filtering (`api/books.php`), form validation, expiry/card formatting.
4. **Back-end integration** — catalogue & search SQL, cart in session, transactional checkout (order + lines + stock decrement), admin CRUD, simulated payment gateway + e-mail.
5. **Responsive CSS** — mobile/tablet/desktop via media queries.
6. **Documentation** — user manual, screenshots (`docs/screenshots/`), rubric traceability.

## 4. Page inventory

| Page | URL | Functions |
|---|---|---|
| Homepage | `/index.php` | search + autocomplete, promo banner, featured/top-rated, genre highlights, new arrivals |
| Catalogue | `/catalogue.php` | filters (genre, author, rating, price), sort, pagination — AJAX dynamic filtering |
| Book details | `/book.php?id=` | synopsis, preview, ISBN/genre/price, reviews + rating distribution, review form, wishlist, add to cart |
| Cart | `/cart.php` | quantity steppers, remove/clear, promo code apply/remove, live totals, checkout button |
| Checkout | `/checkout.php` | delivery details + **delivery method** (Standard/Express/Priority with live total) + simulated payment; validation client+server; transaction + e-mail |
| Confirmation | `/confirmation.php?order=` | receipt with line items, shipping method/cost, **estimated delivery date**, delivery address, total |
| Track delivery | `/track.php?order=` | **timeline** (Ordered → Paid → Shipped → Delivered), carrier, tracking number, ETA |
| Auth | `/login.php`, `/register.php`, `/logout.php` | bcrypt password hashing, sessions |
| Profile | `/profile.php` | update name/e-mail/password, order history |
| Wishlist | `/wishlist.php` | saved books, add to cart / remove |
| Admin dashboard | `/admin/index.php` | KPI cards, sales by genre chart, top sellers, recent orders |
| Admin books | `/admin/books.php` | add / edit / delete / search books (inventory CRUD) |
| Admin orders | `/admin/orders.php`, `/admin/order.php` | order list, line items, **fulfilment workflow** — set status, record carrier/tracking, timestamps (Shipped/Delivered), ETA display |
| Admin users | `/admin/users.php` | search users, view activity, remove customers (FK-guarded) |
| Admin promotions | `/admin/promotions.php` | CRUD discount codes with validity windows and active/upcoming/expired state |

## 5. Key flows

- **Browse → Cart → Checkout**: customer searches/filters, opens a book, adds to cart (session), applies `READ30`, enters delivery + card details → gateway authorises → order + `order_details` inserted and stock decremented inside one DB transaction → status `Paid` → receipt page → confirmation e-mail appended to `storage/email.log`.
- **Admin fulfilment**: admin opens Orders → changes status; each change is persisted and observable (email simulation hook in `notify.php`).
