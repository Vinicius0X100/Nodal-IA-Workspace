<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('provider', 50);          // google, openai, anthropic, ...
            $table->string('model', 100);            // gemini-3.5-flash, gpt-4o, ...
            $table->string('currency', 3)->default('USD');

            // Preços por 1.000.000 de tokens — DECIMAL alta precisão
            $table->decimal('input_rate_per_million', 12, 6);
            $table->decimal('output_rate_per_million', 12, 6);
            $table->decimal('cached_input_rate_per_million', 12, 6)->nullable();
            $table->decimal('cache_storage_rate_per_million_hour', 12, 6)->nullable();

            // Metadata extensível para componentes extras (ex: Google Search grounding)
            $table->json('rate_metadata_json')->nullable();

            // Versionamento temporal — NUNCA atualizar linha antiga
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['provider', 'model']);
            $table->index(['provider', 'model', 'is_active', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_rates');
    }
};
