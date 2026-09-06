<?php
/**
 * Shared helpers — output, auth, flash, session cart, promotions, UI snippets.
 */
declare(strict_types=1);

/* ================= Output helpers ================= */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string
{
    return 'R' . number_format($amount, 2);
}

function stars_html(float $rating): string
{
    $full = (int) round($rating);
    $out  = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= $i <= $full ? '&#9733;' : '&#9734;';
    }
    return '<span class="stars" aria-label="' . number_format($rating, 1) . ' out of 5">' . $out . '</span>';
}

/* ================= Auth ================= */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']['UserID']);
}

function is_admin(): bool
{
    return ($_SESSION['user']['Role'] ?? '') === 'Admin';
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'UserID' => (int) $u['UserID'],
        'Name'   => $u['Name'],
        'Email'  => $u['Email'],
        'Role'   => $u['Role'],
    ];
}

function logout_user(): void
{
    unset($_SESSION['user']);
    session_regenerate_id(true);
}

function require_login(): void
{
    if (!is_logged_in()) {
        $login = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false
            ? '../login.php'
            : 'login.php';
        flash('info', 'Please log in to continue.');
        redirect($login . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Access denied — administrators only.');
    }
}

/* ================= Flash messages ================= */

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

/** Echoes any queued flash messages and clears the queue. */
function render_flashes(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    foreach ($_SESSION['flash'] as $f) {
        $cls = in_array($f['type'], ['success', 'error', 'info'], true) ? $f['type'] : 'info';
        echo '<div class="flash flash-' . $cls . '" role="status">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/* ================= Session cart =================
 * Cart lives in $_SESSION (transient — see D1 glossary §3 / CRC card 4).
 * Shape: $_SESSION['cart'] = [ bookID => qty ]
 */
function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    return array_sum(cart());
}

function cart_add(int $bookId, int $qty): void
{
    $qty = max(1, $qty);
    $_SESSION['cart'][$bookId] = ($_SESSION['cart'][$bookId] ?? 0) + $qty;
}

function cart_set_qty(int $bookId, int $qty): void
{
    if ($qty <= 0) {
        cart_remove($bookId);
        return;
    }
    $_SESSION['cart'][$bookId] = $qty;
}

function cart_remove(int $bookId): void
{
    unset($_SESSION['cart'][$bookId]);
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

function cart_empty(): bool
{
    return cart_count() === 0;
}

/**
 * Fetch full book rows for every line in the cart.
 * @return array{rows: array<int,array{book:array,qty:int,line:float}>, subtotal:float, count:int}
 */
function cart_items(): array
{
    $cart   = cart();
    $result = ['rows' => [], 'subtotal' => 0.0, 'count' => cart_count()];
    if (empty($cart)) {
        return $result;
    }
    $ids   = array_keys($cart);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = db()->prepare("SELECT * FROM books WHERE BookID IN ($marks)");
    $stmt->execute($ids);

    foreach ($stmt as $book) {
        $qty   = (int) $cart[$book['BookID']];
        $price = (float) $book['Price'];
        $result['rows'][] = [
            'book' => $book,
            'qty'  => $qty,
            'line' => round($price * $qty, 2),
        ];
        $result['subtotal'] += $price * $qty;
    }
    $result['subtotal'] = round($result['subtotal'], 2);
    return $result;
}

/* ================= Promotions ================= */

/** @return array|null promo row if the code is valid (exists + within validity). */
function validate_promo(string $code): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM promotions
          WHERE DiscountCode = ?
            AND ValidityStart <= NOW()
            AND ValidityEnd   >= NOW()'
    );
    $stmt->execute([trim($code)]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function promo_in_session(): ?array
{
    $code = $_SESSION['promo'] ?? null;
    if ($code === null) {
        return null;
    }
    return validate_promo($code); // re-check validity on every request
}

function set_promo_session(string $code): ?array
{
    $promo = validate_promo($code);
    if ($promo) {
        $_SESSION['promo'] = $promo['DiscountCode'];
    } else {
        unset($_SESSION['promo']);
    }
    return $promo;
}

function clear_promo_session(): void
{
    unset($_SESSION['promo']);
}

/**
 * Available shipping methods with cost / business-day ETA.
 */
function shipping_methods(): array
{
    return [
        'Standard' => ['label' => 'Standard courier',      'note' => '3&ndash;5 working days nationwide', 'days' => 4, 'cost' => SHIPPING_FLAT, 'freeOver' => FREE_SHIPPING_OVER],
        'Express'  => ['label' => 'Express courier',       'note' => '1&ndash;2 working days (Gauteng &amp; Western Cape same-day hubs)', 'days' => 2, 'cost' => 129.00, 'freeOver' => null],
        'Priority' => ['label' => 'Priority same-day',     'note' => 'Same-day metro delivery (Joburg / Cape Town / Durban)', 'days' => 1, 'cost' => 199.00, 'freeOver' => null],
    ];
}

function ship_cost_for(float $subtotal, string $method = 'Standard'): float
{
    $m = shipping_methods()[$method] ?? shipping_methods()['Standard'];
    if ($m['freeOver'] !== null && $subtotal >= (float) $m['freeOver']) {
        return 0.0;
    }
    return (float) $m['cost'];
}

/** Add working days (Mon–Fri) to a date. */
function add_business_days(DateTimeImmutable $from, int $days): DateTimeImmutable
{
    $d     = $from;
    $added = 0;
    while ($added < $days) {
        $d = $d->modify('+1 day');
        if ((int) $d->format('N') <= 5) {
            $added++;
        }
    }
    return $d;
}

/** Estimated delivery date (Y-m-d) for a shipping method, from today. */
function est_delivery_date(string $method = 'Standard'): string
{
    $m = shipping_methods()[$method] ?? shipping_methods()['Standard'];
    return add_business_days(new DateTimeImmutable('now'), (int) $m['days'])->format('Y-m-d');
}

/**
 * Order totals for a given subtotal with the active promo and shipping method.
 * @return array{subtotal:float,discount:float,shipping:float,total:float,promo:?array,ship_method:string}
 */
function order_totals(float $subtotal, string $method = 'Standard'): array
{
    $promo = promo_in_session();
    $base  = ['subtotal' => 0.0, 'discount' => 0.0, 'shipping' => 0.0, 'total' => 0.0, 'promo' => null, 'ship_method' => $method];
    if ($subtotal <= 0) {
        return $base;
    }
    $discount = 0.0;
    if ($promo) {
        $discount = round($subtotal * ((float) $promo['DiscountValue'] / 100.0), 2);
    }
    $afterDiscount = round($subtotal - $discount, 2);
    $shipping      = ship_cost_for($afterDiscount, $method);
    $total         = round($afterDiscount + $shipping, 2);
    return [
        'subtotal'    => $subtotal,
        'discount'    => $discount,
        'shipping'    => $shipping,
        'total'       => $total,
        'promo'       => $promo,
        'ship_method' => $method,
    ];
}


/* ================= Catalogue helpers ================= */

function genres(): array
{
    static $genres = null;
    if ($genres === null) {
        $genres = db()->query('SELECT DISTINCT Genre FROM books ORDER BY Genre')->fetchAll(PDO::FETCH_COLUMN);
    }
    return $genres;
}

function authors(): array
{
    static $authors = null;
    if ($authors === null) {
        $authors = db()->query('SELECT DISTINCT Author FROM books ORDER BY Author')->fetchAll(PDO::FETCH_COLUMN);
    }
    return $authors;
}

/** Active promo banners for the homepage. */
function active_promos(): array
{
    $stmt = db()->query(
        'SELECT * FROM promotions
          WHERE ValidityStart <= NOW() AND ValidityEnd >= NOW()
          ORDER BY PromoID'
    );
    return $stmt->fetchAll();
}

/* ================= Book cover placeholders =================
 * No image files required: a gradient tile + title initials.
 */
function cover_style(int $seed): string
{
    $palettes = [
        ['#ff9966', '#ff5e62'],
        ['#56ccf2', '#2f80ed'],
        ['#a18cd1', '#fbc2eb'],
        ['#43e97b', '#38f9d7'],
        ['#fa709a', '#fee140'],
        ['#30cfd0', '#330867'],
        ['#f83600', '#f9d423'],
        ['#5ee7df', '#b490ca'],
    ];
    [$a, $b] = $palettes[$seed % count($palettes)];
    return "linear-gradient(135deg, {$a}, {$b})";
}

function cover_initials(string $title): string
{
    $words = preg_split('/\s+/', trim($title)) ?: [];
    $take  = array_slice(array_filter($words, static fn($w) => $w !== 'The' && $w !== 'A' && $w !== 'An'), 0, 2);
    if (count($take) === 0) {
        $take = array_slice($words, 0, 2);
    }
    return implode('', array_map(static fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), $take));
}

/** Render a <div class="book-cover"> HTML fragment. */
function cover_html(array $book, string $extra = ''): string
{
    $style = cover_style((int) $book['BookID']);
    $init  = cover_initials((string) $book['Title']);
    $title = e($book['Title']);
    return '<div class="book-cover ' . e($extra) . '" style="background:' . $style . '" title="' . $title . '"><span>' . e($init) . '</span></div>';
}

/* ================= URLs / assets ================= */
/**
 * Link to a storefront page that works from both /public and /public/admin.
 */
function url(string $path): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $prefix = strpos($script, '/admin/') !== false ? '../' : '';
    return $prefix . $path;
}

function asset(string $path): string
{
    return url($path);
}

/* ================= Input helpers ================= */

function post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function get(string $key, string $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function redirect_back(): void
{
    redirect(get('next', 'index.php'));
}

