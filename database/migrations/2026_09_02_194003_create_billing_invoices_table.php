<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('subscription_id')->nullable();

            $table->timestamp('period_start');
            $table->timestamp('period_end');

            // draft, issued, paid, void
            $table->string('status', 20)->default('draft');

            // Valores em centavos (BRL)
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('overage_cents')->default(0);
            $table->integer('adjustments_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->json('metadata_json')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('organization_subscriptions')->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
