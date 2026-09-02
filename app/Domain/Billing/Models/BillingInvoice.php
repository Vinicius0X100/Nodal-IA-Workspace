<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Organizations\Models\Organization;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingInvoice extends Model
{
    use HasSecondaryUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status'             => InvoiceStatus::class,
        'period_start'       => 'datetime',
        'period_end'         => 'datetime',
        'subtotal_cents'     => 'integer',
        'overage_cents'      => 'integer',
        'adjustments_cents'  => 'integer',
        'total_cents'        => 'integer',
        'issued_at'          => 'datetime',
        'due_at'             => 'datetime',
        'paid_at'            => 'datetime',
        'metadata_json'      => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubscription::class, 'subscription_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillingInvoiceItem::class, 'invoice_id');
    }

    public function totalBrl(): float
    {
        return $this->total_cents / 100;
    }
}
