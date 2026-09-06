# 06 — Database Design

## Entity–Relationship diagram

```mermaid
erDiagram
    USERS ||--o{ ORDERS : "places"
    USERS ||--o{ REVIEWS : "writes"
    USERS ||--o{ WISHLIST_ITEMS : "owns"
    BOOKS ||--o{ ORDER_DETAILS : "appears in"
    BOOKS ||--o{ REVIEWS : "receives"
    BOOKS ||--o{ WISHLIST_ITEMS : "is wished"
    ORDERS ||--o{ ORDER_DETAILS : "contains"

    USERS {
        int UserID PK
        varchar(60) Name
        varchar(120) Email UK
        varchar(255) Password
        enum Role
        datetime DateCreated
    }
    BOOKS {
        int BookID PK
        varchar(200) Title
        varchar(120) Author
        varchar(60) Genre
        varchar(20) ISBN
        decimal(6,2) Price
        int Stock
        varchar(255) ImageURL
        text Synopsis
    }
    ORDERS {
        int OrderID PK
        int UserID FK
        datetime OrderDate
        decimal(8,2) TotalAmount
        enum Status
        varchar(60) ShipName
        varchar(160) ShipAddress
        varchar(60) ShipCity
        varchar(12) ShipPostcode
        varchar(60) ShipCountry
        varchar(30) ShipPhone
        enum ShipMethod
        decimal(6,2) ShipCost
        date EstimatedDelivery
        varchar(60) Carrier
        varchar(40) TrackingNumber
        datetime ShippedDate
        datetime DeliveredDate
        varchar(40) PromoCode
        decimal(8,2) DiscountAmount
    }
    ORDER_DETAILS {
        int OrderDetailID PK
        int OrderID FK
        int BookID FK
        int Quantity
        decimal(6,2) PriceAtPurchase
    }
    REVIEWS {
        int ReviewID PK
        int UserID FK
        int BookID FK
        tinyint Rating
        text Comment
        datetime ReviewDate
    }
    PROMOTIONS {
        int PromoID PK
        varchar(120) Description
        varchar(40) DiscountCode UK
        decimal(5,2) DiscountValue
        datetime ValidityStart
        datetime ValidityEnd
    }
    WISHLIST_ITEMS {
        int WishlistID PK
        int UserID FK
        int BookID FK
        datetime DateAdded
    }
```

## Tables & columns

### `users`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| UserID | INT | PK, AUTO_INCREMENT | |
| Name | VARCHAR(60) | NOT NULL | |
| Email | VARCHAR(120) | NOT NULL, **UNIQUE** | login identifier |
| Password | VARCHAR(255) | NOT NULL | hashed (`password_hash`) |
| Role | ENUM('Customer','Admin') | NOT NULL, DEFAULT 'Customer' | |
| DateCreated | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

### `books`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| BookID | INT | PK, AUTO_INCREMENT | |
| Title | VARCHAR(200) | NOT NULL | indexed for search |
| Author | VARCHAR(120) | NOT NULL | |
| Genre | VARCHAR(60) | NOT NULL | Fiction / Non-fiction / Children's… |
| ISBN | VARCHAR(20) | UNIQUE | |
| Price | DECIMAL(6,2) | NOT NULL | ZAR (South African rand) |
| Stock | INT | NOT NULL DEFAULT 0 | checked at checkout |
| ImageURL | VARCHAR(255) | NULL | cover filename in `/img` |
| Synopsis | TEXT | NULL | long description |

### `orders`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| OrderID | INT | PK, AUTO_INCREMENT | |
| UserID | INT | **FK → users(UserID)** | |
| OrderDate | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| TotalAmount | DECIMAL(8,2) | NOT NULL | after discounts |
| Status | ENUM('Pending','Paid','Shipped','Completed') | DEFAULT 'Pending' | |
| ShipName | VARCHAR(60) | NULL | delivery snapshot |
| ShipAddress | VARCHAR(160) | NULL | |
| ShipCity | VARCHAR(60) | NULL | |
| ShipPostcode | VARCHAR(12) | NULL | |
| ShipCountry | VARCHAR(60) | NULL | |
| ShipPhone | VARCHAR(30) | NULL | |
| PromoCode | VARCHAR(40) | NULL | code applied at checkout |
| DiscountAmount | DECIMAL(8,2) | DEFAULT 0.00 | discount snapshot |
| ShipMethod | ENUM('Standard','Express','Priority') | DEFAULT 'Standard' | chosen at checkout |
| ShipCost | DECIMAL(6,2) | DEFAULT 0.00 | shipping charge |
| EstimatedDelivery | DATE | NULL | business-day ETA from checkout |
| Carrier | VARCHAR(60) | NULL | set by admin on dispatch |
| TrackingNumber | VARCHAR(40) | NULL | auto-generated when shipped |
| ShippedDate | DATETIME | NULL | set when status → Shipped |
| DeliveredDate | DATETIME | NULL | set when status → Completed |

### `order_details`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| OrderDetailID | INT | PK, AUTO_INCREMENT | |
| OrderID | INT | **FK → orders(OrderID)** | |
| BookID | INT | **FK → books(BookID)** | |
| Quantity | INT | NOT NULL, > 0 | |
| PriceAtPurchase | DECIMAL(6,2) | NOT NULL | price snapshot |

### `reviews`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| ReviewID | INT | PK, AUTO_INCREMENT | |
| UserID | INT | **FK → users(UserID)** | |
| BookID | INT | **FK → books(BookID)** | |
| Rating | TINYINT | 1–5 (CHECK) | |
| Comment | TEXT | NULL | |
| ReviewDate | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

### `promotions`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| PromoID | INT | PK, AUTO_INCREMENT | |
| Description | VARCHAR(120) | NOT NULL | |
| DiscountCode | VARCHAR(40) | NOT NULL, **UNIQUE** | e.g. `READ30` |
| DiscountValue | DECIMAL(5,2) | NOT NULL | % off |
| ValidityStart | DATETIME | NOT NULL | |
| ValidityEnd | DATETIME | NOT NULL | |

### `wishlist_items` — *gap-fix table*
Resolves the wishlist references found in the use-case diagram, class diagram and book-details wireframe but missing from the first schema draft.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| WishlistID | INT | PK, AUTO_INCREMENT | |
| UserID | INT | **FK → users(UserID)** | |
| BookID | INT | **FK → books(BookID)** | |
| DateAdded | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| | | UNIQUE(UserID, BookID) | no duplicates |

## Relationships & cardinality

| From | To | Cardinality | Meaning |
|---|---|---|---|
| User | Order | 1 : N | one user places many orders |
| User | Review | 1 : N | one user writes many reviews |
| User | WishlistItem | 1 : N | one user saves many books |
| Book | Review | 1 : N | one book receives many reviews |
| Book | OrderDetail | 1 : N | one book appears in many order lines |
| Book | WishlistItem | 1 : N | one book may be wished by many users |
| Order | OrderDetail | 1 : N | one order contains many line items |

## Design decisions & normalisation

1. **3NF**: no repeating groups; every non-key column depends on the full primary key (`order_details` decomposes the M:N `orders ↔ books`); no transitive dependencies.
2. **Price snapshot:** `order_details.PriceAtPurchase` preserves the price paid even if `books.Price` changes later (avoids update anomaly).
3. **Cart is not persisted** — it is session state (see [CRC](03-crc-cards.md) and [DFD](05-dfd.md)); only completed orders reach the database.
4. **Inheritance `Admin → User`** is implemented with a single-table `Role` discriminator (`ENUM('Customer','Admin')`) — simplest correct approach for two roles.
5. **Password storage:** hashed with PHP `password_hash()`; never plain text.
6. **Promotion ↔ Book M:N** remains *optional* in this release — discounts are code-based at checkout; the `promotions` table already supports extending to a join table later.
7. **Integrity**: all FKs are enforced (`InnoDB`); deletes restricted on parents so history is never silently destroyed (`ON DELETE RESTRICT`), except child rows owned by their parent (`order_details`, `wishlist_items` → `ON DELETE CASCADE`).

## SQL

The executable SQL is kept in two files at the implementation root:
- [`db/schema.sql`](../../db/schema.sql) — `CREATE DATABASE` + `CREATE TABLE` statements exactly as above.
- [`db/seed.sql`](../../db/seed.sql) — sample `INSERT` data (20 books, customers, admin, reviews, promotions).

