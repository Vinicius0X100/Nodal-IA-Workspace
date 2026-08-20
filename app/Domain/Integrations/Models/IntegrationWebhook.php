<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'channel_id',
        'resource_id',
        'resource_uri',
        'state',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
