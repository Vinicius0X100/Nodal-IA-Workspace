<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\BillingExchangeRate;
use Illuminate\Database\Seeder;

class BillingExchangeRatesSeeder extends Seeder
{
    public function run(): void
    {
        // Taxa de referência USD/BRL para Setembro/2026
        // fx_buffer_percent configurado separadamente da taxa base
        // Taxa pode ser atualizada mensalmente criando nova linha com effective_from
        BillingExchangeRate::firstOrCreate(
            [
                'base_currency'  => 'USD',
                'quote_currency' => 'BRL',
                'effective_from' => '2026-09-01 00:00:00',
            ],
            [
                'rate'             => 5.20,      // R$ 5,20/USD — referência Setembro/2026
                'fx_buffer_percent' => 5.00,     // 5% de buffer de proteção cambial
                'reference_period' => '2026-09',
                'source'           => 'manual',
                'effective_to'     => null,
                'metadata_json'    => [
                    'notes' => 'Taxa de referência mensal configurada manualmente. Atualizar no início de cada mês.',
                ],
            ]
        );
    }
}
