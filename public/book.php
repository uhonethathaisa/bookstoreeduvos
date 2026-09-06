<?php
/**
 * Book details — cover, metadata, synopsis, reviews, wishlist + add to cart.
 */
require_once __DIR__ . '/includes/init.php';

$id = (int) get('id');

/* ---------------- POST actions ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $action = post('action');

    if ($action === 'add_review') {
        require_login();
        $rating  = (int) post('rating');
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if ($rating < 1 || $rating > 5) {
            flash('error', 'Please choose a rating between 1 and 5 stars.');
        } elseif (mb_strlen($comment) > 1000) {
            flash('error', 'Your review comment must be under 1000 characters.');
        } else {
            $stmt = db()->prepare('INSERT INTO reviews (UserID, BookID, Rating, Comment) VALUES (?, ?, ?, ?)');
            $stmt->execute([current_user()['UserID'], $id, $rating, $comment !== '' ? $comment : null]);
            flash('success', 'Thank you! Your review has been published.');
        }
        redirect('book.php?id=' . $id . '#reviews');
    }

    if ($action === 'toggle_wishlist') {
        require_login();
        $uid = (int) current_user()['UserID'];
        $in  = db()->prepare('SELECT 1 FROM wishlist_items WHERE UserID = ? AND BookID = ?');
        $in->execute([$uid, $id]);
        if ($in->fetch()) {
            $del = db()->prepare('DELETE FROM wishlist_items WHERE UserID = ? AND BookID = ?');
            $del->execute([$uid, $id]);
            flash('info', 'Removed from your wishlist.');
        } else {
            $add = db()->prepare('INSERT INTO wishlist_items (UserID, BookID) VALUES (?, ?)');
            $add->execute([$uid, $id]);
            flash('success', 'Saved to your wishlist.');
        }
        redirect('book.php?id=' . $id);
    }
}

/* ---------------- Fetch data ---------------- */
if ($id <= 0) {
    redirect('catalogue.php');
}

$stmt = db()->prepare(
    'SELECT b.*, AVG(r.Rating) AS avgRating, COUNT(r.ReviewID) AS nReviews
       FROM books b LEFT JOIN reviews r ON r.BookID = b.BookID
      WHERE b.BookID = ?
      GROUP BY b.BookID'
);
$stmt->execute([$id]);
$book = $stmt->fetch();
if (!$book) {
    http_response_code(404);
    $pageTitle = 'Book not found';
    include __DIR__ . '/includes/header.php';
    echo '<p class="no-results">We could not find that book. <a href="catalogue.php">Back to the catalogue</a>.</p>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $book['Title'];

$stmt = db()->prepare(
    'SELECT r.*, u.Name AS reviewer
       FROM reviews r JOIN users u ON u.UserID = r.UserID
      WHERE r.BookID = ?
      ORDER BY r.ReviewDate DESC
      LIMIT 30'
);
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

$dist = db()->prepare('SELECT Rating, COUNT(*) AS c FROM reviews WHERE BookID = ? GROUP BY Rating');
$dist->execute([$id]);
$distMap = [];
foreach ($dist as $d) { $distMap[(int) $d['Rating']] = (int) $d['c']; }

$avg    = $book['avgRating'] !== null ? (float) $book['avgRating'] : null;
$nRev   = (int) $book['nReviews'];
$stock  = (int) $book['Stock'];

$inWishlist = false;
if (is_logged_in()) {
    $w = db()->prepare('SELECT 1 FROM wishlist_items WHERE UserID = ? AND BookID = ?');
    $w->execute([(int) current_user()['UserID'], $id]);
    $inWishlist = (bool) $w->fetch();
}

include __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
  <a href="index.php">Home</a> &rsaquo;
  <a href="catalogue.php">Catalogue</a> &rsaquo;
  <a href="catalogue.php?genre=<?= urlencode($book['Genre']) ?>"><?= e($book['Genre']) ?></a> &rsaquo;
  <span><?= e($book['Title']) ?></span>
</nav>

<div class="book-detail">
  <!-- Cover & actions -->
  <aside class="book-detail-cover">
    <?= cover_html($book, 'cover-large') ?>
    <p class="stock-note"><?= $stock > 0 ? 'In stock — ' . $stock . ' copies available' : '<span class="out-of-stock">Out of stock</span>' ?></p>

    <form method="post" action="cart.php" class="quick-add js-add-form">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="book_id" value="<?= $id ?>">
      <input type="hidden" name="next" value="cart.php">
      <div class="qty-picker">
        <label for="qty">Qty</label>
        <input type="number" id="qty" name="qty" value="1" min="1" max="<?= max(1, $stock) ?>">
      </div>
      <button type="submit" class="btn btn-dark btn-block js-add-to-cart"
              data-book-id="<?= $id ?>"
              data-book-title="<?= e($book['Title']) ?>"
              data-book-stock="<?= $stock ?>"
              <?= $stock > 0 ? '' : 'disabled' ?>>Add to cart</button>
    </form>

    <form method="post" action="book.php?id=<?= $id ?>">
      <input type="hidden" name="action" value="toggle_wishlist">
      <button type="submit" class="btn btn-light btn-block">
        <?= $inWishlist ? '&#10003; In wishlist (click to remove)' : '&#9825; Add to wishlist' ?>
      </button>
    </form>
  </aside>

  <!-- Details -->
  <section class="book-detail-info">
    <h1><?= e($book['Title']) ?></h1>
    <p class="byline">by <a href="catalogue.php?author=<?= urlencode($book['Author']) ?>"><?= e($book['Author']) ?></a></p>

    <dl class="meta-list">
      <div><dt>ISBN</dt><dd><?= e($book['ISBN']) ?></dd></div>
      <div><dt>Genre</dt><dd><?= e($book['Genre']) ?></dd></div>
      <div><dt>Price</dt><dd class="price"><?= money((float) $book['Price']) ?></dd></div>
      <?php if ($avg !== null): ?>
        <div><dt>Rating</dt><dd><?= stars_html($avg) ?> <?= number_format($avg, 1) ?> (<?= $nRev ?> review<?= $nRev === 1 ? '' : 's' ?>)</dd></div>
      <?php endif; ?>
    </dl>

    <h2>Synopsis</h2>
    <p class="synopsis"><?= nl2br(e($book['Synopsis'] ?? 'No synopsis available for this title yet.')) ?></p>

    <blockquote class="preview-excerpt">
      <span class="muted">Preview excerpt</span>
      <p>&ldquo;<?= e(mb_strimwidth(strip_tags($book['Synopsis'] ?? ''), 0, 220, '…')) ?>&rdquo;</p>
    </blockquote>
  </section>
</div>

<!-- ================= Reviews ================= -->
<section class="reviews-section" id="reviews">
  <h2>Customer reviews <?= $nRev > 0 ? '<span class="muted">(' . $nRev . ')</span>' : '' ?></h2>

  <?php if ($avg !== null): ?>
  <div class="rating-summary">
    <div class="big-rating">
      <strong><?= number_format($avg, 1) ?></strong>
      <?= stars_html($avg) ?>
      <span class="muted">average across <?= $nRev ?> review<?= $nRev === 1 ? '' : 's' ?></span>
    </div>
    <div class="rating-bars">
      <?php for ($i = 5; $i >= 1; $i--): $c = $distMap[$i] ?? 0; $pct = $nRev ? round($c / $nRev * 100) : 0; ?>
        <div class="bar-row"><span><?= $i ?>&#9733;</span>
          <div class="bar"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
          <span class="bar-count"><?= $c ?></span>
        </div>
      <?php endfor; ?>
    </div>
  </div>
  <?php else: ?>
    <p class="muted">No reviews yet for this title.</p>
  <?php endif; ?>

  <?php if ($reviews): ?>
  <ul class="review-list">
    <?php foreach ($reviews as $rev): ?>
      <li class="review-item">
        <div class="review-head">
          <strong><?= e($rev['reviewer']) ?></strong>
          <?= stars_html((float) $rev['Rating']) ?>
          <span class="muted"><?= e(date('j M Y', strtotime($rev['ReviewDate']))) ?></span>
        </div>
        <?php if ($rev['Comment']): ?><p><?= e($rev['Comment']) ?></p><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <div class="review-form-wrap">
    <?php if (is_logged_in()): ?>
      <h3>Write a review</h3>
      <form method="post" action="book.php?id=<?= $id ?>#reviews" class="review-form" data-validate>
        <input type="hidden" name="action" value="add_review">
        <div class="field">
          <label for="rating">Your rating</label>
          <select name="rating" id="rating" required>
            <option value="">— choose —</option>
            <?php for ($i = 5; $i >= 1; $i--): ?>
              <option value="<?= $i ?>"><?= $i ?> star<?= $i === 1 ? '' : 's' ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="field">
          <label for="comment">Comment <span class="muted">(optional, max 1000 characters)</span></label>
          <textarea name="comment" id="comment" rows="4" maxlength="1000"></textarea>
        </div>
        <button type="submit" class="btn btn-dark">Submit review</button>
      </form>
    <?php else: ?>
      <p class="muted"><a href="login.php?next=<?= urlencode('book.php?id=' . $id . '#reviews') ?>">Log in</a> to write a review.</p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

