<?php

namespace App\Domain\Permissions\Models;

use App\Domain\Roles\Models\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'description', 'group'])]
class Permission extends Model
{
    use HasSecondaryUuid;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')->withTimestamps();
    }
}
