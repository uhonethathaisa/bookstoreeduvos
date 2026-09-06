<?php
/**
 * CartController
 * -----------------------------------------------------------------------------
 * JSON API controller for AJAX "Add to Cart".
 *
 * Flow (front-end → back-end):
 *   click "Add to Cart" → modal opens → "Update Cart & Checkout" →
 *   POST { action, book_id, quantity } → CartController::add() /
 *   CartController::setQuantity() → JSON { success, message, cartCount, … }.
 *
 * Validation performed here (never trust the client):
 *   - method must be POST
 *   - BookID must exist
 *   - Quantity must be an integer between 1 and the available stock
 *     (an "add" also accounts for copies already in the session cart)
 *
 * Session cart functions (cart_add / cart_set_qty / cart_count / cart) are
 * provided by the application bootstrap (includes/functions.php). The cart is
 * session-based by design — see the D1 glossary (Cart class / D6 store).
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

class CartController
{
    protected PDO $pdo;

    /** Hard upper bound per line item (safety net; stock usually lower). */
    protected const MAX_QTY_PER_LINE = 99;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Dispatch for the AJAX entry point. */
    public function handle(): never
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['success' => false, 'message' => 'POST request required.'], 405);
        }

        $action = (string) ($_POST['action'] ?? '');
        match ($action) {
            'add' => $this->add(),
            'set' => $this->setQuantity(),
            default => $this->json(['success' => false, 'message' => 'Unknown action.'], 400),
        };
    }

    /** action=add — add $qty more copies of a book to the cart. */
    public function add(): never
    {
        $bookId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
        $qty    = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

        if ($bookId === null || $bookId === false || $bookId <= 0) {
            $this->json(['success' => false, 'message' => 'Missing or invalid book id.'], 400);
        }
        if ($qty === null || $qty === false || $qty < 1) {
            $this->json(['success' => false, 'message' => 'Quantity must be at least 1.'], 400);
        }

        $book = $this->findBook($bookId);
        if (!$book) {
            $this->json(['success' => false, 'message' => 'Book not found.'], 404);
        }

        $stock     = (int) $book['Stock'];
        $title     = (string) $book['Title'];
        $alreadyIn = $this->currentQty($bookId);

        if ($stock <= 0) {
            $this->json(['success' => false, 'message' => '“' . $title . '” is currently out of stock.'], 409);
        }
        if ($alreadyIn + $qty > $stock) {
            $this->json([
                'success' => false,
                'message' => 'Only ' . $stock . ' copies of “' . $title . '” are in stock'
                    . ($alreadyIn > 0 ? ' and you already have ' . $alreadyIn . ' in your cart' : '') . '.',
            ], 409);
        }

        cart_add($bookId, $qty);

        $this->json([
            'success'  => true,
            'message'  => '“' . $title . '” added to your cart.',
            'bookId'   => $bookId,
            'cartQty'  => $this->currentQty($bookId), // exact line quantity now
            'cartCount'=> cart_count(),
            'stock'    => $stock,
        ]);
    }

    /** action=set — set the EXACT line quantity (used when the modal quantity changes). */
    public function setQuantity(): never
    {
        $bookId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
        $qty    = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

        if ($bookId === null || $bookId === false || $bookId <= 0) {
            $this->json(['success' => false, 'message' => 'Missing or invalid book id.'], 400);
        }
        if ($qty === null || $qty === false || $qty < 1) {
            $this->json(['success' => false, 'message' => 'Quantity must be at least 1.'], 400);
        }
        if ($qty > self::MAX_QTY_PER_LINE) {
            $this->json(['success' => false, 'message' => 'Maximum quantity per book is ' . self::MAX_QTY_PER_LINE . '.'], 400);
        }

        $book = $this->findBook($bookId);
        if (!$book) {
            $this->json(['success' => false, 'message' => 'Book not found.'], 404);
        }

        $stock = (int) $book['Stock'];
        if ($stock <= 0) {
            $this->json(['success' => false, 'message' => '“' . (string) $book['Title'] . '” is currently out of stock.'], 409);
        }
        if ($qty > $stock) {
            $this->json(['success' => false, 'message' => 'Only ' . $stock . ' copies are in stock.'], 409);
        }

        cart_set_qty($bookId, $qty);

        $this->json([
            'success'   => true,
            'message'   => 'Cart updated.',
            'bookId'    => $bookId,
            'cartQty'   => $qty,
            'cartCount' => cart_count(),
            'stock'     => $stock,
        ]);
    }

    /* ========================= helpers ================================= */

    protected function findBook(int $bookId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT BookID, Title, Stock, Price FROM books WHERE BookID = ?');
        $stmt->execute([$bookId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Current quantity of a book in the session cart (0 if absent). */
    protected function currentQty(int $bookId): int
    {
        return (int) (cart()[$bookId] ?? 0);
    }

    /** Send a JSON response and stop execution. */
    protected function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
