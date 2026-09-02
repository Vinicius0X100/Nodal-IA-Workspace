<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\AiModelRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AiModelRatesSeeder extends Seeder
{
    public function run(): void
    {
        // Gemini 3.5 Flash — preços Standard (USD) vigentes em Setembro/2026
        // FONTE: Google AI Pricing
        // Estes valores são imutáveis: nunca atualizar esta linha — criar nova com effective_from atual
        AiModelRate::firstOrCreate(
            [
                'provider' => 'google',
                'model'    => 'gemini-3.5-flash',
                'effective_from' => '2026-09-01 00:00:00',
            ],
            [
                'uuid'                              => (string) Str::uuid(),
                'currency'                          => 'USD',
                'input_rate_per_million'            => 1.500000,
                'output_rate_per_million'           => 9.000000,
                'cached_input_rate_per_million'     => 0.150000,
                'cache_storage_rate_per_million_hour' => 1.000000,
                'rate_metadata_json'                => [
                    'notes'  => 'Standard pricing. Google Search grounding and other add-ons are tracked separately via cost_components.',
                    'source' => 'https://ai.google.dev/pricing',
                ],
                'effective_to'  => null,
                'is_active'     => true,
            ]
        );
    }
}
