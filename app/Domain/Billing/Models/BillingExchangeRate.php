<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class BillingExchangeRate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'rate'              => 'float',
        'fx_buffer_percent' => 'float',
        'effective_from'    => 'datetime',
        'effective_to'      => 'datetime',
        'metadata_json'     => 'array',
    ];

    /**
     * Taxa vigente para um par de moedas em uma data.
     */
    public static function activeFor(string $base = 'USD', string $quote = 'BRL', ?\DateTime $at = null): ?static
    {
        $at ??= now();

        return static::where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->where('effective_from', '<=', $at)
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Taxa efetiva com buffer de proteção cambial aplicado.
     * Ex: rate=5.20, fx_buffer_percent=5.00 → 5.20 * 1.05 = 5.46
     */
    public function effectiveRate(): float
    {
        return $this->rate * (1 + ($this->fx_buffer_percent / 100));
    }
}
