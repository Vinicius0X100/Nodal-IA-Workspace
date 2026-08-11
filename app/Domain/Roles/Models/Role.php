<?php

namespace App\Domain\Roles\Models;

use App\Domain\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Domain\Permissions\Models\Permission;

#[Fillable(['organization_id', 'name', 'slug', 'description', 'is_system'])]
class Role extends Model
{
    use Auditable, HasSecondaryUuid;

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot('scope')
            ->withTimestamps();
    }
}
