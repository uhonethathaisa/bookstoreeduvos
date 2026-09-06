</main>

<footer class="site-footer">
  <div class="container footer-row">
    <p>&copy; 2026 <?= e(APP_NAME) ?> South Africa &mdash; proudly local online bookshop. Great stories, delivered to your door.</p>
    <p class="footer-links">
      <a href="<?= url('catalogue.php') ?>">Catalogue</a>
      <a href="<?= url('cart.php') ?>">Cart</a>
      <a href="<?= url('login.php') ?>">Log in</a>
      <?php if (is_admin()): ?><a href="<?= url('admin/index.php') ?>">Admin panel</a><?php endif; ?>
    </p>
  </div>
</footer>

<script src="<?= asset('js/main.js') ?>"></script>
</body>
</html>
