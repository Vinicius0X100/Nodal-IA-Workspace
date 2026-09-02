<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('code', 50)->unique();   // starter, business, enterprise
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Preço em centavos (BRL)
            $table->unsignedInteger('monthly_price_cents');

            // Créditos de IA incluídos por período
            $table->unsignedInteger('included_ai_credits');

            // Limites opcionais
            $table->unsignedInteger('included_users')->nullable();
            $table->unsignedSmallInteger('integrations_limit')->nullable();

            // Preço de excedente em centavos por 1000 créditos
            $table->unsignedInteger('overage_price_per_1000_credits_cents')->nullable();

            // Flags comerciais
            $table->boolean('is_enterprise')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);

            // Features descritivas (para exibição frontend)
            $table->json('features_json')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plans');
    }
};
