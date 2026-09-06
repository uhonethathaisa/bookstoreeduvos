<?php
/**
 * Admin Inventory Management — view
 * Data table + Add/Edit form. Rendered by AdminInventoryController::index().
 *
 * Expected variables (set by the controller before include):
 *   @var array $books      list of catalogue rows
 *   @var ?array $editing   row being edited (null = adding)
 *   @var string[] $errors  validation problems
 *   @var array $old        previously submitted values
 *   @var string $csrf      per-session CSRF token
 */
if (!function_exists('inv_esc')) {
    function inv_esc(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('inv_money')) {
    function inv_money(float $amount): string { return 'R' . number_format($amount, 2, '.', ','); }
}
if (!function_exists('inv_upload_url')) {
    function inv_upload_url(string $filename): string { return '/uploads/covers/' . rawurlencode($filename); }
}

$errors = $errors ?? [];
$old    = $old ?? [];
$csrf   = $csrf ?? '';

$flashes = $_SESSION['inventory_flash'] ?? [];
unset($_SESSION['inventory_flash']);

// Field value precedence: failed attempt > being-edited row > empty.
// NB: DB rows use Title/Author/… (capitalised), form fields use title/author/…
$colMap = ['title' => 'Title', 'author' => 'Author', 'genre' => 'Genre', 'isbn' => 'ISBN', 'price' => 'Price', 'stock' => 'Stock'];
$v = ['title' => '', 'author' => '', 'genre' => '', 'isbn' => '', 'price' => '', 'stock' => ''];
if ($errors !== []) {
    foreach ($v as $k => $ignored) { $v[$k] = $old[$k] ?? ''; }
} elseif ($editing) {
    foreach ($colMap as $k => $col) { $v[$k] = (string) $editing[$col]; }
}

function book_initials(string $title): string
{
    $words = preg_split('/\s+/', trim($title)) ?: [];
    $short = implode('', array_map(static fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2)));
    return $short !== '' ? $short : '?';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin · Inventory Management · BookNest</title>
<style>
  :root{--ink:#1f2a37;--muted:#6b7a8c;--brand:#243b53;--brand2:#35688f;--line:#dfe5ec;--bg:#f4f6f9;--good:#2e7d32;--bad:#b3261e}
  *{box-sizing:border-box}
  body{margin:0;font-family:'Segoe UI',system-ui,Arial,sans-serif;color:var(--ink);background:var(--bg);line-height:1.5}
  a{color:var(--brand2)}
  .wrap{max-width:1240px;margin:0 auto;padding:0 16px}
  .topbar{background:var(--brand);color:#fff;padding:12px 0;margin-bottom:18px}
  .topbar .wrap{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
  .topbar h1{margin:0;font-size:19px}
  .topbar nav a{color:#dbe6f0;text-decoration:none;margin-left:16px;font-size:13px}
  .topbar nav a:hover{color:#fff}
  .flash{border-radius:6px;padding:11px 15px;margin:0 0 14px;font-size:14px}
  .flash.success{background:#e8f5e9;color:#1b5e20;border:1px solid #a5d6a7}
  .flash.error{background:#fdecea;color:#7f1410;border:1px solid #f2b8b5}
  .errors{background:#fdecea;border:1px solid #f2b8b5;color:#7f1410;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:14px}
  .errors ul{margin:4px 0 0;padding-left:18px}
  .layout{display:grid;grid-template-columns:390px 1fr;gap:22px;align-items:start;margin-bottom:30px}
  .card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:18px;box-shadow:0 1px 3px rgba(20,40,60,.08)}
  .card h2{margin:0 0 12px;font-size:16px;padding-bottom:8px;border-bottom:1px solid var(--line)}
  .field{margin-bottom:12px}
  .field label{display:block;font-weight:600;font-size:13px;margin-bottom:4px}
  .field input,.field select{width:100%;padding:8px 10px;border:1px solid #c8d2dc;border-radius:6px;font:inherit;font-size:14px;background:#fff}
  .field input:focus,.field select:focus{outline:2px solid var(--brand2);border-color:transparent}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .row2 .field{margin-bottom:0}
  .hint{font-size:12px;color:var(--muted);font-weight:400}
  .btn{display:inline-block;border:1px solid transparent;cursor:pointer;font:inherit;font-size:14px;padding:9px 18px;border-radius:6px;text-decoration:none;text-align:center}
  .btn-primary{background:var(--brand);color:#fff}
  .btn-ghost{background:#fff;color:var(--brand);border-color:var(--line)}
  .btn-sm{padding:4px 10px;font-size:12px}
  .btn-danger{background:none;border:none;color:var(--bad);cursor:pointer;font-size:12px;text-decoration:underline;padding:0}
  .btn-row{display:flex;gap:10px;margin-top:14px}
  .table-scroll{overflow-x:auto}
  table{width:100%;border-collapse:collapse;background:#fff;font-size:14px}
  th,td{padding:9px 11px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}
  th{background:#eef2f6;font-weight:600;font-size:13px}
  .num{text-align:right;white-space:nowrap}
  .cover-thumb{width:40px;height:56px;object-fit:cover;border-radius:4px;display:block;border:1px solid var(--line)}
  .cover-ph{width:40px;height:56px;border-radius:4px;background:linear-gradient(135deg,#35688f,#243b53);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px}
  .low{color:var(--bad);font-weight:700}
  .actions{display:flex;gap:10px;align-items:center;white-space:nowrap}
  @media(max-width:1000px){.layout{grid-template-columns:1fr}.card:first-of-type{order:2}}
</style>
</head>
<body>
<div class="topbar">
  <div class="wrap">
    <h1>&#128218; BookNest — Admin Inventory Management</h1>
    <nav>
      <a href="../admin/index.php">Back to Admin Panel</a>
      <a href="../index.php">Storefront</a>
      <a href="../logout.php">Log out</a>
    </nav>
  </div>
</div>

<div class="wrap">
  <?php foreach ($flashes as $f): ?>
    <div class="flash <?= $f['type'] === 'error' ? 'error' : 'success' ?>" role="status"><?= inv_esc($f['msg']) ?></div>
  <?php endforeach; ?>

  <?php if ($errors): ?>
    <div class="errors"><strong>Please fix the following:</strong>
      <ul><?php foreach ($errors as $er) { echo '<li>' . inv_esc($er) . '</li>'; } ?></ul>
    </div>
  <?php endif; ?>

  <div class="layout">

    <!-- ============ ADD / EDIT FORM ============ -->
    <section class="card">
      <h2><?= $editing ? 'Edit Book #' . (int) $editing['BookID'] : 'Add New Book' ?></h2>

      <form method="post" action="admin-inventory.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'store' ?>">
        <?php if ($editing): ?><input type="hidden" name="book_id" value="<?= (int) $editing['BookID'] ?>"><?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= inv_esc($csrf) ?>">

        <div class="field">
          <label for="title">Title</label>
          <input type="text" id="title" name="title" maxlength="200" required value="<?= inv_esc($v['title']) ?>" placeholder="e.g. Disgrace">
        </div>
        <div class="field">
          <label for="author">Author</label>
          <input type="text" id="author" name="author" maxlength="120" required value="<?= inv_esc($v['author']) ?>" placeholder="e.g. J.M. Coetzee">
        </div>
        <div class="row2">
          <div class="field">
            <label for="genre">Genre</label>
            <select id="genre" name="genre" required>
              <?php foreach (['Fiction', 'Non-fiction', "Children's"] as $g): ?>
                <option value="<?= inv_esc($g) ?>" <?= $v['genre'] === $g ? 'selected' : '' ?>><?= inv_esc($g) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="isbn">ISBN (13 digits)</label>
            <input type="text" id="isbn" name="isbn" inputmode="numeric" maxlength="13" pattern="\d{13}"
                   title="Exactly 13 digits" required value="<?= inv_esc($v['isbn']) ?>" placeholder="9780624043353">
          </div>
        </div>
        <div class="row2">
          <div class="field">
            <label for="price">Price (R)</label>
            <input type="number" id="price" name="price" min="0.01" step="0.01" required value="<?= inv_esc($v['price']) ?>" placeholder="195.00">
          </div>
          <div class="field">
            <label for="stock">Stock</label>
            <input type="number" id="stock" name="stock" min="0" step="1" required value="<?= inv_esc($v['stock']) ?>" placeholder="0">
          </div>
        </div>
        <div class="field">
          <label for="cover_image">Cover image
            <span class="hint">(JPEG, PNG or WEBP &middot; max 2 MB &middot; <?= $editing ? 'leave empty to keep current cover' : 'optional' ?>)</span>
          </label>
          <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="btn-row">
          <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add book' ?></button>
          <?php if ($editing): ?><a class="btn btn-ghost" href="admin-inventory.php">Cancel edit</a><?php endif; ?>
        </div>
      </form>
    </section>

    <!-- ============ DATA TABLE ============ -->
    <section class="card">
      <h2>Catalogue (<?= count($books) ?> books)</h2>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Cover</th><th>Title</th><th>Author</th><th>Genre</th>
              <th class="num">Stock</th><th class="num">Price</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$books): ?>
              <tr><td colspan="7" style="color:#6b7a8c">No books in the catalogue yet — add your first one with the form.</td></tr>
            <?php endif; ?>

            <?php foreach ($books as $b): ?>
              <?php
                $hasCover = !empty($b['ImageURL'])
                    && !str_contains((string) $b['ImageURL'], '/')
                    && !str_contains((string) $b['ImageURL'], '\\');
              ?>
              <tr>
                <td>
                  <?php if ($hasCover): ?>
                    <img class="cover-thumb" src="<?= inv_upload_url((string) $b['ImageURL']) ?>"
                         alt="Cover of <?= inv_esc((string) $b['Title']) ?>">
                  <?php else: ?>
                    <span class="cover-ph" title="<?= inv_esc((string) $b['Title']) ?>"><?= inv_esc(book_initials((string) $b['Title'])) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <strong><?= inv_esc((string) $b['Title']) ?></strong><br>
                  <span style="font-size:12px;color:#6b7a8c">ID <?= (int) $b['BookID'] ?> &middot;
                    <a class="store-link" href="../book.php?id=<?= (int) $b['BookID'] ?>" target="_blank" rel="noopener">view on storefront</a></span>
                </td>
                <td><?= inv_esc((string) $b['Author']) ?></td>
                <td><?= inv_esc((string) $b['Genre']) ?></td>
                <td class="num <?= (int) $b['Stock'] < 5 ? 'low' : '' ?>"><?= (int) $b['Stock'] ?></td>
                <td class="num"><?= inv_money((float) $b['Price']) ?></td>
                <td>
                  <div class="actions">
                    <a class="btn btn-ghost btn-sm" href="admin-inventory.php?action=edit&id=<?= (int) $b['BookID'] ?>">Edit</a>
                    <form method="post" action="admin-inventory.php"
                          onsubmit="return confirm('Delete “<?= inv_esc((string) $b['Title']) ?>” permanently?');">
                      <input type="hidden" name="action" value="destroy">
                      <input type="hidden" name="book_id" value="<?= (int) $b['BookID'] ?>">
                      <input type="hidden" name="csrf_token" value="<?= inv_esc($csrf) ?>">
                      <button type="submit" class="btn-danger">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

  </div><!-- /.layout -->
</div><!-- /.wrap -->
</body>
</html>



