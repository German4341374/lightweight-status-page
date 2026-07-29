<?php

declare(strict_types=1);

namespace App\Security;

use DateTimeImmutable;
use PDO;

final readonly class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public function __construct(private PDO $pdo, private string $appKey) {}

    public function isBlocked(string $address): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE identifier_hash = :identifier_hash
               AND attempted_at >= :cutoff",
        );
        $statement->execute([
            'identifier_hash' => $this->identifier($address),
            'cutoff' => (new DateTimeImmutable(\sprintf('-%d minutes', self::WINDOW_MINUTES)))->format(DATE_ATOM),
        ]);

        return self::MAX_ATTEMPTS <= (int) $statement->fetchColumn();
    }

    public function recordFailure(string $address): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_attempts (identifier_hash, attempted_at) VALUES (:identifier_hash, :attempted_at)',
        );
        $statement->execute([
            'identifier_hash' => $this->identifier($address),
            'attempted_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
        $cleanup = $this->pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < :cutoff');
        $cleanup->execute(['cutoff' => (new DateTimeImmutable('-1 day'))->format(DATE_ATOM)]);
    }

    public function clear(string $address): void
    {
        $statement = $this->pdo->prepare('DELETE FROM login_attempts WHERE identifier_hash = :identifier_hash');
        $statement->execute(['identifier_hash' => $this->identifier($address)]);
    }

    public function retryAfterSeconds(): int
    {
        return self::WINDOW_MINUTES * 60;
    }

    private function identifier(string $address): string
    {
        return hash_hmac('sha256', $address, $this->appKey);
    }
}
