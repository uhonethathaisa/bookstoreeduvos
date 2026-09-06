<?php
/**
 * Checkout — delivery details + simulated card payment.
 * On success: order + lines stored in a transaction, stock decremented,
 * confirmation e-mail dispatched (see includes/notify.php), cart cleared.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payment.php';
require_once __DIR__ . '/includes/notify.php';

require_login();

$data = cart_items();

if (!$data['rows']) {
    flash('info', 'Your cart is empty — add a book before checking out.');
    redirect('catalogue.php');
}

$selectedMethod = 'Standard';
$totals         = order_totals($data['subtotal'], $selectedMethod);

// Pre-compute what each delivery option would cost for this basket.
$methodCosts = [];
foreach (array_keys(shipping_methods()) as $code) {
    $methodCosts[$code] = order_totals($data['subtotal'], $code);
}

$user = current_user();
$errors = [];
$old = static fn(string $k): string => e((string) ($_POST[$k] ?? ''));

/* ---------------- Submit ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'checkout') {
    $ship = [
        'ship_name'     => post('ship_name'),
        'ship_address'  => post('ship_address'),
        'ship_city'     => post('ship_city'),
        'ship_postcode' => post('ship_postcode'),
        'ship_country'  => post('ship_country'),
        'ship_phone'    => post('ship_phone'),
    ];
    $card = [
        'number' => preg_replace('/\s/', '', post('card_no')),
        'expiry' => post('card_exp'),
        'cvv'    => post('card_cvv'),
    ];

    // Delivery method chosen by the customer.
    $candidate = post('ship_method');
    $selectedMethod = in_array($candidate, array_keys(shipping_methods()), true) ? $candidate : 'Standard';
    $totals = order_totals($data['subtotal'], $selectedMethod);

    if (mb_strlen($ship['ship_name']) < 2)     { $errors[] = 'Please enter the delivery name.'; }
    if (mb_strlen($ship['ship_address']) < 5)  { $errors[] = 'Please enter a full delivery address.'; }
    if ($ship['ship_city'] === '')             { $errors[] = 'Please enter the city.'; }
    if (mb_strlen($ship['ship_postcode']) < 3) { $errors[] = 'Please enter a valid postcode.'; }
    if ($ship['ship_country'] === '')          { $errors[] = 'Please enter the country.'; }
    if ($ship['ship_phone'] === '')            { $errors[] = 'Please enter a contact phone number.'; }

    $charge = $errors === [] ? demo_gateway_charge($card, $totals['total']) : ['ok' => false, 'reason' => ''];
    if (!$charge['ok']) {
        $errors[] = $charge['reason'] ?: 'Payment could not be processed.';
    }

    if ($errors === []) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO orders
                    (UserID, TotalAmount, Status, ShipName, ShipAddress, ShipCity,
                     ShipPostcode, ShipCountry, ShipPhone, ShipMethod, ShipCost,
                     EstimatedDelivery, PromoCode, DiscountAmount)
                 VALUES (?, ?, "Paid", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['UserID'],
                $totals['total'],
                $ship['ship_name'], $ship['ship_address'], $ship['ship_city'],
                $ship['ship_postcode'], $ship['ship_country'], $ship['ship_phone'],
                $selectedMethod,
                $totals['shipping'],
                est_delivery_date($selectedMethod),
                $totals['promo']['DiscountCode'] ?? null,
                $totals['discount'],
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $insLine  = $pdo->prepare('INSERT INTO order_details (OrderID, BookID, Quantity, PriceAtPurchase) VALUES (?, ?, ?, ?)');
            $decStock = $pdo->prepare('UPDATE books SET Stock = Stock - ? WHERE BookID = ? AND Stock >= ?');

            foreach ($data['rows'] as $line) {
                $insLine->execute([$orderId, $line['book']['BookID'], $line['qty'], $line['book']['Price']]);
                $decStock->execute([$line['qty'], $line['book']['BookID'], $line['qty']]);
                if ($decStock->rowCount() !== 1) {
                    throw new RuntimeException('Not enough stock for "' . $line['book']['Title'] . '".');
                }
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getMessage();
        }

        if ($errors === []) {
            $ord = db()->prepare('SELECT * FROM orders WHERE OrderID = ?');
            $ord->execute([$orderId]);
            notify_order_email($ord->fetch(), $user);

            cart_clear();
            clear_promo_session();
            flash('success', 'Order ' . $orderId . ' confirmed — a confirmation e-mail has been sent to ' . $user['Email'] . '.');
            redirect('confirmation.php?order=' . $orderId);
        }
    }
}

$pageTitle = 'Checkout';
include __DIR__ . '/includes/header.php';
?>

<h1>Checkout</h1>

<?php if ($errors): ?>
  <div class="flash flash-error" role="alert"><strong>Please fix the following:</strong>
    <ul><?php foreach ($errors as $er) { echo '<li>' . e($er) . '</li>'; } ?></ul>
  </div>
<?php endif; ?>
<div class="checkout-layout">
  <form method="post" action="checkout.php" data-validate class="checkout-form"
        data-subtotal="<?= $totals['subtotal'] ?>" data-discount="<?= $totals['discount'] ?>">
    <input type="hidden" name="action" value="checkout">

    <fieldset class="panel">
      <legend>1 &middot; Delivery details</legend>
      <div class="field">
        <label for="ship_name">Full name *</label>
        <input type="text" id="ship_name" name="ship_name" value="<?= $old('ship_name') ?: e($user['Name']) ?>"
               required minlength="2" data-rules="required|min:2">
      </div>
      <div class="field">
        <label for="ship_address">Street address *</label>
        <input type="text" id="ship_address" name="ship_address" value="<?= $old('ship_address') ?>"
               required minlength="5" data-rules="required|min:5">
      </div>
      <div class="field-row">
        <div class="field">
          <label for="ship_city">City *</label>
          <input type="text" id="ship_city" name="ship_city" value="<?= $old('ship_city') ?>" required>
        </div>
        <div class="field">
          <label for="ship_postcode">Postcode *</label>
          <input type="text" id="ship_postcode" name="ship_postcode" value="<?= $old('ship_postcode') ?>" required minlength="3">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="ship_country">Country *</label>
          <input type="text" id="ship_country" name="ship_country" value="<?= $old('ship_country') ?: STORE_COUNTRY ?>" required>
        </div>
        <div class="field">
          <label for="ship_phone">Phone *</label>
          <input type="tel" id="ship_phone" name="ship_phone" value="<?= $old('ship_phone') ?>" required>
        </div>
      </div>
    </fieldset>

    <fieldset class="panel">
      <legend>2 &middot; Delivery method</legend>
      <div class="ship-options" id="shipOptions">
        <?php foreach (shipping_methods() as $code => $m): $mc = $methodCosts[$code]; ?>
          <label class="ship-option<?= $selectedMethod === $code ? ' checked' : '' ?>">
            <input type="radio" name="ship_method" value="<?= e($code) ?>"
                   data-cost="<?= (float) $mc['shipping'] ?>" <?= $selectedMethod === $code ? 'checked' : '' ?>>
            <span class="ship-opt-main">
              <strong><?= $m['label'] ?></strong>
              <small><?= $m['note'] ?><?= $m['freeOver'] !== null ? ' &middot; free over ' . money((float) $m['freeOver']) : '' ?></small>
            </span>
            <span class="ship-opt-cost"><?= $mc['shipping'] > 0 ? money($mc['shipping']) : '<b class="good">Free</b>' ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="hint">Estimated delivery is calculated in working days from today and shown on your confirmation and tracking page.</p>
    </fieldset>

    <fieldset class="panel">
      <legend>3 &middot; Payment details <span class="muted">(simulated gateway)</span></legend>
      <div class="field">
        <label for="card_no">Card number *</label>
        <input type="text" id="card_no" name="card_no" inputmode="numeric" placeholder="4111 1111 1111 1111"
               value="<?= $old('card_no') ?>" data-card required>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="card_exp">Expiry (MM/YY) *</label>
          <input type="text" id="card_exp" name="card_exp" placeholder="12/28" value="<?= $old('card_exp') ?>" data-exp required>
        </div>
        <div class="field">
          <label for="card_cvv">CVV *</label>
          <input type="password" id="card_cvv" name="card_cvv" inputmode="numeric" maxlength="4" placeholder="123"
                 value="<?= $old('card_cvv') ?>" data-cvv required>
        </div>
      </div>
      <p class="hint">Demo gateway: any Visa (4&hellip;) or Mastercard (5&hellip;) is authorised; other prefixes are declined.</p>
    </fieldset>

    <button type="submit" class="btn btn-dark btn-lg place-order" id="placeOrderBtn">Place order &mdash; <span id="btnTotal"><?= money($totals['total']) ?></span></button>
  </form>

  <aside class="summary-panel">
    <h2>Order summary</h2>
    <?php if ($data['rows']): ?>
      <ul class="mini-items">
        <?php foreach ($data['rows'] as $line): ?>
          <li>
            <span><?= e($line['book']['Title']) ?> <small>&times; <?= (int) $line['qty'] ?></small></span>
            <span><?= money($line['line']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <dl class="summary-rows">
      <div><dt>Subtotal</dt><dd id="sumSubtotal"><?= money($totals['subtotal']) ?></dd></div>
      <?php if ($totals['discount'] > 0): ?>
        <div><dt>Discount</dt><dd class="good" id="sumDiscount">&minus;<?= money($totals['discount']) ?></dd></div>
      <?php endif; ?>
      <div><dt>Shipping</dt><dd id="sumShipping"><?= $totals['shipping'] > 0 ? money($totals['shipping']) : 'Free' ?></dd></div>
      <div class="total-row"><dt>Total</dt><dd id="sumTotal"><?= money($totals['total']) ?></dd></div>
    </dl>
  </aside>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
