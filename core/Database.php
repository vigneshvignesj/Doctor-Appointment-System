<?php

namespace Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];

    public static function init(array $config): void
    {
        self::$config = $config;
    }

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $c = self::$config;
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $c['host'],
                $c['port'],
                $c['name'],
                $c['charset']
            );

            try {
                self::$connection = new PDO($dsn, $c['user'], $c['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                require __DIR__ . '/../app/views/errors/500.php';
                exit;
            }
        }

        return self::$connection;
    }
}
