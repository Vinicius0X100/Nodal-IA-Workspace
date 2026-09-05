<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\AlertType;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAlertEvent extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'alert_type'             => AlertType::class,
        'threshold'              => 'integer',
        'recipient_summary_json' => 'array',
        'metadata_json'          => 'array',
        'triggered_at'           => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function usagePeriod(): BelongsTo
    {
        return $this->belongsTo(AiUsagePeriod::class, 'usage_period_id');
    }
}
