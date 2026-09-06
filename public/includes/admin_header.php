<?php
/**
 * Admin layout opener. Expects $adminPage (slug) and optional $pageTitle.
 * Requires admin auth and renders the side navigation + content wrapper.
 */
require_once __DIR__ . '/init.php';
require_admin();

$adminPage = $adminPage ?? 'dashboard';
$pageTitle = ($pageTitle ?? 'Admin') . ' · Admin';

include __DIR__ . '/header.php';
?>

<div class="admin-layout">
  <nav class="admin-nav" aria-label="Admin sections">
    <p class="admin-nav-title">Administration</p>
    <a href="index.php"      class="<?= $adminPage === 'dashboard'   ? 'active' : '' ?>">&#128202; Dashboard</a>
    <a href="books.php"      class="<?= $adminPage === 'books'       ? 'active' : '' ?>">&#128218; Books</a>
    <a href="orders.php"     class="<?= $adminPage === 'orders'      ? 'active' : '' ?>">&#128230; Orders</a>
    <a href="users.php"      class="<?= $adminPage === 'users'       ? 'active' : '' ?>">&#128101; Users</a>
    <a href="promotions.php" class="<?= $adminPage === 'promotions'  ? 'active' : '' ?>">&#127991; Promotions</a>
    <a class="back-store" href="<?= url('index.php') ?>">&larr; Back to storefront</a>
  </nav>
  <div class="admin-content">
