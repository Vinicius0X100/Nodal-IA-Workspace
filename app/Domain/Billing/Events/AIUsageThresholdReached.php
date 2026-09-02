<?php

namespace App\Domain\Billing\Events;

use App\Domain\Billing\Enums\AlertType;
use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AIUsageThresholdReached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Organization  $organization,
        public readonly AiUsagePeriod $period,
        public readonly AlertType     $alertType,
        public readonly int           $threshold,
        public readonly float         $percentage,
        public readonly string        $idempotencyKey,
    ) {}
}
