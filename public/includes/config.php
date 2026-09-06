<?php
/**
 * Online Bookstore — configuration
 * (Deliverable 2 — PHP 8.3 + MySQL 8)
 *
 * DB connection is overridable at runtime via environment variables
 * (BN_DB_HOST / BN_DB_PORT / BN_DB_NAME / BN_DB_USER / BN_DB_PASS), e.g. to
 * point at the Docker demo container on port 3307. Defaults target a local
 * MySQL 8 instance with the dedicated application account:
 *   CREATE USER 'bookstore_user'@'localhost' IDENTIFIED BY 'Bookstore123!';
 *   GRANT ALL PRIVILEGES ON bookstore.* TO 'bookstore_user'@'localhost';
 */

declare(strict_types=1);

// ----------------------------------------------------------------------------
// OPTIONAL server-only override.
// If public/includes/config.local.php exists on the server it is loaded FIRST
// and defines the DB_* constants. It is never committed (see .gitignore), so
// live credentials never leak into the public repository. Example content:
//
//   <?php
//   define('DB_HOST', '127.0.0.1');
//   define('DB_PORT', 3306);
//   define('DB_NAME', 'u952164533_eduvos');
//   define('DB_USER', 'u952164533_bookstore');
//   define('DB_PASS', 'YourPasswordHere');
// ----------------------------------------------------------------------------
$__localConfig = __DIR__ . '/config.local.php';
if (is_file($__localConfig)) {
    require $__localConfig;
}

// --- MySQL connection -------------------------------------------------------
// Overridable (in priority order) by config.local.php, then BN_DB_* env vars,
// then the local-demo defaults below.
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('BN_DB_HOST') ?: '127.0.0.1');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('BN_DB_NAME') ?: 'bookstore');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('BN_DB_USER') ?: 'bookstore_user');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('BN_DB_PASS') ?: 'Bookstore123!');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', (int) (getenv('BN_DB_PORT') ?: 3306));
}


// --- Shop settings (South African rand) ------------------------------------
define('SHIPPING_FLAT', 79.00);      // standard courier (ZAR)
define('FREE_SHIPPING_OVER', 500.00); // free standard delivery above this subtotal
define('PAGE_SIZE', 12);             // catalogue pagination
define('APP_NAME', 'BookNest');
define('STORE_COUNTRY', 'South Africa'); // default delivery country

// --- Error display (development). Set to 0 in production. -------------------
ini_set('display_errors', '1');
error_reporting(E_ALL);

// --- Storage for simulated e-mails / logs (outside the web root) ------------
define('STORAGE_DIR', dirname(__DIR__, 2) . '/storage');
