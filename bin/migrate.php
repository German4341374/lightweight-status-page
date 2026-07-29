<?php

declare(strict_types=1);

use App\Support\DatabaseFactory;
use App\Support\MigrationRunner;

require \dirname(__DIR__) . '/vendor/autoload.php';

$config = require \dirname(__DIR__) . '/bootstrap/environment.php';
$attempts = 20;
$lastError = null;

for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
    try {
        $pdo = DatabaseFactory::create($config);
        (new MigrationRunner($pdo, \dirname(__DIR__) . '/migrations'))->migrate();
        exit(0);
    } catch (Throwable $exception) {
        $lastError = $exception;
        if ($attempt === $attempts) {
            break;
        }
        fwrite(STDERR, \sprintf("Database unavailable, retrying migration (%d/%d).\n", $attempt, $attempts));
        sleep(2);
    }
}

fwrite(STDERR, \sprintf("Migration failed: %s\n", $lastError->getMessage()));
exit(1);
