<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\IncidentSeverity;
use App\Domain\IncidentStatus;
use App\Domain\ServiceStatus;
use PHPUnit\Framework\TestCase;

final class DomainEnumTest extends TestCase
{
    public function testServiceLabelsMatchPublicLanguage(): void
    {
        self::assertSame('Degraded Performance', ServiceStatus::Degraded->label());
        self::assertSame('Partial Outage', ServiceStatus::PartialOutage->label());
    }

    public function testOnlyOperationalAndDegradedAreAvailable(): void
    {
        self::assertTrue(ServiceStatus::Operational->isAvailable());
        self::assertTrue(ServiceStatus::Degraded->isAvailable());
        self::assertFalse(ServiceStatus::PartialOutage->isAvailable());
    }

    public function testMaintenanceIsExcludedFromUptime(): void
    {
        self::assertTrue(ServiceStatus::Maintenance->isExcludedFromUptime());
        self::assertFalse(ServiceStatus::MajorOutage->isExcludedFromUptime());
    }

    public function testIncidentOptionsContainAllStates(): void
    {
        self::assertCount(4, IncidentStatus::options());
        self::assertCount(4, IncidentSeverity::options());
    }
}
