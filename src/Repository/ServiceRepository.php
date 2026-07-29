<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\ServiceStatus;
use App\Service\UptimeCalculator;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use RuntimeException;

final readonly class ServiceRepository
{
    public function __construct(private PDO $pdo, private UptimeCalculator $uptimeCalculator) {}

    /** @return list<array<string, mixed>> */
    public function allWithUptime(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $rows = $this->query(
            'SELECT id, name, description, status, display_order, created_at, updated_at
             FROM services
             ORDER BY display_order, name',
        )->fetchAll();
        $rows = array_values($rows);

        foreach ($rows as &$row) {
            $row['status_label'] = ServiceStatus::from((string) $row['status'])->label();
            $row['uptime'] = $this->uptime((int) $row['id'], ServiceStatus::from((string) $row['status']), $from, $until);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, description, status, display_order, created_at, updated_at
             FROM services WHERE id = :id',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (false === $row) {
            throw new RuntimeException('Service not found.');
        }

        return $row;
    }

    public function create(string $name, string $description, ServiceStatus $status, int $displayOrder): int
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO services (name, description, status, display_order)
                 VALUES (:name, :description, :status, :display_order)
                 RETURNING id',
            );
            $statement->execute([
                'name' => $name,
                'description' => $description,
                'status' => $status->value,
                'display_order' => $displayOrder,
            ]);
            $id = $statement->fetchColumn();
            if (false === $id) {
                throw new RuntimeException('Unable to obtain the created service identifier.');
            }
            $statement->closeCursor();
            $serviceId = (int) $id;
            $this->recordStatus($serviceId, $status);
            $this->pdo->commit();

            return $serviceId;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function updateStatus(int $id, ServiceStatus $status): void
    {
        $service = $this->find($id);
        if ($service['status'] === $status->value) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'UPDATE services SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            );
            $statement->execute(['status' => $status->value, 'id' => $id]);
            $this->recordStatus($id, $status);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function recentHistory(DateTimeImmutable $from): array
    {
        $statement = $this->pdo->prepare(
            'SELECT h.id, h.service_id, s.name AS service_name, h.status, h.recorded_at
             FROM service_status_history h
             JOIN services s ON s.id = h.service_id
             WHERE h.recorded_at >= :from
             ORDER BY h.recorded_at DESC',
        );
        $statement->execute(['from' => $from->format(DATE_ATOM)]);

        return array_values($statement->fetchAll());
    }

    private function recordStatus(int $serviceId, ServiceStatus $status): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO service_status_history (service_id, status) VALUES (:service_id, :status)',
        );
        $statement->execute(['service_id' => $serviceId, 'status' => $status->value]);
    }

    private function uptime(
        int $serviceId,
        ServiceStatus $fallback,
        DateTimeImmutable $from,
        DateTimeImmutable $until,
    ): float {
        $prior = $this->pdo->prepare(
            'SELECT status FROM service_status_history
             WHERE service_id = :service_id AND recorded_at <= :from
             ORDER BY recorded_at DESC LIMIT 1',
        );
        $prior->execute(['service_id' => $serviceId, 'from' => $from->format(DATE_ATOM)]);
        $initial = $prior->fetchColumn();
        $initialStatus = false === $initial ? $fallback : ServiceStatus::from((string) $initial);

        $history = $this->pdo->prepare(
            'SELECT status, recorded_at FROM service_status_history
             WHERE service_id = :service_id AND recorded_at > :from AND recorded_at <= :until
             ORDER BY recorded_at',
        );
        $history->execute([
            'service_id' => $serviceId,
            'from' => $from->format(DATE_ATOM),
            'until' => $until->format(DATE_ATOM),
        ]);

        /** @var list<array{status: string, recorded_at: string}> $transitions */
        $transitions = $history->fetchAll();

        return $this->uptimeCalculator->calculate($initialStatus, $transitions, $from, $until);
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
