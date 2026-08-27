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
        'query_hash',
        'status',
        'progress',
        'attempts',
        'pages_processed',
        'records_processed',
        'params',
        'result',
        'result_path',
        'result_expires_at',
        'error_message',
        'metadata',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'params'            => 'array',
        'result'            => 'array',
        'metadata'          => 'array',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
        'expires_at'        => 'datetime',
        'result_expires_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Retorna true se o resultado está disponível (banco ou Storage).
     */
    public function hasResult(): bool
    {
        return !empty($this->result) || !empty($this->result_path);
    }

    /**
     * Retorna true se o resultado em Storage ainda está válido.
     */
    public function isResultStorageValid(): bool
    {
        if (empty($this->result_path)) {
            return false;
        }
        if ($this->result_expires_at && $this->result_expires_at->isPast()) {
            return false;
        }
        return true;
    }
}
