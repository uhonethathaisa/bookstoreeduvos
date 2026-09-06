-- ============================================================================
-- Online Bookstore — Database Schema  (Deliverable 1 §6 → Deliverable 2)
-- Engine: MySQL 8.x (InnoDB, utf8mb4)
-- Run: mysql -u root -p < db/schema.sql
-- ============================================================================

DROP DATABASE IF EXISTS bookstore;
CREATE DATABASE bookstore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bookstore;

-- ----------------------------------------------------------------------------
-- users  (Customer + Admin via Role discriminator — see class diagram)
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    UserID      INT            NOT NULL AUTO_INCREMENT,
    Name        VARCHAR(60)    NOT NULL,
    Email       VARCHAR(120)   NOT NULL,
    Password    VARCHAR(255)   NOT NULL,            -- bcrypt hash (password_hash)
    Role        ENUM('Customer','Admin') NOT NULL DEFAULT 'Customer',
    DateCreated DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- default delivery address (profile) ----------------------------------
    ShipStreet    VARCHAR(160) NULL,
    ShipCity      VARCHAR(60)  NULL,
    ShipProvince  VARCHAR(60)  NULL,
    ShipPostcode  VARCHAR(12)  NULL,
    ShipCountry   VARCHAR(60)  NULL DEFAULT 'South Africa',
    -- PCI-safe payment stub: token + last 4 + expiry only. NEVER full PAN/CVV.
    CardToken     VARCHAR(100) NULL,
    CardLast4     VARCHAR(4)   NULL,
    CardExpiry    CHAR(5)      NULL,
    PRIMARY KEY (UserID),
    UNIQUE KEY uq_users_email (Email)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- books
-- ----------------------------------------------------------------------------
CREATE TABLE books (
    BookID    INT            NOT NULL AUTO_INCREMENT,
    Title     VARCHAR(200)   NOT NULL,
    Author    VARCHAR(120)   NOT NULL,
    Genre     VARCHAR(60)    NOT NULL,
    ISBN      VARCHAR(20)    NOT NULL,
    Price     DECIMAL(6,2)   NOT NULL,
    Stock     INT            NOT NULL DEFAULT 0,
    ImageURL  VARCHAR(255)   NULL,
    Synopsis  TEXT           NULL,
    PRIMARY KEY (BookID),
    UNIQUE KEY uq_books_isbn (ISBN),
    KEY idx_books_genre (Genre),
    KEY idx_books_title (Title)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- orders
-- ----------------------------------------------------------------------------
CREATE TABLE orders (
    OrderID           INT          NOT NULL AUTO_INCREMENT,
    UserID            INT          NOT NULL,
    OrderDate         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    TotalAmount       DECIMAL(8,2) NOT NULL,
    Status            ENUM('Pending','Paid','Shipped','Completed') NOT NULL DEFAULT 'Pending',
    -- delivery snapshot (collected at checkout) -------------------------
    ShipName          VARCHAR(60)  NULL,
    ShipAddress       VARCHAR(160) NULL,
    ShipCity          VARCHAR(60)  NULL,
    ShipPostcode      VARCHAR(12)  NULL,
    ShipCountry       VARCHAR(60)  NULL,
    ShipPhone         VARCHAR(30)  NULL,
    -- shipping method & fulfilment --------------------------------------
    ShipMethod        ENUM('Standard','Express','Priority') NOT NULL DEFAULT 'Standard',
    ShipCost          DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    EstimatedDelivery DATE         NULL,
    Carrier           VARCHAR(60)  NULL,
    TrackingNumber    VARCHAR(40)  NULL,
    ShippedDate       DATETIME     NULL,
    DeliveredDate     DATETIME     NULL,
    -- pricing snapshot ----------------------------------------------------
    PromoCode         VARCHAR(40)  NULL,
    DiscountAmount    DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (OrderID),
    KEY idx_orders_user (UserID),
    KEY idx_orders_status (Status),
    CONSTRAINT fk_orders_user FOREIGN KEY (UserID)
        REFERENCES users(UserID) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;


-- ----------------------------------------------------------------------------
-- order_details  (line items; decomposes M:N orders<->books)
-- ----------------------------------------------------------------------------
CREATE TABLE order_details (
    OrderDetailID   INT          NOT NULL AUTO_INCREMENT,
    OrderID         INT          NOT NULL,
    BookID          INT          NOT NULL,
    Quantity        INT          NOT NULL,
    PriceAtPurchase DECIMAL(6,2) NOT NULL,
    PRIMARY KEY (OrderDetailID),
    KEY idx_od_order (OrderID),
    KEY idx_od_book (BookID),
    CONSTRAINT fk_od_order FOREIGN KEY (OrderID)
        REFERENCES orders(OrderID) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_od_book FOREIGN KEY (BookID)
        REFERENCES books(BookID) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_od_qty CHECK (Quantity > 0)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- reviews
-- ----------------------------------------------------------------------------
CREATE TABLE reviews (
    ReviewID   INT          NOT NULL AUTO_INCREMENT,
    UserID     INT          NOT NULL,
    BookID     INT          NOT NULL,
    Rating     TINYINT      NOT NULL,
    Comment    TEXT         NULL,
    ReviewDate DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ReviewID),
    KEY idx_rev_book (BookID),
    KEY idx_rev_user (UserID),
    CONSTRAINT fk_rev_user FOREIGN KEY (UserID)
        REFERENCES users(UserID) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_rev_book FOREIGN KEY (BookID)
        REFERENCES books(BookID) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_rev_rating CHECK (Rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- promotions  (discount codes)
-- ----------------------------------------------------------------------------
CREATE TABLE promotions (
    PromoID       INT          NOT NULL AUTO_INCREMENT,
    Description   VARCHAR(120) NOT NULL,
    DiscountCode  VARCHAR(40)  NOT NULL,
    DiscountValue DECIMAL(5,2) NOT NULL,             -- percentage off
    ValidityStart DATETIME     NOT NULL,
    ValidityEnd   DATETIME     NOT NULL,
    PRIMARY KEY (PromoID),
    UNIQUE KEY uq_promos_code (DiscountCode)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- wishlist_items  (gap-fix table — see D1 glossary §3)
-- ----------------------------------------------------------------------------
CREATE TABLE wishlist_items (
    WishlistID INT      NOT NULL AUTO_INCREMENT,
    UserID     INT      NOT NULL,
    BookID     INT      NOT NULL,
    DateAdded  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (WishlistID),
    UNIQUE KEY uq_wish_user_book (UserID, BookID),
    KEY idx_wish_book (BookID),
    CONSTRAINT fk_wish_user FOREIGN KEY (UserID)
        REFERENCES users(UserID) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_wish_book FOREIGN KEY (BookID)
        REFERENCES books(BookID) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Verification queries (for screenshots / sanity checks)
-- ----------------------------------------------------------------------------
-- SHOW TABLES;
-- SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'bookstore';
