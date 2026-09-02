<?php

namespace App\Domain\Billing\Models;

use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;

class AiModelRate extends Model
{
    use HasSecondaryUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'input_rate_per_million'           => 'float',
        'output_rate_per_million'          => 'float',
        'cached_input_rate_per_million'    => 'float',
        'cache_storage_rate_per_million_hour' => 'float',
        'rate_metadata_json'               => 'array',
        'effective_from'                   => 'datetime',
        'effective_to'                     => 'datetime',
        'is_active'                        => 'boolean',
    ];

    /**
     * Encontra a rate vigente para um provider/modelo em uma data específica.
     * Retorna a rate mais recente antes ou igual ao momento informado.
     */
    public static function activeFor(string $provider, string $model, ?\DateTime $at = null): ?static
    {
        $at ??= now();

        return static::where('provider', $provider)
            ->where('model', $model)
            ->where('effective_from', '<=', $at)
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            })
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->first();
    }
}
