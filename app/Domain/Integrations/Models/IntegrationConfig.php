<?php

namespace App\Domain\Integrations\Models;

use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationConfig extends Model
{
    use HasFactory, HasSecondaryUuid;

    protected $fillable = [
        'integration_id',
        'client_id',
        'client_secret',
        'redirect_uri',
        'tenant',
        'configuration_json',
        'is_active',
        'last_validated_at',
        'delegation_credentials_json',
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
        'configuration_json' => 'array',
        'is_active' => 'boolean',
        'last_validated_at' => 'datetime',
        'delegation_credentials_json' => 'encrypted:array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
