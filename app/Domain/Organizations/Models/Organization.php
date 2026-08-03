<?php

namespace App\Domain\Organizations\Models;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Roles\Models\Role;
use App\Domain\Settings\Models\Setting;
use App\Support\Traits\Auditable;
use App\Support\Traits\HasSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'logo', 'settings'])]
class Organization extends Model
{
    use SoftDeletes, Auditable, HasSlug;

    protected function casts(): array
    {
        return [
            'settings' => 'json',
        ];
    }

    // ─── Relationships ────────────────────────────────────

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('is_owner', 'joined_at')
            ->withTimestamps();
    }

    public function owner(): BelongsToMany
    {
        return $this->users()->wherePivot('is_owner', true);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    // ─── Helpers ──────────────────────────────────────────

    public function isOwner(User $user): bool
    {
        return $this->users()
            ->where('users.id', $user->id)
            ->wherePivot('is_owner', true)
            ->exists();
    }

    public function hasMember(User $user): bool
    {
        return $this->users()->where('users.id', $user->id)->exists();
    }
}
