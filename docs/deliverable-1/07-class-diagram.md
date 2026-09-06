# 07 — Class Diagram (with Design Patterns)

## Domain class diagram

```mermaid
classDiagram
    direction LR

    class User {
        -int UserID
        -string Name
        -string Email
        -string Password
        +login(email, password) bool
        +register(data) bool
        +updateProfile() bool
        +addReview(bookID, rating, comment) bool
        +manageWishlist(bookID, action) bool
    }

    class Admin {
        +addBook(book) int
        +updateBook(bookID, data) bool
        +removeBook(bookID) bool
        +managePromotion(promo) bool
        +updateOrderStatus(orderID, status) bool
        +viewAnalytics() array
    }

    class Book {
        -int BookID
        -string Title
        -string Author
        -string Genre
        -string ISBN
        -float Price
        -int Stock
        -string ImageURL
        -string Synopsis
        +getDetails() Book
        +updateStock(qty) bool
        +applyPromotion(promo) float
    }

    class Cart {
        -int CartID
        -int UserID
        -Book[] Items
        -float Subtotal
        -float Total
        +addBook(bookID, qty) bool
        +removeBook(bookID) bool
        +updateQuantity(bookID, qty) void
        +calculateTotal(promo) float
    }

    class Order {
        -int OrderID
        -int UserID
        -datetime OrderDate
        -float TotalAmount
        -string Status
        +processCheckout(cart, promo) Order
        +confirmPayment(gatewayResult) bool
        +generateInvoice() string
    }

    class Review {
        -int ReviewID
        -int UserID
        -int BookID
        -int Rating
        -string Comment
        +submitReview() bool
        +displayReview() string
    }

    class Promotion {
        -int PromoID
        -string Description
        -string DiscountCode
        -float DiscountValue
        -datetime ValidityStart
        -datetime ValidityEnd
        +validatePromo(code) bool
        +applyDiscount(subtotal) float
    }

    class DatabaseConnection {
        -static DatabaseConnection instance
        -PDO pdo
        -DatabaseConnection() private
        +static getInstance() DatabaseConnection
        +prepare(sql) PDOStatement
    }

    User <|-- Admin : extends (role discriminator)
    User "1" --> "0..*" Order : places
    User "1" --> "0..*" Review : writes
    User "1" --> "1" Cart : owns
    Cart "1" --> "0..*" Book : contains
    Order "1" --> "0..*" Book : via OrderDetails
    Book "1" --> "0..*" Review : receives
    Admin "1" --> "0..*" Book : manages
    Order "1" --> "0..1" Promotion : uses code
    Book "*" --> "0..*" Promotion : discountable by
    Order ..> DatabaseConnection : <<singleton>>
```

## Design-pattern mapping

### 1. Model–View–Controller (MVC)
The application is separated into three layers:

| Layer | Contents | Location in implementation |
|---|---|---|
| **Model** | `Book`, `User`, `Order`, `Cart`, `Review`, `Promotion` + persistence | `public/includes/` (DB access), MySQL tables |
| **View** | Homepage, Catalogue, Book Details, Cart, Checkout, Login/Register, Admin Panel | `public/*.php`, `public/admin/*.php`, `css/style.css` |
| **Controller** | PHP handlers (search, cart ops, checkout, CRUD) + client-side JavaScript | `public/api/*.php`, `public/js/main.js` |

Flow: **View** (HTML + JS) → user action → **Controller** (`api/*.php`) → **Model** (PDO queries) → response → **View** re-renders (dynamic HTML/JSON).

### 2. Singleton — `DatabaseConnection`
- Ensures **exactly one** MySQL (PDO) connection per request.
- Implemented with a private constructor + static `getInstance()` in `public/includes/db.php`.
- Prevents resource leaks and keeps credentials in one config point (`public/includes/config.php`).

### 3. Observer — Notifications
- **Subject:** `Order` (status changes: Pending → Paid → Shipped → Completed) and `Promotion` (new offers).
- **Observer:** the notification/email dispatcher; **Customers are the observers** who subscribed to updates.
- In the codebase this is realised when `Order::processCheckout()` / `confirmPayment()` writes a status change and triggers the e-mail routine (`Email Service`) that notifies the customer — matching the DFD's `4.0 → 6.0` flow.

## Relationships explained

| Relationship | Type | Rationale |
|---|---|---|
| `Admin` → `User` | inheritance | Admin *is-a* User with elevated role; single-table role discriminator. |
| `User` 1→N `Order` | aggregation | order history. |
| `Order` → `Book` via `OrderDetails` | composition | line items snapshot price/qty. |
| `User` 1→1 `Cart` | aggregation | session basket, not persisted. |
| `Promotion` → `Order` | association | discount code applied at checkout. |
| all persistence classes → `DatabaseConnection` | dependency | Singleton access point. |
