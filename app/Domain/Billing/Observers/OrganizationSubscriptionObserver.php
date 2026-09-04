<?php

namespace App\Domain\Billing\Observers;

use App\Domain\Billing\Models\OrganizationSubscription;
use App\Domain\Billing\Services\BillingSubscriptionService;

class OrganizationSubscriptionObserver
{
    public function __construct(
        private readonly BillingSubscriptionService $subscriptionService,
    ) {}

    public function created(OrganizationSubscription $subscription): void
    {
        $this->subscriptionService->syncCurrentPeriod($subscription->organization);
    }

    public function updated(OrganizationSubscription $subscription): void
    {
        // Campos que impactam diretamente a franquia, o custo de excedente e as datas do período.
        $relevantFields = [
            'billing_plan_id',
            'status',
            'current_period_start',
            'current_period_end',
            'custom_included_ai_credits',
            'custom_overage_price_per_1000_credits_cents',
            'postpaid_enabled',
            'postpaid_limit_cents',
        ];

        if ($subscription->wasChanged($relevantFields)) {
            $this->subscriptionService->syncCurrentPeriod($subscription->organization);
        }
    }

    public function deleted(OrganizationSubscription $subscription): void
    {
        $this->subscriptionService->syncCurrentPeriod($subscription->organization);
    }
}
