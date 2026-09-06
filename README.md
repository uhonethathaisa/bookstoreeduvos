# BookNest — Online Bookstore (coursework project)

A full-stack online bookstore built with **HTML5, CSS3, JavaScript (ES6+), PHP 8.3 and MySQL 8**,
covering **Deliverable 1 (system design)** and **Deliverable 2 (application development)**.

## Live demo & credentials

Run the built-in PHP server from the project root:

```powershell
php -S localhost:8000 -t public
# open http://localhost:8000
```

| Role | E-mail | Password |
|---|---|---|
| Customer | `demo@booknest.com` | `Password1!` |
| Admin | `admin@booknest.com` | `Admin123!` |

> Promo codes for the cart: `READ30` (30% off), `WELCOME10` (10% off), `NONFIC15` (15% off Non-fiction).
> Simulated card payment: any number starting with **4** (Visa) or **5** (Mastercard) is authorised, e.g. `4111 1111 1111 1111`; other prefixes are declined.

## Database setup

```powershell
mysql -u root -p < db/schema.sql
mysql -u root -p < db/seed.sql
# then create the application account (see public/includes/config.php):
#   CREATE USER 'bookstore_user'@'localhost' IDENTIFIED BY 'Bookstore123!';
#   GRANT ALL PRIVILEGES ON bookstore.* TO 'bookstore_user'@'localhost';
```

## Project structure

```
online-bookstore/
├── db/                  schema.sql + seed.sql (7 tables, 20 books, demo users/orders)
├── public/              web root
│   ├── index.php        Homepage — search, promo banner, featured, genres, new arrivals
│   ├── catalogue.php    Catalogue — filters, sorting, pagination (AJAX-enhanced)
│   ├── book.php         Book details — synopsis, reviews + rating bars, wishlist
│   ├── cart.php         Shopping cart — qty steppers, promo code, totals
│   ├── checkout.php     Delivery + simulated payment gateway → order transaction
│   ├── confirmation.php Order receipt + simulated confirmation e-mail
│   ├── login/register/logout/profile/wishlist
│   ├── admin/           Dashboard, Books CRUD, Orders, Users, Promotions
│   ├── api/             search.php, books.php, cart.php (AJAX endpoints)
│   ├── includes/        init/config/db (Singleton)/functions/catalog/header/footer
│   ├── css/style.css    Responsive design (grid + flexbox + media queries)
│   └── js/main.js       Autocomplete, async cart, dynamic filters, validation
├── docs/
│   ├── deliverable-1/   Wireframes + use case + CRC + context + DFD + ERD + class diagram
│   └── deliverable-2/   App development plan, rubric traceability, user manual
└── storage/email.log    Simulated transactional e-mails (created at runtime)
```

## Feature checklist

- [x] Search autocomplete (AJAX) · dynamic genre/author/rating/price filters · sorting · pagination
- [x] Book details with reviews, rating summary and "write a review" (logged-in customers)
- [x] Wishlist add/remove + move-to-cart
- [x] Cart with quantity steppers, promo codes and live totals
- [x] Checkout with **delivery-method choice** (Standard/Express/Priority, live totals) + simulated payment (success/decline)
- [x] Orders stored transactionally; stock decremented; **estimated delivery date** computed (business days)
- [x] **Order fulfilment & delivery**: admin dispatches orders with carrier + tracking number (auto-generated); status timestamps; customer **Track delivery** page with timeline; e-mails on confirm/ship/deliver to `storage/email.log`
- [x] Customer profile + order history
- [x] Admin: analytics dashboard, inventory CRUD, order fulfilment workflow, user admin, promotions CRUD
- [x] Responsive layout (mobile / tablet / desktop breakpoints)
- [x] Accessibility: semantic `<header>/<nav>/<main>/<footer>`, labels, alt-role covers, skip link

## Documentation

- Deliverable 1 index: [`docs/deliverable-1/README.md`](docs/deliverable-1/README.md)
- Deliverable 2 plan & manual: [`docs/deliverable-2/`](docs/deliverable-2/)
