# 02 — Use Case Diagram

> **System:** Online Bookstore Web Application
> **Actors:** Customer, Admin (primary) · Payment Gateway, Email Service (external/supporting)

## Diagram

```mermaid
usecaseDiagram
    actor "Customer" as C
    actor "Admin" as A
    actor "Payment Gateway" as PG
    actor "Email Service" as ES

    rectangle "Online Bookstore Web Application" {
        usecase "Register / Log in" as UC1
        usecase "Browse Catalogue" as UC2
        usecase "Search Books (autocomplete & filters)" as UC3
        usecase "View Book Details" as UC4
        usecase "Manage Shopping Cart" as UC5
        usecase "Manage Wishlist" as UC6
        usecase "Submit Review" as UC7
        usecase "Checkout" as UC8
        usecase "Manage Profile" as UC9
        usecase "Process Payment" as UC10
        usecase "Send Confirmation Email" as UC11
        usecase "Manage Inventory (add / update / remove books)" as UC12
        usecase "Manage User Accounts" as UC13
        usecase "Track & Fulfil Orders" as UC14
        usecase "Manage Promotions" as UC15
        usecase "View Analytics Reports" as UC16
    }

    C --> UC1
    C --> UC2
    C --> UC3
    C --> UC4
    C --> UC5
    C --> UC6
    C --> UC7
    C --> UC8
    C --> UC9

    A --> UC12
    A --> UC13
    A --> UC14
    A --> UC15
    A --> UC16

    PG --> UC10
    ES --> UC11

    UC8 ..> UC10 : <<include>>
    UC8 ..> UC11 : <<include>>
    UC4 ..> UC7 : <<extend>>
```

## Actor & use-case catalogue

| Actor | Use cases | Description |
|---|---|---|
| **Customer** | Register/Log in | Create an account or authenticate (required for checkout, reviews, wishlist). |
| | Browse Catalogue | Explore books grouped by genre/category on the homepage and catalogue page. |
| | Search Books | Type-ahead autocomplete; filter by genre, author, rating, price; sort results. |
| | View Book Details | See title, author, ISBN, synopsis, preview excerpt, reviews and ratings. |
| | Manage Shopping Cart | Add/remove books and change quantities; live subtotal/total updates. |
| | Manage Wishlist | Save books for later; move wishlist items into the cart. |
| | Submit Review | Rate 1–5 stars and comment on a purchased/read book *(extend of View Details)*. |
| | Checkout | Enter/confirm delivery details, apply promo code, pay. **Includes** Process Payment and Send Confirmation Email. |
| | Manage Profile | Update name/email/password; view order history. |
| **Admin** | Manage Inventory | Add, update (price/stock), remove books. |
| | Manage User Accounts | View, activate/deactivate or reset customer accounts. |
| | Track & Fulfil Orders | View incoming orders, update status (Pending → Paid → Shipped → Completed). |
| | Manage Promotions | Create discount codes, set validity window and discount value. |
| | View Analytics Reports | Dashboard: revenue, orders, top sellers, sales by genre. |
| **Payment Gateway** (system actor) | Process Payment | Authorises/captures the card payment during checkout. |
| **Email Service** (system actor) | Send Confirmation Email | Delivers order confirmation and order-status notifications. |

## Relationship notes

- **`Checkout` → `Process Payment` (<<include>>):** a checkout can never complete without payment authorisation.
- **`Checkout` → `Send Confirmation Email` (<<include>>):** every successfully stored order triggers an e-mail via the Email Service.
- **`View Book Details` → `Submit Review` (<<extend>>):** reviewing is an *optional* extra behaviour of viewing a book (only for logged-in customers).
- "Maintain database integrity" is treated as a **non-functional requirement** (ACID transactions, FK constraints) rather than a primary use case, so it does not appear as an oval.

## Traceability

Use cases map to classes/tables as follows: UC2–UC3 ↔ `Book`/`books`; UC5 ↔ `Cart` (session); UC6 ↔ `wishlist_items`; UC7 ↔ `Review`/`reviews`; UC8 ↔ `Order`/`orders`; UC15 ↔ `Promotion`/`promotions`. See the [shared glossary](00-glossary.md).
