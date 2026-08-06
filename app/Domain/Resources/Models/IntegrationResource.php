<?php

namespace App\Domain\Resources\Models;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Resources\Enums\Provider;
use App\Domain\Resources\Enums\ResourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntegrationResource extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'provider' => Provider::class,
        'resource_type' => ResourceType::class,
        'is_folder' => 'boolean',
        'is_shared' => 'boolean',
        'created_by_provider_at' => 'datetime',
        'updated_by_provider_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'metadata_json' => 'array',
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

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
