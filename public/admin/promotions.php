<?php
/**
 * Admin — promotions (discount codes) CRUD.
 */
require_once __DIR__ . '/../includes/init.php';
require_admin();

$errors = [];
$fv = ['Description' => '', 'DiscountCode' => '', 'DiscountValue' => '', 'ValidityStart' => '', 'ValidityEnd' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $id     = (int) post('promo_id');

    if ($action === 'delete') {
        $del = db()->prepare('DELETE FROM promotions WHERE PromoID = ?');
        $del->execute([$id]);
        flash('success', 'Promotion deleted.');
        redirect('promotions.php');
    }

    if ($action === 'create' || $action === 'update') {
        $fv = [
            'Description'   => post('description'),
            'DiscountCode'  => strtoupper(post('code')),
            'DiscountValue' => post('value'),
            'ValidityStart' => post('start'),
            'ValidityEnd'   => post('end'),
        ];

        if ($fv['Description'] === '' || mb_strlen($fv['Description']) > 120) { $errors[] = 'Description is required (max 120 chars).'; }
        if (!preg_match('/^[A-Z0-9]{3,20}$/', $fv['DiscountCode']))          { $errors[] = 'Code must be 3–20 letters/digits (no spaces).'; }
        if (!is_numeric($fv['DiscountValue']) || (float) $fv['DiscountValue'] <= 0 || (float) $fv['DiscountValue'] >= 100) {
            $errors[] = 'Discount value must be between 0 and 100 (%).';
        }
        $start = DateTime::createFromFormat('Y-m-d\TH:i', (string) $fv['ValidityStart']);
        $end   = DateTime::createFromFormat('Y-m-d\TH:i', (string) $fv['ValidityEnd']);
        if (!$start || !$end)             { $errors[] = 'Please set both validity start and end (datetime).'; }
        elseif ($end <= $start)           { $errors[] = 'Validity end must be after the start.'; }

        if ($errors === []) {
            $uniq = $id > 0
                ? db()->prepare('SELECT 1 FROM promotions WHERE DiscountCode = ? AND PromoID <> ?')
                : db()->prepare('SELECT 1 FROM promotions WHERE DiscountCode = ?');
            $uniq->execute($id > 0 ? [$fv['DiscountCode'], $id] : [$fv['DiscountCode']]);
            if ($uniq->fetch()) {
                $errors[] = 'That discount code already exists.';
            }
        }

        if ($errors === []) {
            $fmt = static fn(DateTime $d): string => $d->format('Y-m-d H:i:s');
            if ($id > 0) {
                $stmt = db()->prepare('UPDATE promotions SET Description=?, DiscountCode=?, DiscountValue=?, ValidityStart=?, ValidityEnd=? WHERE PromoID=?');
                $stmt->execute([$fv['Description'], $fv['DiscountCode'], (float) $fv['DiscountValue'], $fmt($start), $fmt($end), $id]);
                flash('success', 'Promotion updated.');
            } else {
                $stmt = db()->prepare('INSERT INTO promotions (Description, DiscountCode, DiscountValue, ValidityStart, ValidityEnd) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$fv['Description'], $fv['DiscountCode'], (float) $fv['DiscountValue'], $fmt($start), $fmt($end)]);
                flash('success', 'Promotion created.');
            }
            redirect('promotions.php');
        }
    }
}

$editing = null;
if (get('edit') !== '') {
    $stmt = db()->prepare('SELECT * FROM promotions WHERE PromoID = ?');
    $stmt->execute([(int) get('edit')]);
    $editing = $stmt->fetch();
    if ($editing) {
        $fv = [
            'Description'   => $editing['Description'],
            'DiscountCode'  => $editing['DiscountCode'],
            'DiscountValue' => $editing['DiscountValue'],
            'ValidityStart' => date('Y-m-d\TH:i', strtotime($editing['ValidityStart'])),
            'ValidityEnd'   => date('Y-m-d\TH:i', strtotime($editing['ValidityEnd'])),
        ];
    }
}

$showForm = get('form') === '1' || $editing !== null
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors !== []);

$promos = db()->query('SELECT * FROM promotions ORDER BY ValidityStart DESC')->fetchAll();

$pageTitle = 'Promotions';
$adminPage = 'promotions';
include __DIR__ . '/../includes/admin_header.php';

function promo_state(array $p): string
{
    $now = time();
    $s   = strtotime($p['ValidityStart']);
    $e   = strtotime($p['ValidityEnd']);
    if ($now < $s) { return 'upcoming'; }
    if ($now > $e) { return 'expired'; }
    return 'active';
}
?>
<div class="panel-head">
  <h1>Promotions</h1>
  <?php if (!$showForm): ?><a class="btn btn-dark" href="promotions.php?form=1">&#43; New promotion</a><?php endif; ?>
</div>

<?php if ($showForm): ?>
  <section class="panel">
    <h2><?= $editing ? 'Edit promotion ' . e($editing['DiscountCode']) : 'Create promotion' ?></h2>
    <?php if ($errors): ?>
      <div class="flash flash-error"><ul><?php foreach ($errors as $er) { echo '<li>' . e($er) . '</li>'; } ?></ul></div>
    <?php endif; ?>
    <form method="post" action="promotions.php" class="book-form" data-validate>
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="promo_id" value="<?= (int) $editing['PromoID'] ?>"><?php endif; ?>

      <div class="field-row">
        <div class="field grow">
          <label for="description">Description</label>
          <input type="text" id="description" name="description" maxlength="120" required value="<?= e($fv['Description']) ?>">
        </div>
        <div class="field">
          <label for="code">Discount code</label>
          <input type="text" id="code" name="code" maxlength="20" required value="<?= e($fv['DiscountCode']) ?>" placeholder="READ30">
        </div>
        <div class="field">
          <label for="value">% off</label>
          <input type="number" id="value" name="value" min="0.1" max="99.99" step="0.01" required value="<?= e((string) $fv['DiscountValue']) ?>">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="start">Valid from</label>
          <input type="datetime-local" id="start" name="start" required value="<?= e($fv['ValidityStart']) ?>">
        </div>
        <div class="field">
          <label for="end">Valid until</label>
          <input type="datetime-local" id="end" name="end" required value="<?= e($fv['ValidityEnd']) ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-dark"><?= $editing ? 'Save changes' : 'Create' ?></button>
      <a class="btn btn-light" href="promotions.php">Cancel</a>
    </form>
  </section>
<?php endif; ?>

<section class="panel">
  <h2>Current promotions</h2>
  <?php if (!$promos): ?>
    <p class="muted">No promotions yet.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="data-table">
        <thead><tr><th>ID</th><th>Description</th><th>Code</th><th class="num">% off</th><th>Valid from</th><th>Valid until</th><th>State</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($promos as $p): $st = promo_state($p); ?>
            <tr>
              <td><?= (int) $p['PromoID'] ?></td>
              <td><?= e($p['Description']) ?></td>
              <td><code><?= e($p['DiscountCode']) ?></code></td>
              <td class="num"><?= rtrim(rtrim(number_format((float) $p['DiscountValue'], 2), '0'), '.') ?>%</td>
              <td><?= e(date('j M Y H:i', strtotime($p['ValidityStart']))) ?></td>
              <td><?= e(date('j M Y H:i', strtotime($p['ValidityEnd']))) ?></td>
              <td><span class="badge badge-<?= $st ?>"><?= ucfirst($st) ?></span></td>
              <td class="row-actions">
                <a class="btn btn-light btn-sm" href="promotions.php?edit=<?= (int) $p['PromoID'] ?>">Edit</a>
                <form method="post" action="promotions.php" onsubmit="return confirm('Delete <?= e($p['DiscountCode']) ?>?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="promo_id" value="<?= (int) $p['PromoID'] ?>">
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
