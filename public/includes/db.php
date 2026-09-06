<?php
/**
 * DatabaseConnection — SINGLETON pattern (see Deliverable 1, class diagram §07)
 * Exactly one shared PDO connection per request.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class DatabaseConnection
{
    private static ?DatabaseConnection $instance = null;
    private PDO $pdo;

    /** Private constructor — Singleton. */
    private function __construct()
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME);

        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function __clone() {}
}
