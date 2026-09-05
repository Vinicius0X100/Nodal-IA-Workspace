<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Organizations\Models\Organization;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPayment extends Model
{
    use HasSecondaryUuid;

    protected $table = 'billing_payments';

    protected $guarded = ['id'];

    protected $casts = [
        'attempt_number'        => 'integer',
        'payment_method'        => PaymentMethod::class,
        'status'                => PaymentStatus::class,
        'amount_cents'          => 'integer',
        'paid_amount_cents'     => 'integer',
        'fee_cents'             => 'integer',
        'due_date'              => 'date',
        'expires_at'            => 'datetime',
        'paid_at'               => 'datetime',
        'failed_at'             => 'datetime',
        'cancelled_at'          => 'datetime',
        'provider_payload_json' => 'array',
        'metadata_json'         => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === PaymentStatus::PROCESSING;
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }

    public function isOverdue(): bool
    {
        return $this->status === PaymentStatus::OVERDUE;
    }

    public function isCancelled(): bool
    {
        return $this->status === PaymentStatus::CANCELLED;
    }

    public function isNeedsReview(): bool
    {
        return $this->status === PaymentStatus::NEEDS_REVIEW;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
