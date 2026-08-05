<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\Traits\HasSecondaryUuid;

class IntegrationOrganization extends Model
{
    use HasFactory, HasSecondaryUuid;

    protected $table = 'integration_accounts';

    protected $fillable = [
        'integration_id',
        'customer_id',
        'organization_name',
        'primary_domain',
        'customer_type',
        'organization_logo',
        'admin_email',
        'admin_name',
        'total_users',
        'total_groups',
        'organization_json',
        'last_synced_at',
    ];

    protected $casts = [
        'organization_json' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
