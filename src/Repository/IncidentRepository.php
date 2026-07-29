<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\IncidentSeverity;
use App\Domain\IncidentStatus;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final readonly class IncidentRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array<string, mixed>> */
    public function active(): array
    {
        $statement = $this->query(
            "SELECT id, title, description, severity, status, started_at, resolved_at, created_at
             FROM incidents WHERE status <> 'resolved' ORDER BY started_at DESC",
        );

        return array_values($statement->fetchAll());
    }

    /** @return list<array<string, mixed>> */
    public function recent(DateTimeImmutable $from): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, description, severity, status, started_at, resolved_at, created_at
             FROM incidents WHERE started_at >= :from ORDER BY started_at DESC',
        );
        $statement->execute(['from' => $from->format(DATE_ATOM)]);
        $incidents = array_values($statement->fetchAll());

        foreach ($incidents as &$incident) {
            $incident['updates'] = $this->updates((int) $incident['id']);
        }

        return $incidents;
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, description, severity, status, started_at, resolved_at, created_at
             FROM incidents WHERE id = :id',
        );
        $statement->execute(['id' => $id]);
        $incident = $statement->fetch();
        if (false === $incident) {
            throw new RuntimeException('Incident not found.');
        }
        $incident['updates'] = $this->updates($id);

        return $incident;
    }

    public function create(
        string $title,
        string $description,
        IncidentSeverity $severity,
        IncidentStatus $status,
        DateTimeImmutable $startedAt,
    ): int {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO incidents (title, description, severity, status, started_at)
                 VALUES (:title, :description, :severity, :status, :started_at)
                 RETURNING id',
            );
            $statement->execute([
                'title' => $title,
                'description' => $description,
                'severity' => $severity->value,
                'status' => $status->value,
                'started_at' => $startedAt->format(DATE_ATOM),
            ]);
            $id = $statement->fetchColumn();
            if (false === $id) {
                throw new RuntimeException('Unable to obtain the created incident identifier.');
            }
            $statement->closeCursor();
            $incidentId = (int) $id;
            $this->insertUpdate($incidentId, $description, $status);
            $this->pdo->commit();

            return $incidentId;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function addUpdate(int $incidentId, string $message, IncidentStatus $status): void
    {
        $this->find($incidentId);
        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare('UPDATE incidents SET status = :status WHERE id = :id');
            $update->execute(['status' => $status->value, 'id' => $incidentId]);
            $this->insertUpdate($incidentId, $message, $status);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function resolve(int $incidentId, string $message): void
    {
        $this->find($incidentId);
        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare(
                "UPDATE incidents
                 SET status = 'resolved', resolved_at = CURRENT_TIMESTAMP
                 WHERE id = :id",
            );
            $update->execute(['id' => $incidentId]);
            $this->insertUpdate($incidentId, $message, IncidentStatus::Resolved);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function updates(int $incidentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, incident_id, message, status, created_at
             FROM incident_updates WHERE incident_id = :incident_id ORDER BY created_at DESC, id DESC',
        );
        $statement->execute(['incident_id' => $incidentId]);

        return array_values($statement->fetchAll());
    }

    private function insertUpdate(int $incidentId, string $message, IncidentStatus $status): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO incident_updates (incident_id, message, status)
             VALUES (:incident_id, :message, :status)',
        );
        $statement->execute([
            'incident_id' => $incidentId,
            'message' => $message,
            'status' => $status->value,
        ]);
    }

    private function query(string $sql): PDOStatement
    {
        $statement = $this->pdo->query($sql);
        if (false === $statement) {
            throw new RuntimeException('Database query failed.');
        }

        return $statement;
    }
}
