<?php

namespace App\Domain\Settings\Models;

use App\Domain\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'key', 'value', 'type'])]
class Setting extends Model
{
    use Auditable, HasSecondaryUuid;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Acessor mágico para tratar o cast do valor baseado no type
     */
    public function getTypedValueAttribute()
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
