<?php

namespace App\Domain\Billing\Models;

use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPaymentCustomer extends Model
{
    protected $table = 'organization_payment_customers';

    protected $guarded = ['id'];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
