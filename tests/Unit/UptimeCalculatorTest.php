<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ServiceStatus;
use App\Service\UptimeCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UptimeCalculatorTest extends TestCase
{
    private UptimeCalculator $calculator;
    private DateTimeImmutable $from;
    private DateTimeImmutable $until;

    protected function setUp(): void
    {
        $this->calculator = new UptimeCalculator();
        $this->from = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $this->until = new DateTimeImmutable('2026-01-01T10:00:00Z');
    }

    public function testFullyOperationalPeriodIsOneHundredPercent(): void
    {
        self::assertSame(100.0, $this->calculator->calculate(ServiceStatus::Operational, [], $this->from, $this->until));
    }

    public function testFullyUnavailablePeriodIsZeroPercent(): void
    {
        self::assertSame(0.0, $this->calculator->calculate(ServiceStatus::MajorOutage, [], $this->from, $this->until));
    }

    public function testDegradedTimeCountsAsAvailable(): void
    {
        self::assertSame(100.0, $this->calculator->calculate(ServiceStatus::Degraded, [], $this->from, $this->until));
    }

    public function testOneHourOutageProducesNinetyPercent(): void
    {
        $transitions = [
            ['status' => 'major_outage', 'recorded_at' => '2026-01-01T04:00:00Z'],
            ['status' => 'operational', 'recorded_at' => '2026-01-01T05:00:00Z'],
        ];

        self::assertSame(90.0, $this->calculator->calculate(ServiceStatus::Operational, $transitions, $this->from, $this->until));
    }

    public function testMaintenanceIsExcludedFromDenominator(): void
    {
        $transitions = [
            ['status' => 'maintenance', 'recorded_at' => '2026-01-01T04:00:00Z'],
            ['status' => 'operational', 'recorded_at' => '2026-01-01T06:00:00Z'],
        ];

        self::assertSame(100.0, $this->calculator->calculate(ServiceStatus::Operational, $transitions, $this->from, $this->until));
    }

    public function testEmptyMeasuredPeriodReturnsOneHundredPercent(): void
    {
        self::assertSame(100.0, $this->calculator->calculate(ServiceStatus::Maintenance, [], $this->from, $this->until));
    }

    public function testTransitionsBeforeWindowOnlyChangeInitialState(): void
    {
        $transitions = [
            ['status' => 'partial_outage', 'recorded_at' => '2025-12-31T23:00:00Z'],
            ['status' => 'operational', 'recorded_at' => '2026-01-01T01:00:00Z'],
        ];

        self::assertSame(90.0, $this->calculator->calculate(ServiceStatus::Operational, $transitions, $this->from, $this->until));
    }
}
