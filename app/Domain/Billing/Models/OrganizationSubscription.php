<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Organizations\Models\Organization;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationSubscription extends Model
{
    use HasSecondaryUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status'                                      => SubscriptionStatus::class,
        'started_at'                                  => 'datetime',
        'current_period_start'                        => 'datetime',
        'current_period_end'                          => 'datetime',
        'cancel_at'                                   => 'datetime',
        'cancelled_at'                                => 'datetime',
        'custom_monthly_price_cents'                  => 'integer',
        'custom_included_ai_credits'                  => 'integer',
        'custom_overage_price_per_1000_credits_cents' => 'integer',
        'postpaid_enabled'                            => 'boolean',
        'postpaid_limit_cents'                        => 'integer',
        'preferred_payment_method'                    => PaymentMethod::class,
        'metadata_json'                               => 'array',
    ];


    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'billing_plan_id');
    }

    public function usagePeriods(): HasMany
    {
        return $this->hasMany(AiUsagePeriod::class, 'subscription_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class, 'subscription_id');
    }

    /**
     * Créditos incluídos efetivos: override contratual > padrão do plano.
     */
    public function effectiveIncludedCredits(): int
    {
        return $this->custom_included_ai_credits
            ?? $this->plan?->included_ai_credits
            ?? 0;
    }

    /**
     * Preço de excedente efetivo por 1000 créditos (em centavos).
     */
    public function effectiveOveragePricePer1000Cents(): int
    {
        return $this->custom_overage_price_per_1000_credits_cents
            ?? $this->plan?->overage_price_per_1000_credits_cents
            ?? 0;
    }

    /**
     * Preço mensal efetivo da assinatura (em centavos).
     */
    public function effectiveMonthlyPriceCents(): int
    {
        return $this->custom_monthly_price_cents
            ?? $this->plan?->monthly_price_cents
            ?? 0;
    }

    public function isActive(): bool
    {
        return $this->status?->isUsable() ?? false;
    }
}
