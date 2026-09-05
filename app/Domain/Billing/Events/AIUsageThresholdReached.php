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

    public Organization  $organization;
    public AiUsagePeriod $period;
    public AlertType     $alertType;
    public int           $threshold;
    public float         $percentage;
    public string        $idempotencyKey;
    public bool          $isTest;
    public ?array        $simulationContext;

    public function __construct(
        Organization  $organization,
        AiUsagePeriod $period,
        AlertType     $alertType,
        int           $threshold,
        float         $percentage,
        string        $idempotencyKey,
        bool          $isTest = false,
        ?array        $simulationContext = null,
    ) {
        $this->organization      = $organization;
        $this->period            = $period;
        $this->alertType         = $alertType;
        $this->threshold         = $threshold;
        $this->percentage        = $percentage;
        $this->idempotencyKey    = $idempotencyKey;
        $this->isTest            = $isTest;
        $this->simulationContext = $simulationContext;
    }
}
