<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_payment_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('provider', 30)->default('asaas');
            $table->string('external_customer_id', 100);
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('restrict');

            $table->unique(['organization_id', 'provider'], 'uq_org_payment_customer_org_provider');
            $table->unique(['provider', 'external_customer_id'], 'uq_org_payment_customer_provider_ext_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_payment_customers');
    }
};
