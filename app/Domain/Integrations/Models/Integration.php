<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Domain\Organizations\Models\Organization;
use App\Support\Traits\HasSecondaryUuid;

class Integration extends Model
{
    use HasFactory, HasSecondaryUuid;

    protected $fillable = [
        'organization_id',
        'provider',
        'status',
        'display_name',
        'description',
        'icon',
        'is_enabled',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scope',
        'last_sync_at',
        'last_health_check',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'scope' => 'array',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_health_check' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function config(): HasOne
    {
        return $this->hasOne(IntegrationConfig::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class);
    }
}
