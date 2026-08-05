<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integration_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('customer_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('primary_domain')->nullable();
            $table->string('customer_type')->nullable();
            $table->text('organization_logo')->nullable();
            $table->string('admin_email')->nullable();
            $table->string('admin_name')->nullable();
            $table->integer('total_users')->nullable();
            $table->integer('total_groups')->nullable();
            $table->json('organization_json')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_accounts');
    }
};
