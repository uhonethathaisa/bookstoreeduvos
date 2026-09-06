<?php
/**
 * Wishlist — list saved books, move to cart or remove.
 */
require_once __DIR__ . '/includes/init.php';

require_login();
$uid = (int) current_user()['UserID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = (int) post('book_id');
    if (post('action') === 'remove_wish') {
        $del = db()->prepare('DELETE FROM wishlist_items WHERE UserID = ? AND BookID = ?');
        $del->execute([$uid, $bookId]);
        flash('info', 'Removed from your wishlist.');
        redirect('wishlist.php');
    }
    if (post('action') === 'move_to_cart') {
        $del = db()->prepare('DELETE FROM wishlist_items WHERE UserID = ? AND BookID = ?');
        $del->execute([$uid, $bookId]);
        cart_add($bookId, 1);
        flash('success', 'Moved to your cart.');
        redirect('cart.php');
    }
}

$rows = db()->prepare(
    'SELECT w.DateAdded, b.*,
            (SELECT AVG(r.Rating)  FROM reviews r WHERE r.BookID = b.BookID) AS avgRating,
            (SELECT COUNT(r.ReviewID) FROM reviews r WHERE r.BookID = b.BookID) AS nReviews
       FROM wishlist_items w JOIN books b ON b.BookID = w.BookID
      WHERE w.UserID = ?
      ORDER BY w.DateAdded DESC'
);
$rows->execute([$uid]);
$items = $rows->fetchAll();

$pageTitle = 'My wishlist';
include __DIR__ . '/includes/header.php';
?>

<h1>My wishlist</h1>

<?php if (!$items): ?>
  <div class="empty-state">
    <p>Your wishlist is empty.</p>
    <p><a class="btn btn-dark" href="catalogue.php">Browse the catalogue &rarr;</a></p>
  </div>
<?php else: ?>
  <div class="book-grid">
    <?php foreach ($items as $b): ?>
      <article class="book-card">
        <a class="book-cover-link" href="book.php?id=<?= (int) $b['BookID'] ?>"><?= cover_html($b) ?></a>
        <h3 class="book-title"><a href="book.php?id=<?= (int) $b['BookID'] ?>"><?= e($b['Title']) ?></a></h3>
        <p class="book-author"><?= e($b['Author']) ?></p>
        <?php if ($b['avgRating'] !== null): ?>
          <p class="book-rating"><?= stars_html((float) $b['avgRating']) ?>
            <span class="review-count"><?= (int) $b['nReviews'] ?></span></p>
        <?php endif; ?>
        <p class="book-price"><?= money((float) $b['Price']) ?></p>
        <div class="wish-actions">
          <form method="post" action="cart.php" class="quick-add">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="book_id" value="<?= (int) $b['BookID'] ?>">
            <input type="hidden" name="qty" value="1">
            <input type="hidden" name="next" value="cart.php">
            <button type="submit" class="btn btn-dark btn-sm">Add to cart</button>
          </form>
          <form method="post" action="wishlist.php">
            <input type="hidden" name="action" value="remove_wish">
            <input type="hidden" name="book_id" value="<?= (int) $b['BookID'] ?>">
            <button type="submit" class="btn-link-danger">Remove</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
