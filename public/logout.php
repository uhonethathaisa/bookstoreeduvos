<?php
/** Log out and return home. */
require_once __DIR__ . '/includes/init.php';
logout_user();
flash('info', 'You have been logged out.');
redirect('index.php');
