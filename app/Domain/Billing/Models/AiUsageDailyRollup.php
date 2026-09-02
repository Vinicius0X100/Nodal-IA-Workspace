<?php

namespace App\Domain\Billing\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageDailyRollup extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date'          => 'date',
        'credits_used'  => 'float',
        'provider_cost_brl' => 'float',
        'billable_cost_brl' => 'float',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
