<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageCostComponent extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity'      => 'float',
        'rate'          => 'float',
        'cost'          => 'float',
        'metadata_json' => 'array',
    ];

    public function usageEvent(): BelongsTo
    {
        return $this->belongsTo(AiUsageEvent::class, 'ai_usage_event_id');
    }
}
