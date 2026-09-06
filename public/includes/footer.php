</main>

<!-- ==================== ADD-TO-CART MODAL (AJAX) ==================== -->
<div id="addToCartModal" class="modal" role="dialog" aria-modal="true"
     aria-labelledby="atcModalTitle" hidden>
  <div class="modal-overlay" data-atc-close></div>
  <div class="modal-card" role="document">
    <button type="button" class="modal-close" data-atc-close aria-label="Close modal">&times;</button>

    <div class="modal-icon" aria-hidden="true">&#10004;</div>
    <h2 id="atcModalTitle">Added to Cart!</h2>

    <p class="modal-book-title" id="atcBookTitle"></p>
    <p class="modal-sub" id="atcBookMeta"></p>

    <form id="atcForm" class="atc-form">
      <input type="hidden" name="book_id" id="atcBookId">

      <div class="atc-qty">
        <label for="atcQty">Quantity</label>
        <div class="qty-stepper">
          <button type="button" class="step" data-atc-step="-1" aria-label="Decrease quantity">&minus;</button>
          <input type="number" name="quantity" id="atcQty" min="1" value="1" inputmode="numeric">
          <button type="button" class="step" data-atc-step="1" aria-label="Increase quantity">+</button>
        </div>
        <p class="hint" id="atcStockNote"></p>
      </div>

      <p class="modal-msg" id="atcMsg" role="status"></p>

      <div class="modal-actions">
        <button type="button" class="btn btn-light" data-atc-close>Continue Shopping</button>
        <button type="submit" class="btn btn-dark" id="atcCheckoutBtn">Update Cart &amp; Checkout</button>
      </div>
    </form>
  </div>
</div>
<!-- ==================== /ADD-TO-CART MODAL ==================== -->

<footer class="site-footer">
  <div class="container footer-row">
    <p>&copy; 2026 <?= e(APP_NAME) ?> South Africa &mdash; proudly local online bookshop. Great stories, delivered to your door.</p>
    <p class="footer-links">
      <a href="<?= url('catalogue.php') ?>">Catalogue</a>
      <a href="<?= url('cart.php') ?>">Cart</a>
      <a href="<?= url('login.php') ?>" data-atc-ignore>Log in</a>
      <?php if (is_admin()): ?><a href="<?= url('admin/index.php') ?>">Admin panel</a><?php endif; ?>
    </p>
  </div>
</footer>

<script src="<?= asset('js/main.js') ?>"></script>
</body>
</html>

