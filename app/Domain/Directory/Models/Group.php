<?php

namespace App\Domain\Directory\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    use HasSecondaryUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Integrations\Models\Integration::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
