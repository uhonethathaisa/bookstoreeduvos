<?php
/**
 * Admin — user accounts (search, view activity, remove customers).
 */
require_once __DIR__ . '/../includes/init.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'remove_user') {
    $target = (int) post('user_id');
    $me     = (int) current_user()['UserID'];
    if ($target > 0 && $target !== $me) {
        $stmt = db()->prepare('SELECT Role FROM users WHERE UserID = ?');
        $stmt->execute([$target]);
        $role = $stmt->fetchColumn();
        if ($role !== 'Admin') {
            try {
                $del = db()->prepare('DELETE FROM users WHERE UserID = ?');
                $del->execute([$target]);
                flash('success', 'User #' . $target . ' was removed.');
            } catch (PDOException) {
                flash('error', 'User #' . $target . ' cannot be removed — they have orders/reviews on record (FK integrity).');
            }
        } else {
            flash('error', 'Administrator accounts cannot be removed.');
        }
    }
    redirect('users.php');
}

$pageTitle = 'Users';
$adminPage = 'users';

$q  = get('q');
$sql = 'SELECT u.*,
              (SELECT COUNT(*) FROM orders o WHERE o.UserID = u.UserID) AS orders_count,
              (SELECT COUNT(*) FROM reviews r WHERE r.UserID = u.UserID) AS reviews_count
         FROM users u';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE u.Name LIKE ? OR u.Email LIKE ?';
    $params = ["%$q%", "%$q%"];
}
$sql .= ' ORDER BY u.DateCreated DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="panel-head">
  <h1>User accounts</h1>
  <form method="get" action="users.php" class="inline-search">
    <label class="sr-only" for="uq">Search users</label>
    <input type="search" name="q" id="uq" value="<?= e($q) ?>" placeholder="Search name or e-mail…">
    <button class="btn btn-dark btn-sm" type="submit">Search</button>
  </form>
</div>

<?php if (!$users): ?>
  <p class="muted">No users found.</p>
<?php else: ?>
  <table class="data-table">
    <thead><tr><th>ID</th><th>Name</th><th>E-mail</th><th>Role</th><th>Joined</th><th class="num">Orders</th><th class="num">Reviews</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= (int) $u['UserID'] ?></td>
          <td><?= e($u['Name']) ?></td>
          <td><?= e($u['Email']) ?></td>
          <td>
            <span class="badge badge-<?= $u['Role'] === 'Admin' ? 'admin' : 'customer' ?>"><?= e($u['Role']) ?></span>
          </td>
          <td><?= e(date('j M Y', strtotime($u['DateCreated']))) ?></td>
          <td class="num"><?= (int) $u['orders_count'] ?></td>
          <td class="num"><?= (int) $u['reviews_count'] ?></td>
          <td>
            <?php if ($u['Role'] !== 'Admin'): ?>
              <form method="post" action="users.php" onsubmit="return confirm('Remove user <?= e($u['Name']) ?>?');">
                <input type="hidden" name="action" value="remove_user">
                <input type="hidden" name="user_id" value="<?= (int) $u['UserID'] ?>">
                <button type="submit" class="btn-link-danger">Remove</button>
              </form>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
