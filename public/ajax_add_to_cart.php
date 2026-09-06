<?php
/**
 * AJAX "Add to Cart" endpoint.
 * -----------------------------------------------------------------------------
 * Receives:  POST { action: 'add' | 'set', book_id, quantity }
 * Returns:   JSON  { success, message, bookId, cartQty, cartCount, stock }
 * Statuses:  200 ok · 400 bad input · 404 unknown book · 405 wrong method
 *            409 stock limit reached (or out of stock)
 *
 * Wire-up (front-end): fetch('ajax_add_to_cart.php', { method: 'POST', body })
 * — see js/main.js → initAddToCartModal().
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';      // session + PDO singleton + cart helpers
require_once dirname(__DIR__) . '/controllers/CartController.php';

(new CartController(DatabaseConnection::getInstance()->pdo()))->handle();
