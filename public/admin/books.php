<?php
/**
 * Admin — inventory management (CRUD for books).
 */
require_once __DIR__ . '/../includes/init.php';
require_admin();

$genres = ['Fiction', 'Non-fiction', "Children's"];

/* ================= POST handlers ================= */
$errors = [];
$fv = ['Title' => '', 'Author' => '', 'Genre' => 'Fiction', 'ISBN' => '', 'Price' => '', 'Stock' => '', 'Synopsis' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $editingId = (int) post('book_id');

    if ($action === 'delete') {
        try {
            $del = db()->prepare('DELETE FROM books WHERE BookID = ?');
            $del->execute([$editingId]);
            flash('success', 'Book #' . $editingId . ' deleted.');
        } catch (Throwable) {
            flash('error', 'Cannot delete book #' . $editingId . ' — it has order/review history (referential integrity).');
        }
        redirect('books.php');
    }

    if ($action === 'create' || $action === 'update') {
        $fv = [
            'Title'    => post('title'),
            'Author'   => post('author'),
            'Genre'    => post('genre'),
            'ISBN'     => post('isbn'),
            'Price'    => post('price'),
            'Stock'    => post('stock'),
            'Synopsis' => trim((string) ($_POST['synopsis'] ?? '')),
        ];

        if ($fv['Title'] === '' || mb_strlen($fv['Title']) > 200)  { $errors[] = 'Title is required (max 200 chars).'; }
        if ($fv['Author'] === '' || mb_strlen($fv['Author']) > 120) { $errors[] = 'Author is required (max 120 chars).'; }
        if (!in_array($fv['Genre'], $genres, true))                 { $errors[] = 'Please choose a valid genre.'; }
        if ($fv['ISBN'] === '' || mb_strlen($fv['ISBN']) > 20)      { $errors[] = 'ISBN is required (max 20 chars).'; }
        if (!is_numeric($fv['Price']) || (float) $fv['Price'] <= 0) { $errors[] = 'Price must be greater than 0.'; }
        if ($fv['Stock'] === '' || !is_numeric($fv['Stock']) || (int) $fv['Stock'] < 0) { $errors[] = 'Stock must be 0 or more.'; }

        if ($errors === []) {
            $uniq = $editingId > 0
                ? db()->prepare('SELECT 1 FROM books WHERE ISBN = ? AND BookID <> ?')
                : db()->prepare('SELECT 1 FROM books WHERE ISBN = ?');
            $uniq->execute($editingId > 0 ? [$fv['ISBN'], $editingId] : [$fv['ISBN']]);
            if ($uniq->fetch()) {
                $errors[] = 'Another book already uses ISBN ' . $fv['ISBN'] . '.';
            }
        }

        if ($errors === []) {
            if ($editingId > 0) {
                $stmt = db()->prepare(
                    'UPDATE books SET Title=?, Author=?, Genre=?, ISBN=?, Price=?, Stock=?, Synopsis=? WHERE BookID=?'
                );
                $stmt->execute([$fv['Title'], $fv['Author'], $fv['Genre'], $fv['ISBN'],
                                (float) $fv['Price'], (int) $fv['Stock'], $fv['Synopsis'] !== '' ? $fv['Synopsis'] : null, $editingId]);
                flash('success', 'Book updated.');
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO books (Title, Author, Genre, ISBN, Price, Stock, Synopsis) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$fv['Title'], $fv['Author'], $fv['Genre'], $fv['ISBN'],
                                (float) $fv['Price'], (int) $fv['Stock'], $fv['Synopsis'] !== '' ? $fv['Synopsis'] : null]);
                flash('success', 'Book added to the catalogue.');
            }
            redirect('books.php');
        }
    }
}

/* ================= Read ================= */
$q       = get('q');
$editing = null;

if (get('edit') !== '') {
    $stmt = db()->prepare('SELECT * FROM books WHERE BookID = ?');
    $stmt->execute([(int) get('edit')]);
    $editing = $stmt->fetch();
    if ($editing) {
        $fv = $editing;
    }
}

$showForm = get('form') === '1' || $editing !== null
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors !== []);

$sql  = 'SELECT * FROM books';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE Title LIKE ? OR Author LIKE ? OR ISBN LIKE ?';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$sql .= ' ORDER BY BookID DESC LIMIT 100';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$pageTitle = 'Books';
$adminPage = 'books';
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="panel-head">
  <h1>Inventory &mdash; books</h1>
  <div>
    <?php if (!$showForm): ?>
      <a class="btn btn-dark" href="books.php?form=1">&#43; Add new book</a>
    <?php endif; ?>
    <form method="get" action="books.php" class="inline-search">
      <label class="sr-only" for="bq">Search books</label>
      <input type="search" name="q" id="bq" value="<?= e($q) ?>" placeholder="Search title, author, ISBN…">
      <button class="btn btn-light btn-sm" type="submit">Search</button>
    </form>
  </div>
</div>

<?php if ($showForm): ?>
  <section class="panel book-form-panel">
    <h2><?= $editing ? 'Edit book #' . (int) $editing['BookID'] . ' — ' . e($editing['Title']) : 'Add a new book' ?></h2>
    <?php if ($errors): ?>
      <div class="flash flash-error"><ul><?php foreach ($errors as $er) { echo '<li>' . e($er) . '</li>'; } ?></ul></div>
    <?php endif; ?>

    <form method="post" action="books.php" class="book-form" data-validate>
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="book_id" value="<?= (int) $editing['BookID'] ?>"><?php endif; ?>

      <div class="field-row">
        <div class="field grow">
          <label for="title">Title *</label>
          <input type="text" id="title" name="title" maxlength="200" required
                 value="<?= e($fv['Title']) ?>" data-rules="required">
        </div>
        <div class="field">
          <label for="author">Author *</label>
          <input type="text" id="author" name="author" maxlength="120" required
                 value="<?= e($fv['Author']) ?>" data-rules="required">
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="genre">Genre</label>
          <select name="genre" id="genre">
            <?php foreach ($genres as $g): ?>
              <option value="<?= e($g) ?>" <?= $fv['Genre'] === $g ? 'selected' : '' ?>><?= e($g) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="isbn">ISBN *</label>
          <input type="text" id="isbn" name="isbn" maxlength="20" required
                 value="<?= e($fv['ISBN']) ?>" data-rules="required">
        </div>
        <div class="field">
          <label for="price">Price (ZAR) *</label>
          <input type="number" id="price" name="price" min="0.01" step="0.01" required
                 value="<?= e((string) $fv['Price']) ?>" data-rules="required">
        </div>
        <div class="field">
          <label for="stock">Stock *</label>
          <input type="number" id="stock" name="stock" min="0" step="1" required
                 value="<?= e((string) $fv['Stock']) ?>" data-rules="required">
        </div>
      </div>

      <div class="field">
        <label for="synopsis">Synopsis</label>
        <textarea name="synopsis" id="synopsis" rows="4"><?= e($fv['Synopsis'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn btn-dark"><?= $editing ? 'Save changes' : 'Add book' ?></button>
      <a class="btn btn-light" href="books.php">Cancel</a>
    </form>
  </section>
<?php endif; ?>

<section class="panel">
  <h2>Catalogue (<?= count($books) ?> shown)</h2>
  <?php if (!$books): ?>
    <p class="muted">No books match. <a href="books.php">Clear search</a> or add one above.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr><th>ID</th><th>Title</th><th>Author</th><th>Genre</th><th class="num">Price</th><th class="num">Stock</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($books as $b): ?>
            <tr>
              <td><?= (int) $b['BookID'] ?></td>
              <td><a href="../book.php?id=<?= (int) $b['BookID'] ?>" target="_blank"><?= e($b['Title']) ?></a></td>
              <td><?= e($b['Author']) ?></td>
              <td><?= e($b['Genre']) ?></td>
              <td class="num"><?= money((float) $b['Price']) ?></td>
              <td class="num <?= (int) $b['Stock'] < 5 ? 'stock-low' : '' ?>"><?= (int) $b['Stock'] ?></td>
              <td class="row-actions">
                <a class="btn btn-light btn-sm" href="books.php?edit=<?= (int) $b['BookID'] ?>">Edit</a>
                <form method="post" action="books.php"
                      onsubmit="return confirm('Delete &ldquo;<?= e($b['Title']) ?>&rdquo;?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="book_id" value="<?= (int) $b['BookID'] ?>">
                  <button type="submit" class="btn-link-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>

