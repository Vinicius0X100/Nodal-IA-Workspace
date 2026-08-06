<?php

namespace App\Domain\AI\Models;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AITool extends Model
{
    use HasSecondaryUuid;

    protected $table = 'ai_tools';

    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
        'requires_confirmation' => 'boolean',
        'configuration_json' => 'json',
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
