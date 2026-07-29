<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public function token(): string
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (!\is_string($token) || 64 !== \strlen($token)) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    public function validate(mixed $submitted): void
    {
        $known = $_SESSION[self::SESSION_KEY] ?? '';
        if (!\is_string($submitted) || !\is_string($known) || '' === $known || !hash_equals($known, $submitted)) {
            throw new RuntimeException('The form has expired. Please retry.');
        }
    }
}
