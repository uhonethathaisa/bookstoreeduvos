<?php
/**
 * Track delivery — order timeline: Ordered → Paid → Shipped → Delivered.
 */
require_once __DIR__ . '/includes/init.php';

require_login();

$id = (int) get('order');
$uid = (int) current_user()['UserID'];

$sql = 'SELECT o.*, u.Email, u.Name AS CustomerName
          FROM orders o JOIN users u ON u.UserID = o.UserID
         WHERE o.OrderID = ?';
if (!is_admin()) {
    $sql .= ' AND o.UserID = ' . $uid;
}
$stmt = db()->prepare($sql);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order not found';
    include __DIR__ . '/includes/header.php';
    echo '<p class="no-results">We could not find that order. <a href="profile.php">View your orders</a>.</p>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = 'Track order #' . $order['OrderID'];

$stmt = db()->prepare(
    'SELECT od.Quantity, b.Title FROM order_details od
       JOIN books b ON b.BookID = od.BookID WHERE od.OrderID = ?'
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$status = $order['Status'];
$steps  = [
    ['key' => 'placed', 'title' => 'Order placed', 'date' => $order['OrderDate'], 'desc' => 'Your order was received.'],
    ['key' => 'paid',   'title' => 'Payment confirmed', 'date' => $order['OrderDate'], 'desc' => 'Payment authorised by the gateway.' ],
    ['key' => 'shipped','title' => 'Shipped', 'date' => $order['ShippedDate'] ?: null, 'desc' => $order['Carrier'] ? 'With ' . $order['Carrier'] . ' — tracking below.' : ''],
    ['key' => 'delivered','title' => 'Delivered', 'date' => $order['DeliveredDate'] ?: null, 'desc' => 'Enjoy your books!'],
];

// Which step is the customer on?
$currentKey = match ($status) {
    'Pending'   => 'paid',
    'Paid'      => 'paid',
    'Shipped'   => 'shipped',
    default     => 'delivered', // Completed
};

function step_state(string $key, string $current): string
{
    $orderList = ['placed', 'paid', 'shipped', 'delivered'];
    $ki = array_search($key, $orderList, true);
    $ci = array_search($current, $orderList, true);
    return $ki < $ci ? 'done' : ($ki === $ci ? 'current' : '');
}

include __DIR__ . '/includes/header.php';
?>

<div class="track-wrap">
  <div class="panel-head">
    <div>
      <h1>Track order #<?= (int) $order['OrderID'] ?></h1>
      <p class="muted">Placed <?= e(date('j M Y H:i', strtotime($order['OrderDate']))) ?></p>
    </div>
    <div>
      <a class="btn btn-light" href="confirmation.php?order=<?= (int) $order['OrderID'] ?>">View receipt</a>
      <a class="btn btn-light" href="profile.php">My orders</a>
    </div>
  </div>

  <div class="track-grid">
    <div class="track-meta">
      <dl><dt>Status</dt><dd><span class="badge badge-<?= e(strtolower($status)) ?>"><?= e($status) ?></span></dd></dl>
    </div>
    <div class="track-meta">
      <dl><dt>Delivery method</dt><dd><?= e($order['ShipMethod']) ?></dd></dl>
    </div>
    <div class="track-meta">
      <dl><dt>Estimated delivery</dt>
        <dd><?= !empty($order['EstimatedDelivery']) ? e(date('D j M Y', strtotime($order['EstimatedDelivery']))) : '—' ?></dd></dl>
    </div>
    <?php if (!empty($order['TrackingNumber'])): ?>
      <div class="track-meta">
        <dl><dt>Tracking number</dt><dd><span class="tracking-ref"><?= e($order['TrackingNumber']) ?></span></dd></dl>
      </div>
    <?php endif; ?>
  </div>

  <ol class="timeline">
    <?php foreach ($steps as $s): $state = step_state($s['key'], $currentKey); ?>
      <li class="<?= $state ?>">
        <span class="dot"><?= $state === 'done' ? '&#10003;' : '' ?></span>
        <h3><?= e($s['title']) ?></h3>
        <?php if ($s['date']): ?>
          <time datetime="<?= e(date('c', strtotime($s['date']))) ?>"><?= e(date('j M Y H:i', strtotime($s['date']))) ?></time>
        <?php endif; ?>
        <?php if ($s['key'] === 'shipped' && !empty($order['TrackingNumber'])): ?>
          <p><?= e($s['desc']) ?> Carrier: <?= e($order['Carrier']) ?>
            &middot; Tracking: <span class="tracking-ref"><?= e($order['TrackingNumber']) ?></span></p>
        <?php elseif ($s['desc']): ?>
          <p><?= e($s['desc']) ?></p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>

  <section class="panel">
    <h2>Items in this order</h2>
    <ul class="mini-items">
      <?php foreach ($items as $it): ?>
        <li><span><?= e($it['Title']) ?> <small>&times; <?= (int) $it['Quantity'] ?></small></span></li>
      <?php endforeach; ?>
    </ul>
    <p class="muted small">Deliver to: <?= e($order['ShipName']) ?>, <?= e($order['ShipAddress']) ?>, <?= e($order['ShipCity']) ?> <?= e($order['ShipPostcode']) ?>, <?= e($order['ShipCountry']) ?></p>
  </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
