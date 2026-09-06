<?php
/**
 * Shopping cart — add/update/remove lines, promo code, order summary.
 */
require_once __DIR__ . '/includes/init.php';

/* ---------------- POST actions ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $next   = post('next', 'cart.php');
    if ($next === '' || preg_match('#^[a-z]+://#i', $next)) {
        $next = 'cart.php';
    }

    if ($action === 'add') {
        $bookId = (int) post('book_id');
        $qty    = max(1, (int) post('qty', '1'));
        $stmt   = db()->prepare('SELECT BookID, Title, Stock FROM books WHERE BookID = ?');
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();
        if ($book) {
            cart_add($bookId, $qty);
            flash('success', 'Added "' . $book['Title'] . '" to your cart.');
        } else {
            flash('error', 'That book could not be found.');
        }
        redirect($next);
    }

    if ($action === 'update') {
        // A row-level "Remove" posts back through this form with remove_book set.
        $removeBook = (int) post('remove_book');
        if ($removeBook > 0) {
            cart_remove($removeBook);
            flash('info', 'Item removed from your cart.');
            redirect('cart.php');
        }
        foreach (cart() as $bookId => $qty) {
            $posted = (int) ($_POST['qty'][$bookId] ?? 0);
            cart_set_qty($bookId, $posted);
        }
        flash('success', 'Cart updated.');
        redirect('cart.php');
    }

    if ($action === 'clear') {
        cart_clear();
        clear_promo_session();
        flash('info', 'Your cart has been cleared.');
        redirect('cart.php');
    }

    if ($action === 'promo') {
        $code  = post('code');
        $promo = set_promo_session($code);
        if ($promo) {
            flash('success', 'Promo code ' . $code . ' applied — '
                . rtrim(rtrim(number_format((float) $promo['DiscountValue'], 2), '0'), '.') . '% off.');
        } else {
            flash('error', 'That promo code is invalid or has expired.');
        }
        redirect('cart.php');
    }

    if ($action === 'remove_promo') {
        clear_promo_session();
        flash('info', 'Promo code removed.');
        redirect('cart.php');
    }
}

/* ---------------- Render ---------------- */
$data   = cart_items();
$totals = order_totals($data['subtotal']);

$pageTitle = 'Shopping cart';
include __DIR__ . '/includes/header.php';
?>

<h1>Your shopping cart</h1>

<?php if (!$data['rows']): ?>
  <div class="empty-state">
    <p>Your cart is empty.</p>
    <p><a class="btn btn-dark" href="catalogue.php">Browse the catalogue &rarr;</a></p>
  </div>
<?php else: ?>

<div class="cart-layout">
  <section class="cart-lines">
    <form method="post" action="cart.php" class="cart-form">
      <input type="hidden" name="action" value="update">
      <table class="data-table cart-table">
        <thead>
          <tr><th>Item</th><th>Price</th><th>Qty</th><th class="num">Subtotal</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($data['rows'] as $line): $bk = $line['book']; ?>
            <tr>
              <td>
                <div class="cart-item">
                  <a href="book.php?id=<?= (int) $bk['BookID'] ?>"><?= cover_html($bk, 'cover-sm') ?></a>
                  <div>
                    <a class="cart-title" href="book.php?id=<?= (int) $bk['BookID'] ?>"><?= e($bk['Title']) ?></a>
                    <span class="muted"><?= e($bk['Author']) ?></span>
                  </div>
                </div>
              </td>
              <td><?= money((float) $bk['Price']) ?></td>
              <td>
                <label class="sr-only" for="qty<?= (int) $bk['BookID'] ?>">Quantity for <?= e($bk['Title']) ?></label>
                <input class="qty-input" type="number" name="qty[<?= (int) $bk['BookID'] ?>]"
                       id="qty<?= (int) $bk['BookID'] ?>" value="<?= (int) $line['qty'] ?>" min="0" max="99">
              </td>
              <td class="num"><?= money($line['line']) ?></td>
              <td>
                <button type="submit" name="remove_book" value="<?= (int) $bk['BookID'] ?>"
                        class="btn-link-danger"
                        onclick="return confirm('Remove this item from your cart?');">Remove</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="cart-actions">
        <button type="submit" class="btn btn-dark">Update quantities</button>
      </p>
    </form>

    <form method="post" action="cart.php" class="clear-form">
      <input type="hidden" name="action" value="clear">
      <button type="submit" class="btn-link-danger" onclick="return confirm('Clear the whole cart?');">Clear cart</button>
    </form>
  </section>

  <aside class="summary-panel">
    <h2>Order summary</h2>
    <dl class="summary-rows">
      <div><dt>Subtotal</dt><dd><?= money($totals['subtotal']) ?></dd></div>
      <?php if ($totals['discount'] > 0): ?>
        <div><dt>Discount (<?= e($totals['promo']['DiscountCode']) ?>)</dt><dd class="good">&minus;<?= money($totals['discount']) ?></dd></div>
      <?php endif; ?>
      <div><dt>Shipping</dt><dd><?= $totals['shipping'] > 0 ? money($totals['shipping']) : '<span class="good">Free</span>' ?></dd></div>
      <div class="total-row"><dt>Total</dt><dd><?= money($totals['total']) ?></dd></div>
    </dl>

    <?php if ($totals['promo']): ?>
      <p class="applied-promo">Promo <b><?= e($totals['promo']['DiscountCode']) ?></b> applied
        (<?= rtrim(rtrim(number_format((float) $totals['promo']['DiscountValue'], 2), '0'), '.') ?>%)</p>
      <form method="post" action="cart.php">
        <input type="hidden" name="action" value="remove_promo">
        <button class="btn-link-danger" type="submit">Remove promo</button>
      </form>
    <?php else: ?>
      <form method="post" action="cart.php" class="promo-form">
        <label class="sr-only" for="code">Promo code</label>
        <input type="text" id="code" name="code" placeholder="Promo code (e.g. READ30)">
        <input type="hidden" name="action" value="promo">
        <button type="submit" class="btn btn-light btn-sm">Apply</button>
      </form>
      <?php if ($totals['subtotal'] < FREE_SHIPPING_OVER): ?>
        <p class="hint">Add <?= money(FREE_SHIPPING_OVER - $totals['subtotal']) ?> more for free shipping.</p>
      <?php endif; ?>
    <?php endif; ?>

    <a class="btn btn-dark btn-block btn-lg checkout-btn" href="checkout.php">Proceed to checkout &rarr;</a>
    <a class="btn btn-light btn-block" href="catalogue.php">Continue shopping</a>
  </aside>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

