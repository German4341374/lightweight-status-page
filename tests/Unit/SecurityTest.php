<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Security\AdminAuthenticator;
use App\Security\Csrf;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testAuthenticatorAcceptsValidCredentials(): void
    {
        $auth = new AdminAuthenticator('operator', password_hash('correct-horse', PASSWORD_DEFAULT));
        self::assertTrue($auth->verify('operator', 'correct-horse'));
    }

    public function testAuthenticatorRejectsWrongPassword(): void
    {
        $auth = new AdminAuthenticator('operator', password_hash('correct-horse', PASSWORD_DEFAULT));
        self::assertFalse($auth->verify('operator', 'wrong-password'));
    }

    public function testAuthenticatorRejectsWrongUsername(): void
    {
        $auth = new AdminAuthenticator('operator', password_hash('correct-horse', PASSWORD_DEFAULT));
        self::assertFalse($auth->verify('another-user', 'correct-horse'));
    }

    public function testCsrfTokenIsStableAndValid(): void
    {
        $csrf = new Csrf();
        $token = $csrf->token();
        self::assertSame($token, $csrf->token());
        $csrf->validate($token);
        self::assertSame(64, \strlen($token));
    }

    public function testCsrfRejectsMissingToken(): void
    {
        $this->expectException(RuntimeException::class);
        (new Csrf())->validate(null);
    }

    public function testCsrfRejectsDifferentToken(): void
    {
        $csrf = new Csrf();
        $csrf->token();
        $this->expectException(RuntimeException::class);
        $csrf->validate(str_repeat('0', 64));
    }
}
