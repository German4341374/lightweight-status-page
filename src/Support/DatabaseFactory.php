<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

final class DatabaseFactory
{
    public static function create(Config $config): PDO
    {
        $pdo = new PDO(
            $config->databaseDsn,
            '' === $config->databaseUser ? null : $config->databaseUser,
            '' === $config->databasePassword ? null : $config->databasePassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        if ('pgsql' === $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $pdo->exec("SET TIME ZONE 'UTC'");
        }

        return $pdo;
    }
}
