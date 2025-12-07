<?php

declare(strict_types=1);

namespace SistemaVentas\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;

    /**
     * Returns a shared PDO instance configured from config/config.php.
     *
     * @throws PDOException when the connection cannot be established.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $config = require __DIR__ . '/../../config/config.php';
        $db = $config['db'];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['database'],
            $db['charset']
        );

        self::$instance = new PDO($dsn, $db['username'], $db['password'], $db['options']);
        return self::$instance;
    }

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Prevent cloning.
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization.
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton Connection');
    }
}
