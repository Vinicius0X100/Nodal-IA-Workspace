<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Organizations\Models\Organization;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationToken extends Model
{
    use HasFactory, HasSecondaryUuid;

    protected $fillable = [
        'organization_id',
        'provider',
        'access_token',
        'refresh_token',
        'expires_at',
        'scope',
        'token_type',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
