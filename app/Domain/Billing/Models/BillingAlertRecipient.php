<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\AlertRecipientType;
use App\Domain\Directory\Models\Group;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAlertRecipient extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'recipient_type'   => AlertRecipientType::class,
        'usage_alerts'     => 'boolean',
        'invoice_alerts'   => 'boolean',
        'payment_alerts'   => 'boolean',
        'channel_email'    => 'boolean',
        'channel_in_app'   => 'boolean',
        'is_active'        => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
