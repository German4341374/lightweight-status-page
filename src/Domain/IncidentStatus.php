<?php

declare(strict_types=1);

namespace App\Domain;

enum IncidentStatus: string
{
    case Investigating = 'investigating';
    case Identified = 'identified';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';

    public function label(): string
    {
        return ucfirst($this->value);
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
