<?php
/**
 * AdminInventoryController
 * -----------------------------------------------------------------------------
 * OOP CRUD controller for the `books` catalogue table (inventory management).
 *
 * Schema (as supplied):  Books( BookID PK, Title, Author, Genre, ISBN, Price,
 * Stock, ImageURL ) — implemented by the `books` table in the BookNest DB.
 *
 * SECURITY (in priority order)
 *  - RBAC: the constructor refuses every request unless
 *          $_SESSION['role'] === 'Admin' (HTTP 403).
 *  - SQL injection: every query uses PDO prepared statements.
 *  - Image upload: real MIME sniffing with finfo_file (never trusts the client
 *    extension) against a strict whitelist: image/jpeg, image/png, image/webp.
 *  - XSS: all view output is escaped via the view helpers.
 *  - CSRF: a per-session token is verified on every state-changing POST.
 *  - Data quality: validateBookData() enforces ISBN = exactly 13 digits,
 *    Price > 0 (float), Stock >= 0 (int) before anything reaches the database.
 *
 * Usage (typical front controller):
 *     $ctrl = new AdminInventoryController($pdo);
 *     $ctrl->handleRequest();
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

class AdminInventoryController
{
    protected PDO $pdo;

    /** Whitelisted real MIME types => canonical file extension. */
    protected const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    protected const MAX_UPLOAD_BYTES = 2097152; // 2 MB

    /** @var string[] Validation failures from the most recent attempt. */
    protected array $errors = [];

    /** @var array<string,string> Last submitted input (re-populated on error). */
    protected array $old = [];

    public function __construct(PDO $pdo)
    {
        /* ---- Role-Based Access Control (enforced in the constructor) ---- */
        if (($_SESSION['role'] ?? null) !== 'Admin') {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit('403 Forbidden — administrator access required.');
        }

        $this->pdo = $pdo;
    }

    /* =====================================================================
     *  PUBLIC API
     * =================================================================== */

    /** Small dispatcher so a one-line front controller can drive the module. */
    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'index';

        match ($action) {
            'edit'    => $this->index(),
            'store'   => $this->store(),
            'update'  => $this->update(),
            'destroy' => $this->destroy(),
            default   => $this->index(),
        };
    }

    /** List all books ordered by title (optionally pre-loads one for editing). */
    public function index(): void
    {
        $stmt  = $this->pdo->query('SELECT * FROM books ORDER BY Title');
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $editing = null;
        $editId  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($editId !== null && $editId !== false) {
            $q = $this->pdo->prepare('SELECT * FROM books WHERE BookID = ?');
            $q->execute([$editId]);
            $editing = $q->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $this->render($books, $editing);
    }

    /** Insert a new book (POST action=store). */
    public function store(): void
    {
        if (!$this->csrfOk()) {
            $this->flash('error', 'Security token expired — please try again.');
            $this->redirectToIndex();
        }

        $this->errors = $this->validateBookData($_POST);
        $this->old    = $this->sanitizeOld($_POST);

        $image = $this->handleImageUpload();
        if (!$image['ok']) {
            $this->errors[] = $image['error'];
        }

        if ($this->errors === []) {
            try {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO books (Title, Author, Genre, ISBN, Price, Stock, ImageURL)
                     VALUES (:title, :author, :genre, :isbn, :price, :stock, :image)'
                );
                $stmt->execute([
                    ':title'  => trim((string) $_POST['title']),
                    ':author' => trim((string) $_POST['author']),
                    ':genre'  => trim((string) $_POST['genre']),
                    ':isbn'   => $this->cleanDigits($_POST['isbn']),
                    ':price'  => (float) $_POST['price'],
                    ':stock'  => (int) $_POST['stock'],
                    ':image'  => $image['file'], // null when no file was uploaded
                ]);
            } catch (PDOException $ex) {
                if ($image['file'] !== null) {
                    $this->deleteCoverFile($image['file']); // clean up the orphaned file
                }
                $this->errors[] = $this->dbError($ex);
            }

            if ($this->errors === []) {
                $this->flash('success', 'Book “' . trim((string) $_POST['title']) . '” added to the catalogue.');
                $this->redirectToIndex();
            }
        }

        // Validation or database failure → re-render the form with errors + old values.
        $this->index();
    }

    /** Update an existing book (POST action=update). */
    public function update(): void
    {
        if (!$this->csrfOk()) {
            $this->flash('error', 'Security token expired — please try again.');
            $this->redirectToIndex();
        }

        $id = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
        $existing = $this->findBook((int) $id);
        if (!$existing) {
            $this->flash('error', 'Book not found — it may have been deleted.');
            $this->redirectToIndex();
        }

        $this->errors = $this->validateBookData($_POST);
        $this->old    = $this->sanitizeOld($_POST);

        $image = $this->handleImageUpload();
        if (!$image['ok']) {
            $this->errors[] = $image['error'];
        }

        if ($this->errors === []) {
            try {
                $stmt = $this->pdo->prepare(
                    'UPDATE books
                        SET Title = :title, Author = :author, Genre = :genre,
                            ISBN = :isbn, Price = :price, Stock = :stock,
                            ImageURL = COALESCE(:image, ImageURL)
                      WHERE BookID = :id'
                );
                $stmt->execute([
                    ':title'  => trim((string) $_POST['title']),
                    ':author' => trim((string) $_POST['author']),
                    ':genre'  => trim((string) $_POST['genre']),
                    ':isbn'   => $this->cleanDigits($_POST['isbn']),
                    ':price'  => (float) $_POST['price'],
                    ':stock'  => (int) $_POST['stock'],
                    ':image'  => $image['file'], // null → keep previous cover
                    ':id'     => $existing['BookID'],
                ]);
            } catch (PDOException $ex) {
                if ($image['file'] !== null) {
                    $this->deleteCoverFile($image['file']);
                }
                $this->errors[] = $this->dbError($ex);
            }

            if ($this->errors === []) {
                // A replaced cover is now orphaned — remove the old file.
                if ($image['file'] !== null && !empty($existing['ImageURL'])) {
                    $this->deleteCoverFile((string) $existing['ImageURL']);
                }
                $this->flash('success', 'Book #' . $existing['BookID'] . ' was updated.');
                $this->redirectToIndex();
            }
        }

        $this->index();
    }

    /** Hard-delete a book (POST action=destroy). */
    public function destroy(): void
    {
        if (!$this->csrfOk()) {
            $this->flash('error', 'Security token expired — please try again.');
            $this->redirectToIndex();
        }

        $id = (int) ($_POST['book_id'] ?? $_GET['id'] ?? 0);
        $existing = $this->findBook($id);
        if (!$existing) {
            $this->flash('error', 'Book not found — it may have been deleted already.');
            $this->redirectToIndex();
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM books WHERE BookID = ?');
            $stmt->execute([$existing['BookID']]);
        } catch (PDOException $ex) {
            // Foreign-key constraints protect order/review history — surface, don't crash.
            $this->flash('error', $this->dbError($ex));
            $this->redirectToIndex();
        }

        if (!empty($existing['ImageURL'])) {
            $this->deleteCoverFile((string) $existing['ImageURL']);
        }

        $this->flash('success', 'Book “' . (string) $existing['Title'] . '” deleted permanently.');
        $this->redirectToIndex();
    }

    /* =====================================================================
     *  VALIDATION & SANITISATION
     * =================================================================== */

    /**
     * Validate + normalise submitted book data.
     * @return string[] list of human-readable problems (empty = all good)
     */
    protected function validateBookData(array $d): array
    {
        $problems = [];

        $title  = trim((string) ($d['title'] ?? ''));
        $author = trim((string) ($d['author'] ?? ''));
        $genre  = trim((string) ($d['genre'] ?? ''));
        $isbn   = $this->cleanDigits($d['isbn'] ?? '');
        $price  = (string) ($d['price'] ?? '');
        $stock  = (string) ($d['stock'] ?? '');

        if ($title === '')                    { $problems[] = 'Title is required.'; }
        elseif (mb_strlen($title) > 200)      { $problems[] = 'Title must be 200 characters or fewer.'; }
        if ($author === '')                   { $problems[] = 'Author is required.'; }
        elseif (mb_strlen($author) > 120)     { $problems[] = 'Author must be 120 characters or fewer.'; }
        if ($genre === '')                    { $problems[] = 'Genre is required.'; }

        // ISBN: exactly 13 digits (ISBN-13; no letters, spaces or hyphens).
        if (!preg_match('/^\d{13}$/', $isbn)) { $problems[] = 'ISBN must be exactly 13 digits.'; }

        // Price: positive float.
        if ($price === '' || !is_numeric($price)) {
            $problems[] = 'Price must be a number.';
        } elseif ((float) $price <= 0.0) {
            $problems[] = 'Price must be greater than 0.';
        }

        // Stock: non-negative integer.
        if ($stock === '' || !preg_match('/^\d+$/', $stock)) {
            $problems[] = 'Stock must be a whole number (0 or more).';
        }

        return $problems;
    }

    /** Keep only known scalar fields for safe form re-population. */
    protected function sanitizeOld(array $d): array
    {
        $out = [];
        foreach (['title', 'author', 'genre', 'isbn', 'price', 'stock'] as $k) {
            $out[$k] = (string) ($d[$k] ?? '');
        }
        return $out;
    }

    protected function cleanDigits(mixed $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    /* =====================================================================
     *  IMAGE UPLOAD — MIME-checked via finfo_file (NOT the extension)
     * =================================================================== */

    /**
     * Validate + store an uploaded cover image.
     * @return array{ok: bool, file?: ?string, error?: string}
     *         'file' is null when no upload was supplied (valid for create/update).
     */
    protected function handleImageUpload(): array
    {
        $field = 'cover_image';

        // No file submitted at all → fine (create without cover / keep existing on update).
        if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'file' => null];
        }

        $file = $_FILES[$field];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded image is too large (max 2 MB).',
                UPLOAD_ERR_PARTIAL                        => 'The image upload was interrupted — try again.',
                default                                   => 'The image could not be uploaded.',
            };
            return ['ok' => false, 'error' => $msg];
        }

        if ((int) $file['size'] > self::MAX_UPLOAD_BYTES) {
            return ['ok' => false, 'error' => 'The uploaded image is too large (max 2 MB).'];
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'The image was not received as a valid upload.'];
        }

        // Real content sniffing — never trust the client-supplied extension/type.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);

        if (!isset(self::ALLOWED_MIME[$mime])) {
            return ['ok' => false, 'error' => 'File type not allowed — only JPEG, PNG or WEBP images may be used as covers.'];
        }

        $extension = self::ALLOWED_MIME[$mime];
        $filename  = 'cover_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

        $uploadDir = defined('BOOK_COVER_UPLOAD_DIR')
            ? constant('BOOK_COVER_UPLOAD_DIR')
            : dirname(__DIR__) . '/public/uploads/covers';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            return ['ok' => false, 'error' => 'Cover upload directory could not be created.'];
        }

        if (!move_uploaded_file($file['tmp_name'], rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename)) {
            return ['ok' => false, 'error' => 'The image could not be saved to the server.'];
        }

        return ['ok' => true, 'file' => $filename];
    }

    protected function findBook(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM books WHERE BookID = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Remove an orphaned cover file (best-effort; rejects path traversal). */
    protected function deleteCoverFile(string $filename): void
    {
        if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return; // refuse anything that is not a plain stored filename
        }
        $uploadDir = defined('BOOK_COVER_UPLOAD_DIR')
            ? constant('BOOK_COVER_UPLOAD_DIR')
            : dirname(__DIR__) . '/public/uploads/covers';
        $path = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Human-friendly message for expected DB constraint failures. */
    protected function dbError(PDOException $ex): string
    {
        return match ((string) $ex->getCode()) {
            '23000' => 'The book could not be saved: this ISBN already exists, or the book is still referenced by orders/reviews.',
            default => 'The database rejected the request: ' . $ex->getMessage(),
        };
    }

    /* =====================================================================
     *  HTTP / SESSION HELPERS
     * =================================================================== */

    protected function render(array $books, ?array $editing): void
    {
        $errors = $this->errors;
        $old    = $this->old;
        $csrf   = $this->csrfToken();
        require dirname(__DIR__) . '/views/admin/inventory.php';
    }

    protected function redirectToIndex(): never
    {
        header('Location: admin-inventory.php');
        exit;
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['inventory_flash'][] = ['type' => $type, 'msg' => $message];
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['inventory_csrf'])) {
            $_SESSION['inventory_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['inventory_csrf'];
    }

    protected function csrfOk(): bool
    {
        $sent = (string) ($_POST['csrf_token'] ?? '');
        return isset($_SESSION['inventory_csrf'])
            && hash_equals($_SESSION['inventory_csrf'], $sent);
    }

    protected function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

/* ============================================================================
 *  Guarded global helpers used by the view (and available anywhere else).
 *  function_exists() guards keep this portable if the controller or view is
 *  dropped into another MVC application.
 * ========================================================================== */
if (!function_exists('inv_esc')) {
    function inv_esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('inv_money')) {
    function inv_money(float $amount): string
    {
        return 'R' . number_format($amount, 2, '.', ',');
    }
}
if (!function_exists('inv_upload_url')) {
    function inv_upload_url(string $filename): string
    {
        return '/uploads/covers/' . rawurlencode($filename);
    }
}




