<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3)->default('USD');
            $table->string('quote_currency', 3)->default('BRL');

            // Taxa de câmbio de referência (ex: 5.20)
            $table->decimal('rate', 10, 6);

            // Buffer de proteção cambial — separado da taxa base
            // Ex: 5.00 = 5% sobre a taxa base
            $table->decimal('fx_buffer_percent', 5, 2)->default(0);

            // Período de referência (ex: "2026-09")
            $table->string('reference_period', 7)->nullable()->comment('YYYY-MM');

            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();

            $table->string('source')->nullable()->comment('BCB, manual, etc.');
            $table->json('metadata_json')->nullable();

            $table->timestamps();

            $table->index(['base_currency', 'quote_currency', 'effective_from'], 'exch_rates_currency_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_exchange_rates');
    }
};
