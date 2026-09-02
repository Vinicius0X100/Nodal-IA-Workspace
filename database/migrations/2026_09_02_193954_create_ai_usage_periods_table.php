<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_periods', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('subscription_id')->nullable();

            $table->timestamp('period_start');
            $table->timestamp('period_end');

            // Créditos incluídos no plano para esse período
            $table->unsignedInteger('included_credits')->default(0);

            // Uso faturável em créditos
            $table->decimal('billable_credits_used', 18, 8)->default(0);
            // Uso não-faturável (ex: retries internos) — para análise de margem
            $table->decimal('non_billable_credits_equivalent', 18, 8)->default(0);

            // Custo do provider para uso faturável (em BRL)
            $table->decimal('provider_cost_brl', 14, 6)->default(0);
            // Custo do provider para uso não-faturável
            $table->decimal('non_billable_provider_cost_brl', 14, 6)->default(0);

            // Excedente calculado
            $table->decimal('overage_credits', 18, 8)->default(0);
            // Estimativa de cobrança do excedente em centavos
            $table->unsignedInteger('estimated_overage_cents')->default(0);

            // open, closed, invoiced
            $table->string('status', 20)->default('open');

            $table->timestamps();

            $table->unique(['organization_id', 'period_start', 'period_end']);

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('organization_subscriptions')->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_periods');
    }
};
