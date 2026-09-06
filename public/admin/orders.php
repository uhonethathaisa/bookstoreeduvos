<?php
/**
 * Admin — order list with quick status workflow.
 */
require_once __DIR__ . '/../includes/init.php';
require_admin();

$pageTitle = 'Orders';
$adminPage = 'orders';
include __DIR__ . '/../includes/admin_header.php';

$orders = db()->query(
    'SELECT o.*, u.Name, u.Email
       FROM orders o JOIN users u ON u.UserID = o.UserID
      ORDER BY o.OrderDate DESC
      LIMIT 60'
)->fetchAll();

$linesByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'OrderID');
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        'SELECT od.OrderID, od.Quantity, od.PriceAtPurchase, b.Title
           FROM order_details od JOIN books b ON b.BookID = od.BookID
          WHERE od.OrderID IN (' . $marks . ')
          ORDER BY od.OrderID'
    );
    $stmt->execute($ids);
    foreach ($stmt as $line) {
        $linesByOrder[(int) $line['OrderID']][] = $line;
    }
}

$statuses = ['Pending', 'Paid', 'Shipped', 'Completed'];
?>

<h1>Orders</h1>

<?php if (!$orders): ?>
  <p class="muted">No orders yet.</p>
<?php else: ?>
  <table class="data-table orders-table">
    <thead>
      <tr><th>Order</th><th>Customer</th><th>Date</th><th class="num">Total</th><th>Status</th><th>Items</th></tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr class="order-master">
          <td><a href="order.php?id=<?= (int) $o['OrderID'] ?>">#<?= (int) $o['OrderID'] ?></a></td>
          <td><?= e($o['Name']) ?><br><span class="muted small"><?= e($o['Email']) ?></span></td>
          <td><?= e(date('j M Y H:i', strtotime($o['OrderDate']))) ?></td>
          <td class="num"><?= money((float) $o['TotalAmount']) ?></td>
          <td>
            <form method="post" action="order.php?id=<?= (int) $o['OrderID'] ?>" class="inline-status">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="order_id" value="<?= (int) $o['OrderID'] ?>">
              <label class="sr-only" for="status<?= (int) $o['OrderID'] ?>">Status for #<?= (int) $o['OrderID'] ?></label>
              <select name="status" id="status<?= (int) $o['OrderID'] ?>" onchange="this.form.submit()">
                <?php foreach ($statuses as $s): ?>
                  <option value="<?= $s ?>" <?= $o['Status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td>
            <details>
              <summary><?= count($linesByOrder[(int) $o['OrderID']] ?? []) ?> item(s) — view</summary>
              <ul class="order-lines">
                <?php foreach ($linesByOrder[(int) $o['OrderID']] ?? [] as $l): ?>
                  <li><?= e($l['Title']) ?> &times; <?= (int) $l['Quantity'] ?>
                    <span class="muted">@ <?= money((float) $l['PriceAtPurchase']) ?></span></li>
                <?php endforeach; ?>
              </ul>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
