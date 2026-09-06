<?php
/**
 * API — asynchronous cart operations used by js/main.js (quick-add buttons).
 * POST { action: add|update|remove|clear, book_id?, qty?, next? }
 * Returns JSON: { ok, count, message?, subtotal? }
 */
require_once dirname(__DIR__) . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'POST required.'], 405);
}

$action = post('action');
$bookId = (int) post('book_id');
$qty    = max(1, (int) post('qty', '1'));

switch ($action) {
    case 'add':
        $stmt = db()->prepare('SELECT BookID, Title, Stock FROM books WHERE BookID = ?');
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();
        if (!$book) {
            json_response(['ok' => false, 'message' => 'Book not found.'], 404);
        }
        if ((int) $book['Stock'] <= 0) {
            json_response(['ok' => false, 'message' => 'Sorry, "' . $book['Title'] . '" is out of stock.', 'count' => cart_count()], 409);
        }
        cart_add($bookId, $qty);
        json_response([
            'ok'      => true,
            'count'   => cart_count(),
            'message' => 'Added "' . $book['Title'] . '" to your cart.',
        ]);
        break;

    case 'update':
        if ($bookId <= 0) {
            json_response(['ok' => false, 'message' => 'Missing book_id.'], 400);
        }
        cart_set_qty($bookId, $qty);
        json_response(['ok' => true, 'count' => cart_count()]);
        break;

    case 'remove':
        cart_remove($bookId);
        json_response(['ok' => true, 'count' => cart_count()]);
        break;

    case 'clear':
        cart_clear();
        clear_promo_session();
        json_response(['ok' => true, 'count' => 0]);
        break;

    default:
        json_response(['ok' => false, 'message' => 'Unknown action.'], 400);
}
