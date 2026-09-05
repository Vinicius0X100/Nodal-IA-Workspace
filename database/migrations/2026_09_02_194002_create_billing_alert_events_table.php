<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_alert_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('usage_period_id')->nullable();

            // credit_usage_70, credit_usage_85, credit_usage_95,
            // credit_usage_100, postpaid_limit_warning, etc.
            $table->string('alert_type', 50);

            // Threshold que disparou (ex: 70, 85, 95, 100)
            $table->unsignedSmallInteger('threshold')->nullable();

            // Snapshot dos destinatários notificados
            $table->json('recipient_summary_json')->nullable();
            
            $table->json('metadata_json')->nullable();

            $table->timestamp('triggered_at');

            // Previne reenvio para o mesmo threshold no mesmo período
            $table->string('idempotency_key', 128)->unique();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('usage_period_id')->references('id')->on('ai_usage_periods')->onDelete('set null');

            $table->index(['organization_id', 'alert_type', 'triggered_at'], 'idx_alert_events_org_type_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_alert_events');
    }
};
