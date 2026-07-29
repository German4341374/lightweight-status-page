<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\IncidentSeverity;
use App\Domain\IncidentStatus;
use App\Domain\ServiceStatus;
use App\Repository\IncidentRepository;
use App\Repository\ServiceRepository;
use App\Service\UptimeCalculator;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class RepositoryTest extends TestCase
{
    private PDO $pdo;
    private ServiceRepository $services;
    private IncidentRepository $incidents;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec(
            <<<'SQL'
CREATE TABLE services (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, description TEXT, status TEXT, display_order INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE service_status_history (id INTEGER PRIMARY KEY AUTOINCREMENT, service_id INTEGER, status TEXT, recorded_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE incidents (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, description TEXT, severity TEXT, status TEXT, started_at TEXT, resolved_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE incident_updates (id INTEGER PRIMARY KEY AUTOINCREMENT, incident_id INTEGER, message TEXT, status TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
SQL,
        );
        $this->services = new ServiceRepository($this->pdo, new UptimeCalculator());
        $this->incidents = new IncidentRepository($this->pdo);
    }

    public function testCreatingServiceRecordsInitialHistory(): void
    {
        $id = $this->services->create('Search', 'Public search endpoint.', ServiceStatus::Operational, 20);
        self::assertSame('Search', $this->services->find($id)['name']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM service_status_history')->fetchColumn());
    }

    public function testChangingServiceStatusRecordsHistory(): void
    {
        $id = $this->services->create('Search', 'Public search endpoint.', ServiceStatus::Operational, 20);
        $this->services->updateStatus($id, ServiceStatus::MajorOutage);
        self::assertSame('major_outage', $this->services->find($id)['status']);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM service_status_history')->fetchColumn());
    }

    public function testUnchangedServiceStatusIsIdempotent(): void
    {
        $id = $this->services->create('Search', 'Public search endpoint.', ServiceStatus::Operational, 20);
        $this->services->updateStatus($id, ServiceStatus::Operational);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM service_status_history')->fetchColumn());
    }

    public function testCreatingIncidentPublishesInitialUpdate(): void
    {
        $id = $this->incidents->create(
            'API latency',
            'Requests are taking longer than expected.',
            IncidentSeverity::Minor,
            IncidentStatus::Investigating,
            new DateTimeImmutable('2026-01-01T10:00:00Z'),
        );
        $incident = $this->incidents->find($id);
        self::assertSame('investigating', $incident['status']);
        self::assertCount(1, $incident['updates']);
    }

    public function testIncidentUpdateChangesCurrentStatus(): void
    {
        $id = $this->incidents->create(
            'API latency',
            'Requests are taking longer than expected.',
            IncidentSeverity::Minor,
            IncidentStatus::Investigating,
            new DateTimeImmutable(),
        );
        $this->incidents->addUpdate($id, 'The dependency has been identified.', IncidentStatus::Identified);
        self::assertSame('identified', $this->incidents->find($id)['status']);
        self::assertCount(2, $this->incidents->updates($id));
    }

    public function testResolvingIncidentSetsTimestampAndUpdate(): void
    {
        $id = $this->incidents->create(
            'API latency',
            'Requests are taking longer than expected.',
            IncidentSeverity::Minor,
            IncidentStatus::Investigating,
            new DateTimeImmutable(),
        );
        $this->incidents->resolve($id, 'Performance has returned to normal.');
        $incident = $this->incidents->find($id);
        self::assertSame('resolved', $incident['status']);
        self::assertNotNull($incident['resolved_at']);
        self::assertCount(2, $incident['updates']);
    }
}
