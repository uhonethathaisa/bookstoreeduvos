<?php
/**
 * Bootstrapping for every page/API entry point.
 * Wired from public root as  __DIR__.'/includes/init.php'
 * and from public/admin + public/api as  dirname(__DIR__).'/includes/init.php'
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Convenience accessor to the shared PDO instance. */
function db(): PDO
{
    return DatabaseConnection::getInstance()->pdo();
}
