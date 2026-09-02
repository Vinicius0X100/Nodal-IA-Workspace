<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_daily_rollups', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('date');

            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('operation', 100);
            $table->string('billing_category', 50);

            // Agregados de créditos e custo
            $table->decimal('credits_used', 18, 8)->default(0);
            $table->decimal('provider_cost_brl', 14, 6)->default(0);
            $table->decimal('billable_cost_brl', 14, 6)->default(0);

            // Agregados de tokens
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('thinking_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);

            // Contagem de requests
            $table->unsignedInteger('requests_count')->default(0);
            $table->unsignedInteger('billable_requests_count')->default(0);

            $table->timestamps();

            // Chave única para upserts
            $table->unique(['organization_id', 'user_id', 'date', 'provider', 'model', 'operation', 'billing_category'], 'rollup_unique');

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['organization_id', 'date']);
            $table->index(['organization_id', 'user_id', 'date']);
            $table->index(['organization_id', 'date', 'provider', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_daily_rollups');
    }
};
