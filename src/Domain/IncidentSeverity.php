<?php

declare(strict_types=1);

namespace App\Domain;

enum IncidentSeverity: string
{
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn(self $severity): array => ['value' => $severity->value, 'label' => $severity->label()],
            self::cases(),
        );
    }
}
