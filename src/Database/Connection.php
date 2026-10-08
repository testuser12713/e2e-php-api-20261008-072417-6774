<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class Connection
{
    public static function fromEnv(): PDO
    {
        $path = getenv('DB_PATH');
        if ($path === false || $path === '') {
            $path = 'var/bookmarks.sqlite';
        }

        return self::open($path);
    }

    public static function open(string $path): PDO
    {
        if ($path !== ':memory:') {
            $directory = dirname($path);
            if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }

        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
