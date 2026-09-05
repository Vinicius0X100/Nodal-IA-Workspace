<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class BillingPaymentWebhookEvent extends Model
{
    protected $table = 'billing_payment_webhook_events';

    protected $guarded = ['id'];

    protected $casts = [
        'payload_json' => 'array',
        'received_at'  => 'datetime',
        'processed_at' => 'datetime',
    ];
}
