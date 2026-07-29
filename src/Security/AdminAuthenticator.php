<?php

declare(strict_types=1);

namespace App\Security;

final readonly class AdminAuthenticator
{
    public function __construct(private string $username, private string $passwordHash) {}

    public function verify(string $username, string $password): bool
    {
        return hash_equals($this->username, $username) && password_verify($password, $this->passwordHash);
    }

    public function login(): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (\ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $name = session_name();
            if (false !== $name) {
                setcookie($name, '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
        }
        session_destroy();
    }

    public function isAuthenticated(): bool
    {
        return true === ($_SESSION['admin_authenticated'] ?? false);
    }
}
