<?php

declare(strict_types=1);

use App\Support\Config;
use Dotenv\Dotenv;

$root = \dirname(__DIR__);
if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

return Config::fromEnvironment();
