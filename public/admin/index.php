<?php
/**
 * Admin — analytics dashboard (revenue, orders, users, stock, top sellers).
 */
$adminPage = 'dashboard';
$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/admin_header.php';

$q = static fn(string $sql, array $p = []) => (static function ($s, $p) {
    $s->execute($p);
    $v = $s->fetch();
    return $v === false ? null : $v;
})(db()->prepare($sql), $p);

$revAll       = $q('SELECT COALESCE(SUM(TotalAmount),0) v FROM orders WHERE Status <> "Pending"');
$revMonth     = $q('SELECT COALESCE(SUM(TotalAmount),0) v FROM orders WHERE Status <> "Pending" AND OrderDate >= ?', [date('Y-m-01 00:00:00')]);
$ordersCount  = $q('SELECT COUNT(*) v FROM orders');
$pendingCount = $q('SELECT COUNT(*) v FROM orders WHERE Status = "Pending"');
$usersCount   = $q('SELECT COUNT(*) v FROM users WHERE Role = "Customer"');
$adminsCount  = $q('SELECT COUNT(*) v FROM users WHERE Role = "Admin"');
$booksCount   = $q('SELECT COUNT(*) v FROM books');
$lowStock     = $q('SELECT COUNT(*) v FROM books WHERE Stock > 0 AND Stock < 5');
$outStock     = $q('SELECT COUNT(*) v FROM books WHERE Stock = 0');

$genreSales = db()->query(
    'SELECT b.Genre, SUM(od.Quantity * od.PriceAtPurchase) AS revenue, SUM(od.Quantity) AS units
       FROM order_details od JOIN books b ON b.BookID = od.BookID
      GROUP BY b.Genre ORDER BY revenue DESC'
)->fetchAll();
$maxGenre = 0;
foreach ($genreSales as $g) { $maxGenre = max($maxGenre, (float) $g['revenue']); }

$topSellers = db()->query(
    'SELECT b.Title, SUM(od.Quantity) AS units, SUM(od.Quantity * od.PriceAtPurchase) AS revenue
       FROM order_details od JOIN books b ON b.BookID = od.BookID
      GROUP BY b.BookID ORDER BY units DESC LIMIT 5'
)->fetchAll();

$recentOrders = db()->query(
    'SELECT o.OrderID, o.OrderDate, o.TotalAmount, o.Status, u.Name, u.Email,
            (SELECT COUNT(*) FROM order_details od WHERE od.OrderID = o.OrderID) AS items
       FROM orders o JOIN users u ON u.UserID = o.UserID
      ORDER BY o.OrderDate DESC LIMIT 8'
)->fetchAll();
?>

<h1>Dashboard</h1>
<p class="muted">Store overview · <?= e(date('j F Y')) ?></p>

<div class="kpi-grid">
  <div class="kpi-card"><span class="kpi-label">Revenue (all time)</span><strong><?= money((float) ($revAll['v'] ?? 0)) ?></strong></div>
  <div class="kpi-card"><span class="kpi-label">Revenue (this month)</span><strong><?= money((float) ($revMonth['v'] ?? 0)) ?></strong></div>
  <div class="kpi-card"><span class="kpi-label">Orders</span><strong><?= (int) ($ordersCount['v'] ?? 0) ?> <small class="muted">(<?= (int) ($pendingCount['v'] ?? 0) ?> pending)</small></strong></div>
  <div class="kpi-card"><span class="kpi-label">Customers / admins</span><strong><?= (int) ($usersCount['v'] ?? 0) ?> <small class="muted">/ <?= (int) ($adminsCount['v'] ?? 0) ?></small></strong></div>
  <div class="kpi-card"><span class="kpi-label">Books in catalogue</span><strong><?= (int) ($booksCount['v'] ?? 0) ?></strong></div>
  <div class="kpi-card"><span class="kpi-label">Stock warnings</span><strong><?= (int) ($lowStock['v'] ?? 0) ?> low <small class="muted">· <?= (int) ($outStock['v'] ?? 0) ?> out</small></strong></div>
</div>

<div class="dash-grid">
  <section class="panel">
    <h2>Sales by genre</h2>
    <?php if (!$genreSales): ?>
      <p class="muted">No completed sales yet.</p>
    <?php else: ?>
      <div class="chart-bars">
        <?php foreach ($genreSales as $g): $pct = $maxGenre > 0 ? round((float) $g['revenue'] / $maxGenre * 100) : 0; ?>
          <div class="chart-row">
            <span class="chart-label"><?= e($g['Genre']) ?></span>
            <div class="bar"><div class="bar-fill genre" style="width:<?= $pct ?>%"></div></div>
            <span class="chart-val"><?= money((float) $g['revenue']) ?> <small class="muted">(<?= (int) $g['units'] ?> units)</small></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2>Top sellers</h2>
    <ol class="top-sellers">
      <?php foreach ($topSellers as $i => $t): ?>
        <li>
          <span class="rank"><?= $i + 1 ?></span>
          <span class="ts-title"><?= e($t['Title']) ?></span>
          <span class="ts-num"><?= (int) $t['units'] ?> sold · <?= money((float) $t['revenue']) ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
</div>

<section class="panel">
  <div class="panel-head">
    <h2>Recent orders</h2>
    <a class="btn btn-light btn-sm" href="orders.php">Manage all orders</a>
  </div>
  <?php if (!$recentOrders): ?>
    <p class="muted">No orders placed yet.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Items</th><th class="num">Total</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td><a href="order.php?id=<?= (int) $o['OrderID'] ?>">#<?= (int) $o['OrderID'] ?></a></td>
            <td><?= e($o['Name']) ?><br><span class="muted small"><?= e($o['Email']) ?></span></td>
            <td><?= e(date('j M Y H:i', strtotime($o['OrderDate']))) ?></td>
            <td><?= (int) $o['items'] ?></td>
            <td class="num"><?= money((float) $o['TotalAmount']) ?></td>
            <td><span class="badge badge-<?= e(strtolower($o['Status'])) ?>"><?= e($o['Status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
