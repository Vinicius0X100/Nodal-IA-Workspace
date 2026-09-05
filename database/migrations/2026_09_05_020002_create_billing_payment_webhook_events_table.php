<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30)->default('asaas');
            $table->string('provider_event_id', 150);
            $table->string('event_name', 100);
            $table->string('provider_external_payment_id', 100)->nullable();
            $table->json('payload_json');

            // received, processing, processed, ignored, failed
            $table->string('status', 30)->default('received');
            $table->text('error_message')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id'], 'uq_billing_webhook_provider_event');
            $table->index(['status', 'received_at'], 'idx_billing_webhook_status_received');
            $table->index(['provider_external_payment_id'], 'idx_billing_webhook_ext_payment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payment_webhook_events');
    }
};
