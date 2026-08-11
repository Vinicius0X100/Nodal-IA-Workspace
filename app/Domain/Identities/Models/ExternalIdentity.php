<?php

namespace App\Domain\Identities\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExternalIdentity extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'organization_id',
        'user_id',
        'integration_id',
        'provider',
        'external_id',
        'primary_email',
        'display_name',
        'status',
        'metadata_json',
        'linked_at',
        'last_synced_at',
    ];

    protected $casts = [
        'metadata_json'  => 'array',
        'linked_at'      => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
