<?php
/**
 * BookNest — automated database migration + initial seed.
 * -----------------------------------------------------------------------------
 * Called from GitHub Actions (appleboy/ssh-action) right after the SCP deploy:
 *
 *     cd .../public_html
 *     php migrate.php
 *
 * SAFE BY DESIGN (runs on every deploy):
 *   1. Connects with the same credentials the app uses (public/includes/config.php
 *      or BN_DB_* environment overrides).
 *   2. Creates any missing tables (CREATE TABLE IF NOT EXISTS — mirrors
 *      db/schema.sql but NEVER drops databases or existing data).
 *   3. Seeds the catalogue ONLY when the `books` table is empty — subsequent
 *      deploys are no-ops, so production orders/reviews are never wiped.
 *
 * Exit code 0 = success · non-zero = failure (fails the Actions job).
 * Run with the PHP CLI:  php migrate.php
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/public/includes/config.php'; // DB_HOST/DB_NAME/DB_USER/DB_PASS (+ BN_* overrides)

function migrate_split_sql(string $sql): array
{
    // Splits on top-level ';' while respecting single-quoted strings and '--' comments.
    $statements = [];
    $buffer = '';
    $len = strlen($sql);
    $inString = false;
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($inString) {
            $buffer .= $c;
            if ($c === '\\' && $i + 1 < $len) { $buffer .= $sql[++$i]; continue; }
            if ($c === "'") {
                if ($i + 1 < $len && $sql[$i + 1] === "'") { $buffer .= $sql[++$i]; }
                else { $inString = false; }
            }
            continue;
        }
        if ($c === "'") { $inString = true; $buffer .= $c; continue; }
        if ($c === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
            while ($i < $len && $sql[$i] !== "\n") { $i++; }
            $buffer .= "\n";
            continue;
        }
        if ($c === ';') {
            $t = trim($buffer);
            if ($t !== '') { $statements[] = $t; }
            $buffer = '';
            continue;
        }
        $buffer .= $c;
    }
    $t = trim($buffer);
    if ($t !== '') { $statements[] = $t; }
    return $statements;
}

function migrate_log(string $line): void
{
    echo '[migrate] ' . $line . PHP_EOL;
}

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    migrate_log('Connected to ' . DB_HOST . ':' . DB_PORT . '/' . DB_NAME);
} catch (PDOException $e) {
    fwrite(STDERR, '[migrate] FATAL: could not connect to MySQL — ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/* ================= 1 · schema (create tables if missing) ================= */
$ddl = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    UserID      INT            NOT NULL AUTO_INCREMENT,
    Name        VARCHAR(60)    NOT NULL,
    Email       VARCHAR(120)   NOT NULL,
    Password    VARCHAR(255)   NOT NULL,
    Role        ENUM('Customer','Admin') NOT NULL DEFAULT 'Customer',
    DateCreated DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ShipStreet    VARCHAR(160) NULL,
    ShipCity      VARCHAR(60)  NULL,
    ShipProvince  VARCHAR(60)  NULL,
    ShipPostcode  VARCHAR(12)  NULL,
    ShipCountry   VARCHAR(60)  NULL DEFAULT 'South Africa',
    CardToken     VARCHAR(100) NULL,
    CardLast4     VARCHAR(4)   NULL,
    CardExpiry    CHAR(5)      NULL,
    PRIMARY KEY (UserID),
    UNIQUE KEY uq_users_email (Email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS books (
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

CREATE TABLE IF NOT EXISTS orders (
    OrderID           INT          NOT NULL AUTO_INCREMENT,
    UserID            INT          NOT NULL,
    OrderDate         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    TotalAmount       DECIMAL(8,2) NOT NULL,
    Status            ENUM('Pending','Paid','Shipped','Completed') NOT NULL DEFAULT 'Pending',
    ShipName          VARCHAR(60)  NULL,
    ShipAddress       VARCHAR(160) NULL,
    ShipCity          VARCHAR(60)  NULL,
    ShipPostcode      VARCHAR(12)  NULL,
    ShipCountry       VARCHAR(60)  NULL,
    ShipPhone         VARCHAR(30)  NULL,
    ShipMethod        ENUM('Standard','Express','Priority') NOT NULL DEFAULT 'Standard',
    ShipCost          DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    EstimatedDelivery DATE         NULL,
    Carrier           VARCHAR(60)  NULL,
    TrackingNumber    VARCHAR(40)  NULL,
    ShippedDate       DATETIME     NULL,
    DeliveredDate     DATETIME     NULL,
    PromoCode         VARCHAR(40)  NULL,
    DiscountAmount    DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (OrderID),
    KEY idx_orders_user (UserID),
    KEY idx_orders_status (Status),
    CONSTRAINT fk_orders_user FOREIGN KEY (UserID)
        REFERENCES users(UserID) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_details (
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
SQL;

$ddl .= <<<'SQL'

CREATE TABLE IF NOT EXISTS reviews (
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

CREATE TABLE IF NOT EXISTS promotions (
    PromoID       INT          NOT NULL AUTO_INCREMENT,
    Description   VARCHAR(120) NOT NULL,
    DiscountCode  VARCHAR(40)  NOT NULL,
    DiscountValue DECIMAL(5,2) NOT NULL,
    ValidityStart DATETIME     NOT NULL,
    ValidityEnd   DATETIME     NOT NULL,
    PRIMARY KEY (PromoID),
    UNIQUE KEY uq_promos_code (DiscountCode)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wishlist_items (
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
SQL;

/* ================= 2 · apply schema ====================================== */
$tablesBefore = 0;
try {
    foreach (migrate_split_sql($ddl) as $statement) {
        $pdo->exec($statement);
    }
    $tablesBefore = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $pdo->quote(DB_NAME)
    )->fetchColumn();
    migrate_log('Schema ready (' . $tablesBefore . ' tables present).');
} catch (PDOException $e) {
    fwrite(STDERR, '[migrate] FATAL: schema step failed — ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/* ================= 3 · seed only when empty ============================== */
try {
    $bookCount = (int) $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();

    if ($bookCount > 0) {
        migrate_log('Catalogue already seeded (' . $bookCount . ' books) — no changes made.');
    } else {
        $seedFile = __DIR__ . '/db/seed.sql';
        if (!is_file($seedFile)) {
            throw new RuntimeException('Seed file not found: ' . $seedFile);
        }
        $seedSql = (string) file_get_contents($seedFile);
        $run = 0;
        foreach (migrate_split_sql($seedSql) as $statement) {
            // Skip the `USE bookstore;` line inside seed.sql — we are already connected.
            if (stripos($statement, 'USE `') === 0 || preg_match('/^USE\s+[a-z_]+$/i', $statement)) {
                continue;
            }
            $pdo->exec($statement);
            $run++;
        }
        migrate_log('Seed applied (' . $run . ' statements).');
    }
} catch (PDOException $e) {
    fwrite(STDERR, '[migrate] FATAL: seed step failed — ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/* ================= 4 · summary =========================================== */
try {
    $counts = [];
    foreach (['users', 'books', 'orders', 'order_details', 'reviews', 'promotions', 'wishlist_items'] as $t) {
        $counts[$t] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $t)->fetchColumn();
    }
    migrate_log('Final counts: ' . json_encode($counts));
    migrate_log('DONE');
} catch (PDOException $e) {
    fwrite(STDERR, '[migrate] WARNING: summary query failed — ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

exit(0);


