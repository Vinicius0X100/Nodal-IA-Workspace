<?php

namespace App\Domain\Reports\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Integrations\Models\Integration;
use App\Support\Traits\HasSecondaryUuid;

class AsyncReport extends Model
{
    use HasFactory, HasSecondaryUuid;

    protected $fillable = [
        'organization_id',
        'integration_id',
        'provider',
        'type',
        'status',
        'progress',
        'params',
        'result',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'params' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
