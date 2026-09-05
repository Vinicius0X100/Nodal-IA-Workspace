<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('usage_period_id')->nullable()->after('subscription_id');
            $table->string('plan_name')->nullable()->after('usage_period_id');
            $table->string('plan_code', 50)->nullable()->after('plan_name');

            $table->unique('usage_period_id');
            $table->foreign('usage_period_id')
                ->references('id')
                ->on('ai_usage_periods')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropForeign(['usage_period_id']);
            $table->dropUnique(['usage_period_id']);
            $table->dropColumn(['usage_period_id', 'plan_name', 'plan_code']);
        });
    }
};
