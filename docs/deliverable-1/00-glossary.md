# Shared Glossary — Online Bookstore

Single source of truth for naming used across **all** Deliverable 1 artifacts and the Deliverable 2 implementation.
If a diagram disagrees with this page, this page wins.

## 1. Actors (external entities)

| Actor | Type | Interacts with |
|---|---|---|
| Customer | Human (primary) | Browse, search, cart, wishlist, reviews, checkout, profile |
| Admin | Human (primary) | Inventory, orders, users, promotions, analytics |
| Payment Gateway | External system | Payment processing during checkout |
| Email Service | External system | Order confirmation / status notification emails |

## 2. Core classes (class diagram, CRC cards)

| Class | Backing table / persistence | Notes |
|---|---|---|
| `User` | `users` | Customer identity, profile, auth |
| `Admin` | `users` (role = 'Admin') | Inherits identity; administration methods |
| `Book` | `books` | Catalogue item |
| `Cart` | session (transient) | Not persisted in MySQL by design |
| `Order` | `orders` + `order_details` | Order header + line items |
| `Review` | `reviews` | Customer feedback on a book |
| `Promotion` | `promotions` | Discount codes / offers |
| `WishlistItem` | `wishlist_items` | Resolves the wishlist gap between D1 drafts |
| `DatabaseConnection` | — | **Singleton** (PDO) |

## 3. Database tables

| Table | PK | Key FKs |
|---|---|---|
| `users` | `UserID` | — |
| `books` | `BookID` | — |
| `orders` | `OrderID` | `UserID` → `users` |
| `order_details` | `OrderDetailID` | `OrderID` → `orders`; `BookID` → `books` |
| `reviews` | `ReviewID` | `UserID` → `users`; `BookID` → `books` |
| `promotions` | `PromoID` | — |
| `wishlist_items` | `WishlistID` | `UserID` → `users`; `BookID` → `books` |

## 4. Pages (views)

Homepage (`index`), Catalogue, Book Details, Shopping Cart, Checkout, Login / Register,
Admin Panel (Dashboard / Books / Orders / Users / Promotions).

## 5. Key relationships (cardinality)

- `users` 1 — N `orders`; `users` 1 — N `reviews`; `users` 1 — N `wishlist_items`
- `books` 1 — N `reviews`; `books` 1 — N `order_details`; `books` 1 — N `wishlist_items`
- `orders` 1 — N `order_details`
- `promotions` N — N `books` *(optional; applied as discount codes at checkout in this build)*
- `Admin` — N `books` (inventory CRUD)

## 6. Design patterns

- **MVC** — Model (`Book`, `User`, `Order`, …), View (HTML/CSS pages), Controller (PHP + JavaScript functions).
- **Singleton** — `DatabaseConnection` (one active PDO/MySQL connection).
- **Observer** — promotion + order-status notifications; Customers are observers, Order/Promotion are subjects.

## 7. Use-case list (customer / admin)

**Customer:** Register/Login · Browse Catalogue · Search Books (autocomplete + filters) ·
View Book Details · Manage Shopping Cart · Manage Wishlist · Submit Review · Checkout ·
Manage Profile.
**Admin:** Manage Inventory · Manage User Accounts · Track & Fulfil Orders · Manage Promotions ·
View Analytics Reports.
**System (supporting):** Process Payment (via gateway) · Send Confirmation Email.
