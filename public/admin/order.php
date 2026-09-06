<?php
/**
 * Admin — single order: items, delivery, status + fulfilment workflow.
 * Shipping an order records carrier/tracking + ShippedDate; completing it
 * records DeliveredDate; each transition notifies the customer (Observer).
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/notify.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_status') {
    $orderId = (int) post('order_id');
    $status  = post('status');
    $allowed = ['Pending', 'Paid', 'Shipped', 'Completed'];

    if ($orderId > 0 && in_array($status, $allowed, true)) {
        $cur = db()->prepare('SELECT Status, ShippedDate, DeliveredDate FROM orders WHERE OrderID = ?');
        $cur->execute([$orderId]);
        $old = $cur->fetch();
        $oldStatus = $old['Status'] ?? null;

        $carrier  = trim(post('carrier'));
        $tracking = trim(post('tracking'));
        if ($status === 'Shipped') {
            if ($carrier === '')  { $carrier = 'BookNest Courier'; }
            if ($tracking === '') { $tracking = 'BN' . $orderId . strtoupper(substr(sha1((string) $orderId), 0, 6)); }
        }

        $set  = ['Status = ?'];
        $vals = [$status];
        if ($status === 'Shipped' && empty($old['ShippedDate'])) {
            $set[] = 'ShippedDate = NOW()';
        }
        if ($status === 'Completed') {
            $set[] = 'DeliveredDate = NOW()';
        }
        if ($carrier !== '')  { $set[] = 'Carrier = ?';        $vals[] = $carrier; }
        if ($tracking !== '') { $set[] = 'TrackingNumber = ?'; $vals[] = $tracking; }
        $vals[] = $orderId;

        $upd = db()->prepare('UPDATE orders SET ' . implode(', ', $set) . ' WHERE OrderID = ?');
        $upd->execute($vals);
        flash('success', 'Order #' . $orderId . ' is now ' . $status . '.');

        // Observer: notify the customer on shipment / delivery transitions.
        if (in_array($status, ['Shipped', 'Completed'], true) && $status !== $oldStatus) {
            $stmt = db()->prepare('SELECT o.*, u.Name, u.Email FROM orders o JOIN users u ON u.UserID = o.UserID WHERE o.OrderID = ?');
            $stmt->execute([$orderId]);
            $row = $stmt->fetch();
            notify_order_email($row, ['Name' => $row['Name'], 'Email' => $row['Email']], $status === 'Shipped' ? 'shipped' : 'completed');
        }
        redirect('order.php?id=' . $orderId);
    }
    redirect('orders.php');
}

$adminPage = 'orders';
$pageTitle = 'Order';
include __DIR__ . '/../includes/admin_header.php';

$id   = (int) get('id');
$stmt = db()->prepare(
    'SELECT o.*, u.Name AS CustomerName, u.Email
       FROM orders o JOIN users u ON u.UserID = o.UserID
      WHERE o.OrderID = ?'
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    echo '<p class="no-results">Order not found. <a href="orders.php">Back to orders</a>.</p>';
    include __DIR__ . '/../includes/admin_footer.php';
    exit;
}

$lines = db()->prepare(
    'SELECT od.*, b.Title, b.Author FROM order_details od
       JOIN books b ON b.BookID = od.BookID WHERE od.OrderID = ?'
);
$lines->execute([$id]);
$items = $lines->fetchAll();

$statuses = ['Pending', 'Paid', 'Shipped', 'Completed'];
?>

<div class="panel-head">
  <div>
    <h1>Order #<?= (int) $order['OrderID'] ?></h1>
    <p class="muted">Placed <?= e(date('j M Y H:i', strtotime($order['OrderDate']))) ?> by <?= e($order['CustomerName']) ?> (<?= e($order['Email']) ?>)</p>
  </div>
  <a class="btn btn-light" href="orders.php">&larr; All orders</a>
</div>

<div class="dash-grid">
  <section class="panel">
    <h2>Items</h2>
    <table class="data-table">
      <thead><tr><th>Book</th><th>Author</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($items as $l): ?>
          <tr>
            <td><?= e($l['Title']) ?></td>
            <td><?= e($l['Author']) ?></td>
            <td class="num"><?= money((float) $l['PriceAtPurchase']) ?></td>
            <td class="num"><?= (int) $l['Quantity'] ?></td>
            <td class="num"><?= money((float) $l['PriceAtPurchase'] * (int) $l['Quantity']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <dl class="summary-rows inline-summary">
      <div><dt>Shipping (<?= e($order['ShipMethod']) ?>)</dt><dd><?= money((float) $order['ShipCost']) ?></dd></div>
      <?php if ((float) $order['DiscountAmount'] > 0): ?>
        <div><dt>Discount (<?= e($order['PromoCode']) ?>)</dt><dd>&minus;<?= money((float) $order['DiscountAmount']) ?></dd></div>
      <?php endif; ?>
      <div class="total-row"><dt>Total</dt><dd><?= money((float) $order['TotalAmount']) ?></dd></div>
    </dl>
  </section>

  <section class="panel">
    <h2>Status &amp; fulfilment</h2>
    <p><span class="badge badge-<?= e(strtolower($order['Status'])) ?>"><?= e($order['Status']) ?></span>
      <?php if (!empty($order['EstimatedDelivery'])): ?>
        <span class="muted small">&middot; ETA <?= e(date('D j M Y', strtotime($order['EstimatedDelivery']))) ?></span>
      <?php endif; ?>
    </p>

    <form method="post" action="order.php?id=<?= (int) $order['OrderID'] ?>" id="statusForm" class="book-form" style="gap:8px">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="order_id" value="<?= (int) $order['OrderID'] ?>">
      <div class="field">
        <label for="status">Status</label>
        <select name="status" id="status">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $order['Status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="carrier">Carrier</label>
        <input type="text" id="carrier" name="carrier" placeholder="BookNest Courier"
               value="<?= e($order['Carrier'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="tracking">Tracking number</label>
        <input type="text" id="tracking" name="tracking" placeholder="auto-generated when shipped"
               value="<?= e($order['TrackingNumber'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-dark btn-sm">Update status</button>
      <p class="hint">Shipped orders without a tracking number get one generated automatically.</p>
    </form>

    <?php if ($order['ShippedDate']): ?>
      <p class="small muted">Shipped <?= e(date('j M Y H:i', strtotime($order['ShippedDate']))) ?>
        &middot; <?= e($order['Carrier'] ?? '') ?> &middot; <span class="tracking-ref"><?= e($order['TrackingNumber'] ?? '-') ?></span></p>
    <?php endif; ?>
    <?php if ($order['DeliveredDate']): ?>
      <p class="small muted">Delivered <?= e(date('j M Y H:i', strtotime($order['DeliveredDate']))) ?></p>
    <?php endif; ?>
    <p class="hint">Status changes trigger the simulated customer notification e-mail (Observer pattern).</p>

    <h2>Delivery details</h2>
    <address class="delivery-addr">
      <?= e($order['ShipName']) ?><br>
      <?= e($order['ShipAddress']) ?><br>
      <?= e($order['ShipCity']) ?> <?= e($order['ShipPostcode']) ?><br>
      <?= e($order['ShipCountry']) ?><br>
      <span class="muted"><?= e($order['ShipPhone']) ?></span>
    </address>
  </section>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
