<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('billing_invoice_id');
            $table->unsignedSmallInteger('attempt_number')->default(1);

            $table->string('provider', 30)->default('asaas');
            $table->string('provider_external_id', 100)->nullable();
            $table->string('payment_method', 20); // pix, boleto

            // pending, processing, paid, failed, cancelled, expired, overdue, refunded, needs_review
            $table->string('status', 30)->default('pending');

            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('paid_amount_cents')->nullable();
            $table->unsignedInteger('fee_cents')->nullable();
            $table->char('currency', 3)->default('BRL');

            $table->date('due_date');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('pix_copy_paste')->nullable();
            $table->longText('pix_qr_code')->nullable();
            $table->string('boleto_barcode', 100)->nullable();
            $table->string('boleto_url', 500)->nullable();

            $table->json('provider_payload_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('idempotency_key', 150)->unique();

            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('restrict');

            $table->foreign('billing_invoice_id')
                ->references('id')
                ->on('billing_invoices')
                ->onDelete('restrict');

            $table->unique(['provider', 'provider_external_id'], 'uq_billing_payments_provider_ext_id');
            $table->unique(['billing_invoice_id', 'attempt_number'], 'uq_billing_payments_invoice_attempt');
            $table->index(['organization_id', 'status']);
            $table->index(['billing_invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};
