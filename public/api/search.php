<?php
/**
 * API — search autocomplete suggestions.
 * GET  api/search.php?q=silen
 * Returns JSON: [ {BookID, Title, Author}, ... ]  (max 8)
 */
require_once dirname(__DIR__) . '/includes/init.php';

$q = get('q');
if (mb_strlen($q) < 2) {
    json_response([], 200);
}

$stmt = db()->prepare(
    'SELECT BookID, Title, Author FROM books
      WHERE Title LIKE ? OR Author LIKE ? OR ISBN LIKE ?
      ORDER BY Title LIMIT 8'
);
$like = '%' . $q . '%';
$stmt->execute([$like, $like, $like]);

json_response($stmt->fetchAll());
