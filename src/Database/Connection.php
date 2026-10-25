<?php

declare(strict_types=1);

namespace Xkhm\Database;

use PDO;
use PDOException;

final class Connection
{
    private static ?PDO $connection = null;

    public static function get(array $config): PDO
    {
        if (self::$connection === null) {
            $port = (int)($config['port'] ?? 3306);
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $port,
                $config['name'],
                $config['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            if (extension_loaded('pdo_mysql')) {
                $constName = 'PDO::MYSQL_ATTR_INIT_COMMAND';
                if (defined($constName)) {
                    $options[constant($constName)] = "SET NAMES {$config['charset']}";
                }
            }
            self::$connection = new PDO($dsn, $config['user'], $config['pass'], $options);
        }

        return self::$connection;
    }

    public static function reset(): void
    {
        self::$connection = null;
    }
}
