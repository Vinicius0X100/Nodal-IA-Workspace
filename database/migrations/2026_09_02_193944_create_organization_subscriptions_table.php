<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('billing_plan_id')->nullable();

            // trial, active, past_due, suspended, cancelled
            $table->string('status', 30)->default('active');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();

            $table->timestamp('cancel_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Overrides contratuais (Enterprise ou acordos especiais)
            $table->unsignedInteger('custom_monthly_price_cents')->nullable();
            $table->unsignedInteger('custom_included_ai_credits')->nullable();
            $table->unsignedInteger('custom_overage_price_per_1000_credits_cents')->nullable();

            // Pós-pago: desabilitado por padrão
            // Deve ser habilitado explicitamente ao aderir ao plano com cobrança de excedente
            $table->boolean('postpaid_enabled')->default(false);
            $table->unsignedInteger('postpaid_limit_cents')->nullable()->comment('Limite mensal adicional de pós-pago em centavos');

            $table->json('metadata_json')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('billing_plan_id')->references('id')->on('billing_plans')->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'current_period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_subscriptions');
    }
};
