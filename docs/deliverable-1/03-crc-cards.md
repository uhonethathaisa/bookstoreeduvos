# 03 — CRC Cards (Class–Responsibility–Collaborator)

CRC cards summarise **what each class is responsible for** and **which other classes it collaborates with** to fulfil those responsibilities. All names follow the [shared glossary](00-glossary.md).

---

## Card 1 — `Book`

| | |
|---|---|
| **Responsibilities** | • Store book catalogue data (title, author, genre, ISBN, price, stock, image).<br>• Expose book details and preview synopsis.<br>• Update stock level when sold / restocked.<br>• Apply a promotion/discount when eligible. |
| **Collaborators** | `Catalogue` (browse/search results) · `Cart` (line items) · `Order` (via `OrderDetails`) · `Review` (aggregate ratings) · `Promotion` (discount) |
| **Backing store** | `books` table · used by `api/books.php`, `catalogue.php`, `book.php` |

---

## Card 2 — `User` (Customer)

| | |
|---|---|
| **Responsibilities** | • Store identity/authentication data (name, e-mail, password, role).<br>• Manage own profile (login, register, update details).<br>• Maintain a wishlist of books.<br>• Submit reviews and place orders. |
| **Collaborators** | `Cart` (session basket) · `Order` (purchase history) · `Review` · `WishlistItem` |
| **Backing store** | `users` table (role = `Customer`) · `wishlist_items` table |

---

## Card 3 — `Admin`

| | |
|---|---|
| **Responsibilities** | • Manage inventory: add / update / delete books.<br>• Manage promotions and featured-book offers.<br>• Track and fulfil customer orders (update status).<br>• Manage user accounts and view analytics. |
| **Collaborators** | `Book` (CRUD) · `User` (accounts) · `Order` (fulfilment) · `Promotion` |
| **Notes** | Specialisation of `User` (same identity, `role = 'Admin'`) — see [class diagram](07-class-diagram.md). |

---

## Card 4 — `Cart`

| | |
|---|---|
| **Responsibilities** | • Add / remove books and change quantities.<br>• Calculate subtotal, shipping, discounts and total.<br>• Persist across a session so the customer can check out later. |
| **Collaborators** | `Book` (product data, stock check) · `Order` (converts to order at checkout) · `Promotion` (code validation) |
| **Backing store** | **None — transient** (PHP `$_SESSION` / JS state). Documented as `D6 Cart (session)` in the DFD; intentionally not a MySQL table. |

---

## Card 5 — `Order`

| | |
|---|---|
| **Responsibilities** | • Process checkout: validate cart + customer, apply promo.<br>• Persist order header and line items (order details).<br>• Confirm payment via gateway and record status.<br>• Trigger confirmation e-mail and invoice generation. |
| **Collaborators** | `Cart` (source items) · `Book` (stock/price snapshot) · `User` (customer) · `Payment Gateway` (external) · `Email Service` (external) |
| **Backing store** | `orders` + `order_details` tables |

---

## Card 6 — `Review`

| | |
|---|---|
| **Responsibilities** | • Store a customer's rating (1–5) and comment for a book.<br>• Aggregate into a book's average rating for display. |
| **Collaborators** | `User` (author) · `Book` (subject) |
| **Backing store** | `reviews` table |

---

## Card 7 — `Promotion`

| | |
|---|---|
| **Responsibilities** | • Define discount codes, discount value and validity window.<br>• Validate a code at checkout and apply the discount.<br>• Drive the homepage promotion banner (featured offers). |
| **Collaborators** | `Order` (applied at checkout) · `Book`/category (target of offer) · `Admin` (creator) |
| **Backing store** | `promotions` table |

---

## Card 8 — `DatabaseConnection` (design-pattern class)

| | |
|---|---|
| **Responsibilities** | • Open and manage **one** shared MySQL (PDO) connection.<br>• Provide prepared-statement access for all persistence classes. |
| **Collaborators** | Every class that reads/writes the database |
| **Pattern** | **Singleton** — implemented in `public/includes/db.php` |

---

## Collaboration summary

| Collaborating pair | Direction / purpose |
|---|---|
| `Book` ↔ `Catalogue` | Books are listed/searched by the catalogue. |
| `Book` → `Cart` | Cart items reference book data and stock. |
| `Cart` → `Order` | Order is built from cart contents at checkout. |
| `Order` → `Payment Gateway` | Payment authorisation request/response. |
| `Order` → `Email Service` | Confirmation notification. |
| `User` → `Order` | Customer places many orders. |
| `User` → `Review`, `Book` → `Review` | Reviews connect customer to book. |
| `Admin` → `Book` / `Promotion` / `Order` / `User` | Administration CRUD and fulfilment. |
| all → `DatabaseConnection` | Single shared DB connection (Singleton). |
