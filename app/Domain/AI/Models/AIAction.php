<?php

namespace App\Domain\AI\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AIAction extends Model
{
    use HasFactory;

    protected $table = 'ai_actions';

    protected $fillable = [
        'organization_id',
        'user_id',
        'integration_id',
        'conversation_id',
        'provider',
        'action_type',
        'target_resource_uuid',
        'prepared_params',
        'snapshot',
        'status',
        'idempotency_key',
        'expires_at',
        'prepared_at',
        'executed_at',
        'result_data',
        'error_data',
    ];

    protected $casts = [
        'prepared_params' => 'array',
        'snapshot' => 'array',
        'result_data' => 'array',
        'error_data' => 'array',
        'expires_at' => 'datetime',
        'prepared_at' => 'datetime',
        'executed_at' => 'datetime',
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

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExecuting(): bool
    {
        return $this->status === 'executing';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function scopePending(Builder $query)
    {
        return $query->where('status', 'pending');
    }
}
