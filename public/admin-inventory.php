<?php
/**
 * Front controller — Admin Inventory Management (MVC demo module)
 * -----------------------------------------------------------------------------
 * URL:   /admin-inventory.php
 * Route: action=index|edit   (GET)   ·  action=store|update|destroy  (POST)
 *
 * Bootstraps the shared BookNest database/session, maps the logged-in role onto
 * the `$_SESSION['role']` key used by the controller's RBAC guard, and hands the
 * request to AdminInventoryController.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

// Only an administrator may open the module at all.
if (!is_logged_in()) {
    redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin-inventory.php'));
}
$_SESSION['role'] = current_user()['Role'] ?? 'Customer';

// Upload targets (controller falls back to these paths when absent).
define('BOOK_COVER_UPLOAD_DIR', __DIR__ . '/uploads/covers');
define('BOOK_COVER_UPLOAD_URL', '/uploads/covers');
if (!is_dir(BOOK_COVER_UPLOAD_DIR)) {
    mkdir(BOOK_COVER_UPLOAD_DIR, 0775, true);
}

require_once dirname(__DIR__) . '/controllers/AdminInventoryController.php';

$controller = new AdminInventoryController(DatabaseConnection::getInstance()->pdo());
$controller->handleRequest();
