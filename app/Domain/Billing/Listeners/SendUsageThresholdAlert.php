<?php

namespace App\Domain\Billing\Listeners;

use App\Domain\Billing\Events\AIUsageThresholdReached;
use App\Domain\Billing\Services\BillingAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendUsageThresholdAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function __construct(
        private readonly BillingAlertService $alertService,
    ) {}

    public function handle(AIUsageThresholdReached $event): void
    {
        $this->alertService->fireThresholdAlert(
            organization:    $event->organization,
            period:          $event->period,
            alertType:       $event->alertType,
            threshold:       $event->threshold,
            percentage:      $event->percentage,
            idempotencyKey:  $event->idempotencyKey,
        );
    }
}
