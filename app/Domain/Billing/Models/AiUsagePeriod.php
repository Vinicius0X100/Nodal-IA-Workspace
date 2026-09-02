<?php

namespace App\Domain\Billing\Models;

use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiUsagePeriod extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'period_start'                      => 'datetime',
        'period_end'                        => 'datetime',
        'included_credits'                  => 'integer',
        'billable_credits_used'             => 'float',
        'non_billable_credits_equivalent'   => 'float',
        'provider_cost_brl'                 => 'float',
        'non_billable_provider_cost_brl'    => 'float',
        'overage_credits'                   => 'float',
        'estimated_overage_cents'           => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubscription::class, 'subscription_id');
    }

    public function alertEvents(): HasMany
    {
        return $this->hasMany(BillingAlertEvent::class, 'usage_period_id');
    }

    public function usagePercentage(): float
    {
        if ($this->included_credits <= 0) return 0;
        return min(($this->billable_credits_used / $this->included_credits) * 100, 100);
    }

    public function isOverQuota(): bool
    {
        return $this->billable_credits_used > $this->included_credits;
    }
}
