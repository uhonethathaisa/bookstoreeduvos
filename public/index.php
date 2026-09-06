<?php
/**
 * Homepage — search, promotions banner, featured books, category highlights.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/catalog.php';

$pageTitle  = 'Home';
$activePage = 'home';

$promos = active_promos();
$promo  = $promos[0] ?? null;

// --- Top-rated books (by number of reviews, then average) ---
$featured = db()->query(
    'SELECT b.*, AVG(r.Rating) AS avgRating, COUNT(r.ReviewID) AS nReviews
       FROM books b LEFT JOIN reviews r ON r.BookID = b.BookID
      GROUP BY b.BookID
      ORDER BY nReviews DESC, avgRating DESC, b.BookID
      LIMIT 6'
)->fetchAll();

// --- New arrivals ---
$newArrivals = db()->query(
    'SELECT b.*, AVG(r.Rating) AS avgRating, COUNT(r.ReviewID) AS nReviews
       FROM books b LEFT JOIN reviews r ON r.BookID = b.BookID
      GROUP BY b.BookID
      ORDER BY b.BookID DESC
      LIMIT 4'
)->fetchAll();

// --- Per-genre highlights ---
$genreBooks = [];
foreach (['Fiction', 'Non-fiction', "Children's"] as $g) {
    $stmt = db()->prepare(
        'SELECT b.*, AVG(r.Rating) AS avgRating, COUNT(r.ReviewID) AS nReviews
           FROM books b LEFT JOIN reviews r ON r.BookID = b.BookID
          WHERE b.Genre = ?
          GROUP BY b.BookID ORDER BY nReviews DESC, avgRating DESC
          LIMIT 3'
    );
    $stmt->execute([$g]);
    $genreBooks[$g] = $stmt->fetchAll();
}

$genreCounts = db()->query('SELECT Genre, COUNT(*) AS c FROM books GROUP BY Genre')->fetchAll(PDO::FETCH_KEY_PAIR);

include __DIR__ . '/includes/header.php';
?>

<?php if ($promo): ?>
  <section class="promo-banner">
    <div>
      <strong><?= e($promo['Description']) ?></strong>
      <span>Use code <b><?= e($promo['DiscountCode']) ?></b> at checkout
        &middot; ends <?= e(date('j M Y', strtotime($promo['ValidityEnd']))) ?></span>
    </div>
    <a class="btn btn-light" href="catalogue.php">Shop now &rarr;</a>
  </section>
<?php endif; ?>

<section class="hero">
  <h1>Local South African books, delivered nationwide</h1>
  <p>Coetzee, Gordimer, Paton, Trevor Noah &amp; more — from Cape Town classics to Joburg crime thrillers, priced in rand.</p>
  <form class="hero-search" action="catalogue.php" method="get" role="search">
    <label class="sr-only" for="heroQ">Search books</label>
    <input type="search" id="heroQ" name="q" placeholder="Try &ldquo;The Silent Patient&rdquo;…">
    <button type="submit" class="btn btn-dark">Search</button>
  </form>
  <div class="hero-cats">
    <?php foreach ($genreCounts as $g => $c): ?>
      <a href="catalogue.php?genre=<?= urlencode($g) ?>" class="chip"><?= e($g) ?> (<?= (int) $c ?>)</a>
    <?php endforeach; ?>
  </div>
</section>

<section class="block">
  <div class="block-head">
    <h2>Featured &amp; best rated</h2>
    <a class="block-link" href="catalogue.php">View all &rarr;</a>
  </div>
  <?php if ($featured): ?>
    <div class="book-grid"><?php foreach ($featured as $b) { echo book_card_html($b); } ?></div>
  <?php else: ?>
    <p class="no-results">No books yet — run <code>db/seed.sql</code>.</p>
  <?php endif; ?>
</section>

<?php foreach (['Fiction', 'Non-fiction', "Children's"] as $g): ?>
  <?php if (!empty($genreBooks[$g])): ?>
    <section class="block">
      <div class="block-head">
        <h2><?= e($g) ?> highlights</h2>
        <a class="block-link" href="catalogue.php?genre=<?= urlencode($g) ?>">More <?= e($g) ?> &rarr;</a>
      </div>
      <div class="book-grid"><?php foreach ($genreBooks[$g] as $b) { echo book_card_html($b); } ?></div>
    </section>
  <?php endif; ?>
<?php endforeach; ?>

<section class="block">
  <div class="block-head">
    <h2>New arrivals</h2>
    <a class="block-link" href="catalogue.php?sort=new">More &rarr;</a>
  </div>
  <div class="book-grid"><?php foreach ($newArrivals as $b) { echo book_card_html($b); } ?></div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
