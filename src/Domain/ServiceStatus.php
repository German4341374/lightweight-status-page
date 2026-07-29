<?php

declare(strict_types=1);

namespace App\Domain;

enum ServiceStatus: string
{
    case Operational = 'operational';
    case Degraded = 'degraded';
    case PartialOutage = 'partial_outage';
    case MajorOutage = 'major_outage';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operational',
            self::Degraded => 'Degraded Performance',
            self::PartialOutage => 'Partial Outage',
            self::MajorOutage => 'Major Outage',
            self::Maintenance => 'Maintenance',
        };
    }

    public function isAvailable(): bool
    {
        return self::Operational === $this || self::Degraded === $this;
    }

    public function isExcludedFromUptime(): bool
    {
        return self::Maintenance === $this;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn(self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
