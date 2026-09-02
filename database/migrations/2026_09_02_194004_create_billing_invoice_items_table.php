<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');

            // subscription, ai_overage, adjustment, credit
            $table->string('type', 30);
            $table->string('description');

            $table->decimal('quantity', 14, 4)->default(1);
            $table->unsignedInteger('unit_amount_cents')->default(0);
            $table->integer('amount_cents')->default(0);

            $table->json('metadata_json')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('invoice_id')->references('id')->on('billing_invoices')->onDelete('cascade');
            $table->index(['invoice_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoice_items');
    }
};
