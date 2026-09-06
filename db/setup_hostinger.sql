-- ============================================================================
-- BookNest — Database setup for Hostinger (and any local/VPS MySQL 8)
-- ============================================================================
-- Creates: database `bookstore`, user `bookstore_user` (password Bookstore123!)
--          with full privileges on the bookstore database.
--
-- USAGE ON HOSTINGER SHARED HOSTING:
--   Hostinger's shared MySQL usually only lets you create databases/users via
--   hPanel (Databases → MySQL Databases), NOT via CREATE USER in phpMyAdmin.
--   So on a shared plan, use the hPanel values EXACTLY as below, then in
--   phpMyAdmin import db/schema.sql FIRST and db/seed.sql SECOND.
--
--   If your account prefixes names (e.g. u952164533_bookstore), use that exact
--   database/user name in hPanel and update public/includes/config.php to match
--   (DB_NAME / DB_USER / DB_PASS).
--
-- USAGE WHERE FULL SQL IS ALLOWED (local MySQL, VPS, or a MySQL root shell):
--   mysql -u root -p < db/setup_hostinger.sql
--   mysql -u root -p < db/schema.sql
--   mysql -u root -p < db/seed.sql
-- ============================================================================

CREATE DATABASE IF NOT EXISTS bookstore
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Match both 'localhost' and '127.0.0.1' TCP connections (config.php default).
CREATE USER IF NOT EXISTS 'bookstore_user'@'localhost' IDENTIFIED BY 'Bookstore123!';
CREATE USER IF NOT EXISTS 'bookstore_user'@'127.0.0.1' IDENTIFIED BY 'Bookstore123!';

GRANT ALL PRIVILEGES ON bookstore.* TO 'bookstore_user'@'localhost';
GRANT ALL PRIVILEGES ON bookstore.* TO 'bookstore_user'@'127.0.0.1';

FLUSH PRIVILEGES;
