<?php

namespace App\Domain\Billing\Models;

use App\Domain\AI\Models\Conversation;
use App\Domain\AI\Models\Message;
use App\Domain\Billing\Enums\BillingCategory;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiUsageEvent extends Model
{
    use HasSecondaryUuid;

    // Ledger imutável: sem updated_at
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'billing_category'          => BillingCategory::class,
        'billable'                  => 'boolean',
        'provider_cost_usd'         => 'float',
        'exchange_rate'             => 'float',
        'provider_cost_brl'         => 'float',
        'commercial_reference_cost_brl' => 'float',
        'credits_used'              => 'float',
        'provider_usage_json'       => 'array',
        'metadata_json'             => 'array',
        'occurred_at'               => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function modelRate(): BelongsTo
    {
        return $this->belongsTo(AiModelRate::class, 'model_rate_id');
    }

    public function costComponents(): HasMany
    {
        return $this->hasMany(AiUsageCostComponent::class);
    }
}
