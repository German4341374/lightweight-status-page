<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\ServiceStatus;
use DateTimeImmutable;

final class UptimeCalculator
{
    /**
     * @param list<array{status: string, recorded_at: string}> $transitions
     */
    public function calculate(
        ServiceStatus $initialStatus,
        array $transitions,
        DateTimeImmutable $from,
        DateTimeImmutable $until,
    ): float {
        if ($until <= $from) {
            return 100.0;
        }

        $availableSeconds = 0;
        $measuredSeconds = 0;
        $cursor = $from;
        $status = $initialStatus;

        foreach ($transitions as $transition) {
            $changedAt = new DateTimeImmutable($transition['recorded_at']);
            if ($changedAt <= $from) {
                $status = ServiceStatus::from($transition['status']);
                continue;
            }
            if ($changedAt >= $until) {
                break;
            }

            [$availableSeconds, $measuredSeconds] = $this->accumulate(
                $status,
                $cursor,
                $changedAt,
                $availableSeconds,
                $measuredSeconds,
            );
            $status = ServiceStatus::from($transition['status']);
            $cursor = $changedAt;
        }

        [$availableSeconds, $measuredSeconds] = $this->accumulate(
            $status,
            $cursor,
            $until,
            $availableSeconds,
            $measuredSeconds,
        );

        return 0 === $measuredSeconds ? 100.0 : round(($availableSeconds / $measuredSeconds) * 100, 3);
    }

    /** @return array{int, int} */
    private function accumulate(
        ServiceStatus $status,
        DateTimeImmutable $from,
        DateTimeImmutable $until,
        int $available,
        int $measured,
    ): array {
        $seconds = max(0, $until->getTimestamp() - $from->getTimestamp());
        if ($status->isExcludedFromUptime()) {
            return [$available, $measured];
        }

        return [
            $available + ($status->isAvailable() ? $seconds : 0),
            $measured + $seconds,
        ];
    }
}
