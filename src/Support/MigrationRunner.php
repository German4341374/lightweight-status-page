<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;
use Throwable;

final readonly class MigrationRunner
{
    public function __construct(private PDO $pdo, private string $directory) {}

    public function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(255) PRIMARY KEY,
                applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );

        $files = glob($this->directory . '/*.sql');
        if (false === $files) {
            throw new RuntimeException('Unable to list migration files.');
        }
        sort($files);

        $query = $this->pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version = :version');
        $record = $this->pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');

        foreach ($files as $file) {
            $version = basename($file);
            $query->execute(['version' => $version]);
            if (0 < (int) $query->fetchColumn()) {
                continue;
            }

            $sql = file_get_contents($file);
            if (false === $sql) {
                throw new RuntimeException(\sprintf('Unable to read migration %s.', $version));
            }

            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sql);
                $record->execute(['version' => $version]);
                $this->pdo->commit();
                fwrite(STDOUT, \sprintf("Applied %s\n", $version));
            } catch (Throwable $exception) {
                $this->pdo->rollBack();
                throw $exception;
            }
        }
    }
}
