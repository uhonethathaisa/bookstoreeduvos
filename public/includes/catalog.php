<?php
/**
 * Catalogue query + card/pager fragment builders.
 * Used by: catalogue.php (server render) and api/books.php (AJAX filter demo).
 */
declare(strict_types=1);

/** One book card as an HTML string. */
function book_card_html(array $b): string
{
    $rating  = (isset($b['avgRating']) && $b['avgRating'] !== null) ? (float) $b['avgRating'] : null;
    $reviews = (int) ($b['nReviews'] ?? 0);
    $id      = (int) $b['BookID'];
    $stock   = (int) $b['Stock'];

    $html  = '<article class="book-card">';
    $html .= '<a class="book-cover-link" href="book.php?id=' . $id . '" tabindex="-1" aria-hidden="true">'
             . cover_html($b) . '</a>';
    $html .= '<h3 class="book-title"><a href="book.php?id=' . $id . '">' . e($b['Title']) . '</a></h3>';
    $html .= '<p class="book-author">' . e($b['Author']) . '</p>';
    if ($rating !== null) {
        $html .= '<p class="book-rating">' . stars_html($rating)
               . ' <span class="review-count">' . $reviews . '</span></p>';
    }
    $html .= '<p class="book-price">' . money((float) $b['Price']) . '</p>';
    $html .= $stock > 0
        ? '<form class="quick-add" method="post" action="cart.php">
             <input type="hidden" name="action" value="add">
             <input type="hidden" name="book_id" value="' . $id . '">
             <input type="hidden" name="qty" value="1">
             <input type="hidden" name="next" value="' . e($_SERVER['REQUEST_URI'] ?? '') . '">
             <button type="submit" class="btn btn-dark btn-sm">Add to cart</button>
           </form>'
        : '<p class="out-of-stock">Out of stock</p>';
    $html .= '</article>';
    return $html;
}

/** Run a catalogue search with filters + sorting + pagination. */
function catalog_query(array $f): array
{
    $q      = trim((string) ($f['q'] ?? ''));
    $genre  = trim((string) ($f['genre'] ?? ''));
    $author = trim((string) ($f['author'] ?? ''));
    $rating = (float) ($f['rating'] ?? 0);
    $minP   = ($f['min_price'] ?? '') !== '' ? (float) $f['min_price'] : null;
    $maxP   = ($f['max_price'] ?? '') !== '' ? (float) $f['max_price'] : null;
    $sort   = (string) ($f['sort'] ?? 'bestsell');
    $page   = max(1, (int) ($f['page'] ?? 1));

    $where  = [];
    $params = [];

    if ($q !== '') {
        $where[] = '(b.Title LIKE ? OR b.Author LIKE ? OR b.ISBN LIKE ?)';
        array_push($params, "%$q%", "%$q%", "%$q%");
    }
    if ($genre !== '') {
        $where[] = 'b.Genre = ?';
        $params[] = $genre;
    }
    if ($author !== '') {
        $where[] = 'b.Author = ?';
        $params[] = $author;
    }
    if ($minP !== null) {
        $where[] = 'b.Price >= ?';
        $params[] = $minP;
    }
    if ($maxP !== null) {
        $where[] = 'b.Price <= ?';
        $params[] = $maxP;
    }
    if ($rating >= 1) {
        $where[] = 'b.BookID IN (SELECT BookID FROM reviews GROUP BY BookID HAVING AVG(Rating) >= ?)';
        $params[] = $rating;
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $core     = ' FROM books b LEFT JOIN reviews r ON r.BookID = b.BookID'
              . $whereSql . ' GROUP BY b.BookID';

    // Total matching books
    $stmt = db()->prepare('SELECT COUNT(*) FROM (SELECT b.BookID' . $core . ') AS matched');
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $pages = max(1, (int) ceil($total / PAGE_SIZE));
    if ($page > $pages) {
        $page = $pages;
    }

    $orderBy = match ($sort) {
        'price_asc'  => 'b.Price ASC, b.Title ASC',
        'price_desc' => 'b.Price DESC, b.Title ASC',
        'new'        => 'b.BookID DESC',
        default      => 'nReviews DESC, avgRating DESC, b.BookID ASC',
    };

    $sql  = 'SELECT b.*, AVG(r.Rating) AS avgRating, COUNT(r.ReviewID) AS nReviews'
          . $core . ' ORDER BY ' . $orderBy
          . ' LIMIT ' . PAGE_SIZE . ' OFFSET ' . (($page - 1) * PAGE_SIZE);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return [
        'rows'  => $stmt->fetchAll(),
        'total' => $total,
        'page'  => $page,
        'pages' => $pages,
    ];
}

/** HTML for the results grid. */
function catalog_grid_html(array $rows): string
{
    if (!$rows) {
        return '<p class="no-results">No books match your filters. <a href="catalogue.php">Clear filters</a>.</p>';
    }
    return '<div class="book-grid">' . implode('', array_map('book_card_html', $rows)) . '</div>';
}

/** HTML for the pager (preserves current filters; overrides page). */
function pager_html(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $keep = $_GET;
    unset($keep['page']);
    $qs = http_build_query($keep);

    $href = static function (int $p) use ($qs): string {
        $parts = [];
        if ($qs !== '') { $parts[] = $qs; }
        if ($p !== 1)   { $parts[] = 'page=' . $p; }
        return $parts ? '?' . implode('&', $parts) : '';
    };

    $html = '<nav class="pager" aria-label="Catalogue pages">';
    $html .= $page > 1
        ? '<a class="page-link" href="' . $href($page - 1) . '">&laquo; Prev</a>'
        : '<span class="page-link disabled">&laquo; Prev</span>';
    for ($i = 1; $i <= $pages; $i++) {
        $html .= $i === $page
            ? '<span class="page-link current">' . $i . '</span>'
            : '<a class="page-link" href="' . $href($i) . '">' . $i . '</a>';
    }
    $html .= $page < $pages
        ? '<a class="page-link" href="' . $href($page + 1) . '">Next &raquo;</a>'
        : '<span class="page-link disabled">Next &raquo;</span>';
    $html .= '</nav>';
    return $html;
}
