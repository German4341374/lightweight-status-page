<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\ServiceStatus;
use App\Repository\IncidentRepository;
use App\Repository\ServiceRepository;
use DateTimeImmutable;

final readonly class StatusPageService
{
    public function __construct(
        private ServiceRepository $services,
        private IncidentRepository $incidents,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(DateTimeImmutable $now): array
    {
        $from = $now->modify('-90 days');
        $services = $this->services->allWithUptime($from, $now);
        $activeIncidents = $this->incidents->active();

        return [
            'services' => $services,
            'active_incidents' => $activeIncidents,
            'recent_incidents' => $this->incidents->recent($from),
            'overall' => $this->overallStatus($services),
            'period_start' => $from,
        ];
    }

    /**
     * @param list<array<string, mixed>> $services
     *
     * @return array{value: string, label: string, message: string}
     */
    public function overallStatus(array $services): array
    {
        $weight = [
            ServiceStatus::Operational->value => 0,
            ServiceStatus::Maintenance->value => 1,
            ServiceStatus::Degraded->value => 2,
            ServiceStatus::PartialOutage->value => 3,
            ServiceStatus::MajorOutage->value => 4,
        ];
        $worst = ServiceStatus::Operational;
        foreach ($services as $service) {
            $candidate = ServiceStatus::from((string) $service['status']);
            if ($weight[$candidate->value] > $weight[$worst->value]) {
                $worst = $candidate;
            }
        }

        return [
            'value' => $worst->value,
            'label' => ServiceStatus::Operational === $worst ? 'All systems operational' : $worst->label(),
            'message' => ServiceStatus::Operational === $worst
                ? 'No active service disruptions are currently reported.'
                : 'At least one service is operating outside its normal state.',
        ];
    }
}
