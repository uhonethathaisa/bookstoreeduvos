<?php
/**
 * Catalogue — server-rendered book grid with filters + sort + pagination.
 * The <form id="filterForm"> is enhanced by js/main.js for AJAX filtering
 * against api/books.php (dynamic filtering requirement).
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/catalog.php';

$pageTitle = 'Catalogue';
$activePage = get('genre') !== '' ? 'genre-' . get('genre') : 'all';

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
$result = catalog_query($f);
$books  = $result['rows'];

$allGenres = genres();
$allAuthors = authors();

include __DIR__ . '/includes/header.php';
?>

<div class="page-title-row">
  <h1>Catalogue<?= $f['genre'] !== '' ? ': ' . e($f['genre']) : '' ?></h1>
  <p class="muted" id="resultCount"><?= (int) $result['total'] ?> book<?= $result['total'] === 1 ? '' : 's' ?> found</p>
</div>

<div class="catalogue-layout">
  <!-- ==================== FILTER SIDEBAR ==================== -->
  <aside class="filter-panel">
    <form id="filterForm" method="get" action="catalogue.php">
      <h2 class="filter-title">Filters</h2>

      <?php if ($f['q'] !== ''): ?>
        <input type="hidden" name="q" value="<?= e($f['q']) ?>">
      <?php endif; ?>

      <fieldset>
        <legend>Genre</legend>
        <?php foreach ($allGenres as $g): ?>
          <label class="check"><input type="radio" name="genre" value="<?= e($g) ?>"
            <?= $f['genre'] === $g ? 'checked' : '' ?>> <?= e($g) ?></label>
        <?php endforeach; ?>
        <label class="check"><input type="radio" name="genre" value=""
          <?= $f['genre'] === '' ? 'checked' : '' ?>> Any genre</label>
      </fieldset>

      <fieldset>
        <legend>Author</legend>
        <select name="author" id="authorSelect">
          <option value="">All authors</option>
          <?php foreach ($allAuthors as $a): ?>
            <option value="<?= e($a) ?>" <?= $f['author'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
          <?php endforeach; ?>
        </select>
      </fieldset>

      <fieldset>
        <legend>Minimum rating</legend>
        <?php foreach ([4, 3, 2, 1] as $r): ?>
          <label class="check">
            <input type="radio" name="rating" value="<?= $r ?>"
              <?= (string) $f['rating'] === (string) $r ? 'checked' : '' ?>>
            <?= str_repeat('&#9733;', $r) ?> &amp; up
          </label>
        <?php endforeach; ?>
        <label class="check"><input type="radio" name="rating" value=""
          <?= $f['rating'] === '' ? 'checked' : '' ?>> Any rating</label>
      </fieldset>

      <fieldset>
        <legend>Price</legend>
        <div class="price-row">
          <label>R&nbsp;<input type="number" name="min_price" min="0" step="0.01" placeholder="Min"
                 value="<?= e($f['min_price']) ?>"></label>
          <label>R&nbsp;<input type="number" name="max_price" min="0" step="0.01" placeholder="Max"
                 value="<?= e($f['max_price']) ?>"></label>
        </div>
      </fieldset>

      <button type="submit" class="btn btn-dark btn-block">Apply filters</button>
      <a class="btn btn-light btn-block" href="catalogue.php">Reset</a>
    </form>
  </aside>

  <!-- ==================== RESULTS ==================== -->
  <section class="results-area">
    <div class="sort-row">
      <label for="sortSelect" class="muted">Sort by</label>
      <select id="sortSelect" name="sort" form="filterForm">
        <option value="bestsell"   <?= $f['sort'] === 'bestsell' ? 'selected' : '' ?>>Bestsellers</option>
        <option value="new"        <?= $f['sort'] === 'new' ? 'selected' : '' ?>>New arrivals</option>
        <option value="price_asc"  <?= $f['sort'] === 'price_asc' ? 'selected' : '' ?>>Price: low → high</option>
        <option value="price_desc" <?= $f['sort'] === 'price_desc' ? 'selected' : '' ?>>Price: high → low</option>
      </select>
    </div>

    <div id="catalogueResults">
      <?= catalog_grid_html($books) ?>
      <?= pager_html($result['page'], $result['pages']) ?>
    </div>
  </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
