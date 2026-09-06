<?php
/**
 * Order confirmation / receipt page.
 */
require_once __DIR__ . '/includes/init.php';

require_login();

$orderId = (int) get('order');
$uid     = (int) current_user()['UserID'];

$sql = 'SELECT o.*, u.Email, u.Name AS CustomerName
          FROM orders o JOIN users u ON u.UserID = o.UserID
         WHERE o.OrderID = ?';
if (!is_admin()) {
    $sql .= ' AND o.UserID = ' . $uid;
}
$stmt = db()->prepare($sql);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order not found';
    include __DIR__ . '/includes/header.php';
    echo '<p class="no-results">We could not find that order. <a href="profile.php">View your orders</a>.</p>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = 'Order #' . $order['OrderID'];

$stmt = db()->prepare(
    'SELECT od.*, b.Title, b.Author
       FROM order_details od JOIN books b ON b.BookID = od.BookID
      WHERE od.OrderID = ?'
);
$stmt->execute([$orderId]);
$lines = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="receipt">
  <div class="receipt-head">
    <span class="receipt-icon">&#10004;</span>
    <h1>Thank you — order confirmed</h1>
    <p class="muted">A confirmation e-mail was sent to <?= e($order['Email']) ?>.
      (Simulated — see <code>storage/email.log</code>.)</p>
  </div>

  <section class="panel">
    <h2>Order <?= (int) $order['OrderID'] ?> &middot; <?= e(date('j M Y H:i', strtotime($order['OrderDate']))) ?></h2>
    <p><span class="badge badge-<?= e(strtolower($order['Status'])) ?>"><?= e($order['Status']) ?></span></p>

    <table class="data-table">
      <thead><tr><th>Book</th><th>Author</th><th>Price</th><th>Qty</th><th class="num">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($lines as $l): ?>
          <tr>
            <td><a href="book.php?id=<?= (int) $l['BookID'] ?>"><?= e($l['Title']) ?></a></td>
            <td><?= e($l['Author']) ?></td>
            <td><?= money((float) $l['PriceAtPurchase']) ?></td>
            <td><?= (int) $l['Quantity'] ?></td>
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
      <div class="total-row"><dt>Total paid</dt><dd><?= money((float) $order['TotalAmount']) ?></dd></div>
    </dl>
    <?php if (!empty($order['EstimatedDelivery'])): ?>
      <p class="hint">Estimated delivery: <b><?= e(date('D j M Y', strtotime($order['EstimatedDelivery']))) ?></b>
        via <?= e($order['ShipMethod']) ?> delivery.</p>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2>Delivery details</h2>
    <p><?= e($order['ShipName']) ?><br>
       <?= e($order['ShipAddress']) ?><br>
       <?= e($order['ShipCity']) ?> <?= e($order['ShipPostcode']) ?><br>
       <?= e($order['ShipCountry']) ?><br>
       <span class="muted">Phone: <?= e($order['ShipPhone']) ?></span></p>
  </section>

  <p><a class="btn btn-dark" href="track.php?order=<?= (int) $order['OrderID'] ?>">Track delivery &rarr;</a>
     <a class="btn btn-light" href="profile.php">View my orders &rarr;</a>
     <a class="btn btn-light" href="catalogue.php">Continue shopping</a></p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
