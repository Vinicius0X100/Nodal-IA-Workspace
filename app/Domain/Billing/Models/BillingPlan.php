<?php

namespace App\Domain\Billing\Models;

use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPlan extends Model
{
    use HasSecondaryUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'monthly_price_cents'                    => 'integer',
        'included_ai_credits'                    => 'integer',
        'included_users'                         => 'integer',
        'integrations_limit'                     => 'integer',
        'overage_price_per_1000_credits_cents'   => 'integer',
        'is_enterprise'                          => 'boolean',
        'is_public'                              => 'boolean',
        'is_active'                              => 'boolean',
        'features_json'                          => 'array',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganizationSubscription::class);
    }

    /**
     * Preço mensal formatado em reais.
     */
    public function monthlyPriceBrl(): float
    {
        return $this->monthly_price_cents / 100;
    }

    /**
     * Preço de excedente por 1000 créditos em reais.
     */
    public function overagePricePer1000Brl(): float
    {
        return ($this->overage_price_per_1000_credits_cents ?? 0) / 100;
    }
}
