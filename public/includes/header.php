<?php
/** Shared page header. Expects optional $pageTitle and $activePage strings. */
if (!isset($pageTitle)) { $pageTitle = APP_NAME; }
$user = current_user();
$cartN = cart_count();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
  <div class="container header-row">
    <a class="logo" href="<?= url('index.php') ?>" aria-label="<?= e(APP_NAME) ?> home">&#128218; BookNest</a>

    <form class="searchbox" action="<?= url('catalogue.php') ?>" method="get" role="search" autocomplete="off">
      <label class="sr-only" for="searchInput">Search books</label>
      <input type="search" name="q" id="searchInput"
             placeholder="Search by title, author or ISBN…"
             value="<?= e(get('q')) ?>">
      <div id="autocomplete" class="ac-dropdown" hidden></div>
      <button type="submit" class="btn btn-dark">Search</button>
    </form>

    <nav class="user-nav" aria-label="Account and basket">
      <?php if (is_logged_in()): ?>
        <a class="nav-link" href="<?= url('wishlist.php') ?>">Wishlist</a>
        <?php if (is_admin()): ?><a class="nav-link" href="<?= url('admin/index.php') ?>">Admin</a><?php endif; ?>
        <div class="user-menu">
          <a class="nav-link" href="<?= url('profile.php') ?>">Hi, <?= e(explode(' ', $user['Name'])[0]) ?> &#9662;</a>
          <div class="user-menu-drop">
            <a href="<?= url('profile.php') ?>">My profile &amp; orders</a>
            <a href="<?= url('logout.php') ?>">Log out</a>
          </div>
        </div>
      <?php else: ?>
        <a class="nav-link" href="<?= url('login.php') ?>">Log in</a>
        <a class="nav-link" href="<?= url('register.php') ?>">Register</a>
      <?php endif; ?>
      <a class="cart-link" href="<?= url('cart.php') ?>">
        &#128722; Cart
        <span class="cart-badge" id="cartCount"><?= (int) $cartN ?></span>
      </a>
    </nav>
  </div>

  <div class="container">
    <nav class="catnav" aria-label="Book categories">
      <a href="<?= url('catalogue.php') ?>" class="<?= ($activePage ?? '') === 'all' ? 'active' : '' ?>">All books</a>
      <?php foreach (['Fiction', 'Non-fiction', "Children's"] as $g): ?>
        <a href="<?= url('catalogue.php') ?>?genre=<?= urlencode($g) ?>"
           class="<?= ($activePage ?? '') === 'genre-' . $g ? 'active' : '' ?>"><?= e($g) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="container flash-zone"><?php render_flashes(); ?></div>
<?php endif; ?>

<main id="main" class="container">
