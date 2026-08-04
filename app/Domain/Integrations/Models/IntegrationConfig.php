<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'client_id',
        'client_secret',
        'redirect_uri',
        'tenant',
        'configuration_json',
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
        'configuration_json' => 'array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
