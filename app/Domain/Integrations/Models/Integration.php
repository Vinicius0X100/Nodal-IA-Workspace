<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Integrations\Enums\IntegrationProvider;
use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'provider', 'status', 'config', 'connected_by', 'connected_at', 'last_sync_at'])]
class Integration extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'provider' => IntegrationProvider::class,
            'status' => IntegrationStatus::class,
            'config' => 'encrypted:json', // O Laravel cuida da encriptação via APP_KEY
            'connected_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
