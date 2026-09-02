<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_alert_recipients', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->string('recipient_type', 20); // user, group

            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();

            // Tipos de alerta que este destinatário recebe
            $table->boolean('usage_alerts')->default(true);
            $table->boolean('invoice_alerts')->default(false);
            $table->boolean('payment_alerts')->default(false);

            // Canais
            $table->boolean('channel_email')->default(true);
            $table->boolean('channel_in_app')->default(true);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'recipient_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_alert_recipients');
    }
};
