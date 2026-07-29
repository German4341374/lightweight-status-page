<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final readonly class Config
{
    public function __construct(
        public string $environment,
        public bool $debug,
        public string $appUrl,
        public string $appKey,
        public string $databaseDsn,
        public string $databaseUser,
        public string $databasePassword,
        public string $adminUsername,
        public string $adminPasswordHash,
        public bool $sessionSecure,
        public string $sessionName,
    ) {
        if (\strlen($this->appKey) < 24) {
            throw new RuntimeException('APP_KEY must contain at least 24 characters.');
        }

        if (!str_starts_with($this->adminPasswordHash, '$2y$') && !str_starts_with($this->adminPasswordHash, '$argon2')) {
            throw new RuntimeException('ADMIN_PASSWORD_HASH must be a password_hash() value.');
        }
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::required('APP_ENV'),
            self::boolean('APP_DEBUG', false),
            rtrim(self::required('APP_URL'), '/'),
            self::required('APP_KEY'),
            self::required('DB_DSN'),
            self::environment('DB_USER', ''),
            self::environment('DB_PASSWORD', ''),
            self::required('ADMIN_USERNAME'),
            self::required('ADMIN_PASSWORD_HASH'),
            self::boolean('SESSION_SECURE', true),
            self::environment('SESSION_NAME', 'status_page_session'),
        );
    }

    private static function required(string $name): string
    {
        $value = trim(self::environment($name, ''));
        if ('' === $value) {
            throw new RuntimeException(\sprintf('Required environment variable %s is missing.', $name));
        }

        return $value;
    }

    private static function environment(string $name, string $default): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return false === $value ? $default : (string) $value;
    }

    private static function boolean(string $name, bool $default): bool
    {
        $value = self::environment($name, $default ? 'true' : 'false');
        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if (null === $parsed) {
            throw new RuntimeException(\sprintf('%s must be true or false.', $name));
        }

        return $parsed;
    }
}
