<?php
/**
 * API — AJAX catalogue filtering (dynamic filtering without full page reload).
 * Mirrors catalogue.php using includes/catalog.php.
 * GET  api/books.php?q=&genre=&author=&rating=&min_price=&max_price=&sort=&page=
 * Returns JSON: { ok, html, total, page, pages }
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

$f = [
    'q'         => get('q'),
    'genre'     => get('genre'),
    'author'    => get('author'),
    'rating'    => get('rating'),
    'min_price' => get('min_price'),
    'max_price' => get('max_price'),
    'sort'      => get('sort', 'bestsell'),
    'page'      => get('page', '1'),
];

$res = catalog_query($f);

json_response([
    'ok'    => true,
    'html'  => catalog_grid_html($res['rows']) . pager_html($res['page'], $res['pages']),
    'total' => $res['total'],
    'page'  => $res['page'],
    'pages' => $res['pages'],
]);
